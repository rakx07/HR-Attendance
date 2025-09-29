<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ShiftWindow;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EmployeesImport;

class EmployeeController extends Controller
{
    public function index()
    {
        return view('employees.index', [
            'users'  => User::paginate(20),
            'shifts' => ShiftWindow::all(),
        ]);
    }

    public function upload(Request $r)
    {
        $r->validate(['file' => 'required|mimes:xlsx,xls']);
        Excel::import(new EmployeesImport, $r->file('file'));
        return back()->with('success', 'Employees imported.');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'            => 'required',
            'email'           => 'required|email|unique:users',
            'temp_password'   => 'required|min:8',
            'zkteco_user_id'  => 'nullable|integer',
            'shift_window_id' => 'nullable|exists:shift_windows,id',
            'flexi_start'     => 'nullable',
            'flexi_end'       => 'nullable',
        ]);

        $u = new User($data);
        $u->password = bcrypt($data['temp_password']);
        $u->save();

        return back()->with('success', 'Employee created.');
    }
}
