<?php

// database/migrations/xxxx_xx_xx_create_attendance_adjustments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('attendance_adjustments', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->constrained('users');
      $t->date('work_date')->index();
      $t->enum('field', ['am_in','am_out','pm_in','pm_out']);
      $t->dateTime('old_value')->nullable();
      $t->dateTime('new_value')->nullable();
      $t->foreignId('edited_by')->constrained('users'); // who edited
      $t->string('reason')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('attendance_adjustments'); }
};

