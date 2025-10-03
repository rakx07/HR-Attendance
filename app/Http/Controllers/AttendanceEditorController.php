<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceEditorController extends Controller
{
    /**
     * GET /attendance/editor
     * Show the user/date picker.
     */
    public function index(Request $request)
    {
        // Build a safe display name = "Last, First Middle"
        $users = DB::table('users')
            ->select([
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,''))) AS name"),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('attendance.editor.index', [
            'users' => $users,
        ]);
    }

    /**
     * GET /attendance/editor/{user}/{date}
     * Show edit form for a specific user's day.
     */
    public function edit(int $user, string $date)
    {
        // Basic sanity check for date (Y-m-d)
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable $e) {
            abort(404);
        }

        $userRow = DB::table('users')
            ->where('id', $user)
            ->select([
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,''))) AS name"),
            ])
            ->first();

        abort_unless($userRow, 404);

        $row = DB::table('attendance_days')
            ->where('user_id', $user)
            ->where('work_date', $dateObj->toDateString())
            ->first();

        return view('attendance.editor.edit', [
            'user' => $userRow,
            'date' => $dateObj->toDateString(),
            'row'  => $row,
        ]);
    }

    /**
     * POST /attendance/editor/{user}/{date}
     * Save manual edits for a day.
     */
    public function update(Request $request, int $user, string $date)
    {
        $data = $request->validate([
            'am_in'  => ['nullable','date'],
            'am_out' => ['nullable','date'],
            'pm_in'  => ['nullable','date'],
            'pm_out' => ['nullable','date'],
            'status' => ['nullable','string','max:50'],
            // You can receive a "reason" field for auditing if desired
            'reason' => ['nullable','string','max:500'],
        ]);

        // Normalize date safely
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable $e) {
            abort(404);
        }

        // Upsert the attendance day record
        DB::table('attendance_days')->updateOrInsert(
            ['user_id' => $user, 'work_date' => $dateObj->toDateString()],
            [
                'am_in'   => $data['am_in']  ?? null,
                'am_out'  => $data['am_out'] ?? null,
                'pm_in'   => $data['pm_in']  ?? null,
                'pm_out'  => $data['pm_out'] ?? null,
                'status'  => $data['status'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Optional: write to an audit table if you have one
        // if (!empty($data['reason'])) {
        //     DB::table('attendance_adjustments')->insert([
        //         'user_id'    => $user,
        //         'work_date'  => $dateObj->toDateString(),
        //         'payload'    => json_encode($data),
        //         'reason'     => $data['reason'],
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ]);
        // }

        return back()->with('success', 'Attendance saved.');
    }
}
