<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('code', 50)->nullable()->unique();
            $t->string('description')->nullable();
            $t->boolean('active')->default(true)->index();
            $t->timestamps();
        });

        // Optional: current department as FK while keeping your old varchar
        if (!Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->foreignId('department_id')->nullable()->after('department')
                    ->constrained('departments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'department_id')) {
                $t->dropConstrainedForeignId('department_id');
            }
        });
        Schema::dropIfExists('departments');
    }
};
