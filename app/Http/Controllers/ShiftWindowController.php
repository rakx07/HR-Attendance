<?php

namespace App\Http\Controllers;

use App\Models\ShiftWindow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftWindowController extends Controller
{
    public function index()
    {
        $rows = ShiftWindow::withCount('days')->orderBy('name')->paginate(20);
        return view('shiftwindows.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('shiftwindows.form', ['sw' => new ShiftWindow()]);
    }

    public function store(Request $r)
    {
        $data = $this->validateData($r);
        $data = $this->normalizeTimes($data);

        $sw = ShiftWindow::create($data);

        // per-day rules
        $days = $this->validateDays($r);
        foreach ($days as $dow => $row) {
            $sw->days()->create(array_merge(['dow' => $dow], $row));
        }

        return redirect()->route('shiftwindows.index')->with('success', 'Created.');
    }

    public function edit(ShiftWindow $shiftwindow)
    {
        $shiftwindow->load('days');
        return view('shiftwindows.form', ['sw' => $shiftwindow]);
    }

    public function update(Request $r, ShiftWindow $shiftwindow)
    {
        $data = $this->validateData($r, $shiftwindow);
        $data = $this->normalizeTimes($data);

        $shiftwindow->update($data);

        $days = $this->validateDays($r);
        foreach ($days as $dow => $row) {
            $shiftwindow->days()->updateOrCreate(['dow' => $dow], $row);
        }

        return redirect()->route('shiftwindows.index')->with('success', 'Updated.');
    }

    public function destroy(ShiftWindow $shiftwindow)
    {
        $shiftwindow->delete();
        return back()->with('success', 'Deleted.');
    }

    /* ───────────── helpers ───────────── */

    /** Accept 12h ("7:30 AM", "1 PM") OR 24h ("07:30", "07:30:00"); return "HH:MM:SS" or null */
    private function parseTime(?string $v): ?string
    {
        if (!$v) return null;
        $raw = trim($v);

        // 12h variants
        $lower = strtolower($raw);
        $formats = ['g:i a','g:ia','h:i a','h:ia','g a','ga','h a','ha']; // 7:30 am / 07:30am / 7 am
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $lower);
            if ($dt !== false) return $dt->format('H:i:00');
        }

        // 24h HH:MM or HH:MM:SS
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)) {
            if (strlen($raw) === 5) $raw .= ':00';
            [$h,$m,$s] = explode(':', $raw);
            $h = (int) $h; $m = (int) $m; $s = (int) ($s ?? 0);
            if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59 && $s >= 0 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }
        }

        return null;
    }

    /** Validation rule that accepts 12h or 24h and shows a friendly message */
    private function timeRule()
    {
        return function (string $attribute, $value, \Closure $fail) {
            if ($this->parseTime($value) === null) {
                $fail("The {$attribute} must be a valid time like 7:30 AM or 17:30.");
            }
        };
    }

    private function validateData(Request $r, ?ShiftWindow $existing = null): array
    {
        $nameRule = ['required','string','max:191', Rule::unique('shift_windows','name')->ignore($existing?->id)];
        $timeRule = $this->timeRule();

        return $r->validate([
            'name'          => $nameRule,
            'am_in_start'   => ['required',$timeRule],
            'am_in_end'     => ['required',$timeRule],
            'am_out_start'  => ['required',$timeRule],
            'am_out_end'    => ['required',$timeRule],
            'pm_in_start'   => ['required',$timeRule],
            'pm_in_end'     => ['required',$timeRule],
            'pm_out_start'  => ['required',$timeRule],
            'pm_out_end'    => ['required',$timeRule],
            'grace_minutes' => ['required','integer','min:0'],
        ]);
    }

    /** Convert all validated time strings to HH:MM:SS */
    private function normalizeTimes(array $data): array
    {
        foreach ([
            'am_in_start','am_in_end',
            'am_out_start','am_out_end',
            'pm_in_start','pm_in_end',
            'pm_out_start','pm_out_end',
        ] as $k) {
            if (!empty($data[$k])) {
                $data[$k] = $this->parseTime($data[$k]);
            }
        }
        return $data;
    }

    /** Parse weekly grid posted as days[dow][...] */
    private function validateDays(Request $r): array
    {
        $days = $r->input('days', []);
        $out  = [];
        for ($d = 1; $d <= 7; $d++) {
            $row = $days[$d] ?? [];
            $isWorking = (bool)($row['is_working'] ?? 0);

            $out[$d] = [
                'is_working' => $isWorking,
                'am_in'  => $this->parseTime($row['am_in']  ?? null),
                'am_out' => $this->parseTime($row['am_out'] ?? null),
                'pm_in'  => $this->parseTime($row['pm_in']  ?? null),
                'pm_out' => $this->parseTime($row['pm_out'] ?? null),
            ];

            // If not working, clear times
            if (!$isWorking) {
                $out[$d]['am_in'] = $out[$d]['am_out'] = $out[$d]['pm_in'] = $out[$d]['pm_out'] = null;
            }
        }
        return $out;
    }
}
