<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maps BioTime employees to your local users table.
 *
 * - Looks for common column aliases across BioTime builds
 *   (emp_code|pin|employee_code, emp_id|userid|id, emp_name|name).
 * - If a numeric employee id column exists, writes it to users.zkteco_user_id.
 * - Always tries to match by code => users.school_id.
 */
class BioTimeSyncUsers extends Command
{
    protected $signature = 'biotime:sync-users {--preview : Show what would change without writing}';
    protected $description = 'Sync BioTime employees to local users (sets users.zkteco_user_id when school_id matches)';

    public function handle(): int
    {
        // Detect columns on the fly (different BioTime releases vary)
        $codeCol = $this->firstExisting('personnel_employee', ['emp_code','pin','employee_code','emp_no','code']);
        $idCol   = $this->firstExisting('personnel_employee', ['emp_id','userid','id','employee_id']);
        $nameCol = $this->firstExisting('personnel_employee', ['emp_name','name','employee_name','fullname']);

        if (!$codeCol) {
            $this->error("Could not find an employee 'code' column (looked for emp_code/pin/employee_code).");
            $this->hint();
            return self::FAILURE;
        }

        $this->line("Using columns → code: {$codeCol}".($idCol ? ", id: {$idCol}" : "").($nameCol ? ", name: {$nameCol}" : ""));

        // Build the select list based on what exists
        $select = [$codeCol.' as code'];
        if ($idCol)   $select[] = $idCol.' as emp_id';
        if ($nameCol) $select[] = $nameCol.' as emp_name';

        $rows = DB::connection('biotime')
            ->table('personnel_employee')
            ->selectRaw(implode(',', $select))
            ->orderBy($codeCol)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No employees returned from personnel_employee.');
            return self::SUCCESS;
        }

        $preview = (bool)$this->option('preview');
        $updated = 0; $seen = 0;

        foreach ($rows as $e) {
            $code = trim((string)$e->code);
            if ($code === '') continue;

            $user = DB::table('users')->where('school_id', $code)->first(['id','zkteco_user_id']);
            if (!$user) continue;

            $empId = property_exists($e,'emp_id') ? $e->emp_id : null;
            if ($empId === '' || $empId === null) {
                // Nothing to set—still counts as a seen mapping
                $seen++; 
                if ($preview) $this->line("school_id={$code} → users.id={$user->id} (no numeric emp_id available)");
                continue;
            }

            if ($preview) {
                $this->line("Would set users.id={$user->id} zkteco_user_id={$empId} (school_id={$code})");
                $updated++; $seen++; 
                continue;
            }

            DB::table('users')->where('id', $user->id)->update(['zkteco_user_id' => (string)$empId]);
            $updated++; $seen++;
        }

        $this->info(($preview ? '[PREVIEW] ' : '')."Processed {$seen} match(es) by school_id; updated {$updated} row(s).");
        return self::SUCCESS;
    }

    private function firstExisting(string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (Schema::connection('biotime')->hasColumn($table, $c)) return $c;
        }
        return null;
    }

    private function hint(): void
    {
        $this->line("Tip: run this to inspect columns:");
        $this->line("  php artisan tinker");
        $this->line("  >>> DB::connection('biotime')->select('SHOW COLUMNS FROM personnel_employee');");
    }
}
