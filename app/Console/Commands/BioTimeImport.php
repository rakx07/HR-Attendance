<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BioTimeImport extends Command
{
    protected $signature = 'biotime:import
        {--days=2 : If no from/to, import last N days}
        {--from= : YYYY-MM-DD}
        {--to= : YYYY-MM-DD}
        {--summary : Print imported rows}
        {--dry : Don’t write to DB (preview)}';

    protected $description = 'Import BioTime iclock_transaction rows into attendance_raw (device_user_id = emp_code)';

    public function handle(): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : now()->subDays((int)$this->option('days'))->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : now()->endOfDay();

        $summary = (bool) $this->option('summary');
        $dry     = (bool) $this->option('dry');

        $this->info("BioTime import window: {$from} → {$to}");

        $has = fn (string $c) => Schema::hasColumn('attendance_raw', $c);

        // ---------- Build lookup maps ----------
        $users = DB::table('users')->select('id', 'school_id', 'zkteco_user_id')->get();

        // Primary: map ZKTeco user code
        $byZkCode = $users
            ->whereNotNull('zkteco_user_id')
            ->mapWithKeys(function ($u) {
                $k = trim((string) $u->zkteco_user_id);
                return $k === '' ? [] : [$k => (int) $u->id];
            })
            ->toArray();

        // Fallback: map school_id (some orgs use this as device PIN/code)
        $bySchoolId = $users
            ->whereNotNull('school_id')
            ->mapWithKeys(function ($u) {
                $k = trim((string) $u->school_id);
                return $k === '' ? [] : [$k => (int) $u->id];
            })
            ->toArray();

        $imported = 0;

        DB::connection('biotime')
            ->table('iclock_transaction')
            ->select([
                'id',           // required by chunkById
                'emp_id',       // BioTime internal user id
                'emp_code',     // device-visible code/PIN (what we see as ZKTeco user code)
                'punch_time',
                'punch_state',
                'verify_type',
                'terminal_sn',
            ])
            ->whereBetween('punch_time', [$from, $to])
            ->orderBy('id') // chunkById requires deterministic order by primary key
            ->chunkById(1000, function ($rows) use ($summary, $dry, $has, $byZkCode, $bySchoolId, &$imported) {

                foreach ($rows as $r) {
                    if (empty($r->punch_time)) {
                        continue;
                    }

                    // Normalize
                    $code = isset($r->emp_code) ? trim((string) $r->emp_code) : null; // e.g., "17"
                    $eid  = isset($r->emp_id)   ? trim((string) $r->emp_id)   : null; // e.g., "148"
                    // If you ever face leading-zero mismatches (e.g., "017" vs "17"), uncomment:
                    // $code = $code !== null ? ltrim($code, '0') : null;

                    $ts = Carbon::parse($r->punch_time);

                    // Keep device_user_id as what the device reports (best for de-dupe/uniqueness)
                    $deviceUserId = $code ?: $eid ?: null;

                    // --------- Link order ----------
                    // 1) emp_code -> users.zkteco_user_id
                    // 2) emp_code -> users.school_id
                    // 3) emp_id   -> users.zkteco_user_id (rare; last resort)
                    $userId = null;
                    if ($code && isset($byZkCode[$code])) {
                        $userId = $byZkCode[$code];
                        $linkHow = 'zk_by_code';
                    } elseif ($code && isset($bySchoolId[$code])) {
                        $userId = $bySchoolId[$code];
                        $linkHow = 'school_by_code';
                    } elseif ($eid && isset($byZkCode[$eid])) {
                        $userId = $byZkCode[$eid];
                        $linkHow = 'zk_by_empid';
                    } else {
                        $linkHow = 'unlinked';
                    }

                    if ($summary) {
                        $this->line(sprintf(
                            'emp_code=%-10s emp_id=%-6s %s %s',
                            (string) $r->emp_code,
                            (string) $r->emp_id,
                            $ts->toDateTimeString(),
                            $userId ? "→ user_id={$userId} ({$linkHow})" : '(unlinked)'
                        ));
                    }

                    if ($dry) {
                        $imported++;
                        continue;
                    }

                    // Build row constrained to existing columns
                    $row = [
                        'device_user_id' => $deviceUserId,
                        'punched_at'     => $ts,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    if ($has('user_id'))    $row['user_id']    = $userId;
                    if ($has('device_sn'))  $row['device_sn']  = $r->terminal_sn ?? null;
                    if ($has('state'))      $row['state']      = $r->punch_state ?? null;
                    if ($has('punch_type')) $row['punch_type'] = $r->verify_type ?? null;
                    if ($has('source'))     $row['source']     = 'biotime';
                    if ($has('payload')) {
                        $row['payload'] = json_encode([
                            'emp_code'    => $r->emp_code,
                            'emp_id'      => $r->emp_id,
                            'punch_state' => $r->punch_state,
                            'verify_type' => $r->verify_type,
                            'terminal_sn' => $r->terminal_sn,
                            'link'        => $linkHow,
                        ], JSON_UNESCAPED_UNICODE);
                    }

                    // Natural de-dupe key: device_user_id + punched_at
                    DB::table('attendance_raw')->updateOrInsert(
                        [
                            'device_user_id' => $deviceUserId,
                            'punched_at'     => $ts,
                        ],
                        $row
                    );

                    $imported++;
                }
            });

        $this->info(($dry ? '[DRY] ' : '') . "Imported {$imported} punch(es).");
        return self::SUCCESS;
    }
}
