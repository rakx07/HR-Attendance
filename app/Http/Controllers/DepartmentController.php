<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use App\Models\DepartmentTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    // List + create form
    public function index(Request $r)
{
    $q = trim((string) $r->query('q', ''));

    // Active users only, searchable, alphabetical
    $users = User::query()
        ->where('active', true)
        ->when($q !== '', function ($x) use ($q) {
            $x->where(function ($y) use ($q) {
                $y->where('last_name', 'like', "%{$q}%")
                  ->orWhere('first_name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        })
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->paginate(20)
        ->withQueryString();

    // Only active departments for the transfer dropdown
    $activeDepts = Department::where('active', true)
        ->orderBy('name')
        ->get(['id','name']);

    // All departments for the Manage modal (own paginator so it doesn’t clash)
    $allDepts = Department::orderBy('name')
        ->paginate(15, ['*'], 'dept_page')
        ->withQueryString();

    return view('departments.index', [
        'users'      => $users,
        'activeDepts'=> $activeDepts,
        'allDepts'   => $allDepts,
        'q'          => $q,
    ]);
}


    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'code' => 'nullable|string|max:50|unique:departments,code',
            'description' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);
        Department::create($data + ['active' => $r->boolean('active')]);
        return back()->with('success', 'Department created.');
    }

    public function update(Request $r, Department $department)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255|unique:departments,name,'.$department->id,
            'code' => 'nullable|string|max:50|unique:departments,code,'.$department->id,
            'description' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);
        $department->update($data + ['active' => $r->boolean('active')]);
        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return back()->with('success','Department deleted.');
    }

    /**
     * Transfer an employee to a new department and log history.
     */
    public function transfer(Request $r)
    {
        $data = $r->validate([
            'user_id'   => 'required|exists:users,id',
            'to_id'     => 'required|exists:departments,id',
            'effective_at' => 'required|date',
            'reason'    => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($data, $r) {
            $user = User::lockForUpdate()->find($data['user_id']);
            $fromId = $user->department_id;

            // Update user's current department (and legacy text column)
            $user->department_id = $data['to_id'];
            $user->department    = Department::find($data['to_id'])->name; // keep old column in sync
            $user->save();

            DepartmentTransfer::create([
                'user_id'           => $user->id,
                'from_department_id'=> $fromId,
                'to_department_id'  => $data['to_id'],
                'reason'            => $data['reason'] ?? null,
                'effective_at'      => $data['effective_at'],
                'created_by'        => $r->user()->id ?? null,
            ]);
        });

        return back()->with('success','Employee transferred.');
    }

    /**
     * Show transfer history for a user (simple modal/table helper).
     */
    public function history(User $user)
    {
        $history = DepartmentTransfer::with(['from','to','author'])
            ->where('user_id', $user->id)
            ->orderByDesc('effective_at')
            ->get();

        return view('departments.history', compact('user','history'));
    }
}
