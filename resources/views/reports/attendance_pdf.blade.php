<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        /* Compact, formal, one-page-per-employee layout */
        @page { margin: 16mm 12mm; } /* top/btm, left/right */
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 10.5px; }
        .header { text-align: center; margin-bottom: 6px; }
        .org { font-weight: 700; font-size: 12px; letter-spacing: .2px; }
        .doc-title { font-size: 14px; font-weight: 700; margin-top: 2px; }
        .line { border-top: 1px solid #000; margin: 6px 0 8px; }

        .meta { margin: 4px 0 6px; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 2px 0; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #444; padding: 4px 4px; }
        th { font-weight: 700; text-align: center; }
        td { vertical-align: top; }

        /* Slight zebra for readability without adding height */
        tbody tr:nth-child(odd) td { background: #fafafa; }

        .totals { margin-top: 6px; font-weight: 700; }
        .small { font-size: 10px; color: #555; }
        .page-break { page-break-after: always; }

        /* Prevent rows from breaking awkwardly */
        tr { page-break-inside: avoid; }

        /* Tighten header row height */
        thead th { line-height: 1.1; }

        /* Keep columns narrow for a single page */
        .w-date { width: 13%; }
        .w-time { width: 11%; }   /* AM/PM in/out cells */
        .w-min  { width: 9.5%; }  /* Late / Undertime */
        .w-hrs  { width: 8%; }    /* Hours */
        .w-stat { width: 13%; }   /* Status */
    </style>
</head>
<body>
@php
    $grouped = $rows->groupBy('user_id'); // one page per employee
    $idx = 0; $count = $grouped->count();
@endphp

@foreach($grouped as $userId => $empRows)
    @php
        $emp = $empRows->first();
        $range = trim(($filters['from'] ?? '—') . ' to ' . ($filters['to'] ?? '—'));
        $lateTotalMin = (int) $empRows->sum('late_minutes');
        $lateHours = intdiv($lateTotalMin, 60);
        $lateRemainder = $lateTotalMin % 60;
    @endphp

    {{-- Header --}}
    <div class="header">
        <div class="org">Attendance Management System</div>
        <div class="doc-title">Attendance Report</div>
    </div>
    <div class="line"></div>

    {{-- Meta block (kept concise to fit one page) --}}
    <table class="meta-table">
        <tr>
            <td><strong>Employee:</strong> {{ $emp->name }} <span class="small">(#{{ $userId }})</span></td>
            <td class="right"><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</td>
        </tr>
        <tr>
            <td><strong>Department:</strong> {{ $emp->department ?? '—' }}</td>
            <td class="right"><strong>Range:</strong> {{ $range }}</td>
        </tr>
    </table>

    {{-- Table (Name and Department columns removed) --}}
    <table style="margin-top: 6px;">
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
        @foreach($empRows as $r)
            <tr>
                <td class="center">{{ $r->work_date }}</td>
                <td class="center">{{ $r->am_in  ? \Carbon\Carbon::parse($r->am_in)->format('g:i A')  : '' }}</td>
                <td class="center">{{ $r->am_out ? \Carbon\Carbon::parse($r->am_out)->format('g:i A') : '' }}</td>
                <td class="center">{{ $r->pm_in  ? \Carbon\Carbon::parse($r->pm_in)->format('g:i A')  : '' }}</td>
                <td class="center">{{ $r->pm_out ? \Carbon\Carbon::parse($r->pm_out)->format('g:i A') : '' }}</td>
                <td class="right">{{ $r->late_minutes }}</td>
                <td class="right">{{ $r->undertime_minutes }}</td>
                <td class="right">{{ number_format((float)$r->total_hours, 2) }}</td>
                <td class="center">{{ $r->status }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Per-employee totals (compact) --}}
    <div class="totals">
        Total Late: {{ $lateHours }} hr {{ $lateRemainder }} min ({{ $lateTotalMin }} min)
    </div>

    @php $idx++; @endphp
    @if($idx < $count)
        <div class="page-break"></div>
    @endif
@endforeach

{{-- Optional page numbers (kept small, bottom-right) --}}
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
    // x,y tuned for letter with margins above
    $pdf->page_text(520, 770, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 9, [0,0,0]);
}
</script>
</body>
</html>
