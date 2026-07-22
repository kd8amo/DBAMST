<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 50)->unique();
        });

        DB::table('notification_types')->insert([
            ['name' => 'calibration_due'],
            ['name' => 'calibration_overdue'],
            ['name' => 'booking_override'],
            ['name' => 'fault_reported'],
            ['name' => 'stale_booking_request'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
