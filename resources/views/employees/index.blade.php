{{-- resources/views/employees/index.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      Employees
    </h2>
  </x-slot>

  <div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-4">

        {{-- Alerts --}}
        @if(session('success'))
          <div class="rounded border border-green-300 bg-green-50 text-green-800 px-3 py-2 text-sm">
            {{ session('success') }}
          </div>
        @endif

        @if($errors->any())
          <div class="rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm">
            <ul class="list-disc list-inside">
              @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
          </div>
        @endif

        {{-- Search (left) + Actions (right) --}}
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <form method="GET" class="flex flex-wrap items-center gap-2">
            <input
              type="text"
              name="q"
              value="{{ request('q') }}"
              placeholder="Search name / email / school ID / ZKTeco ID"
              class="w-72 md:w-96 border rounded px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
            />

            <label class="text-sm text-gray-600 dark:text-gray-300 ml-2">Rows:</label>
            <select name="per_page" onchange="this.form.submit()"
              class="border rounded px-2 py-2 dark:bg-gray-900 dark:border-gray-700">
              @foreach([10,20,30,50,100] as $n)
                <option value="{{ $n }}" @selected(request('per_page', $perPage ?? 10) == $n)>{{ $n }}</option>
              @endforeach
            </select>

            <button class="ml-1 px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white">
              Apply
            </button>
          </form>

          <div class="flex items-center gap-2">
            <a href="{{ route('departments.index') }}"
               class="px-3 py-2 rounded bg-gray-700 hover:bg-gray-800 text-white">
              Manage Departments
            </a>

            <button
              type="button"
              class="px-3 py-2 rounded bg-green-600 hover:bg-green-700 text-white"
              data-modal-target="addEmployeeModal">
              Add Employee
            </button>
          </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto border rounded dark:border-gray-700">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-900">
              <tr class="text-left text-gray-700 dark:text-gray-300">
                <th class="p-2">ZKTeco ID</th>
                <th class="p-2">School ID</th>
                <th class="p-2">Last</th>
                <th class="p-2">First</th>
                <th class="p-2">Middle</th>
                <th class="p-2">Email</th>
                <th class="p-2">Shift</th>
                <th class="p-2">Department</th>
                <th class="p-2">Status</th>
                <th class="p-2 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
              @forelse($users as $u)
                <tr class="text-gray-800 dark:text-gray-100">
                  <td class="p-2">{{ $u->zkteco_user_id }}</td>
                  <td class="p-2">{{ $u->school_id }}</td>
                  <td class="p-2">{{ $u->last_name }}</td>
                  <td class="p-2">{{ $u->first_name }}</td>
                  <td class="p-2">{{ $u->middle_name }}</td>
                  <td class="p-2">{{ $u->email }}</td>
                  <td class="p-2">{{ $u->shiftWindow->name ?? '—' }}</td>
                  <td class="p-2">{{ $u->department->name ?? '—' }}</td>
                  <td class="p-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs
                      {{ $u->active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                     : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                      {{ $u->active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                 <td class="p-2 text-center">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 hover:bg-blue-700 shadow
                            focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-400"
                        data-modal-target="editEmployeeModal-{{ $u->id }}"
                        aria-label="Edit"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9"/>
                        <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                        </svg>
                    </button>
                    </td>


                </tr>

                {{-- Edit Modal (one per row) --}}
                <div id="editEmployeeModal-{{ $u->id }}" class="modal hidden">
                  <div class="modal-backdrop" data-modal-close></div>
                  <div class="modal-panel">
                    <div class="flex items-center justify-between mb-3">
                      <h3 class="text-lg font-semibold">Edit Employee</h3>
                      <button class="modal-close" data-modal-close>&times;</button>
                    </div>

                    <form method="POST" action="{{ route('employees.update', $u) }}" class="space-y-3">
                      @csrf
                      @method('PATCH')

                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                          <label class="form-label">ZKTeco ID</label>
                          <input name="zkteco_user_id" value="{{ $u->zkteco_user_id }}" class="form-input">
                        </div>

                        <div>
                          <label class="form-label">School ID</label>
                          <input name="school_id" value="{{ $u->school_id }}" class="form-input">
                        </div>

                        <div>
                          <label class="form-label">Last name</label>
                          <input name="last_name" value="{{ $u->last_name }}" required class="form-input">
                        </div>

                        <div>
                          <label class="form-label">First name</label>
                          <input name="first_name" value="{{ $u->first_name }}" required class="form-input">
                        </div>

                        <div>
                          <label class="form-label">Middle name</label>
                          <input name="middle_name" value="{{ $u->middle_name }}" class="form-input">
                        </div>

                        <div>
                          <label class="form-label">Email</label>
                          <input type="email" name="email" value="{{ $u->email }}" required class="form-input">
                        </div>

                        <div>
                          <label class="form-label">Shift</label>
                          <select name="shift_window_id" class="form-input">
                            <option value="">— Select shift —</option>
                            @foreach($shifts as $s)
                              <option value="{{ $s->id }}" @selected($u->shift_window_id == $s->id)>{{ $s->name }}</option>
                            @endforeach
                          </select>
                        </div>

                        <div>
                          <label class="form-label">Department</label>
                          <select name="department_id" class="form-input">
                            <option value="">— Select department —</option>
                            @foreach($departments as $d)
                              <option value="{{ $d->id }}" @selected($u->department_id == $d->id)>{{ $d->name }}</option>
                            @endforeach
                          </select>
                        </div>

                        <div>
                          <label class="form-label">Flexi start (HH:MM)</label>
                          <input name="flexi_start" value="{{ $u->flexi_start }}" placeholder="08:00" class="form-input">
                        </div>

                        <div>
                          <label class="form-label">Flexi end (HH:MM)</label>
                          <input name="flexi_end" value="{{ $u->flexi_end }}" placeholder="17:00" class="form-input">
                        </div>

                        <div>
                          <label class="form-label">Status</label>
                          <select name="active" class="form-input">
                            <option value="1" @selected($u->active)>Active</option>
                            <option value="0" @selected(!$u->active)>Inactive</option>
                          </select>
                        </div>

                        <div>
                          <label class="form-label">New Password (optional)</label>
                          <input type="password" name="new_password" class="form-input" placeholder="Min 8 chars">
                        </div>
                      </div>

                      <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                        <button class="btn-primary">Save</button>
                      </div>
                    </form>
                  </div>
                </div>
              @empty
                <tr>
                  <td colspan="10" class="p-4 text-center text-gray-500 dark:text-gray-400">
                    No employees found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        {{-- Pagination --}}
        <div>
          {{ $users->links() }}
        </div>

      </div>
    </div>
  </div>

  {{-- Add Employee Modal --}}
  <div id="addEmployeeModal" class="modal hidden">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-semibold">Add Employee</h3>
        <button class="modal-close" data-modal-close>&times;</button>
      </div>

      <form method="POST" action="{{ route('employees.store') }}" class="space-y-3">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="form-label">ZKTeco ID</label>
            <input name="zkteco_user_id" class="form-input">
          </div>

          <div>
            <label class="form-label">School ID</label>
            <input name="school_id" class="form-input">
          </div>

          <div>
            <label class="form-label">Last name</label>
            <input name="last_name" required class="form-input">
          </div>

          <div>
            <label class="form-label">First name</label>
            <input name="first_name" required class="form-input">
          </div>

          <div>
            <label class="form-label">Middle name</label>
            <input name="middle_name" class="form-input">
          </div>

          <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" required class="form-input">
          </div>

          <div>
            <label class="form-label">Temp Password</label>
            <input type="password" name="temp_password" required class="form-input" placeholder="Min 8 chars">
          </div>

          <div>
            <label class="form-label">Shift</label>
            <select name="shift_window_id" class="form-input">
              <option value="">— Default: first shift —</option>
              @foreach($shifts as $s)
                <option value="{{ $s->id }}" @selected($defaultShiftId == $s->id)>{{ $s->name }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="form-label">Department</label>
            <select name="department_id" class="form-input">
              <option value="">— Select department —</option>
              @foreach($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="form-label">Flexi start (HH:MM)</label>
            <input name="flexi_start" placeholder="08:00" class="form-input">
          </div>

          <div>
            <label class="form-label">Flexi end (HH:MM)</label>
            <input name="flexi_end" placeholder="17:00" class="form-input">
          </div>

          <div>
            <label class="form-label">Status</label>
            <select name="active" class="form-input">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
          <button class="btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Minimal modal styles + JS (vanilla) --}}
  <style>
    .modal { position: fixed; inset: 0; z-index: 50; }
    .modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.4); }
    .modal-panel {
      position: relative;
      margin: 4rem auto;
      max-width: 52rem;
      width: calc(100% - 2rem);
      background: white;
      color: #1f2937;
      border-radius: .75rem;
      padding: 1rem;
    }
    .dark .modal-panel { background: #111827; color: #e5e7eb; }
    .modal.hidden { display: none; }
    .form-label { display:block; font-size:.85rem; color:#4b5563; margin-bottom:.25rem; }
    .dark .form-label { color:#d1d5db; }
    .form-input { width:100%; border:1px solid #d1d5db; border-radius:.5rem; padding:.5rem .75rem; }
    .dark .form-input { background:#0f172a; border-color:#374151; color:#e5e7eb; }
    .btn-primary { background:#2563eb; color:white; padding:.5rem .9rem; border-radius:.5rem; }
    .btn-primary:hover { background:#1e40af; }
    .btn-secondary { background:#6b7280; color:white; padding:.5rem .9rem; border-radius:.5rem; }
    .btn-secondary:hover { background:#4b5563; }
    .modal-close { font-size:1.5rem; line-height:1; padding:.25rem .5rem; border-radius:.375rem; }
  </style>

  <script>
    // Open/close modals using data attributes
    document.addEventListener('click', (e) => {
      const openTarget = e.target.closest('[data-modal-target]');
      if (openTarget) {
        const id = openTarget.getAttribute('data-modal-target');
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
      }
      const closeTarget = e.target.closest('[data-modal-close]');
      if (closeTarget) {
        const modal = closeTarget.closest('.modal');
        if (modal) modal.classList.add('hidden');
      }
      if (e.target.classList.contains('modal-backdrop')) {
        e.target.closest('.modal').classList.add('hidden');
      }
    });
  </script>
</x-app-layout>
