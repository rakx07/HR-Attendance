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
        {--to=   : YYYY-MM-DD}
        {--summary : Print imported rows}
        {--dry : Don’t write to DB (preview)}';

    protected $description = 'Import BioTime iclock_transaction rows into attendance_raw (schema-aware)';

    public function handle(): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : now()->subDays((int)$this->option('days'))->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : now()->endOfDay();

        $summary = (bool)$this->option('summary');
        $dry     = (bool)$this->option('dry');

        $this->info("BioTime import window: {$from->toDateTimeString()} → {$to->toDateTimeString()}");

        // Which columns exist in attendance_raw? (based on your list)
        $has = fn(string $c) => Schema::hasColumn('attendance_raw', $c);

        // Build user maps (for linking)
        $byZkId     = DB::table('users')->whereNotNull('zkteco_user_id')->pluck('id','zkteco_user_id')->toArray();
        $bySchoolId = DB::table('users')->whereNotNull('school_id')->pluck('id','school_id')->toArray();

        $imported = 0;

        DB::connection('biotime')
            ->table('iclock_transaction')
            ->select([
                'id',
                'emp_id',       // numeric in many builds; may be null
                'emp_code',     // string code; we map to users.school_id
                'punch_time',
                'punch_state',
                'verify_type',
                'terminal_sn',
            ])
            ->whereBetween('punch_time', [$from, $to])
            ->orderBy('punch_time')
            ->chunkById(1000, function ($rows) use ($summary, $dry, $has, $byZkId, $bySchoolId, &$imported) {

                foreach ($rows as $r) {
                    if (empty($r->punch_time)) continue;
                    $ts = Carbon::parse($r->punch_time);

                    // Stable identifier for de-dup: prefer emp_id; else hash of emp_code
                    $deviceUserId = !is_null($r->emp_id) ? (string)$r->emp_id : (string)crc32((string)$r->emp_code);

                    // Resolve user_id
                    $userId = null;
                    if (!is_null($r->emp_id) && isset($byZkId[(string)$r->emp_id])) {
                        $userId = (int)$byZkId[(string)$r->emp_id];
                    } elseif (!empty($r->emp_code) && isset($bySchoolId[$r->emp_code])) {
                        $userId = (int)$bySchoolId[$r->emp_code];
                    }

                    if ($summary) {
                        $this->line(sprintf(
                            'emp_code=%-10s emp_id=%-6s %s %s',
                            (string)$r->emp_code,
                            (string)$r->emp_id,
                            $ts->toDateTimeString(),
                            $userId ? "→ user_id={$userId}" : '(unlinked)'
                        ));
                    }

                    if ($dry) { $imported++; continue; }

                    // Build payload ONLY with columns your table has
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
                    if ($has('payload'))    $row['payload']    = json_encode([
                        'emp_code'    => $r->emp_code,
                        'emp_id'      => $r->emp_id,
                        'punch_state' => $r->punch_state,
                        'verify_type' => $r->verify_type,
                        'terminal_sn' => $r->terminal_sn,
                    ], JSON_UNESCAPED_UNICODE);

                    DB::table('attendance_raw')->updateOrInsert(
                        ['device_user_id' => $deviceUserId, 'punched_at' => $ts],
                        $row
                    );

                    $imported++;
                }
            });

        $this->info(($dry ? '[DRY] ' : '')."Imported {$imported} punch(es).");
        return self::SUCCESS;
    }
}
