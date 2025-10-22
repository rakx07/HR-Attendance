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

    /* Adjusted widths for balance */
    .w-date { width:12%; }
    .w-time { width:11%; }   /* 4 × 11 = 44% */
    .w-min  { width:7%; }    /* 2 × 7 = 14% */
    .w-hrs  { width:7%; }
    .w-stat { width:9%; }
    .w-remarks { width:10%; }  /* compact remarks cell */

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

    td.remarks-cell { height:15px; } /* modest handwriting area */
  </style>
</head>
<body>
@php
  use Carbon\Carbon;
  use Carbon\CarbonPeriod;
  use Illuminate\Support\Facades\DB;

  $fromStr = $from ?? ($filters['from'] ?? null);
  $toStr   = $to   ?? ($filters['to']   ?? null);

  $firstDate = optional((is_array($rows)?collect($rows):$rows)->first())->work_date;
  $baseDay   = $firstDate ? Carbon::parse($firstDate) : Carbon::now();
  if (!$fromStr) $fromStr = $baseDay->copy()->startOfMonth()->toDateString();
  if (!$toStr)   $toStr   = $baseDay->copy()->endOfMonth()->toDateString();

  $fromC = Carbon::parse($fromStr)->startOfDay();
  $toC   = Carbon::parse($toStr)->startOfDay();
  $period = new CarbonPeriod($fromC, $toC);

  $holidays = DB::table('holiday_calendars as hc')
      ->join('holiday_dates as hd','hd.holiday_calendar_id','=','hc.id')
      ->where('hc.status','active')
      ->whereBetween('hd.date', [$fromC->toDateString(), $toC->toDateString()])
      ->get(['hd.date','hd.name','hd.is_non_working'])
      ->keyBy(fn($h)=>Carbon::parse($h->date)->toDateString());

  $collection = is_array($rows) ? collect($rows) : $rows;
  $grouped = $collection->groupBy('user_id');
  $shiftIds = $collection->pluck('shift_window_id')->filter()->unique()->values();

  $graceByShift = [];
  if ($shiftIds->isNotEmpty()) {
    $graces = DB::table('shift_windows')->whereIn('id',$shiftIds)->pluck('grace_minutes','id');
    foreach ($graces as $sid=>$gm) $graceByShift[(int)$sid]=(int)$gm;
  }

  $sched=[];
  if ($shiftIds->isNotEmpty()) {
    $rowsDays = DB::table('shift_window_days')
      ->whereIn('shift_window_id',$shiftIds)
      ->get(['shift_window_id','dow','is_working','am_in','am_out','pm_in','pm_out']);
    foreach ($rowsDays as $d) {
      $sid=(int)$d->shift_window_id; $dow0=((int)$d->dow)%7;
      $isWork=isset($d->is_working)?(int)$d->is_working:((is_null($d->am_in)&&is_null($d->pm_in))?0:1);
      $sched[$sid][$dow0]=['work'=>$isWork,'am_in'=>$d->am_in,'am_out'=>$d->am_out,'pm_in'=>$d->pm_in,'pm_out'=>$d->pm_out];
    }
  }

  $timeCell = fn(?string $ts)=>$ts?Carbon::parse($ts)->format('g:i:s A'):'—';
  $fmt2 = fn($n)=>number_format((float)$n,2,'.','');
  $overlapSeconds = fn(?Carbon $a1,?Carbon $a2,?Carbon $b1,?Carbon $b2)
    =>(!$a1||!$a2||!$b1||!$b2||$a2->lte($a1)||$b2->lte($b1))?0:
      max(0,min($a2->timestamp,$b2->timestamp)-max($a1->timestamp,$b1->timestamp));

  $computeHours=function($rec,$daySched,int $graceMin=0)use($overlapSeconds){
    if(!$rec)return 0.0;
    $date=Carbon::parse($rec->work_date)->toDateString();$secs=0;
    $hasAM=!empty($daySched['am_in'])&&!empty($daySched['am_out']);
    $hasPM=!empty($daySched['pm_in'])&&!empty($daySched['pm_out']);
    if($hasAM&&$rec->am_in&&$rec->am_out){
      $amIn=Carbon::parse($rec->am_in);$amOut=Carbon::parse($rec->am_out);
      $wIn=Carbon::parse("$date {$daySched['am_in']}");$wOut=Carbon::parse("$date {$daySched['am_out']}");
      $snap=$wIn->copy()->addMinutes($graceMin);
      if($amIn->betweenIncluded($wIn,$snap))$amIn=$wIn;
      if($amIn->lt($wIn))$amIn=$wIn;if($amOut->gt($wOut))$amOut=$wOut;
      $secs+=$overlapSeconds($amIn,$amOut,$wIn,$wOut);
    }
    if($hasPM&&$rec->pm_in&&$rec->pm_out){
      $pmIn=Carbon::parse($rec->pm_in);$pmOut=Carbon::parse($rec->pm_out);
      $wIn=Carbon::parse("$date {$daySched['pm_in']}");$wOut=Carbon::parse("$date {$daySched['pm_out']}");
      $snap=$wIn->copy()->addMinutes($graceMin);
      if($pmIn->betweenIncluded($wIn,$snap))$pmIn=$wIn;
      if($pmIn->lt($wIn))$pmIn=$wIn;if($pmOut->gt($wOut))$pmOut=$wOut;
      $secs+=$overlapSeconds($pmIn,$pmOut,$wIn,$wOut);
    }
    if(!$hasAM&&!$hasPM){
      if($rec->am_in&&$rec->am_out){$a=Carbon::parse($rec->am_in);$b=Carbon::parse($rec->am_out);if($b->gt($a))$secs+=$b->diffInSeconds($a);}
      if($rec->pm_in&&$rec->pm_out){$a=Carbon::parse($rec->pm_in);$b=Carbon::parse($rec->pm_out);if($b->gt($a))$secs+=$b->diffInSeconds($a);}
    }
    return round(max(0,$secs)/3600,2);
  };
  $calcLate=function($rec,$daySched,$graceMin){
    if(!$rec||!$daySched||(int)($daySched['work']??1)===0)return 0;
    $date=Carbon::parse($rec->work_date)->toDateString();$late=0;
    if(!empty($rec->am_in)&&!empty($daySched['am_in'])){
      $sched=Carbon::parse("$date {$daySched['am_in']}")->addMinutes($graceMin);
      $act=Carbon::parse($rec->am_in);if($act->gt($sched))$late+=$sched->diffInMinutes($act);
    }
    if(!empty($rec->pm_in)&&!empty($daySched['pm_in'])){
      $sched=Carbon::parse("$date {$daySched['pm_in']}")->addMinutes($graceMin);
      $act=Carbon::parse($rec->pm_in);if($act->gt($sched))$late+=$sched->diffInMinutes($act);
    }
    return(int)$late;
  };
  $calcUnder=function($rec,$daySched){
    if(!$rec||!$daySched||(int)($daySched['work']??1)===0)return 0;
    $date=Carbon::parse($rec->work_date)->toDateString();$ut=0;
    if(!empty($rec->am_out)&&!empty($daySched['am_out'])){
      $sched=Carbon::parse("$date {$daySched['am_out']}");$act=Carbon::parse($rec->am_out);
      if($act->lt($sched))$ut+=$act->diffInMinutes($sched);
    }
    if(!empty($rec->pm_out)&&!empty($daySched['pm_out'])){
      $sched=Carbon::parse("$date {$daySched['pm_out']}");$act=Carbon::parse($rec->pm_out);
      if($act->lt($sched))$ut+=$act->diffInMinutes($sched);
    }
    return(int)$ut;
  };
