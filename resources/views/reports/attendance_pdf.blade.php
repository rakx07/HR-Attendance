<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        /* Fit one month per Letter page */
        @page { margin: 10mm 8mm; }

        /* Compact type */
        body { font-family: DejaVu Sans, sans-serif; color:#111; font-size:9px; line-height:1.15; }

        .header { text-align:center; margin-bottom:4px; }
        .org { font-weight:700; font-size:10px; letter-spacing:.15px; }
        .doc-title { font-size:12px; font-weight:700; margin-top:1px; }
        .line { border-top:1px solid #000; margin:4px 0 6px; }

        /* Meta */
        .meta-table { width:100%; border-collapse:collapse; }
        .meta-table td { padding:1px 0; vertical-align:top; }
        .right { text-align:right; }
        .center { text-align:center; }

        /* Main table */
        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        th, td { border:0.6pt solid #444; padding:2px 2px; }
        th { font-weight:700; text-align:center; font-size:8.8px; }
        td { vertical-align:top; }
        thead th { line-height:1.05; }
        tbody tr:nth-child(odd) td { background:#fafafa; }

        .w-date { width:12%; }
        .w-time { width:11%; }
        .w-min  { width:9%;  }
        .w-hrs  { width:7.5%; }
        .w-stat { width:12%; }

        .time { white-space:nowrap; font-size:8.6px; }

        .totals { margin-top:4px; font-weight:700; }

        /* Signatures */
        .sig-table { width:100%; border-collapse:collapse; margin-top:10px; }
        .sig-table td { border:0; vertical-align:bottom; }
        .sig-cell { width:50%; }
        .sig-pad { height:18px; }
        .sig-line { border-top:1px solid #000; height:1px; }
        .sig-label { font-size:8.8px; text-align:center; margin-top:2px; }

        /* Truncation note */
        .truncate-note { margin-top:6px; font-size:8.6px; color:#666; }
    </style>
</head>
<body>
@php
    use Illuminate\Support\Facades\DB;
    use Carbon\Carbon;
    use Carbon\CarbonPeriod;

    /* ---------- Helpers ---------- */

    // Format time as 12h with seconds
    $timeCell = function (?string $ts) {
        if (!$ts) return '—';
        return Carbon::parse($ts)->format('g:i:s A');
    };

    // Establish calendar range (inclusive)
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
    $period = CarbonPeriod::create($from, $to); // includes both ends

    /* ---------- Pull active calendar holidays once ---------- */
    // Map: 'Y-m-d' => ['name' => '...', 'non_working' => 1/0]
    $holidays = DB::table('holiday_calendars as hc')
        ->join('holiday_dates as hd', 'hd.holiday_calendar_id', '=', 'hc.id')
        ->where('hc.status', 'active')
        ->whereBetween('hd.date', [$from->toDateString(), $to->toDateString()])
        ->get(['hd.date', 'hd.name', 'hd.is_non_working'])
        ->keyBy(fn ($h) => Carbon::parse($h->date)->toDateString());

    // Group attendance rows per employee for O(1) lookups per day
    $grouped = $rows->groupBy('user_id');
    $idx = 0; $count = $grouped->count();
@endphp

@foreach($grouped as $userId => $empRows)
    @php
        $emp = $empRows->first();

        // Index this employee's rows by date
        $byDate = $empRows->keyBy(fn($r) => Carbon::parse($r->work_date)->toDateString());

        $rangeText = $from->toDateString() . ' to ' . $to->toDateString();

        $lateTotalMin = (int) $empRows->sum('late_minutes');
        $lateHours    = intdiv($lateTotalMin, 60);
        $lateRemainder= $lateTotalMin % 60;
    @endphp

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
                $r    = $byDate->get($dkey);

                // Decide Status:
                // 1) If there's a record and it has a status (e.g., Present, Vacation Leave, Sick Leave, Holiday from your consolidation),
                //    show it as-is.
                // 2) If there's NO record and the date is an active-calendar non-working holiday, show "Holiday: <name>".
                // 3) If still no record and it's Sunday, show "No Duty".
                // 4) Otherwise, "Absent".
                if ($r && !empty($r->status)) {
                    $status = $r->status;
                } else {
                    $h = $holidays->get($dkey);
                    if ($h && (int)$h->is_non_working === 1 && !$r) {
                        $status = 'Holiday: ' . ($h->name ?? '—');
                    } elseif (!$r && $day->isSunday()) {
                        $status = 'No Duty';
                    } else {
                        $status = $r->status ?? 'Absent';
                    }
                }
            @endphp
            <tr>
                <td class="center">{{ $dkey }}</td>
                <td class="center time">{{ $r ? $timeCell($r->am_in)  : '—' }}</td>
                <td class="center time">{{ $r ? $timeCell($r->am_out) : '—' }}</td>
                <td class="center time">{{ $r ? $timeCell($r->pm_in)  : '—' }}</td>
                <td class="center time">{{ $r ? $timeCell($r->pm_out) : '—' }}</td>
                <td class="right">{{ $r->late_minutes      ?? 0 }}</td>
                <td class="right">{{ $r->undertime_minutes ?? 0 }}</td>
                <td class="right">{{ number_format((float)($r->total_hours ?? 0), 2) }}</td>
                <td class="center">{{ $status }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        Total Late: {{ $lateHours }} hr {{ $lateRemainder }} min ({{ $lateTotalMin }} min)
    </div>

    {{-- Signatures --}}
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

    @php $idx++; @endphp
    @if($idx < $count)
        <div class="page-break"></div>
    @endif
@endforeach

@if(!empty($truncated) && $truncated)
    <div class="truncate-note">
        Note: PDF output truncated to {{ $maxRows }} of {{ $totalRows }} rows to keep the file printable.
        Use Excel export for the full dataset.
    </div>
@endif

{{-- Page numbering --}}
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
    $pdf->page_text(520, 770, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 9, [0,0,0]);
}
</script>
</body>
</html>
