<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Attendance Report Summary</title>
  <style>
    /* Fit 1 employee-month on one page (letter portrait) */
    @page { size: letter portrait; margin: 10mm 8mm; }
    body { font-family: DejaVu Sans, sans-serif; color:#111; font-size:8.3px; line-height:1.12; }

    .employee { page-break-inside: avoid; }
    .employee + .employee { page-break-before: always; }

    .header { text-align:center; margin-bottom:2px; }
    .org { font-weight:700; font-size:9px; letter-spacing:.1px; }
    .doc-title { font-size:10.5px; font-weight:700; margin-top:1px; }
    .line { border-top:1px solid #000; margin:3px 0 4px; }

    .meta-table { width:100%; border-collapse:collapse; }
    .meta-table td { padding:0; vertical-align:top; }

    table { width:100%; border-collapse:collapse; table-layout:fixed; }
    th, td { border:0.5pt solid #444; padding:1px 2px; }
    th { font-weight:700; text-align:center; font-size:8.1px; }
    td { vertical-align:top; font-size:8.1px; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }

    /* Column widths */
    .w-date { width:12%; }
    .w-time { width:11%; }  /* AM In / AM Out / PM In / PM Out (x4 = 44%) */
    .w-min  { width:8%;  }  /* Late / Undertime (x2 = 16%) */
    .w-hrs  { width:7%;  }
    .w-stat { width:7%;  }
    .w-remarks { width:8%; }

    /* Multiline headers that don’t overlap */
    th .th-wrap { display:block; white-space:normal; word-break:break-word; line-height:1.05; overflow:hidden; font-size:8.0px; }

    .time   { white-space:nowrap; font-size:8.1px; }
    .totals { margin-top:3px; font-weight:700; font-size:8.2px; }

    /* Holiday merged cell spans all non-date columns (9 cols) */
    .holiday-merge {
      background:#eef6ff;
      font-weight:700;
      text-align:center;
      white-space:nowrap;
    }

    /* Remarks cell height for handwriting */
    td.remarks-cell { height:13px; }

    .sig-table { width:100%; border-collapse:collapse; margin-top:6px; }
    .sig-table td { border:0; vertical-align:bottom; }
    .sig-cell { width:50%; }
    .sig-pad { height:12px; }
    .sig-line { border-top:1px solid #000; height:1px; }
    .sig-label { font-size:8px; text-align:center; margin-top:2px; }

    .truncate-note { margin-top:4px; font-size:7.8px; color:#666; }
  </style>
</head>
<body>
@php
  use Carbon\Carbon;
  use Carbon\CarbonPeriod;
  use Illuminate\Support\Facades\DB;

  // ==== Inputs / range ====
  $fromStr = $from ?? ($filters['from'] ?? null);
  $toStr   = $to   ?? ($filters['to']   ?? null);

  $firstDate = optional((is_array($rows)?collect($rows):$rows)->first())->work_date;
  $baseDay   = $firstDate ? Carbon::parse($firstDate) : Carbon::now();
  if (!$fromStr) $fromStr = $baseDay->copy()->startOfMonth()->toDateString();
  if (!$toStr)   $toStr   = $baseDay->copy()->endOfMonth()->toDateString();

  $fromC = Carbon::parse($fromStr)->startOfDay();
  $toC   = Carbon::parse($toStr)->startOfDay();
  $period = new CarbonPeriod($fromC, $toC);

  // ==== Holidays (active calendars only) ====
  $holidays = DB::table('holiday_calendars as hc')
      ->join('holiday_dates as hd','hd.holiday_calendar_id','=','hc.id')
      ->where('hc.status','active')
      ->whereBetween('hd.date', [$fromC->toDateString(), $toC->toDateString()])
      ->get(['hd.date','hd.name','hd.is_non_working'])
      ->keyBy(fn($h) => Carbon::parse($h->date)->toDateString());

  // ==== Group by employee ====
  $collection = is_array($rows) ? collect($rows) : $rows;
  $grouped = $collection->groupBy('user_id');

  // ==== Load shift grace + daily schedules ====
  $shiftIds = $collection->pluck('shift_window_id')->filter()->unique()->values();

  $graceByShift = [];
  if ($shiftIds->isNotEmpty()) {
    $graces = DB::table('shift_windows')->whereIn('id', $shiftIds)->pluck('grace_minutes','id');
    foreach ($graces as $sid => $gm) $graceByShift[(int)$sid] = (int)$gm;
  }

  $sched = [];
  if ($shiftIds->isNotEmpty()) {
    $rowsDays = DB::table('shift_window_days')
        ->whereIn('shift_window_id', $shiftIds)
        ->get(['shift_window_id','dow','is_working','am_in','am_out','pm_in','pm_out']);
    foreach ($rowsDays as $d) {
      $sid=(int)$d->shift_window_id; $dow0=((int)$d->dow)%7;
      $isWork = isset($d->is_working) ? (int)$d->is_working
              : ((is_null($d->am_in) && is_null($d->pm_in)) ? 0 : 1);
      $sched[$sid][$dow0]=['work'=>$isWork,'am_in'=>$d->am_in,'am_out'=>$d->am_out,'pm_in'=>$d->pm_in,'pm_out'=>$d->pm_out];
    }
  }

  // ==== Helpers ====
  $timeCell = function (?string $ts) { if (!$ts) return '—'; try { return Carbon::parse($ts)->format('g:i:s A'); } catch (\Throwable $e) { return '—'; } };
  $fmt2 = fn($n) => number_format((float)$n, 2, '.', '');

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
          $amIn=Carbon::parse($rec->am_in); $amOut=Carbon::parse($rec->am_out);
          $wIn=Carbon::parse("$date {$daySched['am_in']}"); $wOut=Carbon::parse("$date {$daySched['am_out']}");
          $snap=$wIn->copy()->addMinutes($graceMin);
          if ($amIn->betweenIncluded($wIn,$snap)) $amIn=$wIn->copy();
          if ($amIn->lt($wIn))  $amIn=$wIn->copy();
          if ($amOut->gt($wOut))$amOut=$wOut->copy();
          $secs += $overlapSeconds($amIn,$amOut,$wIn,$wOut);
      }

      if ($hasPM && $rec->pm_in && $rec->pm_out) {
          $pmIn=Carbon::parse($rec->pm_in); $pmOut=Carbon::parse($rec->pm_out);
          $wIn=Carbon::parse("$date {$daySched['pm_in']}"); $wOut=Carbon::parse("$date {$daySched['pm_out']}");
          $snap=$wIn->copy()->addMinutes($graceMin);
          if ($pmIn->betweenIncluded($wIn,$snap)) $pmIn=$wIn->copy();
          if ($pmIn->lt($wIn))  $pmIn=$wIn->copy();
          if ($pmOut->gt($wOut))$pmOut=$wOut->copy();
          $secs += $overlapSeconds($pmIn,$pmOut,$wIn,$wOut);
      }

      if (!$hasAM && !$hasPM) {
          if ($rec->am_in && $rec->am_out) {
              $a=Carbon::parse($rec->am_in); $b=Carbon::parse($rec->am_out);
              if ($b->gt($a)) $secs += $b->diffInSeconds($a);
          }
          if ($rec->pm_in && $rec->pm_out) {
              $a=Carbon::parse($rec->pm_in); $b=Carbon::parse($rec->pm_out);
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
          $sched=Carbon::parse("$date {$daySched['am_in']}")->addMinutes($graceMin);
          $act=Carbon::parse($rec->am_in);
          if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
      }
      if (!empty($rec->pm_in) && !empty($daySched['pm_in'])) {
          $sched=Carbon::parse("$date {$daySched['pm_in']}")->addMinutes($graceMin);
          $act=Carbon::parse($rec->pm_in);
          if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
      }
      return (int)$late;
  };

  $calcUnder = function($rec,$daySched){
      if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
      $date = Carbon::parse($rec->work_date)->toDateString();
      $ut = 0;
      if (!empty($rec->am_out) && !empty($daySched['am_out'])) {
          $sched=Carbon::parse("$date {$daySched['am_out']}"); $act=Carbon::parse($rec->am_out);
          if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
      }
      if (!empty($rec->pm_out) && !empty($daySched['pm_out'])) {
          $sched=Carbon::parse("$date {$daySched['pm_out']}"); $act=Carbon::parse($rec->pm_out);
          if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
      }
      return (int)$ut;
  };
@endphp

@foreach($grouped as $userId => $empRows)
  @php
    $emp       = $empRows->first();
    $byDate    = $empRows->keyBy(fn($r)=>Carbon::parse($r->work_date)->toDateString());
    $rangeText = $fromC->toDateString().' to '.$toC->toDateString();
    $sumLate = 0.0; $sumUnder = 0.0; $sumHours = 0.0;
  @endphp

  <div class="employee">
    <div class="header">
      <div class="org">Attendance Management System</div>
      <div class="doc-title">Attendance Report Summary</div>
    </div>
    <div class="line"></div>

    <table class="meta-table">
      <tr>
        <td><strong>Employee:</strong> {{ $emp->name ?? '—' }} (#{{ $userId }})</td>
        <td style="text-align:right"><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</td>
      </tr>
      <tr>
        <td><strong>Department:</strong> {{ $emp->department ?? '—' }}</td>
        <td style="text-align:right"><strong>Range:</strong> {{ $rangeText }}</td>
      </tr>
    </table>

    <table style="margin-top:3px;">
      <thead>
        <tr>
          <th class="w-date">Date</th>
          <th class="w-time">AM In</th>
          <th class="w-time">AM Out</th>
          <th class="w-time">PM In</th>
          <th class="w-time">PM Out</th>
          <th class="w-min"><span class="th-wrap">Late<br>(min)</span></th>
          <th class="w-min"><span class="th-wrap">Undertime<br>(min)</span></th>
          <th class="w-hrs">Hours</th>
          <th class="w-stat">Status</th>
          <th class="w-remarks">Remarks</th>
        </tr>
      </thead>
      <tbody>
        @foreach($period as $day)
          @php
            $dkey = $day->toDateString();
            $rec  = $byDate->get($dkey);

            $sid  = (int)($emp->shift_window_id ?? ($rec->shift_window_id ?? 0));
            $dow0 = $day->dayOfWeek;

            $dSched = $sched[$sid][$dow0] ?? [
              'work'  => ($dow0 === Carbon::SUNDAY) ? 0 : 1,
              'am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null,
            ];
            $graceMin = $graceByShift[$sid] ?? 0;

            $hol   = $holidays->get($dkey);
            $isHolidayNonWorking = $hol && (int)$hol->is_non_working === 1;

            $hasScan = $rec && ($rec->am_in || $rec->am_out || $rec->pm_in || $rec->pm_out);
            $isWorkingDay = (int)($dSched['work'] ?? 1) === 1;

            // Determine status text for non-holiday rows
            if ($rec && !empty($rec->status)) {
              $status = $rec->status;
            } elseif ($isHolidayNonWorking && !$hasScan) {
              $status = 'Holiday: '.($hol->name ?? '—');
            } elseif (!$isWorkingDay && !$hasScan) {
              $status = 'No Duty';
            } else {
              $status = $rec ? 'Present' : 'Absent';
            }

            $dispLate  = $rec ? $calcLate($rec,  $dSched, $graceMin) : 0;
            $dispUnder = $rec ? $calcUnder($rec, $dSched)           : 0;

            $hours = 0.00;
            if ($rec) {
              $hours = (float)($rec->total_hours ?? 0);
              if ($hours <= 0) $hours = $computeHours($rec, $dSched, $graceMin);
              $hours = round($hours, 2);
            }

            $sumLate  += $dispLate;
            $sumUnder += $dispUnder;
            $sumHours += $hours;

            $showMergedHoliday = !$hasScan && $isHolidayNonWorking;
          @endphp

          @if($showMergedHoliday)
            <tr>
              <!-- Date stays in first column -->
              <td style="text-align:center">{{ $dkey }}</td>
              <!-- Merge the rest 9 columns -->
              <td class="holiday-merge" colspan="9">Holiday: {{ $hol->name ?? '—' }}</td>
            </tr>
          @else
            <tr>
              <td style="text-align:center">{{ $dkey }}</td>
              <td class="time" style="text-align:center">{{ $timeCell($rec->am_in ?? null) }}</td>
              <td class="time" style="text-align:center">{{ $timeCell($rec->am_out ?? null) }}</td>
              <td class="time" style="text-align:center">{{ $timeCell($rec->pm_in ?? null) }}</td>
              <td class="time" style="text-align:center">{{ $timeCell($rec->pm_out ?? null) }}</td>
              <td style="text-align:right">{{ $fmt2($dispLate) }}</td>
              <td style="text-align:right">{{ $fmt2($dispUnder) }}</td>
              <td style="text-align:right">{{ $fmt2($hours) }}</td>
              <td style="text-align:center">{{ $status }}</td>
              <td class="remarks-cell">&nbsp;</td>
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>

    <div class="totals">
      Late Total: {{ $fmt2($sumLate) }} min &nbsp; | &nbsp;
      Undertime: {{ $fmt2($sumUnder) }} min &nbsp; | &nbsp;
      Hours: {{ $fmt2($sumHours) }}
    </div>

    <table class="sig-table">
      <tr>
        <td class="sig-cell">
          <div class="sig-pad"></div>
          <div class="sig-line"></div>
          <div class="sig-label"><strong>{{ $emp->name ?? '—' }}</strong> — Employee</div>
        </td>
        <td class="sig-cell">
          <div class="sig-pad"></div>
          <div class="sig-line"></div>
          <div class="sig-label"><strong>Unit Head</strong></div>
        </td>
      </tr>
    </table>
  </div>
@endforeach

@if(!empty($truncated ?? false))
  <div class="truncate-note">
    Note: output truncated to {{ number_format($maxRows ?? 0) }} of {{ number_format($totalRows ?? 0) }} rows to keep the file printable.
  </div>
@endif

<script type="text/php">
if (isset($pdf)) {
  $font = $fontMetrics->get_font("DejaVu Sans", "normal");
  $pdf->page_text(520, 770, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 8.5, [0,0,0]);
}
</script>
</body>
</html>
