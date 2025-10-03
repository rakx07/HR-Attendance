<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceExport;

class ReportController extends Controller
{
    public function index(Request $r)
    {
        $employeeId = $r->filled('employee_id') ? (int) $r->employee_id : null;
        $from       = $r->filled('from') ? $r->from : null;   // expect Y-m-d
        $to         = $r->filled('to')   ? $r->to   : null;   // expect Y-m-d

        $q = DB::table('attendance_days')
            ->join('users', 'users.id', '=', 'attendance_days.user_id')
            ->when($employeeId, fn ($x) => $x->where('users.id', $employeeId))
            ->when($r->filled('status'), fn ($x) => $x->where('attendance_days.status', $r->status))
            ->when($r->filled('dept'), fn ($x) => $x->where('users.department', $r->dept))
            ->when($from, fn ($x) => $x->where('attendance_days.work_date', '>=', $from))
            ->when($to,   fn ($x) => $x->where('attendance_days.work_date', '<=', $to))
            ->select(
                'users.id as user_id',
                DB::raw("TRIM(CONCAT(users.last_name, ', ', users.first_name, ' ', COALESCE(users.middle_name, ''))) as name"),
                'users.department',
                'attendance_days.work_date',
                'attendance_days.am_in',
                'attendance_days.am_out',
                'attendance_days.pm_in',
                'attendance_days.pm_out',
                'attendance_days.late_minutes',
                'attendance_days.undertime_minutes',
                'attendance_days.total_hours',
                'attendance_days.status'
            )
            ->orderByDesc('attendance_days.work_date');

        return view('reports.attendance', [
            'rows' => $q->paginate(50)->withQueryString(),
        ]);
    }

    public function export(Request $r)
    {
        $filename = 'attendance_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new AttendanceExport($r->all()), $filename);
    }
}
