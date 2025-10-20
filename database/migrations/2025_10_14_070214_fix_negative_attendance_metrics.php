<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run this migration without wrapping it in a transaction */
    public $withinTransaction = false;

    public function up(): void
    {
        $table = 'attendance_days';

        // Bail out early if table doesn't exist
        if (!Schema::hasTable($table)) {
            return;
        }

        // Get actual columns present
        $cols = array_flip(Schema::getColumnListing($table));

        // Candidate fields we might need to clamp (common names across your variants)
        $candidates = [
            'late_minutes',
            'undertime_minutes',

            // hours / rendered totals (pick any that exist)
            'total_work_hours',
            'total_hours',
            'hours_rendered',
            'total_hours_rendered',
            'total_minutes_rendered',   // some schemas store minutes instead of hours
        ];

        // Build SET parts only for columns that exist
        $setParts = [];
        foreach ($candidates as $c) {
            if (isset($cols[$c])) {
                $setParts[] = "$c = CASE WHEN $c < 0 THEN 0 ELSE $c END";
            }
        }

        if (!empty($setParts)) {
            DB::statement("UPDATE {$table} SET " . implode(", ", $setParts));
        }

        // OPTIONAL: add CHECK constraints only for the columns that exist (ignore if unsupported)
        foreach ($candidates as $c) {
            if (isset($cols[$c])) {
                $constraint = "chk_{$table}_{$c}_nonneg";
                try {
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$c} >= 0)");
                } catch (\Throwable $e) {
                    // ignore (older MySQL/MariaDB may not enforce CHECK or it might already exist)
                }
            }
        }
    }

    public function down(): void
    {
        $table = 'attendance_days';
        if (!Schema::hasTable($table)) return;

        $cols = array_flip(Schema::getColumnListing($table));
        $candidates = [
            'late_minutes',
            'undertime_minutes',
            'total_work_hours',
            'total_hours',
            'hours_rendered',
            'total_hours_rendered',
            'total_minutes_rendered',
        ];

        foreach ($candidates as $c) {
            if (isset($cols[$c])) {
                $constraint = "chk_{$table}_{$c}_nonneg";
                try {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
                } catch (\Throwable $e) {
                    // ignore if not present/unsupported
                }
            }
        }
    }
};
