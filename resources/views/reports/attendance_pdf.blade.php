<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f2f2f2; text-align: left; }
        .meta { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h2>Attendance Report</h2>
    <div class="meta">
        @php
            $mode = $filters['mode'] ?? 'all_active';
        @endphp
        <div><strong>Range:</strong>
            {{ $filters['from'] ?? '—' }} to {{ $filters['to'] ?? '—' }}
        </div>
        <div><strong>Mode:</strong>
            {{ $mode === 'employee' ? 'Single Employee' : 'All Active' }}
        </div>
        @if(($mode === 'employee') && !empty($filters['employee_id']))
            <div><strong>Employee ID:</strong> {{ $filters['employee_id'] }}</div>
        @endif
        @if(!empty($filters['dept'])) <div><strong>Department:</strong> {{ $filters['dept'] }}</div> @endif
        @if(!empty($filters['status'])) <div><strong>Status:</strong> {{ $filters['status'] }}</div> @endif
        <div><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Name</th><th>Dept</th>
                <th>AM In</th><th>AM Out</th>
                <th>PM In</th><th>PM Out</th>
                <th style="text-align:right;">Late</th>
                <th style="text-align:right;">Undertime</th>
                <th style="text-align:right;">Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r->work_date }}</td>
                <td>{{ $r->name }}</td>
                <td>{{ $r->department }}</td>
                <td>{{ $r->am_in  ? \Carbon\Carbon::parse($r->am_in)->format('g:i A')  : '' }}</td>
                <td>{{ $r->am_out ? \Carbon\Carbon::parse($r->am_out)->format('g:i A') : '' }}</td>
                <td>{{ $r->pm_in  ? \Carbon\Carbon::parse($r->pm_in)->format('g:i A')  : '' }}</td>
                <td>{{ $r->pm_out ? \Carbon\Carbon::parse($r->pm_out)->format('g:i A') : '' }}</td>
                <td style="text-align:right;">{{ $r->late_minutes }}</td>
                <td style="text-align:right;">{{ $r->undertime_minutes }}</td>
                <td style="text-align:right;">{{ number_format($r->total_hours,2) }}</td>
                <td>{{ $r->status }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
