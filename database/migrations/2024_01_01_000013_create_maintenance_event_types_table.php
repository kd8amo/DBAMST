<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_event_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'calibration','preventive_maintenance','repair'
            $table->string('name', 30)->unique();
        });

        DB::table('maintenance_event_types')->insert([
            ['name' => 'calibration'],
            ['name' => 'preventive_maintenance'],
            ['name' => 'repair'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_event_types');
    }
};
