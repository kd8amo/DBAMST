<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenanceEventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'calibration'],
            ['name' => 'preventive_maintenance'],
            ['name' => 'repair'],
        ];

        foreach ($types as $type) {
            DB::table('maintenance_event_types')->updateOrInsert(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
