<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Prefixes are used as the leading component of auto-generated asset tags.
        // e.g. a measurement device gets asset tag MEA-000001.
        $categories = [
            ['name' => 'measurement',       'prefix' => 'MEA'],
            ['name' => 'load_emulation',    'prefix' => 'LOD'],
            ['name' => 'emulation',         'prefix' => 'EMU'],
            ['name' => 'automotive_comms',  'prefix' => 'COM'],
            ['name' => 'signal_generation', 'prefix' => 'SIG'],
        ];

        foreach ($categories as $category) {
            DB::table('device_categories')->updateOrInsert(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
