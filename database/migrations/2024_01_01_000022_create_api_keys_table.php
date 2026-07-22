<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);   // human label, e.g. "Test Execution System - Site DET"

            // Raw key shown once at creation, only a hash stored — standard
            // practice (GitHub/Stripe-style tokens).
            $table->string('key_hash', 255)->unique();

            // First few chars shown in the UI so an Admin can identify which
            // key is which without ever re-displaying the secret.
            $table->string('key_prefix', 12);

            // Permitted read/write scope, e.g.
            // {"read":["devices","bookings"],"write":["fault_reports","usage_hours"]}.
            // JSONB rather than a rigid table — scope shape is expected to evolve
            // as the API surface grows; validated at the application layer.
            $table->jsonb('scope');

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users'); // must be Admin — app-enforced
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No expires_at: manual revocation only, per project decision.

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
