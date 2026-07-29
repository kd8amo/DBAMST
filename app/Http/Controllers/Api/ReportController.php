<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\FaultReport;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            ['id' => 'overdue-calibration',  'title' => 'Overdue Calibration & Maintenance',    'description' => 'All devices with calibration or maintenance past due date.',                    'filters' => ['site_id']],
            ['id' => 'due-soon',             'title' => 'Calibration & Maintenance Due Soon',   'description' => 'Devices with calibration or maintenance due within the warning window.',        'filters' => ['site_id', 'days']],
            ['id' => 'devices-by-site',      'title' => 'Devices by Site',                      'description' => 'Full device inventory grouped by site.',                                        'filters' => ['site_id', 'category_id', 'status_id']],
            ['id' => 'open-faults',          'title' => 'Open Fault Reports',                   'description' => 'All unresolved fault reports across all systems and devices.',                  'filters' => ['site_id']],
            ['id' => 'maintenance-history',  'title' => 'Maintenance Event History',             'description' => 'All calibration, PM, and repair events within a date range.',                  'filters' => ['site_id', 'from', 'to', 'event_type_id']],
            ['id' => 'booking-summary',      'title' => 'Booking Summary',                      'description' => 'All bookings within a date range with conflict information.',                   'filters' => ['site_id', 'from', 'to']],
            ['id' => 'audit-log-export',     'title' => 'Audit Log Export',                     'description' => 'Full audit trail export for a given date range.',                              'filters' => ['from', 'to', 'entity_type']],
        ]);
    }

    public function generate(Request $request, string $reportId): mixed
    {
        $format  = $request->input('format', 'json');
        $filters = $request->except('format');
        $data    = $this->buildReportData($reportId, $filters);
        $title   = $this->reportTitle($reportId);

        if ($format === 'excel') {
            return Excel::download(
                new ReportExport($title, $data['headers'], $data['rows']),
                $this->filename($reportId, 'xlsx')
            );
        }

        if ($format === 'pdf') {
            return $this->generatePdf($title, $data['headers'], $data['rows'], $reportId);
        }

        return response()->json($data);
    }

    private function buildReportData(string $reportId, array $filters): array
    {
        return match ($reportId) {
            'overdue-calibration' => $this->overdueCalibrationReport($filters),
            'due-soon'            => $this->dueSoonReport($filters),
            'devices-by-site'     => $this->devicesBySiteReport($filters),
            'open-faults'         => $this->openFaultsReport($filters),
            'maintenance-history' => $this->maintenanceHistoryReport($filters),
            'booking-summary'     => $this->bookingSummaryReport($filters),
            'audit-log-export'    => $this->auditLogReport($filters),
            default               => ['headers' => [], 'rows' => []],
        };
    }

    private function overdueCalibrationReport(array $filters): array
    {
        $query = MaintenanceSchedule::with(['device.site', 'device.category', 'eventType'])
            ->where('next_due_date', '<', now());
        if (!empty($filters['site_id']))
            $query->whereHas('device', fn ($q) => $q->where('site_id', $filters['site_id']));

        $rows = $query->orderBy('next_due_date')->get()->map(fn ($s) => [
            $s->device->asset_tag,
            $s->device->manufacturer . ' ' . $s->device->model,
            $s->device->category->name ?? '—',
            $s->device->site->name ?? '—',
            $s->eventType->name ?? '—',
            $s->next_due_date->toDateString(),
            now()->diffInDays($s->next_due_date) . ' days overdue',
        ])->toArray();

        return [
            'headers' => ['Asset Tag', 'Device', 'Category', 'Site', 'Type', 'Due Date', 'Overdue By'],
            'rows'    => $rows,
        ];
    }

    private function dueSoonReport(array $filters): array
    {
        $days      = (int) ($filters['days'] ?? \App\Models\SystemSetting::getValue('calibration_due_warning_days', 30));
        $threshold = now()->addDays($days);

        $query = MaintenanceSchedule::with(['device.site', 'device.category', 'eventType'])
            ->whereBetween('next_due_date', [now(), $threshold]);
        if (!empty($filters['site_id']))
            $query->whereHas('device', fn ($q) => $q->where('site_id', $filters['site_id']));

        $rows = $query->orderBy('next_due_date')->get()->map(fn ($s) => [
            $s->device->asset_tag,
            $s->device->manufacturer . ' ' . $s->device->model,
            $s->device->category->name ?? '—',
            $s->device->site->name ?? '—',
            $s->eventType->name ?? '—',
            $s->next_due_date->toDateString(),
            $s->next_due_date->diffInDays(now()) . ' days remaining',
        ])->toArray();

        return [
            'headers' => ['Asset Tag', 'Device', 'Category', 'Site', 'Type', 'Due Date', 'Days Remaining'],
            'rows'    => $rows,
        ];
    }

    private function devicesBySiteReport(array $filters): array
    {
        $query = Device::with(['site', 'category', 'status', 'currentAssignment.testSystem'])
            ->where('is_active', true);
        if (!empty($filters['site_id']))     $query->where('site_id', $filters['site_id']);
        if (!empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if (!empty($filters['status_id']))   $query->where('status_id', $filters['status_id']);

        $rows = $query->orderBy('site_id')->orderBy('asset_tag')->get()->map(fn ($d) => [
            $d->asset_tag,
            $d->manufacturer . ' ' . $d->model,
            $d->serial_number ?? '—',
            $d->category->name ?? '—',
            $d->site->name ?? '—',
            $d->status->name ?? '—',
            $d->currentAssignment->testSystem->name ?? 'Unassigned',
        ])->toArray();

        return [
            'headers' => ['Asset Tag', 'Device', 'Serial Number', 'Category', 'Site', 'Status', 'Test System'],
            'rows'    => $rows,
        ];
    }

    private function openFaultsReport(array $filters): array
    {
        $query = FaultReport::with(['device', 'testSystem', 'status', 'reportedBy'])
            ->whereHas('status', fn ($q) => $q->where('name', '!=', 'resolved'));
        if (!empty($filters['site_id'])) {
            $query->where(fn ($q) => $q
                ->whereHas('device', fn ($q) => $q->where('site_id', $filters['site_id']))
                ->orWhereHas('testSystem', fn ($q) => $q->where('site_id', $filters['site_id']))
            );
        }

        $rows = $query->orderByDesc('reported_at')->get()->map(fn ($f) => [
            $f->device->asset_tag ?? '—',
            $f->testSystem->name ?? '—',
            $f->description,
            $f->severity ?? '—',
            $f->status->name ?? '—',
            $f->reportedBy->display_name ?? '—',
            $f->reported_at->toDateString(),
        ])->toArray();

        return [
            'headers' => ['Asset Tag', 'Test System', 'Description', 'Severity', 'Status', 'Reported By', 'Date'],
            'rows'    => $rows,
        ];
    }

    private function maintenanceHistoryReport(array $filters): array
    {
        $query = MaintenanceEvent::with(['device', 'testSystem', 'eventType']);
        if (!empty($filters['from']))          $query->where('performed_at', '>=', $filters['from']);
        if (!empty($filters['to']))            $query->where('performed_at', '<=', $filters['to']);
        if (!empty($filters['event_type_id'])) $query->where('event_type_id', $filters['event_type_id']);
        if (!empty($filters['site_id']))
            $query->whereHas('device', fn ($q) => $q->where('site_id', $filters['site_id']));

        $rows = $query->orderByDesc('performed_at')->get()->map(fn ($e) => [
            $e->device->asset_tag ?? '—',
            $e->device ? $e->device->manufacturer . ' ' . $e->device->model : '—',
            $e->eventType->name ?? '—',
            $e->performed_at->toDateString(),
            $e->performed_by,
            $e->result,
            $e->next_due_at ? $e->next_due_at->toDateString() : '—',
            $e->notes ?? '—',
        ])->toArray();

        return [
            'headers' => ['Asset Tag', 'Device', 'Type', 'Date', 'Performed By', 'Result', 'Next Due', 'Notes'],
            'rows'    => $rows,
        ];
    }

    private function bookingSummaryReport(array $filters): array
    {
        $query = Booking::with(['testSystem.site', 'status', 'createdBy', 'conflicts']);
        if (!empty($filters['from'])) $query->where('starts_at', '>=', $filters['from']);
        if (!empty($filters['to']))   $query->where('ends_at', '<=', $filters['to']);
        if (!empty($filters['site_id']))
            $query->whereHas('testSystem', fn ($q) => $q->where('site_id', $filters['site_id']));

        $rows = $query->orderBy('starts_at')->get()->map(fn ($b) => [
            $b->title,
            $b->testSystem->name ?? '—',
            $b->testSystem->site->name ?? '—',
            $b->starts_at->toDateTimeString(),
            $b->ends_at->toDateTimeString(),
            $b->status->name ?? '—',
            $b->conflicts->count() > 0 ? $b->conflicts->count() . ' conflict(s)' : 'None',
            $b->createdBy->display_name ?? '—',
        ])->toArray();

        return [
            'headers' => ['Title', 'Test System', 'Site', 'Start', 'End', 'Status', 'Conflicts', 'Created By'],
            'rows'    => $rows,
        ];
    }

    private function auditLogReport(array $filters): array
    {
        $query = AuditLog::with(['user']);
        if (!empty($filters['from']))        $query->where('created_at', '>=', $filters['from']);
        if (!empty($filters['to']))          $query->where('created_at', '<=', $filters['to']);
        if (!empty($filters['entity_type'])) $query->where('entity_type', $filters['entity_type']);

        $rows = $query->orderByDesc('created_at')->limit(5000)->get()->map(fn ($e) => [
            $e->created_at->toDateTimeString(),
            $e->user->display_name ?? 'System',
            $e->action,
            $e->entity_type,
            $e->entity_id,
            $e->description,
        ])->toArray();

        return [
            'headers' => ['Timestamp', 'User', 'Action', 'Entity Type', 'Entity ID', 'Description'],
            'rows'    => $rows,
        ];
    }

    private function generatePdf(string $title, array $headers, array $rows, string $reportId): Response
    {
        $appName     = \App\Models\SystemSetting::getValue('app_display_name', 'Test System Maintenance');
        $generatedAt = now()->toDateTimeString();
        $headerHtml  = implode('', array_map(fn ($h) => "<th>{$h}</th>", $headers));
        $rowsHtml    = implode('', array_map(fn ($row) =>
            '<tr>' . implode('', array_map(fn ($cell) => '<td>' . htmlspecialchars((string)$cell) . '</td>', $row)) . '</tr>',
            $rows
        ));
        $count = count($rows);

        $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
body{font-family:Arial,sans-serif;font-size:10px;color:#333;}
h1{font-size:16px;color:#1a56db;margin-bottom:4px;}
.meta{font-size:9px;color:#666;margin-bottom:16px;}
table{width:100%;border-collapse:collapse;}
th{background:#f3f4f6;text-align:left;padding:6px;border:1px solid #d1d5db;font-size:9px;}
td{padding:5px 6px;border:1px solid #e5e7eb;font-size:9px;}
tr:nth-child(even){background:#f9fafb;}
.footer{margin-top:16px;font-size:8px;color:#9ca3af;}
</style></head><body>
<h1>{$title}</h1>
<div class="meta">{$appName} | Generated: {$generatedAt} | Records: {$count}</div>
<table><thead><tr>{$headerHtml}</tr></thead><tbody>{$rowsHtml}</tbody></table>
<div class="footer">Generated by {$appName}</div>
</body></html>
HTML;

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper('A4', count($headers) > 6 ? 'landscape' : 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->filename($reportId, 'pdf') . '"',
        ]);
    }

    private function reportTitle(string $reportId): string
    {
        return match($reportId) {
            'overdue-calibration' => 'Overdue Calibration & Maintenance',
            'due-soon'            => 'Calibration & Maintenance Due Soon',
            'devices-by-site'     => 'Devices by Site',
            'open-faults'         => 'Open Fault Reports',
            'maintenance-history' => 'Maintenance Event History',
            'booking-summary'     => 'Booking Summary',
            'audit-log-export'    => 'Audit Log Export',
            default               => $reportId,
        };
    }

    private function filename(string $reportId, string $ext): string
    {
        return $reportId . '_' . now()->format('Y-m-d') . '.' . $ext;
    }
}
