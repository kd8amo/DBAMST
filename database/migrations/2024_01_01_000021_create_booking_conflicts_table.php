<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');

            $table->unsignedSmallInteger('conflict_type_id');
            $table->foreign('conflict_type_id')->references('id')->on('conflict_types');

            $table->unsignedSmallInteger('check_stage_id');
            $table->foreign('check_stage_id')->references('id')->on('conflict_check_stages');

            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->text('detail')->nullable();
            $table->timestampTz('detected_at')->useCurrent();

            $table->boolean('overridden')->default(false);
            $table->foreignId('overridden_by')->nullable()->constrained('users');
            $table->timestampTz('overridden_at')->nullable();
            $table->text('override_reason')->nullable();

            $table->index(['booking_id', 'check_stage_id']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_conflicts');
    }
};
