<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class AttendanceConsolidate extends Command
{
    protected $signature = 'attendance:consolidate {--days=30}';
    protected $description = 'Consolidate attendance_raw punches into attendance_days for reporting';

    /** cache shift window rows by id */
    private array $shiftCache = [];

    public function handle(): int
    {
        $days  = (int) $this->option('days') ?: 30;
        $since = CarbonImmutable::now()->subDays($days)->startOfDay();

        $this->info("Consolidating punches since {$since->toDateTimeString()} ...");

        // Pull punches and map device_user_id -> users (zkteco_user_id)
        $rows = DB::table('attendance_raw as ar')
            ->join('users as u', 'u.zkteco_user_id', '=', 'ar.device_user_id')
            ->where('ar.punched_at', '>=', $since)
            ->orderBy('u.id')
            ->orderBy('ar.punched_at')
            ->select([
                'ar.id as raw_id',
                'ar.device_user_id',
                'ar.punched_at',
                'u.id as user_id',
                'u.shift_window_id', // we’ll fetch the window lazily (no sw.* in SQL)
            ])
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No punches to process.');
            return self::SUCCESS;
        }

        // Group by (user_id, work_date)
        $grouped = [];
        foreach ($rows as $r) {
            $userId   = (int) $r->user_id;
            $punch    = Carbon::parse($r->punched_at);
            $workDate = $punch->toDateString();

            $grouped[$userId][$workDate]['shift_window_id'] = $r->shift_window_id;
            $grouped[$userId][$workDate]['punches'][]       = $punch;
        }

        $upserts = [];

        foreach ($grouped as $userId => $byDate) {
            foreach ($byDate as $workDate => $bucket) {
                $punches = collect($bucket['punches'] ?? [])->sort()->values();
                if ($punches->isEmpty()) {
                    continue;
                }

                // ---- Resolve shift window times (robust to schema) ----
                $windowId = $bucket['shift_window_id'] ?? null;
                [$swAmIn,$swAmOut,$swPmIn,$swPmOut,$grace] = $this->resolveShiftWindow($windowId);

                // Build Carbon schedule times anchored to work date
                $amInSched  = Carbon::parse("{$workDate} {$swAmIn}");
                $amOutSched = Carbon::parse("{$workDate} {$swAmOut}");
                $pmInSched  = Carbon::parse("{$workDate} {$swPmIn}");
                $pmOutSched = Carbon::parse("{$workDate} {$swPmOut}");

                // Split AM/PM around noon (adjust if needed)
                $noonSplit = Carbon::parse("{$workDate} 12:00:00");
                $amPunches = $punches->filter(fn (Carbon $p) => $p->lte($noonSplit));
                $pmPunches = $punches->filter(fn (Carbon $p) => $p->gt($noonSplit));

                $amIn  = $amPunches->min();
                $amOut = $amPunches->max();
                $pmIn  = $pmPunches->min();
                $pmOut = $pmPunches->max();

                // If only 2 punches, treat as amIn + pmOut
                if ($punches->count() === 2) {
                    $amIn  = $punches->first();
                    $pmOut = $punches->last();
                    $amOut = null;
                    $pmIn  = null;
                }

                // Guard against inversions
                if ($amOut && $amIn && $amOut->lt($amIn)) $amOut = null;
                if ($pmOut && $pmIn && $pmOut->lt($pmIn)) $pmOut = null;

                // Compute metrics
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
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ];
            }
        }

        if (!$upserts) {
            $this->warn('Nothing to upsert.');
            return self::SUCCESS;
        }

        // Upsert by (user_id, work_date)
        DB::table('attendance_days')->upsert(
            $upserts,
            ['user_id', 'work_date'],
            ['am_in','am_out','pm_in','pm_out','late_minutes','undertime_minutes','total_hours','status','updated_at']
        );

        $this->info('Consolidation complete: '.count($upserts).' day rows updated.');
        return self::SUCCESS;
    }

    /**
     * Resolve shift window times from DB in a schema-tolerant way.
     * Returns [am_in, am_out, pm_in, pm_out, grace_minutes].
     */
    private function resolveShiftWindow($windowId): array
    {
        // Defaults if there is no window or columns
        $defaults = ['08:00:00','12:00:00','13:00:00','17:00:00',0];

        if (!$windowId) return $defaults;

        // Memoize
        if (array_key_exists($windowId, $this->shiftCache)) {
            return $this->shiftCache[$windowId];
        }

        // Fetch the row (no specific columns)
        $row = DB::table('shift_windows')->where('id', $windowId)->first();
        if (!$row) {
            return $this->shiftCache[$windowId] = $defaults;
        }

        // Try several possible column names
        $amIn  = $this->getTimeField($row, ['am_in','start_am','morning_in']);
        $amOut = $this->getTimeField($row, ['am_out','end_am','morning_out']);
        $pmIn  = $this->getTimeField($row, ['pm_in','start_pm','afternoon_in']);
        $pmOut = $this->getTimeField($row, ['pm_out','end_pm','afternoon_out']);

        $grace = $this->getIntField($row, ['grace_minutes','grace','late_grace']);

        // Fill missing with defaults
        $amIn  = $amIn  ?? $defaults[0];
        $amOut = $amOut ?? $defaults[1];
        $pmIn  = $pmIn  ?? $defaults[2];
        $pmOut = $pmOut ?? $defaults[3];
        $grace = $grace ?? $defaults[4];

        return $this->shiftCache[$windowId] = [$amIn,$amOut,$pmIn,$pmOut,(int)$grace];
    }

    private function getTimeField(object $row, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn('shift_windows', $col) && !empty($row->{$col})) {
                // normalize to HH:MM:SS
                $val = trim((string)$row->{$col});
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $val)) {
                    return strlen($val) === 5 ? ($val.':00') : $val;
                }
            }
        }
        return null;
    }

    private function getIntField(object $row, array $candidates): ?int
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn('shift_windows', $col) && $row->{$col} !== null) {
                return (int) $row->{$col};
            }
        }
        return null;
    }

    /**
     * Compute late, undertime, total hours, simple status.
     */
    private function computeMetrics(
        ?Carbon $amIn, ?Carbon $amOut, ?Carbon $pmIn, ?Carbon $pmOut,
        Carbon $amInSched, Carbon $amOutSched, Carbon $pmInSched, Carbon $pmOutSched,
        int $graceMinutes
    ): array {
        // Late
        $late = 0;
        if ($amIn) {
            $lateRef = $amInSched->copy()->addMinutes($graceMinutes);
            if ($amIn->gt($lateRef)) {
                $late = $amIn->diffInMinutes($lateRef);
            }
        }

        // Undertime
        $undertime = 0;
        if ($pmOut) {
            if ($pmOut->lt($pmOutSched)) {
                $undertime = $pmOutSched->diffInMinutes($pmOut);
            }
        } elseif ($amOut && !$pmOut && $amOut->lt($pmOutSched)) {
            // half-day
            $undertime = $pmOutSched->diffInMinutes($amOut);
        }

        // Total hours
        $total = 0;
        if ($amIn && $amOut && $amOut->gt($amIn)) $total += $amIn->diffInMinutes($amOut);
        if ($pmIn && $pmOut && $pmOut->gt($pmIn)) $total += $pmIn->diffInMinutes($pmOut);

        if ($amIn && $pmOut && !$amOut && !$pmIn && $pmOut->gt($amIn)) {
            // 2-punch day: estimate minus lunch
            $lunch = max(0, $amOutSched->diffInMinutes($pmInSched));
            $total = max($total, $amIn->diffInMinutes($pmOut) - $lunch);
        }

        $hours  = round($total / 60, 2);

        // Status
        $status = 'Present';
        if (!$amIn && !$pmIn && !$amOut && !$pmOut) {
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
