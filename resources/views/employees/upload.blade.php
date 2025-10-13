<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      Employees • Upload / Import
    </h2>
  </x-slot>

  <div class="py-6">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6">

        {{-- Flash messages --}}
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
        <form id="employees-upload-form"
              action="{{ route('employees.upload') }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-3 border rounded p-4 dark:border-gray-700">
          @csrf
          <div>
            <label class="block text-sm mb-1">Upload Excel (.xlsx / .xls)</label>
            <input type="file" name="file" required
                   class="w-full border rounded p-2 dark:bg-gray-900 dark:border-gray-700">

            <p class="text-xs text-gray-500 mt-1">
              Columns must match the template headings.
              If <strong>shift_window_id</strong> is blank, the system assigns
              <strong>“8-5 Standard” (Shift ID 2)</strong> automatically.
              Email may be blank as long as <strong>school_id</strong> is present.
            </p>
          </div>

          {{-- Loading bar (hidden until submit) --}}
          <div id="upload-progress" class="hidden">
            <div class="w-full bg-gray-200 dark:bg-gray-900 rounded h-2 overflow-hidden">
              <div id="upload-progress-bar"
                   class="h-2 bg-blue-600 transition-all duration-200"
                   style="width:0%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1" id="upload-progress-text">Uploading… please wait</p>
          </div>

          <div class="text-right">
            <button id="upload-submit" class="px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white">
              Import
            </button>
          </div>
        </form>

        {{-- Summary after upload --}}
        @php $sum = session('import_summary'); @endphp
        @if($sum)
          <div class="rounded border border-gray-200 dark:border-gray-700 p-4">
            <h4 class="font-semibold mb-2">Import Summary</h4>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
              <div class="p-2 rounded bg-green-50 border border-green-200 text-green-800">
                <div class="font-medium">Created</div>
                <div class="text-lg">{{ $sum['created'] ?? 0 }}</div>
              </div>
              <div class="p-2 rounded bg-blue-50 border border-blue-200 text-blue-800">
                <div class="font-medium">Updated</div>
                <div class="text-lg">{{ $sum['updated'] ?? 0 }}</div>
              </div>
              <div class="p-2 rounded bg-amber-50 border border-amber-200 text-amber-800">
                <div class="font-medium">Duplicated</div>
                <div class="text-lg">{{ $sum['duplicated'] ?? 0 }}</div>
              </div>
              <div class="p-2 rounded bg-gray-50 border border-gray-200 text-gray-800">
                <div class="font-medium">Skipped</div>
                <div class="text-lg">{{ $sum['skipped'] ?? 0 }}</div>
              </div>
              <div class="p-2 rounded bg-red-50 border border-red-200 text-red-800">
                <div class="font-medium">Failed</div>
                <div class="text-lg">{{ $sum['failed'] ?? 0 }}</div>
              </div>
            </div>

            @if(!empty($sum['fail_messages']))
              <details class="mt-3">
                <summary class="cursor-pointer text-sm text-red-700">
                  See first {{ min(count($sum['fail_messages']), 10) }} errors
                </summary>
                <ul class="mt-2 text-xs list-disc list-inside text-red-700">
                  @foreach(array_slice($sum['fail_messages'], 0, 10) as $msg)
                    <li>{{ $msg }}</li>
                  @endforeach
                </ul>
              </details>
            @endif
          </div>
        @endif

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

  <script>
    (function () {
      const form = document.getElementById('employees-upload-form');
      const barWrap = document.getElementById('upload-progress');
      const bar = document.getElementById('upload-progress-bar');
      const btn = document.getElementById('upload-submit');
      const text = document.getElementById('upload-progress-text');

      if (!form) return;

      form.addEventListener('submit', function () {
        barWrap.classList.remove('hidden');
        btn.disabled = true;
        btn.classList.add('opacity-60','cursor-not-allowed');

        // Smooth fake-progress while waiting for server response (full page reload)
        let pct = 0;
        const tick = () => {
          pct = Math.min(98, pct + Math.random() * 6);
          bar.style.width = pct.toFixed(0) + '%';
        };
        const i = setInterval(tick, 180);
        window.addEventListener('beforeunload', () => clearInterval(i));
      });
    })();
  </script>
</x-app-layout>
