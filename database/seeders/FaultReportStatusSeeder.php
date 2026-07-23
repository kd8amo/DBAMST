<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FaultReportStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'open'],
            ['name' => 'in_progress'],
            ['name' => 'resolved'],
        ];

        foreach ($statuses as $status) {
            DB::table('fault_report_statuses')->updateOrInsert(
                ['name' => $status['name']],
                $status
            );
        }
    }
}
