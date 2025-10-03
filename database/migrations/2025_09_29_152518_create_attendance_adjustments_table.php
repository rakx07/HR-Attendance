<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_adjustments', function (Blueprint $t) {
            $t->id();

            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('work_date')->index();
            $t->enum('field', ['am_in','am_out','pm_in','pm_out']);

            $t->dateTime('old_value')->nullable();
            $t->dateTime('new_value')->nullable();

            $t->foreignId('edited_by')->constrained('users')->restrictOnDelete(); // keep editor integrity
            $t->text('reason')->nullable();

            $t->timestamps();

            // Fast historical lookups (user + date + field)
            $t->index(['user_id','work_date','field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
    }
};
