<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zkteco_users', function (Blueprint $t) {
            $t->id();

            // Primary keys from device
            $t->unsignedInteger('device_user_id')->index();   // the "User ID" you see on device (school ID)
            $t->string('name')->nullable()->index();          // device Name field (optional)
            $t->string('card_no')->nullable()->index();       // RFID/card (if used)
            $t->unsignedTinyInteger('privilege')->nullable(); // 0=user, 14=admin (varies by firmware)
            $t->boolean('enabled')->default(true);

            // Multi-device support
            $t->string('device_sn')->nullable()->index();     // device serial/label
            $t->timestamp('pulled_at')->nullable();           // when this row was fetched
            $t->json('raw')->nullable();                      // raw payload from SDK

            $t->timestamps();

            // avoid duplicates per device
            $t->unique(['device_user_id', 'device_sn'], 'zkteco_users_uid_sn_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_users');
    }
};
