<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'unassigned','assigned','out_for_calibration','retired'
            $table->string('name', 30)->unique();
        });

        // Fixed status set. NOTE per project decision: 'retired' is the only status
        // where devices.is_active should also flip to false — enforced at the
        // application layer (both fields updated together in the same transaction),
        // not by the database.
        DB::table('device_statuses')->insert([
            ['name' => 'unassigned'],
            ['name' => 'assigned'],
            ['name' => 'out_for_calibration'],
            ['name' => 'retired'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('device_statuses');
    }
};
