<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');
            $table->foreignId('test_system_id')->constrained('test_systems');

            // 'fixed' or 'swappable' — informational/reporting only, does not
            // block unassignment. Lives on the relationship, not the device,
            // since a device's fixed/swappable role can change between systems.
            $table->string('assignment_type', 10)->default('swappable');

            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('ended_at')->nullable(); // NULL = currently active assignment

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('ended_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();

            $table->index('test_system_id');
            $table->index(['device_id', 'started_at']);
        });

        // Laravel's schema builder has no first-class partial-index syntax,
        // so this is added as raw SQL. Enforces "at most one open assignment
        // per device" at the database level — proven against live Postgres
        // during schema design (see project decision log).
        DB::statement(
            'CREATE UNIQUE INDEX idx_one_open_assignment_per_device
             ON assignments (device_id) WHERE ended_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
