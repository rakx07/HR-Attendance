<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ShiftWindowController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AttendanceEditorController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Welcome page
Route::get('/', function () {
    return view('welcome');
});

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Profile (all authenticated users)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Employees (restricted by role)
Route::middleware(['auth','role:HR Officer|IT Admin|Administrator'])->group(function () {
    Route::get('/employees', [EmployeeController::class,'index'])->name('employees.index');
    Route::post('/employees/upload', [EmployeeController::class,'upload'])->name('employees.upload');
    Route::post('/employees', [EmployeeController::class,'store'])->name('employees.store');
});

// Reports (restricted by permission)
Route::middleware(['auth','permission:reports.view.org'])
    ->get('/reports/attendance', [ReportController::class,'index'])
    ->name('reports.attendance');

Route::middleware(['auth','permission:reports.export'])
    ->get('/reports/attendance/export', [ReportController::class,'export'])
    ->name('reports.attendance.export');

// Shift Windows (resource routes, restricted by permission)
Route::middleware(['auth','permission:schedules.manage'])
    ->resource('shiftwindows', ShiftWindowController::class)
    ->except(['show']);

// Attendance Editor (restricted by permission)
Route::middleware(['auth','permission:attendance.edit'])->group(function () {
    Route::get('/attendance/editor', [AttendanceEditorController::class,'index'])->name('attendance.editor');
    Route::get('/attendance/editor/{user}/{date}', [AttendanceEditorController::class,'edit'])->name('attendance.editor.edit');
    Route::post('/attendance/editor/{user}/{date}', [AttendanceEditorController::class,'update'])->name('attendance.editor.update');

    
});
Route::middleware(['auth','permission:attendance.edit'])->group(function () {
    Route::get('/attendance/editor', [AttendanceEditorController::class,'index'])->name('attendance.editor');
    Route::get('/attendance/editor/{user}/{date}', [AttendanceEditorController::class,'edit'])->name('attendance.editor.edit');
    Route::post('/attendance/editor/{user}/{date}', [AttendanceEditorController::class,'update'])->name('attendance.editor.update');
});


// Breeze/Fortify/Jetstream auth routes
require __DIR__.'/auth.php';
