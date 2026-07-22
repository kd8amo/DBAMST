<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_system_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'active','in_maintenance','retired'
            $table->string('name', 30)->unique();
        });

        DB::table('test_system_statuses')->insert([
            ['name' => 'active'],
            ['name' => 'in_maintenance'],
            ['name' => 'retired'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('test_system_statuses');
    }
};
