<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_raw', function (Blueprint $t) {
            if (!Schema::hasColumn('attendance_raw', 'user_id')) {
                // Add nullable FK; we’ll backfill next
                $t->foreignId('user_id')
                  ->nullable()
                  ->after('device_user_id')
                  ->constrained('users')
                  ->nullOnDelete();
                $t->index('user_id'); // fast filtering
            }
        });

        // Backfill based on mapping: users.zkteco_user_id == attendance_raw.device_user_id
        // MySQL/MariaDB UPDATE … JOIN
        DB::statement("
            UPDATE attendance_raw ar
            LEFT JOIN users u ON u.zkteco_user_id = ar.device_user_id
            SET ar.user_id = u.id
        ");
    }

    public function down(): void
    {
        Schema::table('attendance_raw', function (Blueprint $t) {
            if (Schema::hasColumn('attendance_raw', 'user_id')) {
                $t->dropConstrainedForeignId('user_id'); // drops FK + index
            }
        });
    }
};
