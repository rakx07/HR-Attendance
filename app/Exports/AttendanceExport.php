<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromArray, WithHeadings, Responsable
{
    public string $fileName = 'attendance.xlsx';
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function headings(): array
    {
        return [
            'Date', 'Name', 'Department',
            'AM In', 'AM Out', 'PM In', 'PM Out',
            'Late (min)', 'Undertime (min)', 'Hours', 'Status',
        ];
    }

    public function array(): array
    {
        // Build the same query as in ReportController::baseQuery (copied inline for simplicity)
        $r = new Request($this->filters);

        $q = DB::table('attendance_days as ad')
            ->join('users as u', 'u.id', '=', 'ad.user_id')
            ->select('ad.work_date','u.name','u.department','ad.am_in','ad.am_out','ad.pm_in','ad.pm_out',
                'ad.late_minutes','ad.undertime_minutes','ad.total_hours','ad.status');

        if ($r->filled('from')) $q->whereDate('ad.work_date', '>=', $r->date('from'));
        if ($r->filled('to'))   $q->whereDate('ad.work_date', '<=', $r->date('to'));

        $mode = $r->input('mode', 'all_active');
        if ($mode === 'employee') {
            if ($r->filled('employee_id')) $q->where('u.id', $r->integer('employee_id'));
        } else {
            if (!$r->boolean('include_inactive')) $q->where('u.active', 1);
        }

        if ($r->filled('dept'))   $q->where('u.department', 'like', '%'.$r->input('dept').'%');
        if ($r->filled('status')) $q->where('ad.status', $r->input('status'));

        $rows = $q->orderBy('u.name')->orderBy('ad.work_date')->get();

        // Format times as 12-hour for Excel output
        return $rows->map(function ($r) {
            $f = fn($t) => $t ? \Carbon\Carbon::parse($t)->format('g:i A') : '';
            return [
                $r->work_date,
                $r->name,
                $r->department,
                $f($r->am_in),
                $f($r->am_out),
                $f($r->pm_in),
                $f($r->pm_out),
                $r->late_minutes,
                $r->undertime_minutes,
                number_format((float)$r->total_hours, 2),
                $r->status,
            ];
        })->toArray();
    }
}
