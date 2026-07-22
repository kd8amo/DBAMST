<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_check_stages', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'requested','confirmed' — conflict detection runs at both points
            $table->string('name', 20)->unique();
        });

        DB::table('conflict_check_stages')->insert([
            ['name' => 'requested'],
            ['name' => 'confirmed'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_check_stages');
    }
};
