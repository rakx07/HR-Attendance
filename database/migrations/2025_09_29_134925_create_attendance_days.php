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
        Schema::create('attendance_days', function (Blueprint $t) {
  $t->id();
  $t->foreignId('user_id')->constrained('users');
  $t->date('work_date')->index();
  $t->dateTime('am_in')->nullable();
  $t->dateTime('am_out')->nullable();
  $t->dateTime('pm_in')->nullable();
  $t->dateTime('pm_out')->nullable();
  $t->integer('late_minutes')->default(0);
  $t->integer('undertime_minutes')->default(0);
  $t->decimal('total_hours',5,2)->default(0);
  $t->string('status', 16)->default('Present');
  $t->timestamps();
  $t->unique(['user_id','work_date']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
    }
};
