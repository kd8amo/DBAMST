<?php

namespace App\Services;

use App\Mail\CalibrationDueMail;
use App\Models\MaintenanceSchedule;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * UC-29: Check for due/overdue calibration and maintenance items
     * and send notifications to the appropriate role-based distribution list.
     */
    public function sendCalibrationDueNotifications(): void
    {
        $warningDays = (int) SystemSetting::getValue('calibration_due_warning_days', 30);
        $threshold   = now()->addDays($warningDays);
        $appName     = SystemSetting::getValue('app_display_name', 'Test System Maintenance');

        // Fetch overdue items
        $overdueSchedules = MaintenanceSchedule::with(['device.site', 'eventType'])
            ->where('next_due_date', '<', now())
            ->get();

        // Fetch due-soon items
        $dueSoonSchedules = MaintenanceSchedule::with(['device.site', 'eventType'])
            ->whereBetween('next_due_date', [now(), $threshold])
            ->get();

        if ($overdueSchedules->isEmpty() && $dueSoonSchedules->isEmpty()) {
            Log::info('CalibrationDueNotification: No items due or overdue.');
            return;
        }

        // Format for email
        $overdueItems = $overdueSchedules->map(fn ($s) => [
            'asset_tag'    => $s->device->asset_tag,
            'manufacturer' => $s->device->manufacturer,
            'model'        => $s->device->model,
            'site'         => $s->device->site->name ?? '—',
            'event_type'   => $s->eventType->name ?? '—',
            'next_due_date'  => $s->next_due_date->toDateString(),
        ])->toArray();

        $dueSoonItems = $dueSoonSchedules->map(fn ($s) => [
            'asset_tag'    => $s->device->asset_tag,
            'manufacturer' => $s->device->manufacturer,
            'model'        => $s->device->model,
            'site'         => $s->device->site->name ?? '—',
            'event_type'   => $s->eventType->name ?? '—',
            'next_due_date'  => $s->next_due_date->toDateString(),
        ])->toArray();

        // Get recipients — technicians, scheduler/managers, and admins
        $recipientRoles = [Role::TECHNICIAN, Role::SCHEDULER_MANAGER, Role::ADMIN];
        $recipients = User::where('is_active', true)
            ->whereHas('availableRoles', fn ($q) => $q->whereIn('name', $recipientRoles))
            ->pluck('email')
            ->toArray();

        if (empty($recipients)) {
            Log::warning('CalibrationDueNotification: No recipients found.');
            return;
        }

        // Send email
        $mailable = new CalibrationDueMail($overdueItems, $dueSoonItems, $appName);
        Mail::to($recipients)->send($mailable);

        // Create in-app notifications
        $notificationTypeId = NotificationType::where('name', 'calibration_due')->value('id');

        foreach (User::where('is_active', true)
            ->whereHas('availableRoles', fn ($q) => $q->whereIn('name', $recipientRoles))
            ->get() as $user) {
	Notification::create([
	    'recipient_user_id'    => $user->id,
	    'notification_type_id' => $notificationTypeId,
	    'message'              => count($overdueItems) > 0
		? count($overdueItems) . ' device(s) overdue for maintenance. Check the maintenance dashboard for details.'
		: count($dueSoonItems) . ' device(s) due for maintenance soon. Check the maintenance dashboard for details.',
	]);

        }

        Log::info('CalibrationDueNotification: Sent to ' . count($recipients) . ' recipients. '
            . count($overdueItems) . ' overdue, ' . count($dueSoonItems) . ' due soon.');
    }
}
