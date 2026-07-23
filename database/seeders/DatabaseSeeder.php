<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters — lookup tables first, then anything that references them.
     * All seeders use updateOrInsert so they are safe to re-run without
     * duplicating rows (idempotent).
     */
    public function run(): void
    {
        $this->call([
            // Lookup tables — no foreign key dependencies
            RoleSeeder::class,
            DeviceCategorySeeder::class,
            DeviceStatusSeeder::class,
            TestSystemStatusSeeder::class,
            BookingStatusSeeder::class,
            FaultReportStatusSeeder::class,
            MaintenanceEventTypeSeeder::class,
            ConflictTypeSeeder::class,
            ConflictCheckStageSeeder::class,
            NotificationTypeSeeder::class,

            // Application configuration
            SystemSettingSeeder::class,
        ]);
    }
}
