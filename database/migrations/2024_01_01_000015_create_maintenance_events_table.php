<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');

            $table->unsignedSmallInteger('event_type_id');
            $table->foreign('event_type_id')->references('id')->on('maintenance_event_types');

            // NULL for repairs / one-off events not tied to a recurring schedule.
            $table->foreignId('schedule_id')->nullable()->constrained('maintenance_schedules');

            $table->date('performed_at');

            // NULL if performed by an external vendor rather than an in-house user.
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users');
            $table->string('performed_by_vendor', 150)->nullable();

            $table->string('result', 20)->nullable(); // 'pass'/'fail' — calibration only
            $table->date('next_due_date')->nullable(); // auto-suggested from schedule, editable
            $table->text('description')->nullable();

            // Repair only: drives devices.status_id (and is_active, if 'retired')
            // via application-layer side effect — see MaintenanceEvent::logRepair().
            $table->unsignedSmallInteger('resulting_status_id')->nullable();
            $table->foreign('resulting_status_id')->references('id')->on('device_statuses');

            // Repair only: the fault this event closes, if any.
            $table->foreignId('fault_report_id')->nullable()->constrained('fault_reports');

            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['device_id', 'performed_at']);
            $table->index('fault_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_events');
    }
};
