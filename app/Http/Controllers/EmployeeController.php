<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ShiftWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EmployeesImport;

class EmployeeController extends Controller
{
    /**
     * List employees (ordered by last, first) and all shift windows.
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->select([
                'id', 'email',
                'first_name', 'middle_name', 'last_name',
                'department', 'zkteco_user_id',
                'shift_window_id', 'flexi_start', 'flexi_end',
                'active', 'created_at',
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($w) use ($term) {
                    $w->where('first_name', 'like', "%$term%")
                      ->orWhere('last_name', 'like', "%$term%")
                      ->orWhere('email', 'like', "%$term%")
                      ->orWhere('zkteco_user_id', 'like', "%$term%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        $shifts = ShiftWindow::orderBy('name')->get(['id','name']);

        return view('employees.index', [
            'users'  => $users,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Excel import for bulk employees.
     */
    public function upload(Request $r)
    {
        $r->validate(['file' => ['required','mimes:xlsx,xls']]);
        Excel::import(new EmployeesImport, $r->file('file'));
        return back()->with('success', 'Employees imported.');
    }

    /**
     * Create a single employee.
     * NOTE: uses first/middle/last fields (no `name` column).
     */
    public function store(Request $r)
    {
        $data = $r->validate([
            'first_name'       => ['required','string','max:100'],
            'middle_name'      => ['nullable','string','max:100'],
            'last_name'        => ['required','string','max:100'],

            'email'            => ['required','email', Rule::unique('users','email')],
            'temp_password'    => ['required','string','min:8'],

            // keep as string to allow leading zeros (some IDs are school IDs)
            'zkteco_user_id'   => ['nullable','string','max:64'],

            'department'       => ['nullable','string','max:100'],
            'shift_window_id'  => ['nullable','integer','exists:shift_windows,id'],
            'flexi_start'      => ['nullable','date_format:H:i'],
            'flexi_end'        => ['nullable','date_format:H:i'],
            'active'           => ['nullable','boolean'],
        ]);

        $user = new User();
        $user->first_name      = $data['first_name'];
        $user->middle_name     = $data['middle_name'] ?? null;
        $user->last_name       = $data['last_name'];
        $user->email           = $data['email'];
        $user->password        = Hash::make($data['temp_password']);

        $user->zkteco_user_id  = $data['zkteco_user_id'] ?? null;
        $user->department      = $data['department'] ?? null;
        $user->shift_window_id = $data['shift_window_id'] ?? null;
        $user->flexi_start     = $data['flexi_start'] ?? null;
        $user->flexi_end       = $data['flexi_end'] ?? null;
        $user->active          = array_key_exists('active', $data) ? (bool)$data['active'] : true;

        $user->save();

        // Optional: assign default role if using Spatie (uncomment if desired)
        // if (class_exists(\Spatie\Permission\Models\Role::class)) {
        //     $user->syncRoles(['Employee']);
        // }

        return back()->with('success', 'Employee created.');
    }

    /**
     * Update minimal employee fields (optional helper).
     */
    public function update(Request $r, User $user)
    {
        $data = $r->validate([
            'first_name'       => ['required','string','max:100'],
            'middle_name'      => ['nullable','string','max:100'],
            'last_name'        => ['required','string','max:100'],
            'email'            => ['required','email', Rule::unique('users','email')->ignore($user->id)],
            'zkteco_user_id'   => ['nullable','string','max:64'],
            'department'       => ['nullable','string','max:100'],
            'shift_window_id'  => ['nullable','integer','exists:shift_windows,id'],
            'flexi_start'      => ['nullable','date_format:H:i'],
            'flexi_end'        => ['nullable','date_format:H:i'],
            'active'           => ['nullable','boolean'],
            'new_password'     => ['nullable','string','min:8'],
        ]);

        $user->fill([
            'first_name'      => $data['first_name'],
            'middle_name'     => $data['middle_name'] ?? null,
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'zkteco_user_id'  => $data['zkteco_user_id'] ?? null,
            'department'      => $data['department'] ?? null,
            'shift_window_id' => $data['shift_window_id'] ?? null,
            'flexi_start'     => $data['flexi_start'] ?? null,
            'flexi_end'       => $data['flexi_end'] ?? null,
            'active'          => array_key_exists('active', $data) ? (bool)$data['active'] : $user->active,
        ]);

        if (!empty($data['new_password'])) {
            $user->password = Hash::make($data['new_password']);
        }

        $user->save();

        return back()->with('success', 'Employee updated.');
    }
}
