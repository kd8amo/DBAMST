<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');

            $table->foreignId('from_site_id')->constrained('sites');
            $table->foreignId('to_site_id')->constrained('sites');

            $table->timestampTz('transferred_at')->useCurrent();
            $table->foreignId('created_by')->constrained('users');
            $table->text('notes')->nullable();

            $table->index(['device_id', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
