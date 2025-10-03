<?php

// database/migrations/2025_10_03_100000_alter_users_zkteco_to_string.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      if (Schema::hasColumn('users','zkteco_user_id')) {
        $t->string('zkteco_user_id', 32)->nullable()->change();
      } else {
        $t->string('zkteco_user_id', 32)->nullable()->index();
      }
      // add a safe unique index that ignores nulls (MySQL does this by default)
      $t->unique('zkteco_user_id', 'users_zkteco_user_id_unique');
    });
  }
  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropUnique('users_zkteco_user_id_unique');
      $t->unsignedInteger('zkteco_user_id')->nullable()->change();
    });
  }
};

