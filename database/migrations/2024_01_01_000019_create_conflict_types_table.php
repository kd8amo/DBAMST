<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'maintenance_window','open_fault','site_transfer'
            $table->string('name', 30)->unique();
        });

        DB::table('conflict_types')->insert([
            ['name' => 'maintenance_window'],
            ['name' => 'open_fault'],
            ['name' => 'site_transfer'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_types');
    }
};
