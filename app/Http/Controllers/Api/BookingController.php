<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingConflict;
use App\Models\BookingDeviceSnapshot;
use App\Models\BookingStatus;
use App\Models\ConflictCheckStage;
use App\Models\ConflictType;
use App\Models\TestSystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * UC-24: List bookings with filters for the availability calendar.
     * Accessible by all authenticated users.
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::with(['testSystem.site', 'status', 'createdBy'])
            ->when($request->filled('site_id'),
                fn ($q) => $q->whereHas('testSystem', fn ($q) => $q->where('site_id', $request->site_id))
            )
            ->when($request->filled('test_system_id'),
                fn ($q) => $q->where('test_system_id', $request->test_system_id)
            )
            ->when($request->filled('status_id'),
                fn ($q) => $q->where('status_id', $request->status_id)
            )
            ->when($request->filled('from'),
                fn ($q) => $q->where('ends_at', '>=', $request->from)
            )
            ->when($request->filled('to'),
                fn ($q) => $q->where('starts_at', '<=', $request->to)
            )
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 100));

        return response()->json($bookings);
    }

    /**
     * UC-21: Show a single booking with its device snapshot and conflicts.
     */
    public function show(Booking $booking): JsonResponse
    {
        $booking->load([
            'testSystem.site',
            'status',
            'createdBy',
            'deviceSnapshots.device.category',
            'conflicts.conflictType',
            'conflicts.resolvedBy',
        ]);

        return response()->json($booking);
    }

    /**
     * UC-21: Create a booking (request stage).
     * Engineer/Technician/Scheduler/Admin only.
     * Automatically detects conflicts (UC-22) and snapshots current devices.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Booking::class);

        $validated = $request->validate([
            'test_system_id' => ['required', 'integer', 'exists:test_systems,id'],
            'starts_at'      => ['required', 'date', 'before:ends_at'],
            'ends_at'        => ['required', 'date', 'after:starts_at'],
            'title'          => ['required', 'string', 'max:180'],
            'notes'          => ['nullable', 'string'],
        ]);

        $testSystem = TestSystem::with(['currentAssignments.device'])->findOrFail($validated['test_system_id']);

        $booking = DB::transaction(function () use ($validated, $testSystem, $request) {
            $requestedStatusId = BookingStatus::where('name', BookingStatus::REQUESTED)->value('id');

            $booking = Booking::create(array_merge($validated, [
                'status_id'  => $requestedStatusId,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]));

            // UC-21: Snapshot currently assigned devices into the booking record.
            foreach ($testSystem->currentAssignments as $assignment) {
                BookingDeviceSnapshot::create([
                    'booking_id'      => $booking->id,
                    'device_id'       => $assignment->device_id,
                    'assignment_type' => $assignment->assignment_type,
                ]);
            }

            // UC-22: Detect conflicts across the booking window.
            $this->detectConflicts($booking, $testSystem, $request);

            AuditLog::recordForUser(
                $request->user(),
                'booking.created',
                'booking',
                $booking->id,
                "Booking '{$booking->title}' created for system '{$testSystem->name}' ({$booking->starts_at} – {$booking->ends_at})"
            );

            return $booking;
        });

        return response()->json($booking->load(['testSystem', 'status', 'conflicts.conflictType']), 201);
    }

    /**
     * UC-25: Update a booking's time range or notes. Re-runs conflict detection.
     */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'starts_at' => ['sometimes', 'date', 'before:ends_at'],
            'ends_at'   => ['sometimes', 'date', 'after:starts_at'],
            'title'     => ['sometimes', 'string', 'max:180'],
            'notes'     => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($booking, $validated, $request) {
            $booking->update(array_merge($validated, ['updated_by' => $request->user()->id]));

            // Clear old unresolved conflicts and re-detect for the new window.
            $booking->conflicts()->whereNull('override_reason')->delete();
            $testSystem = $booking->testSystem()->with('currentAssignments.device')->first();
            $this->detectConflicts($booking, $testSystem, $request);

            AuditLog::recordForUser(
                $request->user(),
                'booking.updated',
                'booking',
                $booking->id,
                "Booking '{$booking->title}' updated"
            );
        });

        return response()->json($booking->fresh(['testSystem', 'status', 'conflicts.conflictType']));
    }

    /**
     * UC-25: Cancel a booking.
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        $booking->cancel($request->user());

        AuditLog::recordForUser(
            $request->user(),
            'booking.cancelled',
            'booking',
            $booking->id,
            "Booking '{$booking->title}' cancelled"
        );

        return response()->json($booking->fresh(['status']));
    }

    /**
     * UC-23: Confirm a booking, overriding any flagged conflicts.
     * Scheduler/Manager and Admin only.
     */
    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('confirm', $booking);

        $validated = $request->validate([
            'override_reason' => ['required_if:has_conflicts,true', 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($booking, $validated, $request) {
            // Mark any unresolved conflicts as overridden.
            if (! empty($validated['override_reason'])) {
                $booking->conflicts()
                    ->whereNull('override_reason')
                    ->update([
                        'override_reason' => $validated['override_reason'],
                        'overridden_by'   => $request->user()->id,
                        'overridden_at'   => now(),
                    ]);
            }

            $booking->confirm($request->user());

            AuditLog::recordForUser(
                $request->user(),
                'booking.confirmed',
                'booking',
                $booking->id,
                "Booking '{$booking->title}' confirmed"
                . (! empty($validated['override_reason']) ? " with override: {$validated['override_reason']}" : '')
            );
        });

        return response()->json($booking->fresh(['status', 'conflicts']));
    }

    /**
     * UC-22: Detect conflicts for a booking window and persist them.
     * Called internally on create and update.
     */
    private function detectConflicts(Booking $booking, TestSystem $testSystem, Request $request): void
    {
        $requestedStageId = ConflictCheckStage::where('name', ConflictCheckStage::REQUESTED)->value('id');
        $maintenanceType  = ConflictType::where('name', ConflictType::MAINTENANCE_WINDOW)->value('id');
        $faultType        = ConflictType::where('name', ConflictType::OPEN_FAULT)->value('id');

        // Check 1: System-level maintenance windows overlapping the booking window.
        $maintenanceConflicts = $testSystem->maintenanceEvents()
            ->whereBetween('performed_at', [$booking->starts_at, $booking->ends_at])
            ->exists();

        if ($maintenanceConflicts) {
            BookingConflict::create([
                'booking_id'       => $booking->id,
                'conflict_type_id' => $maintenanceType,
                'stage_id'         => $requestedStageId,
                'description'      => 'Test system has a maintenance event scheduled within the booking window.',
            ]);
        }

        // Check 2: Open fault reports on the system or any of its current devices.
        $openFaults = $testSystem->faultReports()->whereHas('status', fn ($q) => $q->where('name', '!=', 'resolved'))->exists()
            || $testSystem->currentAssignments()
                ->whereHas('device.faultReports', fn ($q) => $q->whereHas('status', fn ($q) => $q->where('name', '!=', 'resolved')))
                ->exists();

        if ($openFaults) {
            BookingConflict::create([
                'booking_id'       => $booking->id,
                'conflict_type_id' => $faultType,
                'stage_id'         => $requestedStageId,
                'description'      => 'Test system or one of its devices has an open fault report.',
            ]);
        }
    }
}
