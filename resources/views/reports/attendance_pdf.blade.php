{{-- resources/views/reports/attendance_pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Attendance Report</title>
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

    .holiday-merge {
      background:#eef6ff;
      font-weight:700;
      text-align:center;
      white-space:nowrap;
    }

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
  use Illuminate\Support\Facades\DB;
  use Carbon\Carbon;
  use Carbon\CarbonPeriod;

  /* ---------- DATE RANGE (inclusive) ---------- */
  $fromStr = $filters['from'] ?? null;
  $toStr   = $filters['to']   ?? null;

  if (!$fromStr || !$toStr) {
      $firstDate = optional($rows->first())->work_date;
      $baseDay   = $firstDate ? Carbon::parse($firstDate) : Carbon::now();
      $fromStr   = $fromStr ?: $baseDay->copy()->startOfMonth()->toDateString();
      $toStr     = $toStr   ?: $baseDay->copy()->endOfMonth()->toDateString();
  }

  $from   = Carbon::parse($fromStr)->startOfDay();
  $to     = Carbon::parse($toStr)->startOfDay();
  $period = CarbonPeriod::create($from, $to);

  /* ---------- HOLIDAYS (active calendars only) ---------- */
  $holidays = DB::table('holiday_calendars as hc')
      ->join('holiday_dates as hd','hd.holiday_calendar_id','=','hc.id')
      ->where('hc.status','active')
      ->whereBetween('hd.date', [$from->toDateString(), $to->toDateString()])
      ->get(['hd.date','hd.name','hd.is_non_working'])
      ->keyBy(fn($h)=>Carbon::parse($h->date)->toDateString());

  /* ---------- GROUP BY EMPLOYEE ---------- */
  $grouped = $rows->groupBy('user_id');

  /* ---------- PULL PER-DAY SCHEDULE + GRACE PER SHIFT ---------- */
  $shiftIds = $rows->pluck('shift_window_id')->filter()->unique()->values();

  // grace minutes by shift_window_id
  $graceByShift = [];
  if ($shiftIds->isNotEmpty()) {
    $graces = DB::table('shift_windows')
      ->whereIn('id', $shiftIds)
      ->pluck('grace_minutes','id');
    foreach ($graces as $sid => $gm) $graceByShift[(int)$sid] = (int)$gm;
  }

  // per-day schedule for each shift
  $sched = []; // $sched[shift_id][dow0..6] = ['work'=>0/1,'am_in','am_out','pm_in','pm_out']
  if ($shiftIds->isNotEmpty()) {
    $rowsDays = DB::table('shift_window_days')
        ->whereIn('shift_window_id', $shiftIds)
        ->get(['shift_window_id','dow','is_working','am_in','am_out','pm_in','pm_out']);

    foreach ($rowsDays as $d) {
      $sid   = (int)$d->shift_window_id;
      $dowDb = (int)$d->dow;  // 1..7 (Mon..Sun)
      $dow0  = $dowDb % 7;    // -> 0..6 (Sun..Sat)
      $isWork = isset($d->is_working)
                ? (int)$d->is_working
                : ((is_null($d->am_in) && is_null($d->pm_in)) ? 0 : 1);

      $sched[$sid][$dow0] = [
        'work'  => $isWork,
        'am_in' => $d->am_in, 'am_out' => $d->am_out,
        'pm_in' => $d->pm_in, 'pm_out' => $d->pm_out,
      ];
    }
  }

  /* ---------- Helpers ---------- */
  $timeCell = function (?string $ts) {
      if (!$ts) return '—';
      return Carbon::parse($ts)->format('g:i:s A');
  };

  // Hours = earliest IN to latest OUT minus lunch overlap (if any)
  $computeHours = function($rec, $daySched) {
      if (!$rec) return 0.00;
      $date = Carbon::parse($rec->work_date)->toDateString();

      $start = null;
      if ($rec->am_in) $start = Carbon::parse($rec->am_in);
      if (!$start && $rec->pm_in) $start = Carbon::parse($rec->pm_in);

      $end = null;
      if ($rec->pm_out) $end = Carbon::parse($rec->pm_out);
      if (!$end && $rec->am_out) $end = Carbon::parse($rec->am_out);

      if (!$start || !$end || $end->lessThanOrEqualTo($start)) return 0.00;

      $mins = $end->diffInMinutes($start);

      // deduct overlap with lunch window (am_out..pm_in) if both exist
      if ($daySched && $daySched['am_out'] && $daySched['pm_in']) {
          $ls = Carbon::parse("$date {$daySched['am_out']}");
          $le = Carbon::parse("$date {$daySched['pm_in']}");
          $ov = max(0, min($end->timestamp, $le->timestamp) - max($start->timestamp, $ls->timestamp));
          $mins -= (int) floor($ov/60) * 60; // whole minutes
      }

      return round($mins/60, 2);
  };

  // Late = minutes after (scheduled in + grace) for AM-In and PM-In
  $calcLate = function($rec, $daySched, int $graceMin = 0){
      if (!$daySched || (int)($daySched['work'] ?? 1) === 0) return 0;
      $date = Carbon::parse($rec->work_date)->toDateString();
      $late = 0;

      if (!empty($rec->am_in) && !empty($daySched['am_in'])) {
          $sched  = Carbon::parse("$date {$daySched['am_in']}")->addMinutes($graceMin);
          $actual = Carbon::parse($rec->am_in);
          if ($actual->gt($sched)) $late += $sched->diffInMinutes($actual);
      }

      if (!empty($rec->pm_in) && !empty($daySched['pm_in'])) {
          $sched  = Carbon::parse("$date {$daySched['pm_in']}")->addMinutes($graceMin);
          $actual = Carbon::parse($rec->pm_in);
          if ($actual->gt($sched)) $late += $sched->diffInMinutes($actual);
      }

      return (int) $late;
  };

  // Undertime = minutes early vs scheduled PM-Out (no negative)
  $calcUnder = function($rec, $daySched, int $graceMin = 0){
      if (!$daySched || (int)($daySched['work'] ?? 1) === 0) return 0;
      $date = Carbon::parse($rec->work_date)->toDateString();
      if (!empty($rec->pm_out) && !empty($daySched['pm_out'])) {
          $sched  = Carbon::parse("$date {$daySched['pm_out']}");
          $actual = Carbon::parse($rec->pm_out);
          if ($actual->lt($sched)) return (int) $actual->diffInMinutes($sched);
      }
      return 0;
  };
@endphp

@foreach($grouped as $userId => $empRows)
  @php
      $emp = $empRows->first();
      $byDate = $empRows->keyBy(fn($r)=>Carbon::parse($r->work_date)->toDateString());
      $rangeText = $from->toDateString().' to '.$to->toDateString();

      $sumLate = 0.00; $sumUnder = 0.00; $sumHours = 0.00;
  @endphp

  <div class="employee">
    <div class="header">
      <div class="org">Attendance Management System</div>
      <div class="doc-title">Attendance Report</div>
    </div>
    <div class="line"></div>

    <table class="meta-table">
      <tr>
        <td><strong>Employee:</strong> {{ $emp->name }} <span class="small">(#{{ $userId }})</span></td>
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
            $dow0 = $day->dayOfWeek; // 0..6 (Sun..Sat)

            // schedule for the day; default: Sunday off if unknown
            $dSched = $sched[$sid][$dow0] ?? null;
            if ($dSched === null) {
              $dSched = [
                'work'  => ($dow0 === Carbon::SUNDAY) ? 0 : 1,
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
            $dispUnder = $rec ? $calcUnder($rec, $dSched, $graceMin) : 0;

            $hours = 0.00;
            if ($rec) {
              $hours = (float)($rec->total_hours ?? 0);
              if ($hours <= 0) $hours = $computeHours($rec, $dSched);
              $hours = round($hours, 2);
            }

            $sumLate  += $dispLate;
            $sumUnder += $dispUnder;
            $sumHours += $hours;

            // PM-In duplication guard (when data consolidation mirrored Out)
            $pmInShow = $rec? $rec->pm_in : null;
            if ($rec && $rec->pm_in && $rec->pm_out && !$rec->am_out) {
              if (Carbon::parse($rec->pm_in)->equalTo(Carbon::parse($rec->pm_out))) $pmInShow = null;
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
              <td class="right">{{ number_format((float)$dispLate, 2, '.', '') }}</td>
              <td class="right">{{ number_format((float)$dispUnder, 2, '.', '') }}</td>
              <td class="right">{{ number_format((float)$hours, 2, '.', '') }}</td>
              <td class="center">{{ $status }}</td>
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>

    <div class="totals">
      Late Total: {{ number_format((float)$sumLate, 2, '.', '') }} min &nbsp; | &nbsp;
      Undertime: {{ number_format((float)$sumUnder, 2, '.', '') }} min &nbsp; | &nbsp;
      Hours: {{ number_format((float)$sumHours, 2, '.', '') }}
    </div>

    <table class="sig-table">
      <tr>
        <td class="sig-cell">
          <div class="sig-pad"></div>
          <div class="sig-line"></div>
          <div class="sig-label"><strong>{{ $emp->name }}</strong> — Employee</div>
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
    Note: PDF output truncated to {{ $maxRows }} of {{ $totalRows }} rows to keep the file printable.
    Use Excel export for the full dataset.
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
