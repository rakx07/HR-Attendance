<?php

// app/Http/Controllers/AttendanceEditorController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceEditorController extends Controller
{
    public function index(Request $r)
    {
        $users = User::orderBy('name')->get(['id','name']);
        return view('attendance.editor.index', ['users'=>$users]);
    }

    public function edit(User $user, $date)
    {
        $row = DB::table('attendance_days')->where('user_id',$user->id)->where('work_date',$date)->first();
        return view('attendance.editor.edit', ['user'=>$user,'date'=>$date,'row'=>$row]);
    }

    public function update(Request $r, User $user, $date)
    {
        $data = $r->validate([
            'am_in'  => 'nullable|date',
            'am_out' => 'nullable|date',
            'pm_in'  => 'nullable|date',
            'pm_out' => 'nullable|date',
            'reason' => 'nullable|string|max:255',
        ]);

        $current = DB::table('attendance_days')->where('user_id',$user->id)->where('work_date',$date)->first();

        // Audit each changed field
        foreach (['am_in','am_out','pm_in','pm_out'] as $f) {
            $new = $data[$f] ?? null;
            $old = $current?->$f;
            if ($new != $old) {
                DB::table('attendance_adjustments')->insert([
                    'user_id'=>$user->id,'work_date'=>$date,'field'=>$f,
                    'old_value'=>$old,'new_value'=>$new,
                    'edited_by'=>Auth::id(),'reason'=>$data['reason'] ?? null,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        }

        // Save day record (recompute totals/late/under quickly)
        $amIn = $data['am_in'] ? Carbon::parse($data['am_in']) : null;
        $amOut= $data['am_out']? Carbon::parse($data['am_out']): null;
        $pmIn = $data['pm_in'] ? Carbon::parse($data['pm_in']) : null;
        $pmOut= $data['pm_out']? Carbon::parse($data['pm_out']): null;

        $mins = 0;
        if($amIn && $amOut) $mins += $amIn->diffInMinutes($amOut);
        if($pmIn && $pmOut) $mins += $pmIn->diffInMinutes($pmOut);
        $hours = min(round($mins/60,2), (float)config('attendance.cap_hours_per_day',8));

        $status = (!$amIn && !$pmOut) ? 'Absent' : ($amIn && !$pmOut ? 'Incomplete' : 'Present');

        DB::table('attendance_days')->updateOrInsert(
            ['user_id'=>$user->id,'work_date'=>$date],
            [
                'am_in'=>$amIn,'am_out'=>$amOut,'pm_in'=>$pmIn,'pm_out'=>$pmOut,
                'late_minutes'=>0, // (optional) compute against shift windows if needed
                'undertime_minutes'=>0,
                'total_hours'=>$hours,'status'=>$status,
                'updated_at'=>now(),'created_at'=>now()
            ]
        );

        return back()->with('success','Attendance updated with audit trail.');
    }
}
