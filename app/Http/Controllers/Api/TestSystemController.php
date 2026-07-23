<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceStatus;
use App\Models\TestSystem;
use App\Models\TestSystemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestSystemController extends Controller
{
    /**
     * UC-36: List test systems with filters.
     * Accessible by all authenticated users.
     */
    public function index(Request $request): JsonResponse
    {
        $systems = TestSystem::with(['site', 'status'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->site_id))
            ->when($request->filled('status_id'), fn ($q) => $q->where('status_id', $request->status_id))
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return response()->json($systems);
    }

    /**
     * UC-9: Show a test system with its current device roster and bookings.
     */
    public function show(TestSystem $testSystem): JsonResponse
    {
        $testSystem->load([
            'site',
            'status',
            'currentAssignments.device.category',
            'currentAssignments.device.status',
            'faultReports.status',
            'bookings.status',
        ]);

        return response()->json($testSystem);
    }

    /**
     * UC-5: Create a test system. Technician/Scheduler/Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TestSystem::class);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'notes'   => ['nullable', 'string'],
        ]);

        $activeStatusId = TestSystemStatus::where('name', TestSystemStatus::ACTIVE)->value('id');

        $system = TestSystem::create(array_merge($validated, [
            'status_id'  => $activeStatusId,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        AuditLog::recordForUser(
            $request->user(),
            'test_system.created',
            'test_system',
            $system->id,
            "Test system '{$system->name}' created"
        );

        return response()->json($system->load(['site', 'status']), 201);
    }

    /**
     * UC-5: Update a test system. Technician/Scheduler/Admin only.
     */
    public function update(Request $request, TestSystem $testSystem): JsonResponse
    {
        $this->authorize('update', $testSystem);

        $validated = $request->validate([
            'name'      => ['sometimes', 'string', 'max:120'],
            'site_id'   => ['sometimes', 'integer', 'exists:sites,id'],
            'notes'     => ['nullable', 'string'],
            'status_id' => ['sometimes', 'integer', 'exists:test_system_statuses,id'],
        ]);

        $testSystem->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        AuditLog::recordForUser(
            $request->user(),
            'test_system.updated',
            'test_system',
            $testSystem->id,
            "Test system '{$testSystem->name}' updated"
        );

        return response()->json($testSystem->fresh(['site', 'status']));
    }

    /**
     * UC-6: Assign a device to this test system.
     * Technician/Scheduler/Admin only.
     */
    public function assignDevice(Request $request, TestSystem $testSystem): JsonResponse
    {
        $this->authorize('update', $testSystem);

        $validated = $request->validate([
            'device_id'       => ['required', 'integer', 'exists:devices,id'],
            'assignment_type' => ['required', 'string', 'in:fixed,swappable'],
            'notes'           => ['nullable', 'string'],
        ]);

        $device = Device::findOrFail($validated['device_id']);

        // Prevent assigning a device that's already assigned elsewhere.
        if (! is_null($device->currentAssignment)) {
            return response()->json([
                'error' => "Device '{$device->asset_tag}' is already assigned to test system '{$device->currentAssignment->testSystem->name}'.",
            ], 409);
        }

        $assignment = DB::transaction(function () use ($device, $testSystem, $validated, $request) {
            $assignedStatusId = DeviceStatus::where('name', DeviceStatus::ASSIGNED)->value('id');

            $assignment = Assignment::create([
                'device_id'       => $device->id,
                'test_system_id'  => $testSystem->id,
                'assignment_type' => $validated['assignment_type'],
                'started_at'      => now(),
                'created_by'      => $request->user()->id,
                'notes'           => $validated['notes'] ?? null,
            ]);

            $device->update([
                'status_id'  => $assignedStatusId,
                'updated_by' => $request->user()->id,
            ]);

            AuditLog::recordForUser(
                $request->user(),
                'device.assigned',
                'device',
                $device->id,
                "Device '{$device->asset_tag}' assigned to test system '{$testSystem->name}'"
            );

            return $assignment;
        });

        return response()->json($assignment->load(['device', 'testSystem']), 201);
    }

    /**
     * UC-7: Unassign a device from this test system.
     * Technician/Scheduler/Admin only.
     */
    public function unassignDevice(Request $request, TestSystem $testSystem, Device $device): JsonResponse
    {
        $this->authorize('update', $testSystem);

        $assignment = $device->currentAssignment;

        if (is_null($assignment) || $assignment->test_system_id !== $testSystem->id) {
            return response()->json([
                'error' => "Device '{$device->asset_tag}' is not currently assigned to this test system.",
            ], 422);
        }

        DB::transaction(function () use ($assignment, $device, $testSystem, $request) {
            $assignment->close($request->user());

            $unassignedStatusId = DeviceStatus::where('name', DeviceStatus::UNASSIGNED)->value('id');
            $device->update([
                'status_id'  => $unassignedStatusId,
                'updated_by' => $request->user()->id,
            ]);

            AuditLog::recordForUser(
                $request->user(),
                'device.unassigned',
                'device',
                $device->id,
                "Device '{$device->asset_tag}' unassigned from test system '{$testSystem->name}'"
            );
        });

        return response()->json(['message' => "Device '{$device->asset_tag}' unassigned successfully."]);
    }
}
