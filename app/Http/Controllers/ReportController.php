<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

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

        // Sort controls
        $sort = $r->input('sort', 'date');                 // 'date' | 'name'
        $defaultDir = $sort === 'name' ? 'asc' : 'desc';
        $dir  = strtolower($r->input('dir', $defaultDir)); // 'asc' | 'desc'
        $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

        // Build query
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
        // Lift limits for heavy renders (THIS REQUEST ONLY)
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
        if (function_exists('set_time_limit')) @set_time_limit(0);
        DB::connection()->disableQueryLog();

        // Sort
        $sort = $r->input('sort', 'date');
        $defaultDir = $sort === 'name' ? 'asc' : 'desc';
        $dir  = strtolower($r->input('dir', $defaultDir));
        $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

        // Query
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

        if (method_exists($pdf, 'setOption')) $pdf->setOption('dpi', 72);

        return $pdf->stream('attendance_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Core query for listing/printing.
     * Includes u.shift_window_id (needed by Blade for No-Duty logic)
     * and derives "Holiday" when show_holidays=true and no scans exist.
     */
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

        // Scope by employee or by users who have logs in range
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

        // Additional filters
        if ($r->filled('dept')) $q->where('u.department', $r->dept);
        if ($from)              $q->where('ad.work_date', '>=', $from);
        if ($to)                $q->where('ad.work_date', '<=', $to);

        // Status filter
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

        // By default, hide blank non-working holidays unless explicitly requested
        if (!$showHolidays) {
            $q->where(function ($w) {
                $w->whereNull('hd.id')
                  ->orWhere(function ($h) {
                      $this->whereHasAnyScan($h, true);
                  });
            });
        }

        // SELECTS (include shift_window_id)
        $q->select([
            'u.id as user_id',
            'u.shift_window_id',
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

    /* ===================== RAW LOGS API (for the modal) ===================== */

    public function raw(Request $r): JsonResponse
    {
        $v = $r->validate([
            'user_id'  => ['required','integer'],
            'date'     => ['required','date_format:Y-m-d'],
            'page'     => ['sometimes','integer','min:1'],
            'per_page' => ['sometimes','integer','min:1','max:100'],
        ]);

        $userId  = (int) $v['user_id'];
        $date    = $v['date'];
        $page    = (int) $r->input('page', 1);
        $perPage = (int) $r->input('per_page', 25);

        // Your users table has zkteco_user_id (per screenshots). school_id may also exist.
        $user = DB::table('users')
            ->select(['id', 'zkteco_user_id', 'school_id'])
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'rows' => [],
                'meta' => ['current_page'=>1,'last_page'=>1,'per_page'=>$perPage,'total'=>0,'prev'=>null,'next'=>null],
                'error' => 'User not found',
            ], 404);
        }

        $zktecoId = $user->zkteco_user_id ? (string)$user->zkteco_user_id : null;
        $schoolId = $user->school_id ? (string)$user->school_id : null;

        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        $q = DB::table('attendance_raw')
            ->whereBetween('punched_at', [$start, $end])
            ->orderBy('punched_at', 'asc');

        // Match by multiple identifiers (robust for different importers)
        $q->where(function ($w) use ($userId, $zktecoId, $schoolId) {
            $w->where('user_id', $userId);

            if (!empty($zktecoId)) {
                $w->orWhere('device_user_id', $zktecoId);

                $needle = str_replace(['%','_'], ['\%','\_'], $zktecoId);
                $w->orWhere('payload', 'LIKE', '%"emp_code":"'.$needle.'"%')
                  ->orWhere('payload', 'LIKE', '%"emp_id":"'.$needle.'"%')
                  ->orWhere('payload', 'LIKE', '%"emp_id":'.$needle.'%');
            }

            if (!empty($schoolId)) {
                $needle = str_replace(['%','_'], ['\%','\_'], $schoolId);
                $w->orWhere('payload', 'LIKE', '%"school_id":"'.$needle.'"%');
            }
        });

        try {
            $p = $q->paginate(
                $perPage,
                ['id','punched_at','punch_type','source','device_sn','payload'],
                'page',
                $page
            );
        } catch (\Throwable $e) {
            \Log::error('attendance_raw query failed', ['err'=>$e->getMessage()]);
            return response()->json([
                'rows' => [],
                'meta' => ['current_page'=>1,'last_page'=>1,'per_page'=>$perPage,'total'=>0,'prev'=>null,'next'=>null],
                'error' => 'Query failed',
            ], 500);
        }

        $rows = collect($p->items())->map(function ($row) {
            return [
                'id'         => $row->id,
                'punched_at' => Carbon::parse($row->punched_at)->format('Y-m-d H:i:s'),
            ];
        })->values();

        $append = ['user_id'=>$userId, 'date'=>$date, 'per_page'=>$perPage];
        $prev   = $p->previousPageUrl() ? $p->appends($append)->previousPageUrl() : null;
        $next   = $p->nextPageUrl()     ? $p->appends($append)->nextPageUrl()     : null;

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'prev'         => $prev,
                'next'         => $next,
            ],
        ]);
    }

    public function rawUpdate(Request $r): JsonResponse
    {
        // Placeholder for future raw-log edits (not used by the current UI)
        return response()->json(['ok' => true]);
    }

    /** Fetch a day’s consolidated values for the modal (prefill). */
    public function day(Request $r): JsonResponse
    {
        $v = $r->validate([
            'user_id' => ['required','integer'],
            'date'    => ['required','date_format:Y-m-d'],
        ]);

        $rec = DB::table('attendance_days')
            ->where('user_id', $v['user_id'])
            ->where('work_date', $v['date'])
            ->first();

        $day = [
            'user_id' => (int)$v['user_id'],
            'work_date' => $v['date'],
            'am_in' => $rec->am_in ?? null,
            'am_out'=> $rec->am_out ?? null,
            'pm_in' => $rec->pm_in ?? null,
            'pm_out'=> $rec->pm_out ?? null,
            'late_minutes' => (int)($rec->late_minutes ?? 0),
            'undertime_minutes' => (int)($rec->undertime_minutes ?? 0),
            'total_hours' => (float)($rec->total_hours ?? 0),
            'status' => $rec->status ?? '',
        ];

        return response()->json(['day'=>$day]);
    }

    /**
     * Save consolidated AM/PM times and RECOMPUTE late / undertime / hours.
     * This is what keeps the table + PDF aligned with edits from the modal.
     */
    public function dayUpdate(Request $r): \Illuminate\Http\JsonResponse
        {
            $v = $r->validate([
                'user_id' => ['required','integer'],
                'date'    => ['required','date_format:Y-m-d'],
                'am_in'   => ['nullable','date_format:H:i:s'],
                'am_out'  => ['nullable','date_format:H:i:s'],
                'pm_in'   => ['nullable','date_format:H:i:s'],
                'pm_out'  => ['nullable','date_format:H:i:s'],
                'remarks' => ['nullable','string','max:1000'],
            ]);

            $userId  = (int)$v['user_id'];
            $date    = $v['date'];
            $remarks = $v['remarks'] ?? null;

            // Pull shift + grace + schedule
            $user    = DB::table('users')->select('shift_window_id')->where('id',$userId)->first();
            $shiftId = (int)($user->shift_window_id ?? 0);
            $grace   = (int)DB::table('shift_windows')->where('id',$shiftId)->value('grace_minutes');

            $dow0 = \Carbon\Carbon::parse($date)->dayOfWeek;
            $rowDay = DB::table('shift_window_days')
                ->where('shift_window_id',$shiftId)
                ->where(function($w) use($dow0){
                    $w->where('dow', $dow0 === \Carbon\Carbon::SUNDAY ? 7 : $dow0)
                    ->orWhere('dow', $dow0);
                })
                ->first();

            $isWorking = $rowDay ? (int)($rowDay->is_working ?? 1) : ($dow0===\Carbon\Carbon::SUNDAY ? 0 : 1);
            $sched = [
                'work'  => $isWorking,
                'am_in' => $rowDay->am_in ?? null,
                'am_out'=> $rowDay->am_out ?? null,
                'pm_in' => $rowDay->pm_in ?? null,
                'pm_out'=> $rowDay->pm_out ?? null,
            ];

            // Build full timestamps from date + time
            $stamp = fn($t) => $t ? "{$date} {$t}" : null;
            $am_in  = $stamp($v['am_in']  ?? null);
            $am_out = $stamp($v['am_out'] ?? null);
            $pm_in  = $stamp($v['pm_in']  ?? null);
            $pm_out = $stamp($v['pm_out'] ?? null);

            // Hours = earliest IN → latest OUT minus lunch overlap (in minutes)
            $firstIn = $am_in ?: $pm_in;
            $lastOut = $pm_out ?: $am_out;

            $hours = 0.0;
            if ($firstIn && $lastOut && \Carbon\Carbon::parse($lastOut)->gt(\Carbon\Carbon::parse($firstIn))) {
                $mins = \Carbon\Carbon::parse($lastOut)->diffInMinutes(\Carbon\Carbon::parse($firstIn));

                if ($sched['am_out'] && $sched['pm_in']) {
                    $ls = \Carbon\Carbon::parse("$date {$sched['am_out']}");
                    $le = \Carbon\Carbon::parse("$date {$sched['pm_in']}");
                    $ovSec = max(0, min(strtotime($lastOut), $le->timestamp) - max(strtotime($firstIn), $ls->timestamp));
                    $ovMin = (int) floor($ovSec / 60);
                    $mins  = max(0, $mins - $ovMin);
                }

                $hours = max(0, round($mins/60, 2));
            }

            // Late / Undertime
            $late = 0;
            if ($sched['work']) {
                if ($am_in && $sched['am_in']) {
                    $schedIn = \Carbon\Carbon::parse("$date {$sched['am_in']}")->addMinutes($grace);
                    $late += max(0, \Carbon\Carbon::parse($am_in)->diffInMinutes($schedIn, false));
                }
                if ($pm_in && $sched['pm_in']) {
                    $schedIn = \Carbon\Carbon::parse("$date {$sched['pm_in']}")->addMinutes($grace);
                    $late += max(0, \Carbon\Carbon::parse($pm_in)->diffInMinutes($schedIn, false));
                }
            }

            $under = 0;
            if ($sched['work'] && $pm_out && $sched['pm_out']) {
                $schedOut = \Carbon\Carbon::parse("$date {$sched['pm_out']}");
                $under = max(0, $schedOut->diffInMinutes(\Carbon\Carbon::parse($pm_out), false));
            }

            // Status
            $hasAny = ($am_in || $am_out || $pm_in || $pm_out);
            $status = $hasAny ? 'Present' : ($sched['work'] ? 'Absent' : 'No Duty');

            // Upsert attendance_days
            $payload = [
                'user_id'            => $userId,
                'work_date'          => $date,
                'am_in'              => $am_in,
                'am_out'             => $am_out,
                'pm_in'              => $pm_in,
                'pm_out'             => $pm_out,
                'late_minutes'       => (int)$late,
                'undertime_minutes'  => (int)$under,
                'total_hours'        => (float)$hours,
                'status'             => $status,
                'updated_at'         => now(),
            ];

            $exists = DB::table('attendance_days')->where('user_id',$userId)->where('work_date',$date)->exists();
            if ($exists) {
                DB::table('attendance_days')->where('user_id',$userId)->where('work_date',$date)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('attendance_days')->insert($payload);
            }

            // ---- AUDIT: attendance_corrections (one row per user/date) ----
            $audit = [
                'am_in'     => $v['am_in']  ?? null,
                'am_out'    => $v['am_out'] ?? null,
                'pm_in'     => $v['pm_in']  ?? null,
                'pm_out'    => $v['pm_out'] ?? null,
                'remarks'   => $remarks,
                'edited_by' => auth()->id(),
                'updated_at'=> now(),
            ];

            $hasAudit = DB::table('attendance_corrections')->where('user_id',$userId)->where('work_date',$date)->exists();
            if ($hasAudit) {
                DB::table('attendance_corrections')
                    ->where('user_id',$userId)->where('work_date',$date)
                    ->update($audit);
            } else {
                DB::table('attendance_corrections')->insert(array_merge($audit, [
                    'user_id'   => $userId,
                    'work_date' => $date,
                    'created_at'=> now(),
                ]));
            }

            return response()->json(['ok'=>true, 'day'=>$payload]);
        }


    public function summary(Request $r)
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

    // Sorting (reuse same style as index)
    $sort = $r->input('sort', 'date');
    $defaultDir = $sort === 'name' ? 'asc' : 'desc';
    $dir  = strtolower($r->input('dir', $defaultDir));
    $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

    // Query (reuse your baseQuery + applySort)
    $q = $this->baseQuery($r);
    $q = $this->applySort($q, $sort, $dir);

    $rows = $q->paginate(25)->withQueryString();

    return view('reports.attendancereportsummary', [
        'rows'      => $rows,
        'employees' => $employees,
    ]);
}

/** Optional: dedicated PDF for the Summary page (reuses your pdf logic) */
public function summaryPdf(Request $r)
{
    // Reuse your existing pdf() logic but a different view/filename if you like
    $sort = $r->input('sort', 'date');
    $defaultDir = $sort === 'name' ? 'asc' : 'desc';
    $dir  = strtolower($r->input('dir', $defaultDir));
    $dir  = in_array($dir, ['asc','desc']) ? $dir : $defaultDir;

    $q = $this->baseQuery($r);
    $q = $this->applySort($q, $sort, $dir);
    $rows = $q->get();

    $pdf = Pdf::loadView('reports.attendance_summary_pdf', [
        'rows'    => $rows,
        'filters' => $r->all(),
    ])->setPaper('letter', 'portrait');

    return $pdf->stream('attendance_summary_' . now()->format('Ymd_His') . '.pdf');
}

}
