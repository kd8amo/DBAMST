<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_system_id')->constrained('test_systems');

            $table->unsignedSmallInteger('status_id');
            $table->foreign('status_id')->references('id')->on('booking_statuses');

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('purpose', 255)->nullable();

            $table->foreignId('requested_by')->constrained('users');
            $table->timestampTz('requested_at')->useCurrent();

            // NULL until confirmed. Must be Scheduler/Manager/Admin — app-enforced,
            // not a DB-level rule (no role check is expressible as a simple FK).
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestampTz('confirmed_at')->nullable();

            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->index(['test_system_id', 'starts_at', 'ends_at']);
            $table->index('status_id');
        });

        // Raw SQL: no fluent Blueprint check-constraint API (same pattern as
        // fault_target_check). Proven against live Postgres during schema testing
        // — an ends_at <= starts_at booking is correctly rejected.
        DB::statement(
            'ALTER TABLE bookings ADD CONSTRAINT booking_time_check CHECK (ends_at > starts_at)'
        );

        // Partial index for the stale-request background scan (status_id = 1 is
        // 'requested' per the seed order above).
        DB::statement(
            'CREATE INDEX idx_bookings_requested_at ON bookings(requested_at) WHERE status_id = 1'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
