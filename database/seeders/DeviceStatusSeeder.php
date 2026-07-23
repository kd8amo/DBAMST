<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'unassigned'],
            ['name' => 'assigned'],
            ['name' => 'out_for_calibration'],
            ['name' => 'retired'],
        ];

        foreach ($statuses as $status) {
            DB::table('device_statuses')->updateOrInsert(
                ['name' => $status['name']],
                $status
            );
        }
    }
}
