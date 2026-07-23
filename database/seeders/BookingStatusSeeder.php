<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'requested'],
            ['name' => 'confirmed'],
            ['name' => 'cancelled'],
        ];

        foreach ($statuses as $status) {
            DB::table('booking_statuses')->updateOrInsert(
                ['name' => $status['name']],
                $status
            );
        }
    }
}
