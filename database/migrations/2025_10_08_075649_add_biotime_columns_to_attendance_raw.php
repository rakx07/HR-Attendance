<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_biotime_columns_to_attendance_raw.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendance_raw', function (Blueprint $t) {
            if (!Schema::hasColumn('attendance_raw','device_sn'))  $t->string('device_sn')->nullable()->after('device_user_id');
            if (!Schema::hasColumn('attendance_raw','punch_type')) $t->string('punch_type', 50)->nullable()->after('state');
            if (!Schema::hasColumn('attendance_raw','source'))     $t->string('source', 32)->nullable()->after('device_ip');
        });
    }
    public function down(): void {
        Schema::table('attendance_raw', function (Blueprint $t) {
            if (Schema::hasColumn('attendance_raw','device_sn'))  $t->dropColumn('device_sn');
            if (Schema::hasColumn('attendance_raw','punch_type')) $t->dropColumn('punch_type');
            if (Schema::hasColumn('attendance_raw','source'))     $t->dropColumn('source');
        });
    }
};
