<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ShiftWindowController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AttendanceEditorController;
use Illuminate\Support\Facades\DB;          // ✅ add this
use Illuminate\Http\Request;               // ✅ optional (if you prefer not to fully-qualify)

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware(['auth','role:HR Officer|IT Admin|Administrator'])->group(function(){
  Route::get('/employees', [EmployeeController::class,'index'])->name('employees.index');
  Route::post('/employees/upload', [EmployeeController::class,'upload'])->name('employees.upload');
  Route::post('/employees', [EmployeeController::class,'store'])->name('employees.store');
});

Route::middleware(['auth','can:reports.view.org'])->get('/reports/attendance', function (Request $r) {
    $q = DB::table('attendance_days')->join('users','users.id','=','attendance_days.user_id')
        ->when($r->filled('status'), fn($x)=>$x->where('status',$r->status))
        ->when($r->filled('dept'), fn($x)=>$x->where('users.department',$r->dept))
        ->when($r->filled('from'), fn($x)=>$x->where('work_date','>=',$r->from))
        ->when($r->filled('to'), fn($x)=>$x->where('work_date','<=',$r->to))
        ->select('users.name','users.department','work_date','late_minutes','undertime_minutes','total_hours','status')
        ->orderByDesc('work_date');

    return view('reports.attendance', ['rows' => $q->paginate(50)]);
});

Route::middleware(['auth','permission:reports.view.org'])->get('/reports/attendance', [ReportController::class,'index'])->name('reports.attendance');
Route::middleware(['auth','permission:reports.export'])->get('/reports/attendance/export', [ReportController::class,'export'])->name('reports.attendance.export');

Route::middleware(['auth','permission:schedules.manage'])
    ->resource('shiftwindows', ShiftWindowController::class)->except(['show']);

Route::middleware(['auth','permission:attendance.edit'])->group(function(){
  Route::get('/attendance/editor', [AttendanceEditorController::class,'index'])->name('attendance.editor');
  Route::get('/attendance/editor/{user}/{date}', [AttendanceEditorController::class,'edit'])->name('attendance.editor.edit');
  Route::post('/attendance/editor/{user}/{date}', [AttendanceEditorController::class,'update'])->name('attendance.editor.update');
});


require __DIR__.'/auth.php';
