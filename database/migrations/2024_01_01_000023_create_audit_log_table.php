<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();

            // Exactly one of these two is populated per row: a human user action,
            // or a system-to-system action via a scoped API key. Not enforced by
            // a DB check constraint (unlike fault_reports' target check) since
            // "exactly one, not both-or-neither" is stricter and this is purely
            // an attribution field, not a business rule with edge cases to allow.
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys');

            $table->string('action', 50);       // 'device.update','booking.override','user.role_change', etc.
            $table->string('entity_type', 50);  // 'device','booking','user', etc.
            $table->unsignedBigInteger('entity_id');

            // Human-readable summary only — this is a generic action log for
            // accountability ("who did what, when"), not a field-level diff
            // table. Business-history queries ("device history", "equipment
            // used for this activity") are answered directly from the entity
            // tables (assignments, maintenance_events, booking_device_snapshots),
            // per project decision.
            $table->text('summary')->nullable();

            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
