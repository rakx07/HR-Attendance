<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceExport;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(Request $r)
    {
        // Employees dropdown (active by default)
        $employees = DB::table('users')
            ->select(
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ''))) AS name"),
                'department'
            )
            ->where('active', 1)
            ->orderBy('name')
            ->get();

        $rows = $this->baseQuery($r)
            ->orderBy('u.department')
            ->orderBy('u.id')
            ->orderByDesc('ad.work_date')
            ->paginate(50)
            ->withQueryString();

        return view('reports.attendance', [
            'rows' => $rows,
            'employees' => $employees,
        ]);
    }

    public function export(Request $r)
    {
        $filename = 'attendance_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new AttendanceExport($r->all()), $filename);
    }

    public function pdf(Request $r)
    {
        $rows = $this->baseQuery($r)
            ->orderBy('u.department')
            ->orderBy('u.id')
            ->orderBy('ad.work_date')
            ->get();

        $pdf = Pdf::loadView('reports.attendance_pdf', [
            'rows'    => $rows,
            'filters' => $r->all(),
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('attendance_' . now()->format('Ymd_His') . '.pdf');
    }

    protected function baseQuery(Request $r)
    {
        $mode            = $r->input('mode', 'all_active'); // <-- NEW: respect mode
        $employeeIdParam = $r->filled('employee_id') ? (int) $r->employee_id : null;
        $employeeId      = ($mode === 'employee') ? $employeeIdParam : null; // <-- ONLY when mode=employee

        $from            = $r->filled('from') ? $r->from : null;   // Y-m-d
        $to              = $r->filled('to')   ? $r->to   : null;   // Y-m-d
        $includeInactive = (bool) $r->boolean('include_inactive', false);
        $showHolidays    = (bool) $r->boolean('show_holidays', false);
        $flag            = $showHolidays ? 1 : 0;

        // Use LEFT JOIN so we can list all users with rows in range
        $q = DB::table('users as u')
            ->leftJoin('attendance_days as ad', function ($j) {
                $j->on('u.id', '=', 'ad.user_id');
            })
            ->leftJoin('holiday_calendars as hc', function ($j) {
                $j->on(DB::raw('YEAR(ad.work_date)'), '=', DB::raw('hc.year'))
                  ->where('hc.status', 'active');
            })
            ->leftJoin('holiday_dates as hd', function ($j) {
                $j->on('hd.holiday_calendar_id', '=', 'hc.id')
                  ->on('hd.date', '=', 'ad.work_date')
                  ->where('hd.is_non_working', 1);
            })
            ->when(!$includeInactive, fn($x) => $x->where('u.active', 1));

        // Mode handling
        if ($employeeId) {
            // Single employee
            $q->where('u.id', $employeeId);
        } else {
            // All active: ensure we only include users who have logs in the date window
            if ($from || $to) {
                $q->whereExists(function ($sub) use ($from, $to) {
                    $sub->select(DB::raw(1))
                        ->from('attendance_days as ad2')
                        ->whereColumn('ad2.user_id', 'u.id')
                        ->when($from, fn($s) => $s->where('ad2.work_date', '>=', $from))
                        ->when($to,   fn($s) => $s->where('ad2.work_date', '<=', $to));
                });
            }
        }

        // Filters
        if ($r->filled('dept')) {
            $q->where('u.department', $r->dept);
        }
        if ($from) {
            $q->where('ad.work_date', '>=', $from);
        }
        if ($to) {
            $q->where('ad.work_date', '<=', $to);
        }

        // Status filter (Holiday or normal)
        if ($r->filled('status')) {
            $status = $r->status;
            if ($status === 'Holiday' && $showHolidays) {
                $q->whereNotNull('hd.id')
                  ->where(function ($noScan) {
                      $this->whereHasAnyScan($noScan, false);
                  });
            } else {
                $q->where('ad.status', $status);
            }
        }

        // By default, hide blank non-working holidays
        if (!$showHolidays) {
            $q->where(function ($w) {
                $w->whereNull('hd.id')
                  ->orWhere(function ($h) {
                      $this->whereHasAnyScan($h, true);
                  });
            });
        }

        // Selects
        $q->select([
            'u.id as user_id',
            DB::raw("TRIM(CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))) as name"),
            'u.department',
            'ad.work_date',
            'ad.am_in',
            'ad.am_out',
            'ad.pm_in',
            'ad.pm_out',
            'ad.late_minutes',
            'ad.undertime_minutes',
            'ad.total_hours',
            DB::raw("
                CASE
                  WHEN ($flag = 1)
                       AND hd.id IS NOT NULL
                       AND ad.am_in IS NULL AND ad.am_out IS NULL
                       AND ad.pm_in IS NULL AND ad.pm_out IS NULL
                  THEN 'Holiday'
                  ELSE ad.status
                END AS status
            "),
        ]);

        // In All Active mode, avoid returning users with no rows at all
        if ($mode !== 'employee') {
            $q->whereNotNull('ad.work_date');
        }

        return $q;
    }

    private function whereHasAnyScan($q, bool $wantHasScan): void
    {
        if ($wantHasScan) {
            $q->whereNotNull('ad.am_in')
              ->orWhereNotNull('ad.am_out')
              ->orWhereNotNull('ad.pm_in')
              ->orWhereNotNull('ad.pm_out');
        } else {
            $q->whereNull('ad.am_in')
              ->whereNull('ad.am_out')
              ->whereNull('ad.pm_in')
              ->whereNull('ad.pm_out');
        }
    }
}
