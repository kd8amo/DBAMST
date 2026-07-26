<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Device;
use App\Models\FaultReport;
use App\Models\FaultReportStatus;
use App\Models\MaintenanceSchedule;
use App\Models\TestSystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Return a summary of key metrics for the dashboard.
     * Accessible by all authenticated users.
     */
    public function summary(Request $request): JsonResponse
    {
        $warningDays = (int) \App\Models\SystemSetting::getValue('calibration_due_warning_days', 30);
        $threshold   = now()->addDays($warningDays);

        // Calibration/maintenance counts
        $overdueCount  = MaintenanceSchedule::where('next_due_at', '<', now())->count();
        $dueSoonCount  = MaintenanceSchedule::whereBetween('next_due_at', [now(), $threshold])->count();

        // Fault report counts
        $openFaultStatus       = FaultReportStatus::where('name', 'open')->value('id');
        $inProgressFaultStatus = FaultReportStatus::where('name', 'in_progress')->value('id');
        $openFaultsCount       = FaultReport::whereIn('status_id', [$openFaultStatus, $inProgressFaultStatus])->count();

        // Device counts
        $totalDevices    = Device::where('is_active', true)->count();
        $assignedDevices = Device::where('is_active', true)
            ->whereHas('status', fn ($q) => $q->where('name', 'assigned'))
            ->count();

        // Test system count
        $totalSystems = TestSystem::where('is_active', true)->count();

        // Upcoming bookings (next 7 days)
        $confirmedStatusId = BookingStatus::where('name', 'confirmed')->value('id');
        $upcomingBookings  = Booking::with(['testSystem'])
            ->where('status_id', $confirmedStatusId)
            ->where('starts_at', '>=', now())
            ->where('starts_at', '<=', now()->addDays(7))
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        // Recent audit activity
        $recentActivity = \App\Models\AuditLog::with(['user'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Overdue items detail (top 5)
        $overdueItems = MaintenanceSchedule::with(['device.site', 'eventType'])
            ->where('next_due_at', '<', now())
            ->orderBy('next_due_at')
            ->limit(5)
            ->get();

        // Open faults detail (top 5)
        $openFaults = FaultReport::with(['device', 'testSystem', 'status'])
            ->whereIn('status_id', [$openFaultStatus, $inProgressFaultStatus])
            ->orderByDesc('reported_at')
            ->limit(5)
            ->get();

        return response()->json([
            'counts' => [
                'overdue_maintenance' => $overdueCount,
                'due_soon'            => $dueSoonCount,
                'open_faults'         => $openFaultsCount,
                'total_devices'       => $totalDevices,
                'assigned_devices'    => $assignedDevices,
                'total_systems'       => $totalSystems,
            ],
            'upcoming_bookings' => $upcomingBookings,
            'recent_activity'   => $recentActivity,
            'overdue_items'     => $overdueItems,
            'open_faults'       => $openFaults,
        ]);
    }
}
