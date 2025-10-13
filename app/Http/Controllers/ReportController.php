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

        // --- read & sanitize sort params ---
        $sort = $r->input('sort', 'date');                 // 'date' | 'name'
        $defaultDir = $sort === 'name' ? 'asc' : 'desc';
        $dir  = strtolower($r->input('dir', $defaultDir)); // 'asc' | 'desc'
        $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

        // Build base query then apply sort dynamically
        $q = $this->baseQuery($r);
        $q = $this->applySort($q, $sort, $dir);

        $rows = $q->paginate(50)->withQueryString();

        return view('reports.attendance', [
            'rows'      => $rows,
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
        // Raise memory for this render (process only)
        @ini_set('memory_limit', '512M');

        // Read sort (optional)
        $sort = $r->input('sort', 'date');
        $defaultDir = $sort === 'name' ? 'asc' : 'desc';
        $dir  = strtolower($r->input('dir', $defaultDir));
        $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

        // Build query with STRICT date filters for PDF
        $q = $this->baseQueryForPdf($r);
        $q = $this->applySort($q, $sort, $dir);

        // Guard: cap rows to avoid OOM in DomPDF
        $MAX_ROWS = 5000; // tune to your server
        $total = (clone $q)->count();
        $truncated = false;
        if ($total > $MAX_ROWS) {
            $truncated = true;
            $q->limit($MAX_ROWS);
        }

        $rows = $q->get();

        $pdf = Pdf::loadView('reports.attendance_pdf', [
            'rows'      => $rows,
            'filters'   => $r->all(),
            'truncated' => $truncated,
            'totalRows' => $total,
            'maxRows'   => $MAX_ROWS,
        ])->setPaper('letter', 'portrait');

        // Lower DPI to reduce memory footprint (requires barryvdh/laravel-dompdf >= 2.x)
        $pdf->setOption('dpi', 72);

        return $pdf->stream('attendance_' . now()->format('Ymd_His') . '.pdf');
    }

    protected function baseQuery(Request $r)
    {
        $mode            = $r->input('mode', 'all_active');
        $employeeIdParam = $r->filled('employee_id') ? (int) $r->employee_id : null;
        $employeeId      = ($mode === 'employee') ? $employeeIdParam : null;

        $from            = $r->filled('from') ? $r->from : null;
        $to              = $r->filled('to')   ? $r->to   : null;
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

    /**
     * STRICT date filtering for PDF to prevent out-of-range rows
     * and reduce HTML size for DomPDF.
     */
    protected function baseQueryForPdf(Request $r)
    {
        $mode            = $r->input('mode', 'all_active');
        $employeeIdParam = $r->filled('employee_id') ? (int) $r->employee_id : null;
        $employeeId      = ($mode === 'employee') ? $employeeIdParam : null;

        $from            = $r->filled('from') ? $r->input('from') : null; // Y-m-d
        $to              = $r->filled('to')   ? $r->input('to')   : null; // Y-m-d
        $includeInactive = (bool) $r->boolean('include_inactive', false);
        $showHolidays    = (bool) $r->boolean('show_holidays', false);
        $flag            = $showHolidays ? 1 : 0;

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
            $q->where('u.id', $employeeId);
        } else {
            if ($from || $to) {
                $q->whereExists(function ($sub) use ($from, $to) {
                    $sub->select(DB::raw(1))
                        ->from('attendance_days as ad2')
                        ->whereColumn('ad2.user_id', 'u.id')
                        ->when($from, fn($s) => $s->whereDate('ad2.work_date', '>=', $from))
                        ->when($to,   fn($s) => $s->whereDate('ad2.work_date', '<=', $to));
                });
            }
        }

        // Filters
        if ($r->filled('dept')) {
            $q->where('u.department', $r->dept);
        }
        // STRICT date filters
        if ($from) $q->whereDate('ad.work_date', '>=', $from);
        if ($to)   $q->whereDate('ad.work_date', '<=', $to);

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

        if (!$showHolidays) {
            $q->where(function ($w) {
                $w->whereNull('hd.id')
                  ->orWhere(function ($h) {
                      $this->whereHasAnyScan($h, true);
                  });
            });
        }

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

        if ($mode !== 'employee') {
            $q->whereNotNull('ad.work_date');
        }

        return $q;
    }

    /**
     * Apply dynamic sorting to the query.
     *
     * @param \Illuminate\Database\Query\Builder $q
     * @param string $sort 'date'|'name'
     * @param string $dir  'asc'|'desc'
     * @return \Illuminate\Database\Query\Builder
     */
    protected function applySort($q, string $sort, string $dir)
    {
        if ($sort === 'name') {
            // Group by department; then alphabetical by last/first/middle.
            return $q->orderBy('u.department')
                     ->orderBy('u.last_name', $dir)
                     ->orderBy('u.first_name', $dir)
                     ->orderBy('u.middle_name', $dir)
                     ->orderBy('ad.work_date', 'desc'); // stable secondary
        }

        // Default: date sort; then stable by name
        return $q->orderBy('u.department')
                 ->orderBy('ad.work_date', $dir)
                 ->orderBy('u.last_name', 'asc')
                 ->orderBy('u.first_name', 'asc');
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
