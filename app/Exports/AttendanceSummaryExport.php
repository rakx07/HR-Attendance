<?php

// app/Exports/AttendanceSummaryExport.php
namespace App\Exports;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AttendanceSummaryExport implements FromArray, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return [
            'Employee','Department','Date',
            'AM In','AM Out','PM In','PM Out',
            'Late (min)','Undertime (min)','Hours','Status',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT, // AM In
            'E' => NumberFormat::FORMAT_TEXT, // AM Out
            'F' => NumberFormat::FORMAT_TEXT, // PM In
            'G' => NumberFormat::FORMAT_TEXT, // PM Out
            'H' => '0',                       // Late
            'I' => '0',                       // Undertime
            'J' => '0.00',                    // Hours
        ];
    }

    public function array(): array
    {
        // === Same base query as your PDF ===
        $from  = $this->filters['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to    = $this->filters['to']   ?? Carbon::now()->endOfMonth()->toDateString();
        $empId = $this->filters['employee_id'] ?? null;
        $dept  = $this->filters['dept'] ?? null;

        $q = DB::table('attendance_days as d')
            ->join('users as u','u.id','=','d.user_id')
            ->when($from,  fn($qq)=>$qq->where('d.work_date','>=',$from))
            ->when($to,    fn($qq)=>$qq->where('d.work_date','<=',$to))
            ->when($empId, fn($qq)=>$qq->where('d.user_id',$empId))
            ->when($dept,  fn($qq)=>$qq->where('u.department','like',"%{$dept}%"));

        $rows = (clone $q)
            ->select([
                'd.user_id','d.work_date','d.am_in','d.am_out','d.pm_in','d.pm_out',
                'd.late_minutes','d.undertime_minutes','d.status',
                'u.shift_window_id',
                DB::raw("TRIM(CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))) AS name"),
                'u.department',
            ])
            ->orderBy('d.user_id')
            ->orderBy('d.work_date')
            ->get();

        // === Build period (same defaulting as PDF) ===
        $firstDate = optional($rows->first())->work_date;
        $baseDay   = $firstDate ? Carbon::parse($firstDate) : Carbon::now();
        $fromStr   = $from ?: $baseDay->copy()->startOfMonth()->toDateString();
        $toStr     = $to   ?: $baseDay->copy()->endOfMonth()->toDateString();

        $fromC  = Carbon::parse($fromStr)->startOfDay();
        $toC    = Carbon::parse($toStr)->startOfDay();
        $period = new CarbonPeriod($fromC, $toC);

        // === Holidays (active) ===
        $holidays = DB::table('holiday_calendars as hc')
            ->join('holiday_dates as hd','hd.holiday_calendar_id','=','hc.id')
            ->where('hc.status','active')
            ->whereBetween('hd.date', [$fromC->toDateString(), $toC->toDateString()])
            ->get(['hd.date','hd.name','hd.is_non_working'])
            ->keyBy(fn($h) => Carbon::parse($h->date)->toDateString());

        // === Shift grace + schedule ===
        $shiftIds = $rows->pluck('shift_window_id')->filter()->unique()->values();

        $graceByShift = [];
        if ($shiftIds->isNotEmpty()) {
            $graces = DB::table('shift_windows')->whereIn('id',$shiftIds)->pluck('grace_minutes','id');
            foreach ($graces as $sid=>$gm) $graceByShift[(int)$sid] = (int)$gm;
        }

        $sched = [];
        if ($shiftIds->isNotEmpty()) {
            $days = DB::table('shift_window_days')
                ->whereIn('shift_window_id', $shiftIds)
                ->get(['shift_window_id','dow','is_working','am_in','am_out','pm_in','pm_out']);
            foreach ($days as $d) {
                $sid  = (int)$d->shift_window_id;
                $dow0 = ((int)$d->dow) % 7;
                $isWork = isset($d->is_working) ? (int)$d->is_working
                        : ((is_null($d->am_in) && is_null($d->pm_in)) ? 0 : 1);
                $sched[$sid][$dow0] = [
                    'work'=>$isWork,'am_in'=>$d->am_in,'am_out'=>$d->am_out,'pm_in'=>$d->pm_in,'pm_out'=>$d->pm_out,
                ];
            }
        }

        // === Helpers (same as PDF view) ===
        $timeCell = function (?string $ts) {
            if (!$ts) return '—';
            try { return Carbon::parse($ts)->format('g:i:s A'); }
            catch (\Throwable) { return '—'; }
        };
        $overlapSeconds = function (?Carbon $a1, ?Carbon $a2, ?Carbon $b1, ?Carbon $b2): int {
            if (!$a1 || !$a2 || !$b1 || !$b2) return 0;
            if ($a2->lte($a1) || $b2->lte($b1)) return 0;
            $s = max($a1->timestamp, $b1->timestamp);
            $e = min($a2->timestamp, $b2->timestamp);
            return $e > $s ? ($e - $s) : 0;
        };
        $computeHours = function($rec, $daySched, int $graceMin = 0) use ($overlapSeconds) {
            if (!$rec) return 0.00;
            $date = Carbon::parse($rec->work_date)->toDateString();
            $secs = 0;
            $hasAM = !empty($daySched['am_in']) && !empty($daySched['am_out']);
            $hasPM = !empty($daySched['pm_in']) && !empty($daySched['pm_out']);

            if ($hasAM && $rec->am_in && $rec->am_out) {
                $amIn = Carbon::parse($rec->am_in); $amOut = Carbon::parse($rec->am_out);
                $wIn  = Carbon::parse("$date {$daySched['am_in']}"); $wOut = Carbon::parse("$date {$daySched['am_out']}");
                $snap = $wIn->copy()->addMinutes($graceMin);
                if ($amIn->betweenIncluded($wIn,$snap)) $amIn=$wIn->copy();
                if ($amIn->lt($wIn))  $amIn=$wIn->copy();
                if ($amOut->gt($wOut))$amOut=$wOut->copy();
                $secs += $overlapSeconds($amIn,$amOut,$wIn,$wOut);
            }

            if ($hasPM && $rec->pm_in && $rec->pm_out) {
                $pmIn = Carbon::parse($rec->pm_in); $pmOut = Carbon::parse($rec->pm_out);
                $wIn  = Carbon::parse("$date {$daySched['pm_in']}"); $wOut = Carbon::parse("$date {$daySched['pm_out']}");
                $snap = $wIn->copy()->addMinutes($graceMin);
                if ($pmIn->betweenIncluded($wIn,$snap)) $pmIn=$wIn->copy();
                if ($pmIn->lt($wIn))  $pmIn=$wIn->copy();
                if ($pmOut->gt($wOut))$pmOut=$wOut->copy();
                $secs += $overlapSeconds($pmIn,$pmOut,$wIn,$wOut);
            }

            if (!$hasAM && !$hasPM) {
                if ($rec->am_in && $rec->am_out) {
                    $a = Carbon::parse($rec->am_in); $b = Carbon::parse($rec->am_out);
                    if ($b->gt($a)) $secs += $b->diffInSeconds($a);
                }
                if ($rec->pm_in && $rec->pm_out) {
                    $a = Carbon::parse($rec->pm_in); $b = Carbon::parse($rec->pm_out);
                    if ($b->gt($a)) $secs += $b->diffInSeconds($a);
                }
            }

            return round(max(0,$secs)/3600, 2);
        };
        $calcLate = function($rec,$daySched,$graceMin){
            if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
            $date = Carbon::parse($rec->work_date)->toDateString();
            $late = 0;
            if (!empty($rec->am_in) && !empty($daySched['am_in'])) {
                $sched = Carbon::parse("$date {$daySched['am_in']}")->addMinutes($graceMin);
                $act   = Carbon::parse($rec->am_in);
                if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
            }
            if (!empty($rec->pm_in) && !empty($daySched['pm_in'])) {
                $sched = Carbon::parse("$date {$daySched['pm_in']}")->addMinutes($graceMin);
                $act   = Carbon::parse($rec->pm_in);
                if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
            }
            return (int)$late;
        };
        $calcUnder = function($rec,$daySched){
            if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
            $date = Carbon::parse($rec->work_date)->toDateString();
            $ut = 0;
            if (!empty($rec->am_out) && !empty($daySched['am_out'])) {
                $sched = Carbon::parse("$date {$daySched['am_out']}"); $act = Carbon::parse($rec->am_out);
                if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
            }
            if (!empty($rec->pm_out) && !empty($daySched['pm_out'])) {
                $sched = Carbon::parse("$date {$daySched['pm_out']}"); $act = Carbon::parse($rec->pm_out);
                if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
            }
            return (int)$ut;
        };

        // === Build rows: mirror PDF ===
        $grouped = $rows->groupBy('user_id');
        $out = [];

        foreach ($grouped as $userId => $empRows) {
            $emp    = $empRows->first();
            $byDate = $empRows->keyBy(fn($r)=>Carbon::parse($r->work_date)->toDateString());

            foreach ($period as $day) {
                $dkey = $day->toDateString();
                $rec  = $byDate->get($dkey);

                $sid  = (int)($emp->shift_window_id ?? ($rec->shift_window_id ?? 0));
                $dow0 = $day->dayOfWeek;

                $dSched = $sched[$sid][$dow0] ?? [
                    'work'=>($dow0===Carbon::SUNDAY?0:1),'am_in'=>null,'am_out'=>null,'pm_in'=>null,'pm_out'=>null
                ];
                $grace = $graceByShift[$sid] ?? 0;

                $hol   = $holidays->get($dkey);
                $isHol = $hol && (int)$hol->is_non_working === 1;

                $hasScan = $rec && ($rec->am_in || $rec->am_out || $rec->pm_in || $rec->pm_out);
                $isWorkingDay = (int)($dSched['work'] ?? 1) === 1;

                $status = $rec && !empty($rec->status)
                    ? $rec->status
                    : ($isHol && !$hasScan ? ('Holiday: '.($hol->name ?? '—'))
                    : (!$isWorkingDay && !$hasScan ? 'No Duty'
                    : ($rec ? 'Present' : 'Absent')));

                // PM-in duplication guard
                $pmInShow = $rec? $rec->pm_in : null;
                if ($rec && $rec->pm_in && $rec->pm_out && !$rec->am_out) {
                    if (Carbon::parse($rec->pm_in)->equalTo(Carbon::parse($rec->pm_out))) $pmInShow = null;
                }

                $late  = $rec ? $calcLate($rec,$dSched,$grace) : 0;
                $under = $rec ? $calcUnder($rec,$dSched)       : 0;

                $hours = 0.00;
                if ($rec) {
                    $hours = (float)($rec->total_hours ?? 0);
                    if ($hours <= 0) $hours = $computeHours($rec,$dSched,$grace);
                    $hours = round($hours, 2);
                }

                $out[] = [
                    $emp->name ?? '—',
                    $emp->department ?? '—',
                    $dkey,
                    $rec ? $timeCell($rec->am_in)  : '—',
                    $rec ? $timeCell($rec->am_out) : '—',
                    $rec ? $timeCell($pmInShow)    : '—',
                    $rec ? $timeCell($rec->pm_out) : '—',
                    $late,
                    $under,
                    $hours,
                    $status,
                ];
            }
        }

        return $out;
    }
}

