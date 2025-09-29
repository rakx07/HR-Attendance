<?php

// app/Exports/AttendanceExport.php
namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromQuery, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function query()
    {
        $r = (object)$this->filters;

        return DB::table('attendance_days')
            ->join('users','users.id','=','attendance_days.user_id')
            ->when(!empty($r->employee_id), fn($x)=>$x->where('users.id',$r->employee_id))
            ->when(!empty($r->status), fn($x)=>$x->where('status',$r->status))
            ->when(!empty($r->dept), fn($x)=>$x->where('users.department',$r->dept))
            ->when(!empty($r->from), fn($x)=>$x->where('work_date','>=',$r->from))
            ->when(!empty($r->to), fn($x)=>$x->where('work_date','<=',$r->to))
            ->select(
                'users.name','users.department','work_date',
                'am_in','am_out','pm_in','pm_out',
                'late_minutes','undertime_minutes','total_hours','status'
            )
            ->orderByDesc('work_date');
    }

    public function headings(): array
    {
        return ['Name','Department','Date','AM In','AM Out','PM In','PM Out','Late (min)','Undertime (min)','Hours','Status'];
    }
}

