<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class AttendanceEditorController extends Controller
{
    /**
     * GET /attendance/editor
     * Show the user/range picker + table (newest first).
     */
    public function index(Request $request)
    {
        $users = DB::table('users')
            ->select([
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,''))) AS name"),
            ])
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $userId = (int) $request->query('user_id', 0) ?: null;
        $mode   = $request->query('range', 'day'); // day|week|month|custom
        $asof   = $request->query('date');
        $fromQ  = $request->query('from');
        $toQ    = $request->query('to');

        $from = $to = null;
        if ($mode === 'custom' && $fromQ && $toQ) {
            $from = CarbonImmutable::parse($fromQ)->startOfDay();
            $to   = CarbonImmutable::parse($toQ)->endOfDay();
        } else {
            $anchor = $asof ? CarbonImmutable::parse($asof) : CarbonImmutable::today();
            switch ($mode) {
                case 'week':
                    $from = $anchor->startOfWeek();
                    $to   = $anchor->endOfWeek();
                    break;
                case 'month':
                    $from = $anchor->startOfMonth();
                    $to   = $anchor->endOfMonth();
                    break;
                case 'day':
                default:
                    $from = $anchor->startOfDay();
                    $to   = $anchor->endOfDay();
                    break;
            }
        }

        $rows = collect();
        if ($userId) {
            $rows = DB::table('attendance_days as ad')
                ->where('ad.user_id', $userId)
                ->when($from, fn($q) => $q->where('ad.work_date', '>=', $from->toDateString()))
                ->when($to,   fn($q) => $q->where('ad.work_date', '<=', $to->toDateString()))
                ->orderByDesc('ad.work_date')
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
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable $e) {
            abort(404);
        }

        $userRow = DB::table('users')
            ->select([
                'id',
                DB::raw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,''))) as name"),
            ])
            ->where('id', $user)
            ->first();

        abort_unless($userRow !== null, 404);

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
     * Save manual edits: recompute metrics and write audit rows.
     */
    public function update(Request $request, int $user, string $date)
    {
        $data = $request->validate([
            'am_in'  => ['nullable','date'],
            'am_out' => ['nullable','date'],
            'pm_in'  => ['nullable','date'],
            'pm_out' => ['nullable','date'],
            'status' => ['nullable','string','max:50'],
            'reason' => ['nullable','string','max:500'],
        ]);

        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable $e) {
            abort(404);
        }

        // Load existing row + user (for shift window)
        $existing = DB::table('attendance_days')
            ->where('user_id', $user)
            ->where('work_date', $dateObj->toDateString())
            ->first();

        $userRow = DB::table('users')
            ->select('id','shift_window_id')
            ->where('id', $user)
            ->first();
        abort_unless($userRow !== null, 404);

        // New timestamps
        $amIn  = !empty($data['am_in'])  ? Carbon::parse($data['am_in'])  : null;
        $amOut = !empty($data['am_out']) ? Carbon::parse($data['am_out']) : null;
        $pmIn  = !empty($data['pm_in'])  ? Carbon::parse($data['pm_in'])  : null;
        $pmOut = !empty($data['pm_out']) ? Carbon::parse($data['pm_out']) : null;

        // Resolve shift window to recompute metrics
        [$swAmIn,$swAmOut,$swPmIn,$swPmOut,$grace] = $this->resolveShiftWindow($userRow->shift_window_id ?? null, $dateObj->toDateString());

        [$late,$undertime,$hours,$statusAuto] = $this->computeMetrics(
            $amIn,$amOut,$pmIn,$pmOut,$swAmIn,$swAmOut,$swPmIn,$swPmOut,$grace
        );

        // Allow manual status override if provided
        $status = $data['status'] ?? $statusAuto;

        $payload = [
            'user_id'           => $user,
            'work_date'         => $dateObj->toDateString(),
            'am_in'             => $amIn?->toDateTimeString(),
            'am_out'            => $amOut?->toDateTimeString(),
            'pm_in'             => $pmIn?->toDateTimeString(),
            'pm_out'            => $pmOut?->toDateTimeString(),
            'late_minutes'      => $late,
            'undertime_minutes' => $undertime,
            'total_hours'       => $hours,
            'status'            => $status,
            'updated_at'        => now(),
            'created_at'        => now(),
        ];

        // Audit: compare per field and insert into attendance_adjustments
        $changed = [];
        $fields  = ['am_in','am_out','pm_in','pm_out','status'];
        foreach ($fields as $f) {
            $old = $existing->{$f} ?? null;
            $new = $payload[$f] ?? null;
            if ($old !== $new) {
                $changed[$f] = [$old, $new];
            }
        }

        DB::transaction(function () use ($payload, $user, $dateObj, $changed, $data) {
            DB::table('attendance_days')->updateOrInsert(
                ['user_id' => $user, 'work_date' => $dateObj->toDateString()],
                $payload
            );

            if ($changed && Schema::hasTable('attendance_adjustments')) {
                $rows = [];
                foreach ($changed as $field => [$old, $new]) {
                    $rows[] = [
                        'user_id'   => $user,
                        'work_date' => $dateObj->toDateString(),
                        'field'     => $field,
                        'old_value' => $old,
                        'new_value' => $new,
                        'edited_by' => Auth::id(),
                        'reason'    => $data['reason'] ?? null,
                        'created_at'=> now(),
                        'updated_at'=> now(),
                    ];
                }
                if (!empty($rows)) {
                    DB::table('attendance_adjustments')->insert($rows);
                }
            }
        });

        return back()->with('success', 'Attendance saved & recalculated.');
    }

    /* ----------------------- Helpers ----------------------- */

    /**
     * Return [am_in_s, am_out_s, pm_in_s, pm_out_s, grace] as Carbon instances (schedule).
     * Falls back to 08:00–12:00 / 13:00–17:00 with 0 grace.
     */
    private function resolveShiftWindow(?int $windowId, string $workDate): array
    {
        $def = ['08:00:00','12:00:00','13:00:00','17:00:00',0];

        if (!$windowId) {
            return $this->scheduleToCarbons($workDate, ...$def);
        }

        $row = DB::table('shift_windows')->where('id', $windowId)->first();
        if (!$row) {
            return $this->scheduleToCarbons($workDate, ...$def);
        }

        $amIn  = $this->getTimeField($row, ['am_in','am_in_start','start_am','morning_in'])  ?? $def[0];
        $amOut = $this->getTimeField($row, ['am_out','am_out_end','end_am','morning_out'])    ?? $def[1];
        $pmIn  = $this->getTimeField($row, ['pm_in','pm_in_start','start_pm','afternoon_in']) ?? $def[2];
        $pmOut = $this->getTimeField($row, ['pm_out','pm_out_end','end_pm','afternoon_out'])  ?? $def[3];
        $grace = $this->getIntField($row,  ['grace_minutes','grace','late_grace'])            ?? $def[4];

        return $this->scheduleToCarbons($workDate, $amIn,$amOut,$pmIn,$pmOut,$grace);
    }

    private function scheduleToCarbons(string $date, string $amIn, string $amOut, string $pmIn, string $pmOut, int $grace): array
    {
        return [
            Carbon::parse("$date $amIn"),
            Carbon::parse("$date $amOut"),
            Carbon::parse("$date $pmIn"),
            Carbon::parse("$date $pmOut"),
            $grace,
        ];
    }

    private function getTimeField(object $row, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn('shift_windows', $col) && !empty($row->{$col})) {
                $val = trim((string)$row->{$col});
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $val)) {
                    return strlen($val) === 5 ? "$val:00" : $val;
                }
            }
        }
        return null;
    }

    private function getIntField(object $row, array $candidates): ?int
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn('shift_windows', $col) && $row->{$col} !== null) {
                return (int)$row->{$col};
            }
        }
        return null;
    }

    /**
     * Compute late/undertime/hours/status from punches & schedule.
     */
    private function computeMetrics(
        ?Carbon $amIn, ?Carbon $amOut, ?Carbon $pmIn, ?Carbon $pmOut,
        Carbon $amInSched, Carbon $amOutSched, Carbon $pmInSched, Carbon $pmOutSched,
        int $graceMinutes
    ): array {
        // Late
        $late = 0;
        if ($amIn) {
            $lateRef = $amInSched->copy()->addMinutes($graceMinutes);
            if ($amIn->gt($lateRef)) {
                $late = $amIn->diffInMinutes($lateRef);
            }
        }

        // Undertime
        $undertime = 0;
        if ($pmOut) {
            if ($pmOut->lt($pmOutSched)) {
                $undertime = $pmOutSched->diffInMinutes($pmOut);
            }
        } elseif ($amOut && !$pmOut && $amOut->lt($pmOutSched)) {
            // half-day
            $undertime = $pmOutSched->diffInMinutes($amOut);
        }

        // Total hours
        $totalMin = 0;
        if ($amIn && $amOut && $amOut->gt($amIn)) $totalMin += $amIn->diffInMinutes($amOut);
        if ($pmIn && $pmOut && $pmOut->gt($pmIn)) $totalMin += $pmIn->diffInMinutes($pmOut);

        if ($amIn && $pmOut && !$amOut && !$pmIn && $pmOut->gt($amIn)) {
            // 2-punch estimate (minus lunch)
            $lunch = max(0, $amOutSched->diffInMinutes($pmInSched));
            $totalMin = max($totalMin, $amIn->diffInMinutes($pmOut) - $lunch);
        }

        $hours = round($totalMin / 60, 2);

        // Status
        $status = 'Present';
        if (!$amIn && !$pmIn && !$amOut && !$pmOut) {
            $status = 'Absent';
        } elseif ($late > 0 && $undertime > 0) {
            $status = 'Late/Undertime';
        } elseif ($late > 0) {
            $status = 'Late';
        } elseif ($undertime > 0) {
            $status = 'Undertime';
        }

        return [$late, $undertime, $hours, $status];
    }
}
