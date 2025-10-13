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
        $employees = DB::table('users')
            ->select(
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ''))) AS name"),
                'department'
            )
            ->where('active', 1)
            ->orderBy('name')
            ->get();

        $sort = $r->input('sort', 'date');                 // 'date' | 'name'
        $defaultDir = $sort === 'name' ? 'asc' : 'desc';
        $dir  = strtolower($r->input('dir', $defaultDir)); // 'asc' | 'desc'
        $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

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
        // --- Lift limits for heavy renders (THIS REQUEST ONLY) ---
        @ini_set('max_execution_time', '0'); // 0 = unlimited for this request
        @ini_set('memory_limit', '1024M');   // raise if needed
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);              // also remove SAPI time limit
        }
        DB::connection()->disableQueryLog(); // don’t store all queries in RAM

        // Optional sort
        $sort = $r->input('sort', 'date');
        $defaultDir = $sort === 'name' ? 'asc' : 'desc';
        $dir  = strtolower($r->input('dir', $defaultDir));
        $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

        // Build query (use your stricter PDF variant if you prefer)
        $q = $this->baseQuery($r);
        $q = $this->applySort($q, $sort, $dir);

        $rows = $q->get();

        $pdf = Pdf::loadView('reports.attendance_pdf', [
            'rows'      => $rows,
            'filters'   => $r->all(),
            'truncated' => false,
            'totalRows' => $rows->count(),
            'maxRows'   => null,
        ])->setPaper('letter', 'portrait');

        // keep output light
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

        if ($employeeId) {
            $q->where('u.id', $employeeId);
        } else {
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

        if ($r->filled('dept'))        $q->where('u.department', $r->dept);
        if ($from)                     $q->where('ad.work_date', '>=', $from);
        if ($to)                       $q->where('ad.work_date', '<=', $to);

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

    protected function applySort($q, string $sort, string $dir)
    {
        if ($sort === 'name') {
            return $q->orderBy('u.department')
                     ->orderBy('u.last_name', $dir)
                     ->orderBy('u.first_name', $dir)
                     ->orderBy('u.middle_name', $dir)
                     ->orderBy('ad.work_date', 'desc');
        }

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
