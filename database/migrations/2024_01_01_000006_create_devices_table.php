<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            // System-generated, e.g. "DET-MEAS-000123". Stable for the life of the
            // device — does NOT get regenerated if the device transfers sites later.
            $table->string('asset_tag', 30)->unique();

            $table->unsignedSmallInteger('category_id');
            $table->foreign('category_id')->references('id')->on('device_categories');

            $table->string('manufacturer', 100);
            $table->string('model', 100);

            // Manufacturer serial number — secondary ID, optional and NOT unique
            // (manufacturers reuse serials across product lines).
            $table->string('serial_number', 100)->nullable();

            $table->foreignId('site_id')->constrained('sites');

            $table->unsignedSmallInteger('status_id');
            $table->foreign('status_id')->references('id')->on('device_statuses');

            // Reserved for future usage-based scheduling (deferred feature).
            // Populated via API (UC-37) as an incrementing counter; no due-date
            // logic reads this yet in V1.
            $table->decimal('total_usage_hours', 10, 2)->default(0);

            $table->text('notes')->nullable();

            // False only when retired. Paired with status_id = 'retired' in the
            // same transaction at the application layer — see project decision log.
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->index('site_id');
            $table->index('category_id');
            $table->index('status_id');
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
