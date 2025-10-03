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
    // app/Http/Controllers/AttendanceEditorController.php

public function index(Request $request)
{
    // Build picker list: "Last, First Middle"
    $users = DB::table('users')
        ->select([
            'id',
            DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,''))) AS name"),
        ])
        ->orderBy('last_name')->orderBy('first_name')
        ->get();

    $userId = (int) $request->query('user_id', 0) ?: null;
    $mode   = $request->query('range', 'day'); // day|week|month|custom
    $asof   = $request->query('date');         // anchor date for day/week/month
    $fromQ  = $request->query('from');
    $toQ    = $request->query('to');

    $from = $to = null;

    // Compute window
    if ($mode === 'custom' && $fromQ && $toQ) {
        $from = \Carbon\Carbon::parse($fromQ)->startOfDay();
        $to   = \Carbon\Carbon::parse($toQ)->endOfDay();
    } else {
        $anchor = $asof ? \Carbon\Carbon::parse($asof) : \Carbon\Carbon::today();
        switch ($mode) {
            case 'week':
                $from = $anchor->copy()->startOfWeek(); // Mon
                $to   = $anchor->copy()->endOfWeek();   // Sun
                break;
            case 'month':
                $from = $anchor->copy()->startOfMonth();
                $to   = $anchor->copy()->endOfMonth();
                break;
            case 'day':
            default:
                $from = $anchor->copy()->startOfDay();
                $to   = $anchor->copy()->endOfDay();
                break;
        }
    }

    $rows = collect(); // empty by default
    if ($userId) {
        $rows = DB::table('attendance_days as ad')
            ->where('ad.user_id', $userId)
            ->when($from, fn($q) => $q->where('ad.work_date', '>=', $from->toDateString()))
            ->when($to,   fn($q) => $q->where('ad.work_date', '<=', $to->toDateString()))
            ->orderByDesc('ad.work_date')       // newest first
            ->select([
                'ad.work_date','ad.am_in','ad.am_out','ad.pm_in','ad.pm_out',
                'ad.late_minutes','ad.undertime_minutes','ad.total_hours','ad.status',
            ])
            ->paginate(20)
            ->withQueryString();
    }

    return view('attendance.editor.index', [
        'users' => $users,
        'rows'  => $rows,
        'filters' => [
            'user_id' => $userId,
            'range'   => $mode,
            'date'    => $asof ?: now()->toDateString(),
            'from'    => $from?->toDateString(),
            'to'      => $to?->toDateString(),
        ],
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
