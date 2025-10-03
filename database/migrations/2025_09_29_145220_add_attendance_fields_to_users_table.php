<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'zkteco_user_id')) {
                $t->unsignedInteger('zkteco_user_id')->nullable()->index();
            }
            if (!Schema::hasColumn('users', 'shift_window_id')) {
                $t->foreignId('shift_window_id')
                  ->nullable()
                  ->constrained('shift_windows')
                  ->nullOnDelete(); // if a shift is removed, keep user, set null
            }
            if (!Schema::hasColumn('users', 'flexi_start')) {
                $t->time('flexi_start')->nullable();
            }
            if (!Schema::hasColumn('users', 'flexi_end')) {
                $t->time('flexi_end')->nullable();
            }
            if (!Schema::hasColumn('users', 'department')) {
                $t->string('department')->nullable()->index();
            }
            if (!Schema::hasColumn('users', 'active')) {
                $t->boolean('active')->default(true)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'active')) $t->dropColumn('active');
            if (Schema::hasColumn('users', 'department')) $t->dropColumn('department');
            if (Schema::hasColumn('users', 'flexi_end')) $t->dropColumn('flexi_end');
            if (Schema::hasColumn('users', 'flexi_start')) $t->dropColumn('flexi_start');
            if (Schema::hasColumn('users', 'shift_window_id')) $t->dropConstrainedForeignId('shift_window_id');
            if (Schema::hasColumn('users', 'zkteco_user_id')) $t->dropColumn('zkteco_user_id');
        });
    }
};
