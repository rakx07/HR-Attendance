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
        $rows = $this->baseQuery($r)
            ->orderByDesc('attendance_days.work_date')
            ->paginate(50)
            ->withQueryString();

        return view('reports.attendance', ['rows' => $rows]);
    }

    public function export(Request $r)
    {
        $filename = 'attendance_' . now()->format('Ymd_His') . '.xlsx';
        // Pass the built query params to the export class (unchanged on your side)
        return Excel::download(new AttendanceExport($r->all()), $filename);
    }

    /**
     * Build the common query. By default it shows ONLY active users.
     * If you ever need to include inactive, pass ?include_inactive=1 in the URL.
     */
    protected function baseQuery(Request $r)
    {
        $employeeId = $r->filled('employee_id') ? (int) $r->employee_id : null;
        $from       = $r->filled('from') ? $r->from : null;   // Y-m-d
        $to         = $r->filled('to')   ? $r->to   : null;   // Y-m-d
        $includeInactive = (bool) $r->boolean('include_inactive', false);

        return DB::table('attendance_days')
            ->join('users', 'users.id', '=', 'attendance_days.user_id')

            // <<< KEY LINE: only active users unless include_inactive=1 >>>
            ->when(!$includeInactive, fn ($x) => $x->where('users.active', 1))

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
            );
    }
}
