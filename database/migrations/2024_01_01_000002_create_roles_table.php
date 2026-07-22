<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'engineer', 'technician', 'scheduler_manager', 'admin', 'auditor'
            $table->string('name', 30)->unique();
        });

        // Seed the fixed 5-role set immediately — this is a closed lookup list,
        // not something users add rows to via the GUI.
        DB::table('roles')->insert([
            ['name' => 'engineer'],
            ['name' => 'technician'],
            ['name' => 'scheduler_manager'],
            ['name' => 'admin'],
            ['name' => 'auditor'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
