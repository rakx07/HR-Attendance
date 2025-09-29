<?php

// app/Http/Controllers/ShiftWindowController.php
namespace App\Http\Controllers;

use App\Models\ShiftWindow;
use Illuminate\Http\Request;

class ShiftWindowController extends Controller
{
    public function index()  { return view('shiftwindows.index', ['rows'=>ShiftWindow::paginate(20)]); }
    public function create() { return view('shiftwindows.form', ['sw'=>new ShiftWindow]); }
    public function store(Request $r) {
        $data = $this->validateData($r); ShiftWindow::create($data);
        return redirect()->route('shiftwindows.index')->with('success','Created.');
    }
    public function edit(ShiftWindow $shiftwindow)  { return view('shiftwindows.form', ['sw'=>$shiftwindow]); }
    public function update(Request $r, ShiftWindow $shiftwindow) {
        $shiftwindow->update($this->validateData($r));
        return redirect()->route('shiftwindows.index')->with('success','Updated.');
    }
    public function destroy(ShiftWindow $shiftwindow) {
        $shiftwindow->delete(); return back()->with('success','Deleted.');
    }
    private function validateData(Request $r){
        return $r->validate([
            'name'=>'required',
            'am_in_start'=>'required','am_in_end'=>'required',
            'am_out_start'=>'required','am_out_end'=>'required',
            'pm_in_start'=>'required','pm_in_end'=>'required',
            'pm_out_start'=>'required','pm_out_end'=>'required',
            'grace_minutes'=>'required|integer|min:0'
        ]);
    }
}
