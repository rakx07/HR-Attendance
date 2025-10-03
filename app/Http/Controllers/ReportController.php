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
            ->orderByDesc('ad.work_date')
            ->paginate(50)
            ->withQueryString();

        return view('reports.attendance', ['rows' => $rows]);
    }

    public function export(Request $r)
    {
        $filename = 'attendance_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new AttendanceExport($r->all()), $filename);
    }

    /**
     * Build the common query for attendance reports.
     *
     * Query params:
     * - from, to:         Y-m-d
     * - dept:             department string (users.department)
     * - employee_id:      numeric user id
     * - status:           Present | Absent | Incomplete (or Holiday if show_holidays=1)
     * - include_inactive: 1 to include inactive users (default 0 = only active)
     * - show_holidays:    1 to include non-working holidays (no scans) labeled "Holiday"
     */
    protected function baseQuery(Request $r)
    {
        $employeeId      = $r->filled('employee_id') ? (int) $r->employee_id : null;
        $from            = $r->filled('from') ? $r->from : null;   // Y-m-d
        $to              = $r->filled('to')   ? $r->to   : null;   // Y-m-d
        $includeInactive = (bool) $r->boolean('include_inactive', false);
        $showHolidays    = (bool) $r->boolean('show_holidays', false);

        // Aliases:
        // ad = attendance_days, u = users, hc = holiday_calendars, hd = holiday_dates
        $q = DB::table('attendance_days as ad')
            ->join('users as u', 'u.id', '=', 'ad.user_id')

            // Only active users unless overridden
            ->when(!$includeInactive, fn ($x) => $x->where('u.active', 1))

            // Active holiday calendar for the year of the work_date
            ->leftJoin('holiday_calendars as hc', function ($j) {
                $j->on(DB::raw('YEAR(ad.work_date)'), '=', DB::raw('hc.year'))
                  ->where('hc.status', 'active');
            })
            // Join only non-working holiday entries for that exact date
            ->leftJoin('holiday_dates as hd', function ($j) {
                $j->on('hd.holiday_calendar_id', '=', 'hc.id')
                  ->on('hd.date', '=', 'ad.work_date')
                  ->where('hd.is_non_working', 1);
            })

            // Filters
            ->when($employeeId, fn ($x) => $x->where('u.id', $employeeId))
            ->when($r->filled('dept'), fn ($x) => $x->where('u.department', $r->dept))
            ->when($from, fn ($x) => $x->where('ad.work_date', '>=', $from))
            ->when($to,   fn ($x) => $x->where('ad.work_date', '<=', $to));

        // Status filter (if "Holiday" requested and show_holidays enabled)
        if ($r->filled('status')) {
            $status = $r->status;
            if ($status === 'Holiday' && $showHolidays) {
                // Holiday rows = on a non-working holiday AND no scans
                $q->whereNotNull('hd.id')
                  ->where(function ($noScan) {
                      $this->whereHasAnyScan($noScan, false);
                  });
            } else {
                // Normal status from attendance_days
                $q->where('ad.status', $status);
            }
        }

        // Exempt: by default, DON'T show non-working holidays with NO scans
        // (if show_holidays=1, keep them and label as Holiday)
        if (!$showHolidays) {
            $q->where(function ($w) {
                $w->whereNull('hd.id') // not a (non-working) holiday
                  ->orWhere(function ($h) {
                      // it's a holiday, but keep the row if there IS a scan
                      $this->whereHasAnyScan($h, true);
                  });
            });
        }

        // Selects (name, times, numbers, and a friendly status display)
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

            // Show "Holiday" when it's a non-working holiday with no scans AND show_holidays=1.
            DB::raw("
                CASE
                  WHEN (:showHolidays = 1) AND hd.id IS NOT NULL
                       AND ad.am_in IS NULL AND ad.am_out IS NULL
                       AND ad.pm_in IS NULL AND ad.pm_out IS NULL
                  THEN 'Holiday'
                  ELSE ad.status
                END as status
            "),
        ])->addBinding($showHolidays ? 1 : 0, 'select'); // bind :showHolidays

        return $q;
    }

    /**
     * Add conditions for "has scan" or "no scan" to a query group.
     *
     * @param \Illuminate\Database\Query\Builder $q
     * @param bool $wantHasScan  true = keep if any scan exists; false = keep only if no scans
     */
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
