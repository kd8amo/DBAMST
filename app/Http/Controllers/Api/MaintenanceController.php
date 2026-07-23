<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceStatus;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceEventType;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * UC-20: List maintenance schedules for a device.
     * Accessible by all authenticated users.
     */
    public function indexSchedules(Request $request): JsonResponse
    {
        $schedules = MaintenanceSchedule::with(['device', 'eventType'])
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $request->device_id))
            ->when($request->filled('event_type_id'), fn ($q) => $q->where('event_type_id', $request->event_type_id))
            ->orderBy('next_due_at')
            ->get();

        return response()->json($schedules);
    }

    /**
     * UC-13: Create a calibration/PM schedule for a device.
     * Technician/Scheduler/Admin only.
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        $this->authorize('createSchedule', MaintenanceEvent::class);

        $validated = $request->validate([
            'device_id'          => ['required', 'integer', 'exists:devices,id'],
            'event_type_id'      => ['required', 'integer', 'exists:maintenance_event_types,id'],
            'interval_days'      => ['required', 'integer', 'min:1'],
            'last_performed_at'  => ['nullable', 'date'],
            'next_due_at'        => ['required', 'date'],
            'notes'              => ['nullable', 'string'],
        ]);

        $schedule = MaintenanceSchedule::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        AuditLog::recordForUser(
            $request->user(),
            'maintenance_schedule.created',
            'maintenance_schedule',
            $schedule->id,
            "Maintenance schedule created for device ID {$schedule->device_id}"
        );

        return response()->json($schedule->load(['device', 'eventType']), 201);
    }

    /**
     * UC-13: Update a maintenance schedule.
     * Technician/Scheduler/Admin only.
     */
    public function updateSchedule(Request $request, MaintenanceSchedule $schedule): JsonResponse
    {
        $this->authorize('updateSchedule', $schedule);

        $validated = $request->validate([
            'interval_days' => ['sometimes', 'integer', 'min:1'],
            'next_due_at'   => ['sometimes', 'date'],
            'notes'         => ['nullable', 'string'],
        ]);

        $schedule->update($validated);

        AuditLog::recordForUser(
            $request->user(),
            'maintenance_schedule.updated',
            'maintenance_schedule',
            $schedule->id,
            "Maintenance schedule ID {$schedule->id} updated"
        );

        return response()->json($schedule->fresh(['device', 'eventType']));
    }

    /**
     * UC-20: List maintenance events (history) for a device or system.
     * Accessible by all authenticated users.
     */
    public function indexEvents(Request $request): JsonResponse
    {
        $events = MaintenanceEvent::with(['eventType', 'device', 'testSystem', 'performedByUser'])
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $request->device_id))
            ->when($request->filled('test_system_id'), fn ($q) => $q->where('test_system_id', $request->test_system_id))
            ->when($request->filled('event_type_id'), fn ($q) => $q->where('event_type_id', $request->event_type_id))
            ->orderByDesc('performed_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($events);
    }

    /**
     * UC-14/UC-15/UC-16: Log a maintenance event (calibration, PM, or repair).
     * Technician/Scheduler/Admin only.
     */
    public function storeEvent(Request $request): JsonResponse
    {
        $this->authorize('logEvent', MaintenanceEvent::class);

        $validated = $request->validate([
            'device_id'       => ['nullable', 'integer', 'exists:devices,id'],
            'test_system_id'  => ['nullable', 'integer', 'exists:test_systems,id'],
            'event_type_id'   => ['required', 'integer', 'exists:maintenance_event_types,id'],
            'performed_at'    => ['required', 'date'],
            'performed_by'    => ['required', 'string', 'max:180'],  // name of person/vendor
            'result'          => ['required', 'string', 'in:pass,fail,completed'],
            'next_due_at'     => ['nullable', 'date'],
            'vendor'          => ['nullable', 'string', 'max:180'],
            'notes'           => ['nullable', 'string'],
            'fault_report_id' => ['nullable', 'integer', 'exists:fault_reports,id'],
        ]);

        if (empty($validated['device_id']) && empty($validated['test_system_id'])) {
            return response()->json([
                'error' => 'A maintenance event must reference a device, a test system, or both.',
            ], 422);
        }

        $event = DB::transaction(function () use ($validated, $request) {
            $event = MaintenanceEvent::create(array_merge($validated, [
                'created_by' => $request->user()->id,
            ]));

            // If next_due_at provided, update the device's maintenance schedule.
            if (! empty($validated['device_id']) && ! empty($validated['next_due_at'])) {
                $eventTypeId = $validated['event_type_id'];
                MaintenanceSchedule::where('device_id', $validated['device_id'])
                    ->where('event_type_id', $eventTypeId)
                    ->update([
                        'last_performed_at' => $validated['performed_at'],
                        'next_due_at'       => $validated['next_due_at'],
                    ]);
            }

            AuditLog::recordForUser(
                $request->user(),
                'maintenance_event.logged',
                'maintenance_event',
                $event->id,
                "Maintenance event logged: type ID {$event->event_type_id}, result: {$event->result}"
            );

            return $event;
        });

        return response()->json($event->load(['eventType', 'device', 'testSystem']), 201);
    }

    /**
     * UC-17: Mark a device as out for external calibration.
     * Technician/Scheduler/Admin only.
     */
    public function markOutForCalibration(Request $request, Device $device): JsonResponse
    {
        $this->authorize('markOutForCalibration', MaintenanceEvent::class);

        $outForCalStatusId = DeviceStatus::where('name', DeviceStatus::OUT_FOR_CALIBRATION)->value('id');

        $device->update([
            'status_id'  => $outForCalStatusId,
            'updated_by' => $request->user()->id,
        ]);

        AuditLog::recordForUser(
            $request->user(),
            'device.out_for_calibration',
            'device',
            $device->id,
            "Device '{$device->asset_tag}' marked as out for external calibration"
        );

        return response()->json($device->fresh(['status']));
    }

    /**
     * UC-20: List devices with calibration/PM due soon or overdue.
     * Accessible by all authenticated users.
     */
    public function dueList(Request $request): JsonResponse
    {
        $warningDays = (int) \App\Models\SystemSetting::getValue('calibration_due_warning_days', 30);
        $threshold   = now()->addDays($warningDays);

        $schedules = MaintenanceSchedule::with(['device.site', 'device.status', 'eventType'])
            ->where('next_due_at', '<=', $threshold)
            ->when($request->filled('site_id'),
                fn ($q) => $q->whereHas('device', fn ($q) => $q->where('site_id', $request->site_id))
            )
            ->orderBy('next_due_at')
            ->get()
            ->map(fn ($s) => array_merge($s->toArray(), [
                'is_overdue' => $s->next_due_at->isPast(),
            ]));

        return response()->json($schedules);
    }
}
