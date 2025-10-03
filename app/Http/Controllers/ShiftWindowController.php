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
        // empty model; blade will show default Mon–Fri working
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

    /** ───────────────── helpers ───────────────── */

    private function validateData(Request $r, ?ShiftWindow $existing = null): array
    {
        $nameRule = ['required','string','max:191', Rule::unique('shift_windows','name')->ignore($existing?->id)];
        $timeRule = ['required','date_format:H:i'];

        return $r->validate([
            'name'          => $nameRule,
            'am_in_start'   => $timeRule,
            'am_in_end'     => $timeRule,
            'am_out_start'  => $timeRule,
            'am_out_end'    => $timeRule,
            'pm_in_start'   => $timeRule,
            'pm_in_end'     => $timeRule,
            'pm_out_start'  => $timeRule,
            'pm_out_end'    => $timeRule,
            'grace_minutes' => ['required','integer','min:0'],
        ]);
    }

    private function normalizeTimes(array $data): array
    {
        foreach ([
            'am_in_start','am_in_end',
            'am_out_start','am_out_end',
            'pm_in_start','pm_in_end',
            'pm_out_start','pm_out_end',
        ] as $k) {
            if (!empty($data[$k]) && strlen($data[$k]) === 5) {
                $data[$k] .= ':00';
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
            $out[$d] = [
                'is_working' => (bool)($row['is_working'] ?? 0),
                'am_in'  => $this->hhmm($row['am_in']  ?? null),
                'am_out' => $this->hhmm($row['am_out'] ?? null),
                'pm_in'  => $this->hhmm($row['pm_in']  ?? null),
                'pm_out' => $this->hhmm($row['pm_out'] ?? null),
            ];
        }
        return $out;
    }

    private function hhmm(?string $v): ?string
    {
        if (!$v) return null;
        if (preg_match('/^\d{1,2}(:\d{1,2})?$/', $v)) {
            [$h,$m] = array_pad(explode(':', $v, 2), 2, '00');
            return sprintf('%02d:%02d:00', (int)$h % 24, (int)$m % 60);
        }
        return strlen($v) === 5 ? $v . ':00' : $v; // already H:i or H:i:s
    }
}
