<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Assignment;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\DeviceStatus;
use App\Models\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    /**
     * UC-36: List devices with filters.
     * Accessible by all authenticated users.
     */
    public function index(Request $request): JsonResponse
    {
        $devices = Device::with(['category', 'status', 'site', 'currentAssignment.testSystem'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->site_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('status_id'), fn ($q) => $q->where('status_id', $request->status_id))
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('search'), fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('asset_tag', 'ilike', "%{$request->search}%")
                    ->orWhere('serial_number', 'ilike', "%{$request->search}%")
                    ->orWhere('manufacturer', 'ilike', "%{$request->search}%")
                    ->orWhere('model', 'ilike', "%{$request->search}%")
                )
            )
            ->orderBy('asset_tag')
            ->paginate($request->integer('per_page', 50));

        return response()->json($devices);
    }

    /**
     * UC-8: Show a single device with full history.
     */
    public function show(Device $device): JsonResponse
    {
        $device->load([
            'category',
            'status',
            'site',
            'currentAssignment.testSystem',
            'assignments.testSystem',
            'transfers.fromSite',
            'transfers.toSite',
            'maintenanceSchedules.eventType',
            'maintenanceEvents.eventType',
            'faultReports.status',
        ]);

        return response()->json($device);
    }

    /**
     * UC-1: Create a device. Technician/Scheduler/Admin only.
     * Asset tag is auto-generated from the category prefix + sequential number.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Device::class);

        $validated = $request->validate([
            'category_id'   => ['required', 'integer', 'exists:device_categories,id'],
            'manufacturer'  => ['required', 'string', 'max:120'],
            'model'         => ['required', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'site_id'       => ['required', 'integer', 'exists:sites,id'],
            'notes'         => ['nullable', 'string'],
        ]);

        $device = DB::transaction(function () use ($validated, $request) {
            // Generate asset tag: PREFIX-NNNNNN (zero-padded 6-digit sequence).
            $prefix  = DeviceCategory::findOrFail($validated['category_id'])->prefix;
            $count   = Device::where('category_id', $validated['category_id'])->count() + 1;
            $assetTag = $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

            $unassignedStatusId = DeviceStatus::where('name', DeviceStatus::UNASSIGNED)->value('id');

            $device = Device::create(array_merge($validated, [
                'asset_tag'  => $assetTag,
                'status_id'  => $unassignedStatusId,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]));

            AuditLog::recordForUser(
                $request->user(),
                'device.created',
                'device',
                $device->id,
                "Device '{$device->asset_tag}' ({$device->manufacturer} {$device->model}) created"
            );

            return $device;
        });

        return response()->json($device->load(['category', 'status', 'site']), 201);
    }

    /**
     * UC-3: Update device attributes. Technician/Scheduler/Admin only.
     */
    public function update(Request $request, Device $device): JsonResponse
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'manufacturer'  => ['sometimes', 'string', 'max:120'],
            'model'         => ['sometimes', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'site_id'       => ['sometimes', 'integer', 'exists:sites,id'],
            'notes'         => ['nullable', 'string'],
        ]);

        $device->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        AuditLog::recordForUser(
            $request->user(),
            'device.updated',
            'device',
            $device->id,
            "Device '{$device->asset_tag}' updated"
        );

        return response()->json($device->fresh(['category', 'status', 'site']));
    }

    /**
     * UC-4: Retire a device. Technician/Scheduler/Admin only.
     */
    public function retire(Request $request, Device $device): JsonResponse
    {
        $this->authorize('retire', $device);

        $device->retire($request->user());

        AuditLog::recordForUser(
            $request->user(),
            'device.retired',
            'device',
            $device->id,
            "Device '{$device->asset_tag}' retired"
        );

        return response()->json($device->fresh(['status']));
    }

    /**
     * UC-10: Transfer a device to a different site.
     * Technician/Scheduler/Admin only.
     * Warns if device is currently assigned (conflict), Scheduler/Admin can override.
     */
    public function transfer(Request $request, Device $device): JsonResponse
    {
        $this->authorize('transfer', $device);

        $validated = $request->validate([
            'to_site_id'      => ['required', 'integer', 'exists:sites,id', 'different:' . $device->site_id],
            'notes'           => ['nullable', 'string'],
            'override_reason' => ['nullable', 'string'],
        ]);

        // Conflict: device is currently assigned to a system.
        $hasConflict = ! is_null($device->currentAssignment);

        if ($hasConflict && empty($validated['override_reason'])) {
            // Surface conflict — caller must re-submit with override_reason.
            return response()->json([
                'conflict'         => true,
                'conflict_message' => "Device '{$device->asset_tag}' is currently assigned to test system '{$device->currentAssignment->testSystem->name}'. Provide override_reason to proceed.",
            ], 409);
        }

        if ($hasConflict) {
            // Scheduler/Admin required to override.
            $this->authorize('overrideConflict', $device);
        }

        $transfer = DB::transaction(function () use ($device, $validated, $request) {
            $fromSiteId = $device->site_id;

            $device->update([
                'site_id'    => $validated['to_site_id'],
                'updated_by' => $request->user()->id,
            ]);

            $transfer = Transfer::create([
                'device_id'    => $device->id,
                'from_site_id' => $fromSiteId,
                'to_site_id'   => $validated['to_site_id'],
                'transferred_at' => now(),
                'created_by'   => $request->user()->id,
                'notes'        => $validated['notes'] ?? null,
            ]);

            AuditLog::recordForUser(
                $request->user(),
                'device.transferred',
                'device',
                $device->id,
                "Device '{$device->asset_tag}' transferred to site ID {$validated['to_site_id']}"
                . (! empty($validated['override_reason']) ? " (override: {$validated['override_reason']})" : '')
            );

            return $transfer;
        });

        return response()->json($transfer->load(['fromSite', 'toSite']), 201);
    }

    /**
     * UC-12: Look up a device by asset tag (barcode/QR scan).
     */
    public function findByAssetTag(Request $request): JsonResponse
    {
        $request->validate([
            'asset_tag' => ['required', 'string'],
        ]);

        $device = Device::where('asset_tag', $request->asset_tag)
            ->with(['category', 'status', 'site', 'currentAssignment.testSystem'])
            ->firstOrFail();

        return response()->json($device);
    }
}
