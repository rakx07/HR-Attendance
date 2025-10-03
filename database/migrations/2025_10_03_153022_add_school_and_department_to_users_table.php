// database/migrations/2025_10_03_000200_add_school_and_department_to_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // school_id (unique, nullable is OK; MySQL allows multiple NULLs)
            if (! Schema::hasColumn('users', 'school_id')) {
                $table->string('school_id', 64)->nullable()->after('email');
                $table->unique('school_id', 'users_school_id_unique');
            }

            // department_id FK (keep your legacy 'department' string if present)
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('department');
                $table->foreign('department_id')
                      ->references('id')->on('departments')
                      ->nullOnDelete();
            }

            // (Optional) ensure zkteco_user_id exists and is unique
            if (! Schema::hasColumn('users', 'zkteco_user_id')) {
                $table->string('zkteco_user_id', 64)->nullable()->after('school_id');
                $table->unique('zkteco_user_id', 'users_zkteco_user_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop FKs/Indexes before columns (guard with hasColumn/hasIndex if desired)
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
            if (Schema::hasColumn('users', 'school_id')) {
                $table->dropUnique('users_school_id_unique');
                $table->dropColumn('school_id');
            }
            if (Schema::hasColumn('users', 'zkteco_user_id')) {
                $table->dropUnique('users_zkteco_user_id_unique');
                $table->dropColumn('zkteco_user_id');
            }
        });
    }
};
