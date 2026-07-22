<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->string('value', 255);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampTz('updated_at')->useCurrent();
        });

        // Seed values driving the stale-booking escalation job (project
        // decision: escalating cadence, tunable without a deployment).
        DB::table('system_settings')->insert([
            ['key' => 'stale_booking_request_days', 'value' => '7'],
            ['key' => 'stale_booking_notify_days', 'value' => '7,14,21,30'],
            ['key' => 'stale_booking_escalate_admin_days', 'value' => '30'],
            ['key' => 'stale_booking_repeat_after_days', 'value' => '7'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
