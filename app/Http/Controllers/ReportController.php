<?php

// app/Http/Controllers/ReportController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceExport;

class ReportController extends Controller
{
    public function index(Request $r)
    {
        $q = DB::table('attendance_days')
            ->join('users','users.id','=','attendance_days.user_id')
            ->when($r->filled('employee_id'), fn($x)=>$x->where('users.id',$r->employee_id))
            ->when($r->filled('status'), fn($x)=>$x->where('status',$r->status))
            ->when($r->filled('dept'), fn($x)=>$x->where('users.department',$r->dept))
            ->when($r->filled('from'), fn($x)=>$x->where('work_date','>=',$r->from))
            ->when($r->filled('to'), fn($x)=>$x->where('work_date','<=',$r->to))
            ->select(
                'users.id as user_id','users.name','users.department',
                'work_date','am_in','am_out','pm_in','pm_out',
                'late_minutes','undertime_minutes','total_hours','status'
            )
            ->orderByDesc('work_date');

        return view('reports.attendance', [
            'rows' => $q->paginate(50)->withQueryString()
        ]);
    }

    public function export(Request $r)
    {
        $filename = 'attendance_'.now()->format('Ymd_His').'.xlsx';
        return Excel::download(new AttendanceExport($r->all()), $filename);
    }
}

