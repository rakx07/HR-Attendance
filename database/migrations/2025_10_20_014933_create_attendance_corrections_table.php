<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('work_date');

            // Store only time-of-day; merge with date in controller when mirroring
            $table->time('am_in')->nullable();
            $table->time('am_out')->nullable();
            $table->time('pm_in')->nullable();
            $table->time('pm_out')->nullable();

            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('edited_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id','work_date']);
            $table->index(['work_date','user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
