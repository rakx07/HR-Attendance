<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shift_window_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shift_window_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('dow'); // 1=Mon ... 7=Sun
            $t->boolean('is_working')->default(true);
            $t->time('am_in')->nullable();
            $t->time('am_out')->nullable();
            $t->time('pm_in')->nullable();
            $t->time('pm_out')->nullable();
            $t->timestamps();
            $t->unique(['shift_window_id','dow']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_window_days');
    }
};
