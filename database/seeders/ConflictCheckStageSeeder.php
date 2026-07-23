<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConflictCheckStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'requested'],
            ['name' => 'confirmed'],
        ];

        foreach ($stages as $stage) {
            DB::table('conflict_check_stages')->updateOrInsert(
                ['name' => $stage['name']],
                $stage
            );
        }
    }
}
