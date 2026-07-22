<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_reports', function (Blueprint $table) {
            $table->id();

            // Nullable: a fault may target a device only, a system only, or both
            // (a device fault may or may not take the whole system down — deliberately
            // flexible per project decision). Enforced by a check constraint below.
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->foreignId('test_system_id')->nullable()->constrained('test_systems');

            $table->unsignedSmallInteger('status_id');
            $table->foreign('status_id')->references('id')->on('fault_report_statuses');

            // Free text, e.g. "Must repair before Test X" / "Not urgent" — deliberately
            // unstructured. Widened to 255 after testing showed 20 chars was too small
            // for realistic sentence-length values.
            $table->string('severity', 255)->nullable();

            // Structured deadline field, separate from the free-text severity — this is
            // what UC-22 conflict detection actually reasons against.
            $table->date('needed_by_date')->nullable();

            $table->text('description');
            $table->foreignId('reported_by')->constrained('users');
            $table->timestampTz('reported_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status_id');
        });

        // Laravel's schema builder has no fluent check-constraint API, so this
        // is added as raw SQL — proven against live Postgres during schema testing.
        DB::statement(
            'ALTER TABLE fault_reports
             ADD CONSTRAINT fault_target_check
             CHECK (device_id IS NOT NULL OR test_system_id IS NOT NULL)'
        );

        // Partial indexes (open/in-progress lookups only) — also raw SQL for the
        // same reason. status_id = 3 is 'resolved' per the seed order above.
        DB::statement('CREATE INDEX idx_faults_device ON fault_reports(device_id) WHERE status_id != 3');
        DB::statement('CREATE INDEX idx_faults_system ON fault_reports(test_system_id) WHERE status_id != 3');
        DB::statement('CREATE INDEX idx_faults_needed_by ON fault_reports(needed_by_date) WHERE needed_by_date IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_reports');
    }
};
