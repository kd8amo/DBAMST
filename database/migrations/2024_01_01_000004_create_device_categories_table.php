<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'measurement','load_emulation','emulation','automotive_comms','signal_generation'
            $table->string('name', 50)->unique();
            // Short code used in generated asset tags, e.g. 'MEAS','LOAD','EMU','COMM','SIG'
            $table->string('prefix', 10)->unique();
        });

        // Fixed 5-category set — closed lookup list, not user-editable via the GUI.
        DB::table('device_categories')->insert([
            ['name' => 'measurement', 'prefix' => 'MEAS'],
            ['name' => 'load_emulation', 'prefix' => 'LOAD'],
            ['name' => 'emulation', 'prefix' => 'EMU'],
            ['name' => 'automotive_comms', 'prefix' => 'COMM'],
            ['name' => 'signal_generation', 'prefix' => 'SIG'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('device_categories');
    }
};
