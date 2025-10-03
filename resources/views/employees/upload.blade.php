<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      Employees • Upload / Import
    </h2>
  </x-slot>

  <div class="py-6">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6">

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

        <div class="flex items-center justify-between">
          <a href="{{ route('employees.index') }}" class="px-3 py-2 rounded bg-gray-700 hover:bg-gray-800 text-white">
            ← Back to Employees
          </a>

          <a href="{{ route('employees.template') }}"
             class="px-3 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white">
            Download Excel Template
          </a>
        </div>

        {{-- Import form --}}
        <form action="{{ route('employees.upload') }}" method="POST" enctype="multipart/form-data"
              class="space-y-3 border rounded p-4 dark:border-gray-700">
          @csrf
          <div>
            <label class="block text-sm mb-1">Upload Excel (.xlsx / .xls)</label>
            <input type="file" name="file" required
                   class="w-full border rounded p-2 dark:bg-gray-900 dark:border-gray-700">
            <p class="text-xs text-gray-500 mt-1">
              Columns must match the template headings. If <strong>shift_window_id</strong> is blank,
              the system assigns the default (first) shift automatically.
            </p>
          </div>
          <div class="text-right">
            <button class="px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white">
              Import
            </button>
          </div>
        </form>

        {{-- Quick-create (optional) --}}
        <form action="{{ route('employees.store') }}" method="POST" class="space-y-3 border rounded p-4 dark:border-gray-700">
          @csrf
          <h3 class="font-semibold">Quick Create</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="form-label">School ID</label>
              <input name="school_id" class="form-input">
            </div>

            <div>
              <label class="form-label">ZKTeco ID</label>
              <input name="zkteco_user_id" class="form-input">
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
                  <option value="{{ $s->id }}">{{ $s->name }}</option>
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
              <label class="form-label">Active?</label>
              <select name="active" class="form-input">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
          </div>

          <div class="text-right">
            <button class="btn-primary">Create</button>
          </div>
        </form>

        {{-- (Optional) small list below --}}
        <div class="border rounded dark:border-gray-700">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-900">
              <tr class="text-left text-gray-700 dark:text-gray-300">
                <th class="p-2">School ID</th>
                <th class="p-2">Name</th>
                <th class="p-2">Email</th>
                <th class="p-2">ZKTeco ID</th>
                <th class="p-2">Shift</th>
              </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
              @foreach($users as $u)
                <tr class="text-gray-800 dark:text-gray-100">
                  <td class="p-2">{{ $u->school_id }}</td>
                  <td class="p-2">{{ $u->last_name }}, {{ $u->first_name }} {{ $u->middle_name }}</td>
                  <td class="p-2">{{ $u->email }}</td>
                  <td class="p-2">{{ $u->zkteco_user_id }}</td>
                  <td class="p-2">{{ optional($u->shiftWindow)->name ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div>{{ $users->links() }}</div>

      </div>
    </div>
  </div>

  <style>
    .form-label { display:block; font-size:.85rem; color:#4b5563; margin-bottom:.25rem; }
    .dark .form-label { color:#d1d5db; }
    .form-input { width:100%; border:1px solid #d1d5db; border-radius:.5rem; padding:.5rem .75rem; }
    .dark .form-input { background:#0f172a; border-color:#374151; color:#e5e7eb; }
    .btn-primary { background:#2563eb; color:white; padding:.5rem .9rem; border-radius:.5rem; }
    .btn-primary:hover { background:#1e40af; }
  </style>
</x-app-layout>
