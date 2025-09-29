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
       Schema::create('attendance_raw', function (Blueprint $t) {
  $t->id();
  $t->foreignId('user_id')->nullable()->constrained('users');
  $t->unsignedInteger('device_user_id')->index();
  $t->dateTime('punched_at')->index();
  $t->unsignedTinyInteger('state')->nullable();
  $t->string('device_ip',45)->nullable();
  $t->boolean('is_duplicate')->default(false)->index();
  $t->timestamps();
  $t->unique(['device_user_id','punched_at']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_raw');
    }
};
