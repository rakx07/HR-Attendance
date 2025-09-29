<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ShiftWindow;

class AttendanceConsolidate extends Command
{
    protected $signature = 'attendance:consolidate {--days=3}';
    protected $description = 'Consolidate raw attendance logs into daily records';

    public function handle()
    {
        $since = now()->startOfDay()->subDays((int)$this->option('days'));

        $rows = DB::table('attendance_raw')
            ->where('punched_at', '>=', $since)
            ->whereNotNull('user_id')
            ->orderBy('user_id')->orderBy('punched_at')
            ->get();

        $users = User::with('shiftWindow')->get()->keyBy('id');
        $byDay = [];
        foreach ($rows as $r) {
            $d = Carbon::parse($r->punched_at)->toDateString();
            $byDay[$r->user_id][$d][] = $r;
        }

        foreach ($byDay as $uid => $days) {
            $u  = $users[$uid] ?? null;
            $sw = $u?->shiftWindow ?? ShiftWindow::first();

            foreach ($days as $date => $punches) {
                $cool = (int) config('attendance.cooldown_seconds', 60);
                $w    = config('attendance.windows');

                $am_in  = $this->pickFirst($this->windowPunches($punches, $date, $w['am_in'][0],  $w['am_in'][1],  $cool));
                $am_out = $this->pickLast ($this->windowPunches($punches, $date, $w['am_out'][0], $w['am_out'][1], $cool));
                $pm_in  = $this->pickFirst($this->windowPunches($punches, $date, $w['pm_in'][0],  $w['pm_in'][1],  $cool));
                $pm_out = $this->pickLast ($this->windowPunches($punches, $date, $w['pm_out'][0], $w['pm_out'][1], $cool));

                [$late, $under, $hours, $status] = $this->compute($u, $sw, $date, $am_in, $am_out, $pm_in, $pm_out);

                DB::table('attendance_days')->updateOrInsert(
                    ['user_id' => $uid, 'work_date' => $date],
                    [
                        'am_in' => $am_in, 'am_out' => $am_out,
                        'pm_in' => $pm_in, 'pm_out' => $pm_out,
                        'late_minutes' => $late,
                        'undertime_minutes' => $under,
                        'total_hours' => $hours,
                        'status' => $status,
                        'updated_at' => now(), 'created_at' => now(),
                    ]
                );
            }
        }

        $this->info('Consolidation complete.');
        return self::SUCCESS;
    }

    private function windowPunches($punches, $date, $start, $end, $cool)
    {
        $s = Carbon::parse("$date $start");
        $e = Carbon::parse("$date $end");
        $kept = []; $last = null;

        foreach ($punches as $p) {
            $t = Carbon::parse($p->punched_at);
            if (!$t->between($s, $e, true)) continue;

            if (!$last || $t->diffInSeconds($last) > $cool) {
                $kept[] = $t; $last = $t;
            } else {
                DB::table('attendance_raw')->where('id', $p->id)->update(['is_duplicate' => 1]);
            }
        }
        return $kept;
    }

    private function pickFirst($arr) { return $arr ? $arr[0] : null; }
    private function pickLast($arr)  { return $arr ? $arr[count($arr)-1] : null; }

    private function compute($u, $sw, $date, $amIn, $amOut, $pmIn, $pmOut)
    {
        $grace = (int) ($sw->grace_minutes ?? config('attendance.windows.grace_minutes', 0));
        $start = Carbon::parse("$date " . ($u?->flexi_start ?? $sw->am_in_start))->addMinutes($grace);
        $endPlanned = Carbon::parse("$date " . ($sw->pm_out_end ?? '17:00'));

        $late  = ($amIn && $amIn->gt($start)) ? $amIn->diffInMinutes($start) : 0;
        $under = ($pmOut && $pmOut->lt($endPlanned)) ? $endPlanned->diffInMinutes($pmOut) : 0;

        $mins = 0;
        if ($amIn && $amOut) $mins += $amIn->diffInMinutes($amOut);
        if ($pmIn && $pmOut) $mins += $pmIn->diffInMinutes($pmOut);

        $hours = min(round($mins / 60, 2), (float) config('attendance.cap_hours_per_day', 8));
        $status = (!$amIn && !$pmOut) ? 'Absent' : ($amIn && !$pmOut ? 'Incomplete' : 'Present');

        return [$late, $under, $hours, $status];
    }
}
