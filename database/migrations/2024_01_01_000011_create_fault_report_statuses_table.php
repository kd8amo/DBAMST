<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_report_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'open','in_progress','resolved'
            $table->string('name', 20)->unique();
        });

        DB::table('fault_report_statuses')->insert([
            ['name' => 'open'],
            ['name' => 'in_progress'],
            ['name' => 'resolved'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_report_statuses');
    }
};