@endphp

@foreach($grouped as $userId=>$empRows)
  @php
    $emp=$empRows->first();
    $byDate=$empRows->keyBy(fn($r)=>Carbon::parse($r->work_date)->toDateString());
    $rangeText=$fromC->toDateString().' to '.$toC->toDateString();
    $sumLate=$sumUnder=$sumHours=0;
  @endphp

  <div class="employee">
    <div class="header">
      <div class="org">Attendance Management System</div>
      <div class="doc-title">Attendance Report Summary</div>
    </div>
    <div class="line"></div>

    <table class="meta-table">
      <tr><td><strong>Employee:</strong> {{ $emp->name ?? '—' }} (#{{ $userId }})</td><td class="right"><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</td></tr>
      <tr><td><strong>Department:</strong> {{ $emp->department ?? '—' }}</td><td class="right"><strong>Range:</strong> {{ $rangeText }}</td></tr>
    </table>

    <table style="margin-top:4px;">
      <thead>
        <tr>
          <th class="w-date">Date</th>
          <th class="w-time">AM In</th><th class="w-time">AM Out</th>
          <th class="w-time">PM In</th><th class="w-time">PM Out</th>
          <th class="w-min">Late (min)</th><th class="w-min">Undertime (min)</th>
          <th class="w-hrs">Hours</th><th class="w-stat">Status</th>
          <th class="w-remarks">Remarks</th>
        </tr>
      </thead>
      <tbody>
        @foreach($period as $day)
          @php
            $dkey=$day->toDateString();$rec=$byDate->get($dkey);
            $sid=(int)($emp->shift_window_id??$rec->shift_window_id??0);$dow0=$day->dayOfWeek;
            $dSched=$sched[$sid][$dow0]??['work'=>($dow0===Carbon::SUNDAY?0:1)];
            $graceMin=$graceByShift[$sid]??0;
            $hol=$holidays->get($dkey);$isHolidayNonWorking=$hol&&(int)$hol->is_non_working===1;
            $hasScan=$rec&&($rec->am_in||$rec->am_out||$rec->pm_in||$rec->pm_out);
            $isWorkingDay=(int)($dSched['work']??1)===1;
            $status=$rec->status??($isHolidayNonWorking&&!$hasScan?'Holiday: '.($hol->name??'—'):(!$isWorkingDay&&!$hasScan?'No Duty':($rec?'Present':'Absent')));
            $dispLate=$rec?$calcLate($rec,$dSched,$graceMin):0;
            $dispUnder=$rec?$calcUnder($rec,$dSched):0;
            $hours=$rec?(float)($rec->total_hours??0):0;if($hours<=0&&$rec)$hours=$computeHours($rec,$dSched,$graceMin);
            $sumLate+=$dispLate;$sumUnder+=$dispUnder;$sumHours+=$hours;
          @endphp
          <tr>
            <td class="center">{{ $dkey }}</td>
            <td class="center time">{{ $timeCell($rec->am_in??null) }}</td>
            <td class="center time">{{ $timeCell($rec->am_out??null) }}</td>
            <td class="center time">{{ $timeCell($rec->pm_in??null) }}</td>
            <td class="center time">{{ $timeCell($rec->pm_out??null) }}</td>
            <td class="right">{{ $fmt2($dispLate) }}</td>
            <td class="right">{{ $fmt2($dispUnder) }}</td>
            <td class="right">{{ $fmt2($hours) }}</td>
            <td class="center">{{ $status }}</td>
            <td class="remarks-cell">&nbsp;</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="totals">
      Late Total: {{ $fmt2($sumLate) }} min &nbsp;|&nbsp;
      Undertime: {{ $fmt2($sumUnder) }} min &nbsp;|&nbsp;
      Hours: {{ $fmt2($sumHours) }}
    </div>

    <table class="sig-table">
      <tr>
        <td class="sig-cell"><div class="sig-pad"></div><div class="sig-line"></div><div class="sig-label"><strong>{{ $emp->name ?? '—' }}</strong> — Employee</div></td>
        <td class="sig-cell"><div class="sig-pad"></div><div class="sig-line"></div><div class="sig-label"><strong>Unit Head</strong></div></td>
      </tr>
    </table>
  </div>
@endforeach

@if(!empty($truncated)&&$truncated)
<div class="truncate-note">Note: Output truncated to {{ number_format($maxRows??0) }} of {{ number_format($totalRows??0) }} rows for printability. Use Excel export for full dataset.</div>
@endif

<script type="text/php">
if(isset($pdf)){
  $font=$fontMetrics->get_font("DejaVu Sans","normal");
  $pdf->page_text(520,770,"Page {PAGE_NUM} of {PAGE_COUNT}",$font,9,[0,0,0]);
}
</script>
</body>
</html>
