<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');

            $table->unsignedSmallInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles');

            $table->timestamp('created_at')->useCurrent();

            // A user can hold a given role at most once — proven against
            // Postgres (a duplicate assignment is correctly rejected).
            $table->unique(['user_id', 'role_id']);
        });

        // Backfill: every existing user's current default role (users.role_id)
        // must also appear here, per project decision that the default role is
        // always a subset of the roles a user is permitted to act as.
        DB::statement(
            'INSERT INTO user_roles (user_id, role_id, created_at)
             SELECT id, role_id, now() FROM users
             ON CONFLICT (user_id, role_id) DO NOTHING'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
