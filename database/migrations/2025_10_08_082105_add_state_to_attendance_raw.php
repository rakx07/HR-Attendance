<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_state_to_attendance_raw.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendance_raw', function (Blueprint $t) {
            if (!Schema::hasColumn('attendance_raw','state')) {
                $t->string('state', 50)->nullable()->after('user_id');
            }
        });
    }
    public function down(): void {
        Schema::table('attendance_raw', function (Blueprint $t) {
            if (Schema::hasColumn('attendance_raw','state')) {
                $t->dropColumn('state');
            }
        });
    }
};
