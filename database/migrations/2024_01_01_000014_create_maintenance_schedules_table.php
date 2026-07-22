<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');

            $table->unsignedSmallInteger('event_type_id');
            $table->foreign('event_type_id')->references('id')->on('maintenance_event_types');
            // 'calibration' or 'preventive_maintenance' only — 'repair' is reactive,
            // never scheduled. Not enforced at the DB level; application-layer rule.

            $table->smallInteger('interval_months');
            $table->date('next_due_date'); // recalculated whenever a fulfilling event is logged
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            // One active schedule per device per event type — proven against
            // Postgres during schema testing (a second calibration schedule for
            // the same device is correctly rejected; a PM schedule is not, since
            // it's a different event type).
            $table->unique(['device_id', 'event_type_id']);
        });

        DB::statement(
            'CREATE INDEX idx_maint_schedules_due ON maintenance_schedules(next_due_date) WHERE is_active = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
