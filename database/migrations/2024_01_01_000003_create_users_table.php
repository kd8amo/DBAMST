<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255);
            $table->string('display_name', 150);

            $table->unsignedSmallInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles');

            // "Home" site: default/filter/notification-scope only — NOT an access boundary.
            $table->foreignId('site_id')->nullable()->constrained('sites');

            $table->string('locale', 10)->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('role_id');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
