<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FaultReport;
use App\Models\FaultReportStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaultReportController extends Controller
{
    /**
     * UC-18/UC-19: List fault reports with filters.
     * Accessible by all authenticated users.
     */
    public function index(Request $request): JsonResponse
    {
        $reports = FaultReport::with(['status', 'reportedBy', 'resolvedBy'])
            ->when($request->filled('status_id'),
                fn ($q) => $q->where('status_id', $request->status_id)
            )
            ->when($request->filled('device_id'),
                fn ($q) => $q->where('device_id', $request->device_id)
            )
            ->when($request->filled('test_system_id'),
                fn ($q) => $q->where('test_system_id', $request->test_system_id)
            )
            ->when($request->boolean('open_only', false),
                fn ($q) => $q->whereHas('status', fn ($q) => $q->where('name', '!=', 'resolved'))
            )
            ->orderByDesc('reported_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($reports);
    }

    /**
     * UC-18: Show a single fault report.
     */
    public function show(FaultReport $faultReport): JsonResponse
    {
        $faultReport->load(['status', 'reportedBy', 'resolvedBy', 'device', 'testSystem']);

        return response()->json($faultReport);
    }

    /**
     * UC-18: Report a fault. Any authenticated user except Auditor.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', FaultReport::class);

        $validated = $request->validate([
            'device_id'      => ['nullable', 'integer', 'exists:devices,id'],
            'test_system_id' => ['nullable', 'integer', 'exists:test_systems,id'],
            'description'    => ['required', 'string'],
            'severity'       => ['nullable', 'string', 'in:low,medium,high,critical'],
        ]);

        // Must reference at least a device or a test system.
        if (empty($validated['device_id']) && empty($validated['test_system_id'])) {
            return response()->json([
                'error' => 'A fault report must reference a device, a test system, or both.',
            ], 422);
        }

        $openStatusId = FaultReportStatus::where('name', FaultReportStatus::OPEN)->value('id');

        $report = FaultReport::create(array_merge($validated, [
            'status_id'   => $openStatusId,
            'reported_by' => $request->user()->id,
            'reported_at' => now(),
        ]));

        AuditLog::recordForUser(
            $request->user(),
            'fault_report.created',
            'fault_report',
            $report->id,
            "Fault reported: {$report->description}"
        );

        return response()->json($report->load(['status', 'reportedBy']), 201);
    }

    /**
     * UC-19: Update fault report status (triage/resolve).
     * Technician/Scheduler/Admin only.
     */
    public function update(Request $request, FaultReport $faultReport): JsonResponse
    {
        $this->authorize('update', $faultReport);

        $validated = $request->validate([
            'status_id'          => ['sometimes', 'integer', 'exists:fault_report_statuses,id'],
            'resolution_notes'   => ['nullable', 'string'],
            'maintenance_event_id' => ['nullable', 'integer', 'exists:maintenance_events,id'],
        ]);

        DB::transaction(function () use ($faultReport, $validated, $request) {
            // If resolving, record who resolved and when.
            $resolvedStatus = FaultReportStatus::where('name', FaultReportStatus::RESOLVED)->first();
            if (
                isset($validated['status_id'])
                && $validated['status_id'] === $resolvedStatus->id
                && is_null($faultReport->resolved_by)
            ) {
                $validated['resolved_by'] = $request->user()->id;
                $validated['resolved_at'] = now();
            }

            $faultReport->update($validated);

            AuditLog::recordForUser(
                $request->user(),
                'fault_report.updated',
                'fault_report',
                $faultReport->id,
                "Fault report #{$faultReport->id} updated"
            );
        });

        return response()->json($faultReport->fresh(['status', 'reportedBy', 'resolvedBy']));
    }
}
