<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            // 'requested','confirmed','cancelled'
            $table->string('name', 20)->unique();
        });

        DB::table('booking_statuses')->insert([
            ['name' => 'requested'],
            ['name' => 'confirmed'],
            ['name' => 'cancelled'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_statuses');
    }
};
