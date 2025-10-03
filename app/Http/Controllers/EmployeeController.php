<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use App\Models\ShiftWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EmployeesImport;

class EmployeeController extends Controller
{
    /**
     * Employees list with search & pagination.
     *
     * Query params:
     *  - q: search term (name/email/school_id/zkteco_user_id)
     *  - per_page: 10|20|30|50|100 (default 10)
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10,20,30,50,100])) {
            $perPage = 10;
        }

        $users = User::query()
            ->with(['shiftWindow','department'])
            ->select([
                'id', 'email', 'school_id',
                'first_name', 'middle_name', 'last_name',
                'department_id', 'department', // (string label kept for legacy)
                'zkteco_user_id', 'shift_window_id',
                'flexi_start', 'flexi_end', 'active', 'created_at',
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($w) use ($term) {
                    $w->where('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name',  'like', "%{$term}%")
                      ->orWhere('email',      'like', "%{$term}%")
                      ->orWhere('school_id',  'like', "%{$term}%")
                      ->orWhere('zkteco_user_id','like', "%{$term}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage)
            ->withQueryString();

        $shifts         = ShiftWindow::orderBy('name')->get(['id','name']);
        $departments    = Department::orderBy('name')->get(['id','name']);
        $defaultShiftId = optional($shifts->first())->id;

        return view('employees.index', [
            'users'          => $users,
            'shifts'         => $shifts,
            'departments'    => $departments,
            'defaultShiftId' => $defaultShiftId,
            'perPage'        => $perPage,
        ]);
    }
   // Show the upload page (with quick-create + import form)
public function uploadPage()
{
    $users   = \App\Models\User::orderBy('last_name')->paginate(10);
    $shifts  = \App\Models\ShiftWindow::orderBy('name')->get(['id','name']);
    return view('employees.upload', compact('users','shifts'));
}

// Download a clean Excel template (headings only)
public function downloadTemplate()
{
    // requires Maatwebsite\Excel
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\EmployeesTemplateExport, 'employee_upload_template.xlsx');
}



    /**
     * Excel import for bulk employees (optional).
     * Accepts .xlsx/.xls; your EmployeesImport should map to users table.
     */
    public function upload(Request $r)
    {
        $r->validate(['file' => ['required','mimes:xlsx,xls']]);
        Excel::import(new EmployeesImport, $r->file('file'));
        return back()->with('success', 'Employees imported.');
    }

    /**
     * Create a single employee (uses users table).
     * Sets default shift to the first ShiftWindow if not provided.
     */
    public function store(Request $r)
    {
        $data = $r->validate([
            'first_name'       => ['required','string','max:100'],
            'middle_name'      => ['nullable','string','max:100'],
            'last_name'        => ['required','string','max:100'],

            'email'            => ['required','email', Rule::unique('users','email')],
            'temp_password'    => ['required','string','min:8'],

            'school_id'        => ['nullable','string','max:64', Rule::unique('users','school_id')],
            'zkteco_user_id'   => ['nullable','string','max:64', Rule::unique('users','zkteco_user_id')],

            'department_id'    => ['nullable','integer','exists:departments,id'],
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

        $user->school_id       = $data['school_id'] ?? null;
        $user->zkteco_user_id  = $data['zkteco_user_id'] ?? null;

        $user->department_id   = $data['department_id'] ?? null;
        $user->shift_window_id = $data['shift_window_id']
                               ?? ShiftWindow::orderBy('id')->value('id'); // default: first shift
        $user->flexi_start     = $data['flexi_start'] ?? null;
        $user->flexi_end       = $data['flexi_end'] ?? null;
        $user->active          = array_key_exists('active', $data) ? (bool)$data['active'] : true;

        $user->save();

        // Optional Spatie role assignment:
        // $user->syncRoles(['Employee']);

        return back()->with('success', 'Employee created.');
    }

    /**
     * Update an employee.
     */
    public function update(Request $r, User $user)
    {
        $data = $r->validate([
            'first_name'       => ['required','string','max:100'],
            'middle_name'      => ['nullable','string','max:100'],
            'last_name'        => ['required','string','max:100'],
            'email'            => ['required','email', Rule::unique('users','email')->ignore($user->id)],

            'school_id'        => ['nullable','string','max:64', Rule::unique('users','school_id')->ignore($user->id)],
            'zkteco_user_id'   => ['nullable','string','max:64', Rule::unique('users','zkteco_user_id')->ignore($user->id)],

            'department_id'    => ['nullable','integer','exists:departments,id'],
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

            'school_id'       => $data['school_id'] ?? null,
            'zkteco_user_id'  => $data['zkteco_user_id'] ?? null,

            'department_id'   => $data['department_id'] ?? null,
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
