<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConflictTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'maintenance_window'],
            ['name' => 'open_fault'],
            ['name' => 'site_transfer'],
        ];

        foreach ($types as $type) {
            DB::table('conflict_types')->updateOrInsert(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
