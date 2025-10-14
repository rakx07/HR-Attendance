<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ShiftWindowController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AttendanceEditorController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\HolidayController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Welcome page
Route::match(['GET','HEAD'], '/', fn () => view('welcome'));

// Dashboard
Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth','verified'])
    ->name('dashboard');

// Profile (all authenticated users)
Route::middleware('auth')->group(function () {
    Route::get   ('/profile',  [ProfileController::class, 'edit'])   ->name('profile.edit');
    Route::patch ('/profile',  [ProfileController::class, 'update']) ->name('profile.update');
    Route::delete('/profile',  [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Employees (restricted by role)
Route::middleware(['auth','role:HR Officer|IT Admin|Administrator'])->group(function () {
    Route::get ('/employees',              [EmployeeController::class,'index'])->name('employees.index');
    Route::patch('/employees/{user}',      [EmployeeController::class,'update'])->name('employees.update');

    // Upload / template
    Route::get ('/employees/upload',       [EmployeeController::class,'uploadPage'])->name('employees.upload.page');
    Route::get ('/employees/template',     [EmployeeController::class,'downloadTemplate'])->name('employees.template');
    Route::post('/employees/upload',       [EmployeeController::class,'upload'])->name('employees.upload');

    Route::post('/employees',              [EmployeeController::class,'store'])->name('employees.store');
});

// Reports (restricted by permission)
Route::middleware(['auth','permission:reports.view.org'])
    ->get('/reports/attendance', [ReportController::class,'index'])
    ->name('reports.attendance');

Route::middleware(['auth','permission:reports.export'])
    ->get('/reports/attendance/export', [ReportController::class,'export'])
    ->name('reports.attendance.export');

Route::middleware(['auth','permission:reports.export'])
    ->get('/reports/attendance/pdf', [ReportController::class,'pdf'])
    ->name('reports.attendance.pdf');

/**
 * Raw logs API used by the modal
 */
Route::middleware(['auth','permission:reports.view.org'])->get(
    '/reports/attendance/raw',
    [ReportController::class, 'raw']
)->name('reports.attendance.raw');

Route::middleware(['auth','permission:attendance.edit'])->post(
    '/reports/attendance/raw/update',
    [ReportController::class, 'rawUpdate']
)->name('reports.attendance.raw.update');

/**
 * Consolidated day (for modal): fetch + save (recomputes late/under/hours)
 */
Route::middleware(['auth','permission:reports.view.org'])->get(
    '/reports/attendance/day',
    [ReportController::class, 'day']
)->name('reports.attendance.day');

Route::middleware(['auth','permission:attendance.edit'])->post(
    '/reports/attendance/day/update',
    [ReportController::class, 'dayUpdate']
)->name('reports.attendance.day.update');

// Shift Windows (restricted by permission)
Route::middleware(['auth','permission:schedules.manage'])
    ->resource('shiftwindows', ShiftWindowController::class)
    ->except(['show']);

// Attendance Editor (restricted by permission)
Route::middleware(['auth','permission:attendance.edit'])->group(function () {
    Route::get ('/attendance/editor',                   [AttendanceEditorController::class,'index'])->name('attendance.editor');
    Route::get ('/attendance/editor/{user}/{date}',     [AttendanceEditorController::class,'edit'])->name('attendance.editor.edit');
    Route::post('/attendance/editor/{user}/{date}',     [AttendanceEditorController::class,'update'])->name('attendance.editor.update');
});

// Departments (restricted by role)
Route::middleware(['auth','role:HR Officer|IT Admin|Administrator'])->group(function () {
    Route::get   ('/departments',                     [DepartmentController::class, 'index'])->name('departments.index');
    Route::post  ('/departments',                     [DepartmentController::class, 'store'])->name('departments.store');
    Route::patch ('/departments/{department}',        [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{department}',        [DepartmentController::class, 'destroy'])->name('departments.destroy');

    // transfers
    Route::post ('/departments/transfer',             [DepartmentController::class, 'transfer'])->name('departments.transfer');
    Route::get  ('/departments/history/{user}',       [DepartmentController::class, 'history'])->name('departments.history');
});

// Holidays (restricted by role)
Route::middleware(['auth','role:HR Officer|IT Admin|Administrator'])->group(function () {
    Route::get   ('/holidays',                         [HolidayController::class,'index'])->name('holidays.index');
    Route::post  ('/holidays',                         [HolidayController::class,'store'])->name('holidays.store');
    Route::get   ('/holidays/{calendar}',              [HolidayController::class,'show'])->name('holidays.show');
    Route::patch ('/holidays/{calendar}/activate',     [HolidayController::class,'activate'])->name('holidays.activate');

    // Nested date routes
    Route::post  ('/holidays/{calendar}/dates',                [HolidayController::class,'storeDate'])->name('holidays.dates.store');
    Route::patch ('/holidays/{calendar}/dates/{date}',         [HolidayController::class,'updateDate'])->name('holidays.dates.update');
    Route::delete('/holidays/{calendar}/dates/{date}',         [HolidayController::class,'destroyDate'])->name('holidays.dates.destroy');
});

// Breeze/Fortify/Jetstream auth routes
require __DIR__.'/auth.php';
