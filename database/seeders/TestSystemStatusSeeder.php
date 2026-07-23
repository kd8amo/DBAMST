<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestSystemStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'active'],
            ['name' => 'in_maintenance'],
            ['name' => 'retired'],
        ];

        foreach ($statuses as $status) {
            DB::table('test_system_statuses')->updateOrInsert(
                ['name' => $status['name']],
                $status
            );
        }
    }
}
