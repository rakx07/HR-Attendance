<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('shift_windows', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->time('am_in_start');
    $table->time('am_in_end');
    $table->time('am_out_start');
    $table->time('am_out_end');
    $table->time('pm_in_start');
    $table->time('pm_in_end');
    $table->time('pm_out_start');
    $table->time('pm_out_end');
    $table->unsignedSmallInteger('grace_minutes')->default(0);
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_windows');
    }
};
