<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('notification_type_id');
            $table->foreign('notification_type_id')->references('id')->on('notification_types');

            // Fanned out one row per recipient at creation time — NOT a shared
            // row with a recipient list — so per-user read/unread tracking and
            // the in-app notification center (UC-31) work correctly. Verified
            // against live Postgres: a role-based trigger produces exactly one
            // row per matching, site-scoped recipient.
            $table->foreignId('recipient_user_id')->constrained('users');

            $table->string('entity_type', 50)->nullable(); // 'device','booking','fault_report', etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('message');

            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('emailed_at')->nullable(); // NULL if not yet emailed / in-app only so far

            $table->timestamp('created_at')->useCurrent();

            $table->index(['recipient_user_id', 'is_read']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
