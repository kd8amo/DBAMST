<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendCalibrationNotifications extends Command
{
    protected $signature   = 'notifications:calibration-due';
    protected $description = 'Check for due/overdue calibration and send email + in-app notifications';

    public function handle(NotificationService $service): void
    {
        $this->info('Checking for due/overdue calibration items...');
        $service->sendCalibrationDueNotifications();
        $this->info('Done.');
    }
}
