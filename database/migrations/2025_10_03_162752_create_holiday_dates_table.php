<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('holiday_dates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('holiday_calendar_id'); // BIGINT UNSIGNED to match calendars.id
            $table->date('date');
            $table->string('name');
            $table->boolean('is_non_working')->default(true);
            $table->timestamps();

            $table->unique(['holiday_calendar_id','date']);
            $table->index('date');

            $table->foreign('holiday_calendar_id')
                  ->references('id')->on('holiday_calendars')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_dates');
    }
};
