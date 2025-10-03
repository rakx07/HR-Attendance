<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_raw', function (Blueprint $t) {
            $t->id();

            // Device-linked user id from the terminal (not your app's users.id)
            $t->unsignedInteger('device_user_id')->index();

            // Exact time the device recorded the punch
            $t->dateTime('punched_at')->index();

            // Optional, but very helpful for multi-terminal environments
            $t->string('device_sn')->nullable()->index(); // terminal serial no / identifier

            // Source of the log: allow more values for testing
            $t->string('source', 20)->default('pull'); 
            // e.g. pull (from device), push (device sends), manual (encoded),
            // seed/mock (for testing)

            // Optional metadata
            $t->string('punch_type')->nullable(); // in/out if device provides, else null
            $t->json('payload')->nullable();      // raw payload/extra fields if you store them

            $t->timestamps();

            // Unique constraint that tolerates multiple devices
            $t->unique(['device_user_id','punched_at','device_sn'], 'raw_device_user_time_sn_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_raw');
    }
};
