<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

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

        // Main list (NOTE: we intentionally DO NOT select d.total_hours; we recompute in Blade)
        $rows = DB::table('attendance_days as d')
            ->join('users as u','u.id','=','d.user_id')
            ->select([
                'd.user_id','d.work_date','d.am_in','d.am_out','d.pm_in','d.pm_out',
                'd.late_minutes','d.undertime_minutes','d.status', // kept for reference; not used for totals
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
        $userId = (int)$req->query('user_id');
        $date   = $req->query('date');

        $rows = DB::table('attendance_raw')
            ->select('id','punched_at','punch_type','source','device_sn')
            ->where('user_id', $userId)
            ->whereDate('punched_at', $date)
            ->orderBy('punched_at')
            ->limit(200)
            ->get();

        return response()->json(['rows'=>$rows]);
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

        // recomputation of totals stays in your existing endpoints / nightly jobs
        return response()->json(['ok'=>true]);
    }

    /** PDF export (we still avoid using DB total_hours; the Blade recomputes) */
    public function pdf(Request $r)
    {
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '2048M');
        if (function_exists('set_time_limit')) @set_time_limit(0);
        DB::connection()->disableQueryLog();

        $from  = $r->input('from');
        $to    = $r->input('to');
        $empId = $r->input('employee_id');
        $dept  = $r->input('dept');

        $q = DB::table('attendance_days as d')
            ->join('users as u','u.id','=','d.user_id')
            ->when($from,  fn($qq)=>$qq->where('d.work_date','>=',$from))
            ->when($to,    fn($qq)=>$qq->where('d.work_date','<=',$to))
            ->when($empId, fn($qq)=>$qq->where('d.user_id',$empId))
            ->when($dept,  fn($qq)=>$qq->where('u.department','like',"%{$dept}%"));

        $totalRows = (clone $q)->count('d.user_id');
        $maxRows   = (int)($r->input('max_rows') ?: 1500);
        $truncated = $totalRows > $maxRows;

        $rows = (clone $q)
            ->select([
                'd.user_id','d.work_date','d.am_in','d.am_out','d.pm_in','d.pm_out',
                'd.late_minutes','d.undertime_minutes','d.status',
                'u.shift_window_id',
                DB::raw("TRIM(CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name, ''))) AS name"),
                'u.department',
            ])
            ->orderBy('d.work_date','desc')
            ->orderBy('u.last_name','asc')
            ->orderBy('u.first_name','asc')
            ->limit($maxRows)
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
}
