<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceEditorController extends Controller
{
    /**
     * Simple editor: choose a user & date range, list attendance_days,
     * and show a user dropdown (built from first/middle/last).
     */
    public function index(Request $request)
    {
        // Build a safe display name alias = "Last, First Middle"
        $userSelect = DB::table('users')
            ->select([
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,''))) as name"),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Optional filters
        $userId = $request->integer('user_id') ?: null;
        $from   = $request->date('from');
        $to     = $request->date('to');

        // Query attendance_days with joined user (for department + display name)
        $rows = DB::table('attendance_days as ad')
            ->join('users as u', 'u.id', '=', 'ad.user_id')
            ->when($userId, fn ($q) => $q->where('ad.user_id', $userId))
            ->when($from,   fn ($q) => $q->where('ad.work_date', '>=', $from->format('Y-m-d')))
            ->when($to,     fn ($q) => $q->where('ad.work_date', '<=', $to->format('Y-m-d')))
            ->orderByDesc('ad.work_date')
            ->select([
                'ad.*',
                'u.department',
                DB::raw("TRIM(CONCAT(u.last_name, ', ', u.first_name, ' ', COALESCE(u.middle_name,''))) as name"),
            ])
            ->paginate(25)
            ->withQueryString();

        return view('attendance.editor', [
            'users' => $userSelect,
            'rows'  => $rows,
            'filters' => [
                'user_id' => $userId,
                'from'    => $from?->format('Y-m-d'),
                'to'      => $to?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Example: save edits for a day (AM/PM times). Adjust to your needs.
     */
    public function update(Request $request, int $userId, string $workDate)
    {
        $data = $request->validate([
            'am_in'  => ['nullable','date'],
            'am_out' => ['nullable','date'],
            'pm_in'  => ['nullable','date'],
            'pm_out' => ['nullable','date'],
            'status' => ['nullable','string','max:50'],
        ]);

        DB::table('attendance_days')->updateOrInsert(
            ['user_id' => $userId, 'work_date' => $workDate],
            array_merge($data, ['updated_at' => now(), 'created_at' => now()])
        );

        return back()->with('status', 'Attendance updated.');
    }
}
