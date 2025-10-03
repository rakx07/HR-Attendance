{{-- resources/views/departments/index.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Departments & Transfers</h2>
  </x-slot>

  @php
    // Filters
    $q        = request('q');
    $perPage  = (int) request('per_page', 10);
    $allowed  = [10,25,50,100];
    if (!in_array($perPage, $allowed, true)) { $perPage = 10; }

    // Data
    $employees = \App\Models\User::query()
      ->where('active', true)
      ->when($q, fn($qb) =>
        $qb->whereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,'')) LIKE ?", ["%{$q}%"])
           ->orWhere('email', 'like', "%{$q}%")
      )
      ->orderBy('last_name')
      ->orderBy('first_name')
      ->paginate($perPage)
      ->withQueryString();

    $activeDepts = \App\Models\Department::where('active', true)->orderBy('name')->get();
  @endphp

  <div class="p-6 bg-white dark:bg-gray-800 rounded shadow">
    {{-- Top controls: Left = search + per-page; Right = Manage button --}}
    <div class="flex items-end justify-between gap-4 flex-wrap mb-4">
      <form method="GET" action="{{ route('departments.index') }}" class="flex items-end gap-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Search</label>
          <input type="text" name="q" value="{{ $q }}" placeholder="Search employee..."
                 class="border rounded px-3 py-2 w-64">
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Rows per page</label>
          <select name="per_page" class="border rounded px-3 py-2" onchange="this.form.submit()">
            @foreach([10,25,50,100] as $n)
              <option value="{{ $n }}" @selected($perPage===$n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="self-end">
          <button class="px-4 py-2 bg-gray-700 text-white rounded">Apply</button>
        </div>
      </form>

      <div class="self-end">
        <button onclick="openDeptModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded shadow hover:bg-blue-700">
          Manage Departments
        </button>
      </div>
    </div>

    {{-- Flash / validation --}}
    @if(session('success')) <div class="mb-3 text-green-700">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="mb-3 text-red-700">{{ session('error') }}</div>   @endif
    @if($errors->any())
      <div class="mb-3 text-red-700 text-sm">
        <ul class="list-disc list-inside">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    {{-- Employees table --}}
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700">
          <tr>
            <th class="px-3 py-2 text-left">Name</th>
            <th class="px-3 py-2 text-left">Email</th>
            <th class="px-3 py-2 text-left">Department</th>
            <th class="px-3 py-2 text-center">Transfer</th>
          </tr>
        </thead>
        <tbody>
        @forelse($employees as $emp)
          <tr class="border-t">
            <td class="px-3 py-2">
              {{ $emp->last_name }}, {{ $emp->first_name }} {{ $emp->middle_name }}
            </td>
            <td class="px-3 py-2">{{ $emp->email }}</td>
            <td class="px-3 py-2">
              {{-- Prefer relation if present, fallback to legacy text column --}}
              {{ optional($emp->department)->name ?? ($emp->department ?? '-') }}
            </td>
            <td class="px-3 py-2">
              <form method="POST" action="{{ route('departments.transfer') }}"
                    class="flex flex-wrap gap-2 items-center justify-center">
                @csrf
                <input type="hidden" name="user_id" value="{{ $emp->id }}">

                <select name="to_id" class="border rounded px-2 py-1" required>
                  <option value="">-- choose --</option>
                  @foreach($activeDepts as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                  @endforeach
                </select>

                <input type="datetime-local" name="effective_at" required
                       class="border rounded px-2 py-1">

                <input type="text" name="reason" placeholder="Reason"
                       class="border rounded px-2 py-1 w-40">

                <button class="px-3 py-1 bg-blue-600 text-white rounded">Go</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">No active employees found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $employees->links() }}</div>
  </div>

  {{-- Modal: Manage Departments --}}
  <div id="deptModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-lg w-full max-w-3xl">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-semibold text-lg">Manage Departments</h3>
        <button onclick="closeDeptModal()" class="text-gray-500 hover:text-gray-800">✖</button>
      </div>

      {{-- Add Department --}}
      <form method="POST" action="{{ route('departments.store') }}" class="space-y-2 mb-5">
        @csrf
        <div class="grid md:grid-cols-2 gap-2">
          <input type="text" name="name" placeholder="Name" class="border rounded px-2 py-1" required>
          <input type="text" name="code" placeholder="Code" class="border rounded px-2 py-1">
          <input type="text" name="description" placeholder="Description" class="border rounded px-2 py-1 md:col-span-2">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="active" value="1" checked> <span>Active</span>
          </label>
        </div>
        <button class="px-4 py-2 bg-blue-600 text-white rounded">Add</button>
      </form>

      <hr class="my-4">

      {{-- Existing Departments (inline edit / deactivate) --}}
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-100 dark:bg-gray-700">
            <tr>
              <th class="px-2 py-1 text-left">Name</th>
              <th class="px-2 py-1 text-left">Code</th>
              <th class="px-2 py-1 text-left">Description</th>
              <th class="px-2 py-1 text-center">Active</th>
              <th class="px-2 py-1 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach(\App\Models\Department::orderBy('name')->get() as $d)
              <tr class="border-t">
                <form method="POST" action="{{ route('departments.update', $d) }}" class="contents">
                  @csrf @method('PATCH')
                  <td class="px-2 py-1">
                    <input type="text" name="name" value="{{ $d->name }}" class="border rounded px-2 py-1 w-full">
                  </td>
                  <td class="px-2 py-1">
                    <input type="text" name="code" value="{{ $d->code }}" class="border rounded px-2 py-1 w-full">
                  </td>
                  <td class="px-2 py-1">
                    <input type="text" name="description" value="{{ $d->description }}" class="border rounded px-2 py-1 w-full">
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input type="checkbox" name="active" value="1" @checked($d->active)>
                  </td>
                  <td class="px-2 py-1 text-center">
                    <button class="px-3 py-1 bg-gray-800 text-white rounded">Save</button>
                  </td>
                </form>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    function openDeptModal(){ document.getElementById('deptModal').classList.remove('hidden'); }
    function closeDeptModal(){ document.getElementById('deptModal').classList.add('hidden'); }
  </script>
</x-app-layout>
