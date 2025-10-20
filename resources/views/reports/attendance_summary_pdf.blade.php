{{-- resources/views/reports/attendance_summary_pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Attendance Report Summary</title>
  <style>
    @page { size: letter portrait; margin: 10mm 8mm; }
    body { font-family: DejaVu Sans, sans-serif; color:#111; font-size:9px; line-height:1.15; }

    .employee { page-break-inside: avoid; }
    .employee + .employee { page-break-before: always; }

    .header { text-align:center; margin-bottom:4px; }
    .org { font-weight:700; font-size:10px; letter-spacing:.15px; }
    .doc-title { font-size:12px; font-weight:700; margin-top:1px; }
    .line { border-top:1px solid #000; margin:4px 0 6px; }

    .meta-table { width:100%; border-collapse:collapse; }
    .meta-table td { padding:1px 0; vertical-align:top; }
    .right { text-align:right; }
    .center { text-align:center; }

    table { width:100%; border-collapse:collapse; table-layout:fixed; }
    th, td { border:0.6pt solid #444; padding:2px; }
    th { font-weight:700; text-align:center; font-size:8.6px; }
    td { vertical-align:top; font-size:8.6px; }

    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tr { page-break-inside: avoid; }

    .w-date { width:12%; }
    .w-time { width:11%; }
    .w-min  { width:9%; }
    .w-hrs  { width:8%; }
    .w-stat { width:12%; }

    .time   { white-space:nowrap; font-size:8.5px; }
    .totals { margin-top:4px; font-weight:700; }

    .holiday-merge { background:#eef6ff; font-weight:700; text-align:center; white-space:nowrap; }

    .sig-table { width:100%; border-collapse:collapse; margin-top:8px; }
    .sig-table td { border:0; vertical-align:bottom; }
    .sig-cell { width:50%; }
    .sig-pad { height:14px; }
    .sig-line { border-top:1px solid #000; height:1px; }
    .sig-label { font-size:8.4px; text-align:center; margin-top:2px; }

    .truncate-note { margin-top:6px; font-size:8.4px; color:#666; }
  </style>
</head>
<body>
@php
  // ==== Inputs / range ====
  $fromStr = $from ?? ($filters['from'] ?? null);
  $toStr   = $to   ?? ($filters['to']   ?? null);

  $firstDate = optional((is_array($rows)?collect($rows):$rows)->first())->work_date;
  $baseDay   = $firstDate ? \Carbon\Carbon::parse($firstDate) : \Carbon\Carbon::now();
  if (!$fromStr) $fromStr = $baseDay->copy()->startOfMonth()->toDateString();
  if (!$toStr)   $toStr   = $baseDay->copy()->endOfMonth()->toDateString();

  $fromC = \Carbon\Carbon::parse($fromStr)->startOfDay();
  $toC   = \Carbon\Carbon::parse($toStr)->startOfDay();
  $period = new \Carbon\CarbonPeriod($fromC, $toC);

  // ==== Holidays (active calendars only) ====
  $holidays = \Illuminate\Support\Facades\DB::table('holiday_calendars as hc')
      ->join('holiday_dates as hd','hd.holiday_calendar_id','=','hc.id')
      ->where('hc.status','active')
      ->whereBetween('hd.date', [$fromC->toDateString(), $toC->toDateString()])
      ->get(['hd.date','hd.name','hd.is_non_working'])
      ->keyBy(function($h){ return \Carbon\Carbon::parse($h->date)->toDateString(); });

  // ==== Group by employee ====
  $collection = is_array($rows) ? collect($rows) : $rows;
  $grouped = $collection->groupBy('user_id');

  // ==== Gather all shift ids, load grace & daily schedules ====
  $shiftIds = $collection->pluck('shift_window_id')->filter()->unique()->values();

  $graceByShift = [];
  if ($shiftIds->isNotEmpty()) {
    $graces = \Illuminate\Support\Facades\DB::table('shift_windows')
      ->whereIn('id', $shiftIds)->pluck('grace_minutes','id');
    foreach ($graces as $sid => $gm) $graceByShift[(int)$sid] = (int)$gm;
  }

  $sched = [];
  if ($shiftIds->isNotEmpty()) {
    $rowsDays = \Illuminate\Support\Facades\DB::table('shift_window_days')
        ->whereIn('shift_window_id', $shiftIds)
        ->get(['shift_window_id','dow','is_working','am_in','am_out','pm_in','pm_out']);
    foreach ($rowsDays as $d) {
      $sid=(int)$d->shift_window_id; $dow0=((int)$d->dow) % 7;
      $isWork = isset($d->is_working) ? (int)$d->is_working
              : ((is_null($d->am_in) && is_null($d->pm_in)) ? 0 : 1);
      $sched[$sid][$dow0]=['work'=>$isWork,'am_in'=>$d->am_in,'am_out'=>$d->am_out,'pm_in'=>$d->pm_in,'pm_out'=>$d->pm_out];
    }
  }

  // ==== Helpers (no "use" statements) ====
  $timeCell = function (?string $ts) { if (!$ts) return '—'; try { return \Carbon\Carbon::parse($ts)->format('g:i:s A'); } catch (\Throwable $e) { return '—'; } };
  $fmt2 = fn($n) => number_format((float)$n, 2, '.', '');

  $overlapMinutes = function (? \Carbon\Carbon $a1, ? \Carbon\Carbon $a2, ? \Carbon\Carbon $b1, ? \Carbon\Carbon $b2): int {
      if (!$a1 || !$a2 || !$b1 || !$b2) return 0;
      if ($a2->lte($a1) || $b2->lte($b1)) return 0;
      $s = max($a1->timestamp, $b1->timestamp);
      $e = min($a2->timestamp, $b2->timestamp);
      return $e > $s ? (int) floor(($e - $s)/60) : 0;
  };

  $computeHours = function($rec, $daySched) use ($overlapMinutes) {
      if (!$rec) return 0.00;
      $date = \Carbon\Carbon::parse($rec->work_date)->toDateString();
      $mins = 0;
      $hasAM = !empty($daySched['am_in']) && !empty($daySched['am_out']);
      $hasPM = !empty($daySched['pm_in']) && !empty($daySched['pm_out']);
      if ($hasAM || $hasPM) {
          if (!empty($rec->am_in) && !empty($rec->am_out) && $hasAM) {
              $amIn=\Carbon\Carbon::parse($rec->am_in); $amOut=\Carbon\Carbon::parse($rec->am_out);
              $wIn=\Carbon\Carbon::parse("$date {$daySched['am_in']}"); $wOut=\Carbon\Carbon::parse("$date {$daySched['am_out']}");
              $mins += $overlapMinutes($amIn,$amOut,$wIn,$wOut);
          }
          if (!empty($rec->pm_in) && !empty($rec->pm_out) && $hasPM) {
              $pmIn=\Carbon\Carbon::parse($rec->pm_in); $pmOut=\Carbon\Carbon::parse($rec->pm_out);
              $wIn=\Carbon\Carbon::parse("$date {$daySched['pm_in']}"); $wOut=\Carbon\Carbon::parse("$date {$daySched['pm_out']}");
              $mins += $overlapMinutes($pmIn,$pmOut,$wIn,$wOut);
          }
      } else {
          if (!empty($rec->am_in) && !empty($rec->am_out)) {
              $a=\Carbon\Carbon::parse($rec->am_in); $b=\Carbon\Carbon::parse($rec->am_out);
              if ($b->gt($a)) $mins += $b->diffInMinutes($a);
          }
          if (!empty($rec->pm_in) && !empty($rec->pm_out)) {
              $a=\Carbon\Carbon::parse($rec->pm_in); $b=\Carbon\Carbon::parse($rec->pm_out);
              if ($b->gt($a)) $mins += $b->diffInMinutes($a);
          }
      }
      return round(max(0,$mins)/60, 2);
  };

  $calcLate = function($rec,$daySched,$graceMin){
      if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
      $date = \Carbon\Carbon::parse($rec->work_date)->toDateString();
      $late = 0;
      if (!empty($rec->am_in) && !empty($daySched['am_in'])) {
          $sched=\Carbon\Carbon::parse("$date {$daySched['am_in']}")->addMinutes($graceMin);
          $act=\Carbon\Carbon::parse($rec->am_in);
          if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
      }
      if (!empty($rec->pm_in) && !empty($daySched['pm_in'])) {
          $sched=\Carbon\Carbon::parse("$date {$daySched['pm_in']}")->addMinutes($graceMin);
          $act=\Carbon\Carbon::parse($rec->pm_in);
          if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
      }
      return (int)$late;
  };

  $calcUnder = function($rec,$daySched){
      if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
      $date = \Carbon\Carbon::parse($rec->work_date)->toDateString();
      $ut = 0;
      if (!empty($rec->am_out) && !empty($daySched['am_out'])) {
          $sched=\Carbon\Carbon::parse("$date {$daySched['am_out']}"); $act=\Carbon\Carbon::parse($rec->am_out);
          if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
      }
      if (!empty($rec->pm_out) && !empty($daySched['pm_out'])) {
          $sched=\Carbon\Carbon::parse("$date {$daySched['pm_out']}"); $act=\Carbon\Carbon::parse($rec->pm_out);
          if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
      }
      return (int)$ut;
  };
@endphp

@foreach($grouped as $userId => $empRows)
  @php
    $emp     = $empRows->first();
    $byDate  = $empRows->keyBy(fn($r)=>\Carbon\Carbon::parse($r->work_date)->toDateString());
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
        <td><strong>Employee:</strong> {{ $emp->name ?? '—' }} <span class="small">(#{{ $userId }})</span></td>
        <td class="right"><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</td>
      </tr>
      <tr>
        <td><strong>Department:</strong> {{ $emp->department ?? '—' }}</td>
        <td class="right"><strong>Range:</strong> {{ $rangeText }}</td>
      </tr>
    </table>

    <table style="margin-top:4px;">
      <thead>
        <tr>
          <th class="w-date">Date</th>
          <th class="w-time">AM In</th>
          <th class="w-time">AM Out</th>
          <th class="w-time">PM In</th>
          <th class="w-time">PM Out</th>
          <th class="w-min">Late (min)</th>
          <th class="w-min">Undertime (min)</th>
          <th class="w-hrs">Hours</th>
          <th class="w-stat">Status</th>
        </tr>
      </thead>
      <tbody>
        @foreach($period as $day)
          @php
            $dkey = $day->toDateString();
            $rec  = $byDate->get($dkey);

            $sid  = (int)($emp->shift_window_id ?? $rec->shift_window_id ?? 0);
            $dow0 = $day->dayOfWeek; // 0..6

            $dSched = $sched[$sid][$dow0] ?? null;
            if ($dSched === null) {
              $dSched = [
                'work'  => ($dow0 === \Carbon\Carbon::SUNDAY) ? 0 : 1,
                'am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null,
              ];
            }
            $graceMin = $graceByShift[$sid] ?? 0;

            $hol   = $holidays->get($dkey);
            $isHolidayNonWorking = $hol && (int)$hol->is_non_working === 1;

            $hasScan = $rec && ($rec->am_in || $rec->am_out || $rec->pm_in || $rec->pm_out);
            $isWorkingDay = (int)($dSched['work'] ?? 1) === 1;

            // Status
            if ($rec && !empty($rec->status)) {
              $status = $rec->status;
            } elseif ($isHolidayNonWorking && !$hasScan) {
              $status = 'Holiday: '.($hol->name ?? '—');
            } elseif (!$isWorkingDay && !$hasScan) {
              $status = 'No Duty';
            } else {
              $status = $rec ? 'Present' : 'Absent';
            }

            $showMergedHoliday = !$hasScan && $isHolidayNonWorking;

            // Late / Undertime / Hours
            $dispLate  = $rec ? $calcLate($rec,  $dSched, $graceMin) : 0;
            $dispUnder = $rec ? $calcUnder($rec, $dSched)           : 0;

            $hours = 0.00;
            if ($rec) {
              $hours = (float)($rec->total_hours ?? 0);
              if ($hours <= 0) $hours = $computeHours($rec, $dSched);
              $hours = round($hours, 2);
            }

            $sumLate  += $dispLate;
            $sumUnder += $dispUnder;
            $sumHours += $hours;

            // PM-In duplication guard
            $pmInShow = $rec? $rec->pm_in : null;
            if ($rec && $rec->pm_in && $rec->pm_out && !$rec->am_out) {
              if (\Carbon\Carbon::parse($rec->pm_in)->equalTo(\Carbon\Carbon::parse($rec->pm_out))) $pmInShow = null;
            }
          @endphp

          @if($showMergedHoliday)
            <tr>
              <td class="center">{{ $dkey }}</td>
              <td class="holiday-merge" colspan="8">Holiday: {{ $hol->name ?? '—' }}</td>
            </tr>
          @else
            <tr>
              <td class="center">{{ $dkey }}</td>
              <td class="center time">{{ $rec ? $timeCell($rec->am_in)  : '—' }}</td>
              <td class="center time">{{ $rec ? $timeCell($rec->am_out) : '—' }}</td>
              <td class="center time">{{ $rec ? $timeCell($pmInShow)    : '—' }}</td>
              <td class="center time">{{ $rec ? $timeCell($rec->pm_out) : '—' }}</td>
              <td class="right">{{ $fmt2($dispLate) }}</td>
              <td class="right">{{ $fmt2($dispUnder) }}</td>
              <td class="right">{{ $fmt2($hours) }}</td>
              <td class="center">{{ $status }}</td>
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

@if(!empty($truncated) && $truncated)
  <div class="truncate-note">
    Note: PDF output truncated to {{ number_format($maxRows ?? 0) }} of {{ number_format($totalRows ?? 0) }} rows to keep the file printable.
    Use Excel export or narrow your filters for the full dataset.
  </div>
@endif

<script type="text/php">
if (isset($pdf)) {
  $font = $fontMetrics->get_font("DejaVu Sans", "normal");
  $pdf->page_text(520, 770, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 9, [0,0,0]);
}
</script>
</body>
</html>
