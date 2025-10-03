<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class AttendanceConsolidate extends Command
{
    protected $signature = 'attendance:consolidate
        {--days=30 : If --since/--until not set, use now()-days .. now()}
        {--since= : Start datetime (Y-m-d or Y-m-d H:i:s)}
        {--until= : End datetime (Y-m-d or Y-m-d H:i:s)}
        {--strict : QA mode: require 4 punches; skip partial days}';

    protected $description = 'Consolidate attendance_raw punches into attendance_days for reporting';

    /** cache shift window rows by id */
    private array $shiftCache = [];

    public function handle(): int
    {
        $tz = config('app.timezone', 'UTC');

        // ---- time window (prod default: include today) ----
        $since = $this->option('since')
            ? CarbonImmutable::parse($this->option('since'), $tz)
            : CarbonImmutable::now($tz)->subDays((int)($this->option('days') ?: 30))->startOfDay();

        $until = $this->option('until')
            ? CarbonImmutable::parse($this->option('until'), $tz)->endOfDay()
            : CarbonImmutable::now($tz)->endOfDay();

        $strict = (bool)$this->option('strict');

        $this->info("Consolidating from {$since->toDateTimeString()} to {$until->toDateTimeString()} (strict=".($strict?'yes':'no').") ...");

        // Pull punches joined to users (device_user_id -> users.zkteco_user_id)
        $rows = DB::table('attendance_raw as ar')
            ->join('users as u', 'u.zkteco_user_id', '=', 'ar.device_user_id')
            ->whereBetween('ar.punched_at', [$since->toDateTimeString(), $until->toDateTimeString()])
            ->orderBy('u.id')
            ->orderBy('ar.punched_at')
            ->get([
                'ar.id as raw_id',
                'ar.device_user_id',
                'ar.punched_at',
                'u.id as user_id',
                'u.shift_window_id',
            ]);

        if ($rows->isEmpty()) {
            $this->warn('No punches to process.');
            return self::SUCCESS;
        }

        // Group by (user_id, work_date) in app TZ
        $grouped = [];
        foreach ($rows as $r) {
            $userId   = (int)$r->user_id;
            $punch    = Carbon::parse($r->punched_at, $tz);
            $workDate = $punch->toDateString();

            $grouped[$userId][$workDate]['shift_window_id'] = $r->shift_window_id;
            $grouped[$userId][$workDate]['punches'][]       = $punch;
        }

        $now = now($tz);
        $upserts = [];
        $saved = 0; $skipped = 0;

        foreach ($grouped as $userId => $byDate) {
            foreach ($byDate as $workDate => $bucket) {
                $punches = collect($bucket['punches'] ?? [])->sort()->values();
                if ($punches->isEmpty()) continue;

                // ---- Shift window (schema tolerant) ----
                $windowId = $bucket['shift_window_id'] ?? null;
                [$swAmIn,$swAmOut,$swPmIn,$swPmOut,$grace] = $this->resolveShiftWindow($windowId);

                $amInSched  = Carbon::parse("$workDate {$swAmIn}",  $tz);
                $amOutSched = Carbon::parse("$workDate {$swAmOut}", $tz);
                $pmInSched  = Carbon::parse("$workDate {$swPmIn}",  $tz);
                $pmOutSched = Carbon::parse("$workDate {$swPmOut}", $tz);

                // Split around noon (you can make this configurable)
                $noon = Carbon::parse("$workDate 12:00:00", $tz);
                $amPunches = $punches->filter(fn (Carbon $p) => $p->lte($noon))->values();
                $pmPunches = $punches->filter(fn (Carbon $p) => $p->gt($noon))->values();

                // Assign sequentially with sensible fallbacks
                $amIn  = $amPunches->get(0);
                $amOut = $amPunches->count() > 1 ? $amPunches->last() : null;
                $pmIn  = $pmPunches->get(0);
                $pmOut = $pmPunches->count() > 1 ? $pmPunches->last() : null;

                // If only 2 total punches across the day, treat as amIn + pmOut
                if ($punches->count() === 2 && !$pmOut) {
                    $amIn  = $punches->first();
                    $pmOut = $punches->last();
                    $amOut = null; $pmIn = null;
                }

                // Guard inversions
                if ($amOut && $amIn && $amOut->lt($amIn)) $amOut = null;
                if ($pmOut && $pmIn && $pmOut->lt($pmIn)) $pmOut = null;

                // STRICT mode (QA only): require 4 punches, skip otherwise
                if ($strict) {
                    $have = collect([$amIn,$amOut,$pmIn,$pmOut])->filter()->count();
                    if ($have < 4) {
                        DB::table('attendance_days')->where('user_id',$userId)->where('work_date',$workDate)->delete();
                        $skipped++;
                        continue;
                    }
                }

                // Metrics (compute with what we have)
                [$late,$undertime,$hours,$status] = $this->computeMetrics(
                    $amIn,$amOut,$pmIn,$pmOut,
                    $amInSched,$amOutSched,$pmInSched,$pmOutSched,
                    $grace
                );

                $upserts[] = [
                    'user_id'           => $userId,
                    'work_date'         => $workDate,
                    'am_in'             => $amIn?->toDateTimeString(),
                    'am_out'            => $amOut?->toDateTimeString(),
                    'pm_in'             => $pmIn?->toDateTimeString(),
                    'pm_out'            => $pmOut?->toDateTimeString(),
                    'late_minutes'      => $late,
                    'undertime_minutes' => $undertime,
                    'total_hours'       => $hours,
                    'status'            => $status,
                    'updated_at'        => $now,
                    'created_at'        => $now,
                ];
                $saved++;
            }
        }

        if ($upserts) {
            DB::table('attendance_days')->upsert(
                $upserts,
                ['user_id','work_date'],
                ['am_in','am_out','pm_in','pm_out','late_minutes','undertime_minutes','total_hours','status','updated_at']
            );
        }

        $this->info("Consolidation complete: saved {$saved}, skipped {$skipped} (strict mode only).");
        return self::SUCCESS;
    }

    private function resolveShiftWindow($windowId): array
    {
        $defaults = ['08:00:00','12:00:00','13:00:00','17:00:00',0];
        if (!$windowId) return $defaults;
        if (array_key_exists($windowId, $this->shiftCache)) return $this->shiftCache[$windowId];

        $row = DB::table('shift_windows')->where('id',$windowId)->first();
        if (!$row) return $this->shiftCache[$windowId] = $defaults;

        $amIn  = $this->getTimeField($row, ['am_in','am_in_start','start_am','morning_in']);
        $amOut = $this->getTimeField($row, ['am_out','am_out_end','end_am','morning_out']);
        $pmIn  = $this->getTimeField($row, ['pm_in','pm_in_start','start_pm','afternoon_in']);
        $pmOut = $this->getTimeField($row, ['pm_out','pm_out_end','end_pm','afternoon_out']);
        $grace = $this->getIntField($row,  ['grace_minutes','grace','late_grace']);

        return $this->shiftCache[$windowId] = [
            $amIn  ?? $defaults[0],
            $amOut ?? $defaults[1],
            $pmIn  ?? $defaults[2],
            $pmOut ?? $defaults[3],
            (int)($grace ?? $defaults[4]),
        ];
    }

    private function getTimeField(object $row, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn('shift_windows',$col) && !empty($row->{$col})) {
                $val = trim((string)$row->{$col});
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $val)) return strlen($val)===5 ? "$val:00" : $val;
            }
        }
        return null;
    }

    private function getIntField(object $row, array $candidates): ?int
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn('shift_windows',$col) && $row->{$col} !== null) return (int)$row->{$col};
        }
        return null;
    }

    private function computeMetrics(
        ?Carbon $amIn, ?Carbon $amOut, ?Carbon $pmIn, ?Carbon $pmOut,
        Carbon $amInSched, Carbon $amOutSched, Carbon $pmInSched, Carbon $pmOutSched,
        int $graceMinutes
    ): array {
        // Late (only if am_in exists)
        $late = 0;
        if ($amIn) {
            $lateRef = $amInSched->copy()->addMinutes($graceMinutes);
            if ($amIn->gt($lateRef)) $late = $amIn->diffInMinutes($lateRef);
        }

        // Undertime (only if pm_out exists)
        $undertime = 0;
        if ($pmOut && $pmOut->lt($pmOutSched)) {
            $undertime = $pmOutSched->diffInMinutes($pmOut);
        }

        // Total hours (with fallbacks)
        $total = 0;
        if ($amIn && $amOut && $amOut->gt($amIn)) $total += $amIn->diffInMinutes($amOut);
        if ($pmIn && $pmOut && $pmOut->gt($pmIn)) $total += $pmIn->diffInMinutes($pmOut);

        // If only 2 punches (am_in & pm_out), subtract scheduled lunch
        if ($amIn && !$amOut && !$pmIn && $pmOut && $pmOut->gt($amIn)) {
            $lunch = max(0, $amOutSched->diffInMinutes($pmInSched));
            $total = max($total, $amIn->diffInMinutes($pmOut) - $lunch);
        }

        $hours = round($total / 60, 2);

        // Status (simple)
        $status = 'Present';
        if (!$amIn && !$amOut && !$pmIn && !$pmOut) {
            $status = 'Absent';
        } elseif ($late > 0 && $undertime > 0) {
            $status = 'Late/Undertime';
        } elseif ($late > 0) {
            $status = 'Late';
        } elseif ($undertime > 0) {
            $status = 'Undertime';
        }

        return [$late, $undertime, $hours, $status];
    }
}
