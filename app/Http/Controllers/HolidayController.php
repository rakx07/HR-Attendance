<?php

namespace App\Http\Controllers;

use App\Models\HolidayCalendar;
use App\Models\HolidayDate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class HolidayController extends Controller
{
    /** List calendars by year with counts. */
    public function index()
    {
        $calendars = HolidayCalendar::withCount('dates')
            ->orderByDesc('year')
            ->paginate(15);

        return view('holidays.index', compact('calendars'));
    }

    /** Create a new calendar (optionally copy from another year). */
    public function store(Request $r)
    {
        $data = $r->validate([
            'year'          => ['required','integer','min:1900','max:3000', Rule::unique('holiday_calendars','year')],
            'status'        => ['required','in:draft,active'],
            'copy_from_year'=> ['nullable','integer','min:1900','max:3000'],
        ]);

        DB::transaction(function () use ($data) {
            $cal = HolidayCalendar::create([
                'year' => $data['year'],
                'status' => $data['status'],
                'activated_at' => $data['status']==='active' ? now() : null,
            ]);

            if (!empty($data['copy_from_year'])) {
                $from = HolidayCalendar::where('year',$data['copy_from_year'])->with('dates')->first();
                if ($from) {
                    foreach ($from->dates as $d) {
                        $cal->dates()->create([
                            'date' => $this->shiftYear($d->date, $from->year, $cal->year),
                            'name' => $d->name,
                            'is_non_working' => $d->is_non_working,
                        ]);
                    }
                }
            }
        });

        return back()->with('success','Calendar created.');
    }

    /** Manage a calendar (list + add/edit dates). */
    public function show(HolidayCalendar $calendar)
    {
        $calendar->load(['dates' => fn($q) => $q->orderBy('date')]);
        return view('holidays.manage', compact('calendar'));
    }

    /** Activate a calendar (set status=active). */
    public function activate(HolidayCalendar $calendar)
    {
        $calendar->update(['status' => 'active', 'activated_at' => now()]);
        return back()->with('success', 'Calendar activated.');
    }

    /** Add a date to a calendar. */
    public function storeDate(Request $r, HolidayCalendar $calendar)
    {
        $data = $r->validate([
            'date' => ['required','date','after_or_equal:'.$calendar->year.'-01-01','before_or_equal:'.$calendar->year.'-12-31'],
            'name' => ['required','string','max:191'],
            'is_non_working' => ['nullable','boolean'],
        ]);

        $calendar->dates()->create([
            'date' => $data['date'],
            'name' => $data['name'],
            'is_non_working' => (bool)($data['is_non_working'] ?? true),
        ]);

        return back()->with('success', 'Holiday added.');
    }

    /** Update a date. */
    public function updateDate(Request $r, HolidayCalendar $calendar, HolidayDate $date)
    {
        abort_unless($date->holiday_calendar_id === $calendar->id, 404);

        $data = $r->validate([
            'date' => ['required','date','after_or_equal:'.$calendar->year.'-01-01','before_or_equal:'.$calendar->year.'-12-31'],
            'name' => ['required','string','max:191'],
            'is_non_working' => ['nullable','boolean'],
        ]);

        $date->update([
            'date' => $data['date'],
            'name' => $data['name'],
            'is_non_working' => (bool)($data['is_non_working'] ?? true),
        ]);

        return back()->with('success', 'Holiday updated.');
    }

    /** Delete a date. */
    public function destroyDate(HolidayCalendar $calendar, HolidayDate $date)
    {
        abort_unless($date->holiday_calendar_id === $calendar->id, 404);
        $date->delete();
        return back()->with('success','Holiday removed.');
    }

    /** Utility: change the year part of a date string (Y-m-d). */
    private function shiftYear(string $ymd, int $fromYear, int $toYear): string
    {
        // handles leap years: 2024-02-29 -> 2025-02-28
        [$y,$m,$d] = array_map('intval', explode('-', $ymd));
        $y = $toYear;
        $last = cal_days_in_month(CAL_GREGORIAN, $m, $y);
        $d = min($d, $last);
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
