<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('department_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->foreignId('to_department_id')->constrained('departments')->cascadeOnDelete();
            $t->text('reason')->nullable();
            $t->timestamp('effective_at')->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_transfers');
    }
};
