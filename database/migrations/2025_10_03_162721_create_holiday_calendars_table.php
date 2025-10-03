<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('holiday_calendars', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id(); // BIGINT UNSIGNED
            $table->unsignedMediumInteger('year')->unique();
            $table->enum('status', ['draft','active'])->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_calendars');
    }
};
