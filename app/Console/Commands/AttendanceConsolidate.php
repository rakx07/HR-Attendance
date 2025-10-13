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
        {--strict : QA mode: require 4 punches; skip partial days}
        {--mode=windows : Classification: windows|sequence}';

    protected $description = 'Consolidate attendance_raw punches into attendance_days for reporting';

    /** Cache per shift_window_id */
    private array $shiftCache = [];

    public function handle(): int
    {
        $tz = config('app.timezone', 'UTC');

        $since = $this->option('since')
            ? CarbonImmutable::parse($this->option('since'), $tz)
            : CarbonImmutable::now($tz)->subDays((int)($this->option('days') ?: 30))->startOfDay();

        $until = $this->option('until')
            ? CarbonImmutable::parse($this->option('until'), $tz)->endOfDay()
            : CarbonImmutable::now($tz)->endOfDay();

        $strict = (bool)$this->option('strict');
        $mode   = strtolower((string)$this->option('mode') ?: 'windows');
        if (!in_array($mode, ['windows', 'sequence'], true)) $mode = 'windows';

        $this->info("Consolidating from {$since} to {$until} (strict=".($strict?'yes':'no').", mode={$mode}) ...");

        $rows = DB::table('attendance_raw as ar')
            ->join('users as u', 'u.id', '=', 'ar.user_id')
            ->whereBetween('ar.punched_at', [$since->toDateTimeString(), $until->toDateTimeString()])
            ->orderBy('u.id')
            ->orderBy('ar.punched_at')
            ->get([
                'ar.id as raw_id',
                'ar.user_id as user_id',
                'ar.device_user_id',
                'ar.punched_at',
                'u.shift_window_id',
            ]);

        if ($rows->isEmpty()) {
            $this->warn('No punches to process.');
            return self::SUCCESS;
        }

        // Group by (user_id, work_date)
        $grouped = [];
        foreach ($rows as $r) {
            $userId   = (int) $r->user_id;
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

                $windowId = $bucket['shift_window_id'] ?? null;
                [
                    $amInSched, $amOutSched, $pmInSched, $pmOutSched, $grace,
                    $amInWinS, $amInWinE, $amOutWinS, $amOutWinE, $pmInWinS, $pmInWinE, $pmOutWinS, $pmOutWinE,
                ] = $this->resolveShiftWindowWithWindows($windowId, $workDate, $tz);

                // Sanity for windows mode: clamp AM windows to before pmInWinS
                $amCutoff = $pmInWinS->copy()->subSecond();
                if ($amInWinE->gte($amCutoff))  $amInWinE  = $amCutoff->copy();
                if ($amOutWinE->gte($amCutoff)) $amOutWinE = $amCutoff->copy();

                // ---- CLASSIFICATION ----
                $amIn = $amOut = $pmIn = $pmOut = null;

                if ($mode === 'sequence') {
                    // Hard windows per your rules
                    $amInEnd    = Carbon::parse("$workDate 11:29:59", $tz); // AM period end
                    $amOutWinS  = Carbon::parse("$workDate 11:30:00", $tz);
                    $amOutWinE  = Carbon::parse("$workDate 12:59:59", $tz);
                    $pmInPrefS  = Carbon::parse("$workDate 11:45:00", $tz); // preferred PM-in window
                    $pmInPrefE  = Carbon::parse("$workDate 12:59:59", $tz);
                    $onePM      = Carbon::parse("$workDate 13:00:00", $tz); // ≥ 1:00 PM is late PM-in
                    $pmOutMin   = Carbon::parse("$workDate 17:00:00", $tz); // earliest OK PM-out

                    // 1) AM IN: first scan ≤ 11:29:59 (extra scans before 11:30 are ignored)
                    foreach ($punches as $p) {
                        if ($p->lte($amInEnd)) { $amIn = $p; break; }
                        if ($p->gte($pmInPrefS)) break; // first punch already PM-ish → no AM
                    }

                    // 2) AM OUT: first scan in 11:30–12:59 strictly after AM-in (if any)
                    foreach ($punches as $p) {
                        if ($amIn && $p->lte($amIn)) continue;
                        if ($p->gte($amOutWinS) && $p->lte($amOutWinE)) { $amOut = $p; break; }
                    }

                    // 3) PM IN: prefer 11:45–12:59, else first scan ≥ 1:00 PM (late)
                    foreach ($punches as $p) {
                        if ($amOut && $p->lte($amOut)) continue;
                        if ($amIn  && !$amOut && $p->lte($amIn)) continue;

                        if ($p->gte($pmInPrefS) && $p->lte($pmInPrefE)) { $pmIn = $p; break; }
                        if ($p->gte($onePM)) { $pmIn = $p; break; } // late PM-in
                    }

                    // 4) PM OUT: always LAST scan of the day (if any).
                    //    If no pmIn and last scan is AM (~11:30), it's morning-only.
                    $last = null;
                    foreach ($punches as $p) { $last = $p; }
                    if ($last) {
                        if ($pmIn) {
                            $pmOut = $last;
                        } else {
                            if ($last->gte($pmOutMin)) {
                                // Rare case: no pmIn but a lone ≥5pm scan → treat as pmOut
                                $pmOut = $last;
                            }
                        }
                    }
                } else {
                    // WINDOWS MODE (unchanged)
                    $amIn  = $this->pickFirstInWindow($punches, $amInWinS,  $amInWinE,  null);
                    $amOut = $this->pickFirstInWindow($punches, $amOutWinS, $amOutWinE, $amIn);
                    $pmIn  = $this->pickFirstInWindow($punches, $pmInWinS,  $pmInWinE,  $amOut ?? $amIn)
                           ?: $this->pickFirstOnOrAfter($punches, $pmInWinS, $amOut ?? $amIn);
                    $pmOut = $this->pickLastOfDay($punches, $workDate, $tz, $pmIn ?? $amOut ?? $amIn);

                    if ($punches->count() === 2 && !$pmOut) {
                        $amIn  = $punches->first();
                        $pmOut = $punches->last();
                        $amOut = null; $pmIn = null;
                    }
                }

                // Defensive: prevent negative intervals
                if ($amOut && $amIn && $amOut->lt($amIn)) $amOut = null;
                if ($pmOut && $pmIn && $pmOut->lt($pmIn)) $pmOut = null;

                // STRICT: require all 4
                if ($strict) {
                    $have = collect([$amIn,$amOut,$pmIn,$pmOut])->filter()->count();
                    if ($have < 4) {
                        DB::table('attendance_days')->where('user_id',$userId)->where('work_date',$workDate)->delete();
                        $skipped++;
                        continue;
                    }
                }

                // Metrics
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

    /**
     * Build schedule + classification windows for the day.
     * Fallback when columns are missing:
     *   AM-IN : 07:30–11:29
     *   AM-OUT: 11:30–12:59
     *   PM-IN : 13:00–16:59  (set DB pm_in_start to 11:45 if you want earlier)
     *   PM-OUT: 17:00–23:59
     */
    private function resolveShiftWindowWithWindows($windowId, string $workDate, string $tz): array
    {
        [$swAmIn,$swAmOut,$swPmIn,$swPmOut,$grace] = $this->resolveShiftAnchors($windowId);
        $win = $this->resolveClassificationWindows($windowId);

        // Anchors for metrics
        $amInSched  = Carbon::parse("$workDate {$swAmIn}",  $tz);
        $amOutSched = Carbon::parse("$workDate {$swAmOut}", $tz);
        $pmInSched  = Carbon::parse("$workDate {$swPmIn}",  $tz);
        $pmOutSched = Carbon::parse("$workDate {$swPmOut}", $tz);

        // Windows
        $amInWinS   = Carbon::parse("$workDate {$win['am_in_start']}",   $tz);
        $amInWinE   = Carbon::parse("$workDate {$win['am_in_end']}",     $tz);
        $amOutWinS  = Carbon::parse("$workDate {$win['am_out_start']}",  $tz);
        $amOutWinE  = Carbon::parse("$workDate {$win['am_out_end']}",    $tz);
        $pmInWinS   = Carbon::parse("$workDate {$win['pm_in_start']}",   $tz);
        $pmInWinE   = Carbon::parse("$workDate {$win['pm_in_end']}",     $tz);
        $pmOutWinS  = Carbon::parse("$workDate {$win['pm_out_start']}",  $tz);
        $pmOutWinE  = Carbon::parse("$workDate {$win['pm_out_end']}",    $tz);

        $W = $this->sanitizeWindows(compact(
            'amInWinS','amInWinE','amOutWinS','amOutWinE','pmInWinS','pmInWinE','pmOutWinS','pmOutWinE'
        ));
        extract($W);

        return [
            $amInSched, $amOutSched, $pmInSched, $pmOutSched, $grace,
            $amInWinS, $amInWinE, $amOutWinS, $amOutWinE, $pmInWinS, $pmInWinE, $pmOutWinS, $pmOutWinE,
        ];
    }

    /** Resolve scheduled anchor times (schema tolerant). */
    private function resolveShiftAnchors($windowId): array
    {
        $defaults = ['08:00:00','12:00:00','13:00:00','17:00:00',0];
        if (!$windowId) return $defaults;
        if (array_key_exists($windowId, $this->shiftCache) && isset($this->shiftCache[$windowId]['_anchors'])) {
            return $this->shiftCache[$windowId]['_anchors'];
        }

        $row = DB::table('shift_windows')->where('id',$windowId)->first();
        if (!$row) return $this->shiftCache[$windowId]['_anchors'] = $defaults;

        $amIn  = $this->getTimeField($row, ['am_in','am_in_sched','start_am','morning_in']);
        $amOut = $this->getTimeField($row, ['am_out','am_out_sched','end_am','morning_out']);
        $pmIn  = $this->getTimeField($row, ['pm_in','pm_in_sched','start_pm','afternoon_in']);
        $pmOut = $this->getTimeField($row, ['pm_out','pm_out_sched','end_pm','afternoon_out']);
        $grace = $this->getIntField($row,  ['grace_minutes','grace','late_grace']);

        return $this->shiftCache[$windowId]['_anchors'] = [
            $amIn  ?? $defaults[0],
            $amOut ?? $defaults[1],
            $pmIn  ?? $defaults[2],
            $pmOut ?? $defaults[3],
            (int)($grace ?? $defaults[4]),
        ];
    }

    /** Resolve classification windows (schema tolerant). */
    private function resolveClassificationWindows($windowId): array
    {
        $fallback = [
            'am_in_start'   => '07:30:00', 'am_in_end'   => '11:29:59',
            'am_out_start'  => '11:30:00', 'am_out_end'  => '12:59:59',
            'pm_in_start'   => '13:00:00', 'pm_in_end'   => '16:59:59',
            'pm_out_start'  => '17:00:00', 'pm_out_end'  => '23:59:59',
        ];

        if (!$windowId) return $fallback;
        if (array_key_exists($windowId, $this->shiftCache) && isset($this->shiftCache[$windowId]['_windows'])) {
            return $this->shiftCache[$windowId]['_windows'];
        }

        $row = DB::table('shift_windows')->where('id',$windowId)->first();
        if (!$row) return $this->shiftCache[$windowId]['_windows'] = $fallback;

        $val = fn(string $col, ?string $def = null) =>
            (Schema::hasColumn('shift_windows', $col) && !empty($row->{$col}))
                ? $this->normalizeTime((string)$row->{$col})
                : $def;

        $win = [
            'am_in_start'   => $val('am_in_start',  $fallback['am_in_start']),
            'am_in_end'     => $val('am_in_end',    $fallback['am_in_end']),
            'am_out_start'  => $val('am_out_start', $fallback['am_out_start']),
            'am_out_end'    => $val('am_out_end',   $fallback['am_out_end']),
            'pm_in_start'   => $val('pm_in_start',  $fallback['pm_in_start']),
            'pm_in_end'     => $val('pm_in_end',    $fallback['pm_in_end']),
            'pm_out_start'  => $val('pm_out_start', $fallback['pm_out_start']),
            'pm_out_end'    => $val('pm_out_end',   $fallback['pm_out_end']),
        ];

        return $this->shiftCache[$windowId]['_windows'] = $win;
    }

    /** Normalize HH:MM / HH:MM:SS / h:mm AM/PM to HH:MM:SS. */
    private function normalizeTime(string $val): string
    {
        $val = trim($val);
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $val)) {
            return strlen($val) === 5 ? ($val . ':00') : $val;
        }
        if (preg_match('/^\d{1,2}:\d{2}\s?(AM|PM)$/i', $val)) {
            return Carbon::parse($val)->format('H:i:s');
        }
        return '00:00:00';
    }

    private function getTimeField(object $row, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn('shift_windows', $col) && !empty($row->{$col})) {
                return $this->normalizeTime((string)$row->{$col});
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

    /** Ensure windows don’t overlap/invert: AM-IN ≤ AM-OUT ≤ PM-IN ≤ PM-OUT. */
    private function sanitizeWindows(array $w): array
    {
        foreach (['amIn','amOut','pmIn','pmOut'] as $k) {
            $s = "{$k}WinS"; $e = "{$k}WinE";
            if ($w[$s]->gt($w[$e])) {
                $w[$e] = $w[$s]->copy(); // clamp inverted
            }
        }
        if ($w['amOutWinE']->gte($w['pmInWinS'])) {
            $w['amOutWinE'] = $w['pmInWinS']->copy()->subSecond();
        }
        if ($w['pmInWinE']->gte($w['pmOutWinS'])) {
            $w['pmInWinE'] = $w['pmOutWinS']->copy()->subSecond();
        }
        return $w;
    }

    /** First punch inside [start..end] strictly AFTER $after (if provided). */
    private function pickFirstInWindow(\Illuminate\Support\Collection $punches, Carbon $start, Carbon $end, ?Carbon $after = null): ?Carbon
    {
        foreach ($punches as $p) {
            if ($after && $p->lte($after)) continue;  // strictly after previous slot
            if ($p->gte($start) && $p->lte($end)) return $p;
        }
        return null;
    }

    /** First punch at/after $threshold, strictly after $after (if provided). */
    private function pickFirstOnOrAfter(\Illuminate\Support\Collection $punches, Carbon $threshold, ?Carbon $after = null): ?Carbon
    {
        foreach ($punches as $p) {
            if ($p->lt($threshold)) continue;
            if ($after && $p->lte($after)) continue;
            return $p;
        }
        return null;
    }

    /** LAST punch of the day strictly AFTER $afterIfAny. */
    private function pickLastOfDay(\Illuminate\Support\Collection $punches, string $workDate, string $tz, ?Carbon $afterIfAny = null): ?Carbon
    {
        $startDay = Carbon::parse("$workDate 00:00:00", $tz);
        $endDay   = Carbon::parse("$workDate 23:59:59", $tz);

        $last = null;
        foreach ($punches as $p) {
            if ($p->lt($startDay) || $p->gt($endDay)) continue;
            if ($afterIfAny && $p->lte($afterIfAny)) continue;
            $last = $p; // latest valid
        }
        return $last;
    }

    private function computeMetrics(
        ?Carbon $amIn, ?Carbon $amOut, ?Carbon $pmIn, ?Carbon $pmOut,
        Carbon $amInSched, Carbon $amOutSched, Carbon $pmInSched, Carbon $pmOutSched,
        int $graceMinutes
    ): array {
        // Lateness
        $late = 0;

        // AM grace (e.g., 07:35 if sched 07:30 + 5)
        if ($amIn) {
            $amLateRef = $amInSched->copy()->addMinutes($graceMinutes);
            if ($amIn->gt($amLateRef)) {
                $late += $amIn->diffInMinutes($amLateRef);
            }
        }

        // PM-in late: any pmIn > 13:00:00 (no grace)
        if ($pmIn && $pmIn->gt($pmInSched)) {
            $late += $pmIn->diffInMinutes($pmInSched);
        }

        // Undertime: PM-out < 17:00:00
        $undertime = 0;
        if ($pmOut && $pmOut->lt($pmOutSched)) {
            $undertime = $pmOutSched->diffInMinutes($pmOut);
        }

        // Total worked minutes
        $total = 0;
        if ($amIn && $amOut && $amOut->gt($amIn)) {
            $total += $amIn->diffInMinutes($amOut);
        }
        if ($pmIn && $pmOut && $pmOut->gt($pmIn)) {
            $total += $pmIn->diffInMinutes($pmOut);
        }

        // 2-punch fallback: AM-in + PM-out (no AM-out/PM-in) → subtract scheduled lunch
        if ($amIn && !$amOut && !$pmIn && $pmOut && $pmOut->gt($amIn)) {
            $lunch = max(0, $amOutSched->diffInMinutes($pmInSched)); // e.g., 90 mins
            $total = max($total, $amIn->diffInMinutes($pmOut) - $lunch);
        }

        $hours = round($total / 60, 2);

        // Status
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

// (Legacy macro kept for compatibility)
if (!method_exists(Carbon::class, 'betweenIncluded')) {
    Carbon::macro('betweenIncluded', function (Carbon $from, Carbon $to): bool {
        /** @var Carbon $self */
        $self = $this;
        return $self->gte($from) && $self->lte($to);
    });
}
