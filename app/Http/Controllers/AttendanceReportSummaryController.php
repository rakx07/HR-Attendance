<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceSummaryExport; // ⬅ create in step 4

class AttendanceReportSummaryController extends Controller
{
    public function index(Request $req)
    {
        // perf guards (this request only)
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
        if (function_exists('set_time_limit')) @set_time_limit(0);
        DB::connection()->disableQueryLog();

        $from  = $req->input('from') ?: Carbon::now()->startOfMonth()->toDateString();
        $to    = $req->input('to')   ?: Carbon::now()->toDateString();
        $empId = $req->input('employee_id');
        $dept  = $req->input('dept');

        // Employees dropdown
        $employees = DB::table('users')
            ->select(
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ''))) AS name"),
                'department'
            )
            ->when($dept, fn($q) => $q->where('department', 'like', "%$dept%"))
            ->where('active', 1)
            ->orderBy('name')
            ->get();

        // Main list (web table) — paginate is OK for web
        $rows = DB::table('attendance_days as d')
            ->join('users as u','u.id','=','d.user_id')
            ->select([
                'd.user_id','d.work_date','d.am_in','d.am_out','d.pm_in','d.pm_out',
                'd.late_minutes','d.undertime_minutes','d.status',
                'u.shift_window_id',
                DB::raw("TRIM(CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))) AS name"),
                'u.department',
            ])
            ->when($from, fn($q)=>$q->where('d.work_date','>=',$from))
            ->when($to,   fn($q)=>$q->where('d.work_date','<=',$to))
            ->when($empId,fn($q)=>$q->where('d.user_id',$empId))
            ->when($dept, fn($q)=>$q->where('u.department','like',"%$dept%"))
            ->orderBy('d.work_date','desc')
            ->paginate(25)
            ->withQueryString();

        return view('reports.attendancereportsummary', [
            'employees'=>$employees,
            'rows'=>$rows,
        ]);
    }

    /** Raw logs for modal */
    public function raw(Request $req)
{
    $userId = (int) $req->query('user_id');
    $date   = $req->query('date');

    // 1) Get this employee’s mappings that exist in *your* schema
    $u = DB::table('users')
        ->select('id', 'zkteco_user_id', 'school_id')   // <- you have these columns
        ->where('id', $userId)
        ->first();

    if (!$u) {
        return response()->json(['rows' => []]);
    }

    // 2) Build a precise filter for this user only
    //    - Biotime: your import stores Biotime’s "person code" in attendance_raw.device_user_id,
    //               and you store your person code in users.school_id.
    //    - ZKTeco (if ever present): many installs write the device id into attendance_raw.user_id;
    //               fall back to that when source != 'biotime'.
    $q = DB::table('attendance_raw')
        ->select('id','punched_at','punch_type','source','device_sn','device_user_id','user_id')
        ->whereDate('punched_at', $date)
        ->where(function ($qq) use ($u) {
            // BIOTIME rows that belong to this user (device_user_id == school_id)
            if (!empty($u->school_id)) {
                $qq->orWhere(function ($q2) use ($u) {
                    $q2->where('source', 'biotime')
                       ->where('device_user_id', (string) $u->school_id);
                });
            }

            // NON-BIOTIME rows (e.g., zkteco) that belong to this user
            if (!empty($u->zkteco_user_id)) {
                $qq->orWhere(function ($q2) use ($u) {
                    $q2->where('source', '!=', 'biotime')
                       ->where('user_id', (int) $u->zkteco_user_id);
                });
            }

            // If your import already saved Laravel users.id into attendance_raw.user_id,
            // this keeps them too (harmless no-op otherwise).
            $qq->orWhere('user_id', (int) $u->id);
        });

    // Optional: keep only attendance punches if you store other events
    if (Schema::hasColumn('attendance_raw', 'punch_type')) {
        $q->where('punch_type', 15);
    }

    $rows = $q->orderBy('punched_at')->limit(1000)->get();

    return response()->json(['rows' => $rows]);
}


    /** Save/Upsert a correction into attendance_corrections (audit trail) */
    public function save(Request $req)
    {
        $v = Validator::make($req->all(), [
            'user_id'   => 'required|integer|exists:users,id',
            'work_date' => 'required|date',
            'am_in'     => 'nullable|date_format:H:i:s',
            'am_out'    => 'nullable|date_format:H:i:s',
            'pm_in'     => 'nullable|date_format:H:i:s',
            'pm_out'    => 'nullable|date_format:H:i:s',
            'remarks'   => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            return response()->json(['ok'=>false, 'message'=>$v->errors()->first()], 422);
        }

        $data = $v->validated();
        $uid  = (int)$data['user_id'];
        $date = $data['work_date'];

        $payload = [
            'am_in'     => $data['am_in'],
            'am_out'    => $data['am_out'],
            'pm_in'     => $data['pm_in'],
            'pm_out'    => $data['pm_out'],
            'remarks'   => $data['remarks'] ?? null,
            'edited_by' => auth()->id(),
            'updated_at'=> now(),
        ];

        $exists = DB::table('attendance_corrections')
            ->where('user_id',$uid)->where('work_date',$date)->exists();

        if ($exists) {
            DB::table('attendance_corrections')
                ->where('user_id',$uid)->where('work_date',$date)->update($payload);
        } else {
            DB::table('attendance_corrections')->insert(array_merge($payload, [
                'user_id'=>$uid, 'work_date'=>$date, 'created_at'=>now()
            ]));
        }

        return response()->json(['ok'=>true]);
    }

    /** PDF export — FIXED: same data as web, ordered by user_id, work_date; no pagination */
    public function pdf(Request $r)
    {
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '2048M');
        if (function_exists('set_time_limit')) @set_time_limit(0);
        DB::connection()->disableQueryLog();

        $from  = $r->input('from') ?: Carbon::now()->startOfMonth()->toDateString();
        $to    = $r->input('to')   ?: Carbon::now()->endOfMonth()->toDateString();
        $empId = $r->input('employee_id');
        $dept  = $r->input('dept');

        $q = DB::table('attendance_days as d')
            ->join('users as u','u.id','=','d.user_id')
            ->when($from,  fn($qq)=>$qq->where('d.work_date','>=',$from))
            ->when($to,    fn($qq)=>$qq->where('d.work_date','<=',$to))
            ->when($empId, fn($qq)=>$qq->where('d.user_id',$empId))
            ->when($dept,  fn($qq)=>$qq->where('u.department','like',"%{$dept}%"));

        // Count BEFORE any ordering/limits
        $totalRows = (clone $q)->count('d.user_id');

        // If you truly need a cap, keep it generous. Otherwise you can remove it.
        $maxRows   = (int)($r->input('max_rows') ?: 50000);
        $truncated = $totalRows > $maxRows;

        $rows = (clone $q)
            ->select([
                'd.user_id','d.work_date','d.am_in','d.am_out','d.pm_in','d.pm_out',
                'd.late_minutes','d.undertime_minutes','d.status',
                'u.shift_window_id',
                DB::raw("TRIM(CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))) AS name"),
                'u.department',
            ])
            // IMPORTANT: employee-first, date ASC so Blade grouping works
            ->orderBy('d.user_id')
            ->orderBy('d.work_date')
            ->when($truncated, fn($qq)=>$qq->limit($maxRows))
            ->get();

        if ($r->boolean('debug')) {
            return view('reports.attendance_summary_pdf', compact(
                'rows','from','to','empId','dept','truncated','totalRows','maxRows'
            ));
        }

        $pdf = Pdf::loadView('reports.attendance_summary_pdf', compact(
                'rows','from','to','empId','dept','truncated','totalRows','maxRows'
            ))
            ->setPaper('letter','portrait');

        if (method_exists($pdf, 'setOption')) {
            $pdf->setOption('dpi', 72);
            $pdf->setOption('isRemoteEnabled', false);
            $pdf->setOption('enable_font_subsetting', true);
        }

        return $pdf->stream('attendance_summary_'.now()->format('Ymd_His').'.pdf');
    }
    public function excel(Request $r)
{
    // performance guards like your PDF
    @ini_set('max_execution_time', '0');
    @ini_set('memory_limit', '2048M');
    if (function_exists('set_time_limit')) @set_time_limit(0);
    DB::connection()->disableQueryLog();

    $filters = $r->only(['from','to','employee_id','dept']);
    $filename = 'attendance_summary_' . now()->format('Ymd_His') . '.xlsx';

    return Excel::download(new AttendanceSummaryExport($filters), $filename);
}
}
