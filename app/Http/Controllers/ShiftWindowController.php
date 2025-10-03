<?php

// app/Http/Controllers/ShiftWindowController.php
namespace App\Http\Controllers;

use App\Models\ShiftWindow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftWindowController extends Controller
{
    public function index()
    {
        $rows = ShiftWindow::query()
            ->orderBy('name')
            ->paginate(20);

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

        ShiftWindow::create($data);

        return redirect()
            ->route('shiftwindows.index')
            ->with('success', 'Created.');
    }

    public function edit(ShiftWindow $shiftwindow)
    {
        return view('shiftwindows.form', ['sw' => $shiftwindow]);
    }

    public function update(Request $r, ShiftWindow $shiftwindow)
    {
        $data = $this->validateData($r, $shiftwindow);
        $data = $this->normalizeTimes($data);

        $shiftwindow->update($data);

        return redirect()
            ->route('shiftwindows.index')
            ->with('success', 'Updated.');
    }

    public function destroy(ShiftWindow $shiftwindow)
    {
        $shiftwindow->delete();

        return back()->with('success', 'Deleted.');
    }

    /**
     * Validate input; on update, allow keeping the same name.
     */
    private function validateData(Request $r, ?ShiftWindow $existing = null): array
    {
        $nameRule = ['required', 'string', 'max:191'];
        $nameRule[] = Rule::unique('shift_windows', 'name')
            ->ignore($existing?->id);

        // Your migration uses TIME columns; enforce H:i and we’ll normalize to H:i:s
        $timeRule = ['required', 'date_format:H:i'];

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
            'grace_minutes' => ['required', 'integer', 'min:0'],
        ]);
    }

    /**
     * Convert H:i to H:i:s for TIME columns (DB accepts both, but keeping it consistent).
     */
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
}
