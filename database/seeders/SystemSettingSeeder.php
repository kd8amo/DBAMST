<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        // All values are strings — cast to appropriate types in application
        // code via SystemSetting::getValue() / SystemSetting::getIntList().
        $settings = [
            // How many days before due date to send a "calibration due soon" alert.
            [
                'key'   => 'calibration_due_warning_days',
                'value' => '30',
            ],
            // Escalating cadence (days) for stale booking request notifications.
            // e.g. notify at 7, 14, 21, 30 days after the request was made.
            [
                'key'   => 'stale_booking_notify_days',
                'value' => '7,14,21,30',
            ],
            // Default locale for new users before they set their own preference.
            [
                'key'   => 'default_locale',
                'value' => 'en',
            ],
            // Application display name shown in email notifications.
            [
                'key'   => 'app_display_name',
                'value' => 'Test System Maintenance',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
