<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'calibration_due'],
            ['name' => 'calibration_overdue'],
            ['name' => 'booking_override'],
            ['name' => 'fault_reported'],
            ['name' => 'stale_booking_request'],
        ];

        foreach ($types as $type) {
            DB::table('notification_types')->updateOrInsert(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
