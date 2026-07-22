<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_device_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('device_id')->constrained('devices');

            $table->unique(['booking_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_device_snapshots');
    }
};
