<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('site_id')->constrained('sites');

            $table->unsignedSmallInteger('status_id');
            $table->foreign('status_id')->references('id')->on('test_system_statuses');

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->index('site_id');
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_systems');
    }
};
