<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Departments</h2></x-slot>

  <div class="p-6 grid lg:grid-cols-3 gap-6">

    {{-- CREATE / LIST --}}
    <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
      @if(session('success')) <div class="mb-3 text-green-700">{{ session('success') }}</div> @endif
      @if(session('error'))   <div class="mb-3 text-red-700">{{ session('error') }}</div>   @endif
      @if($errors->any())
        <div class="mb-3 text-red-700 text-sm">
          <ul class="list-disc list-inside">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
      @endif

      <h3 class="font-semibold mb-3">Add Department</h3>
      <form method="POST" action="{{ route('departments.store') }}" class="space-y-3">
        @csrf
        <label class="block">
          <span class="text-sm text-gray-700">Name</span>
          <input type="text" name="name" class="border rounded px-2 py-1 w-full" value="{{ old('name') }}" required>
        </label>

        <label class="block">
          <span class="text-sm text-gray-700">Code (optional)</span>
          <input type="text" name="code" class="border rounded px-2 py-1 w-full" value="{{ old('code') }}">
        </label>

        <label class="block">
          <span class="text-sm text-gray-700">Description (optional)</span>
          <input type="text" name="description" class="border rounded px-2 py-1 w-full" value="{{ old('description') }}">
        </label>

        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="active" value="1" {{ old('active', '1') ? 'checked' : '' }}>
          <span>Active</span>
        </label>

        <button class="px-4 py-2 bg-blue-600 text-white rounded">Add</button>
      </form>

      <hr class="my-5">

      <h3 class="font-semibold mb-3">Existing</h3>
      <div class="space-y-2">
        @forelse($rows as $d)
          <form method="POST" action="{{ route('departments.update', $d) }}" class="grid grid-cols-1 gap-2 md:grid-cols-5 items-center">
            @csrf @method('PATCH')
            <input type="text" name="name" class="border rounded px-2 py-1 md:col-span-2" value="{{ $d->name }}" required>
            <input type="text" name="code" class="border rounded px-2 py-1" value="{{ $d->code }}">
            <input type="text" name="description" class="border rounded px-2 py-1" value="{{ $d->description }}">
            <label class="inline-flex items-center gap-1">
              <input type="checkbox" name="active" value="1" @checked($d->active)> Active
            </label>
            <div class="md:col-span-5 flex gap-2">
              <button class="px-3 py-1 bg-gray-800 text-white rounded">Save</button>

              {{-- Optional delete button (comment out to disable) --}}
              <form method="POST" action="{{ route('departments.destroy', $d) }}" onsubmit="return confirm('Delete this department?')" class="inline">
                @csrf @method('DELETE')
                <button class="px-3 py-1 bg-red-600 text-white rounded">Delete</button>
              </form>
            </div>
          </form>
        @empty
          <div class="text-gray-500 text-sm">No departments yet.</div>
        @endforelse

        <div class="mt-3">
          {{ $rows->links() }}
        </div>
      </div>
    </div>

    {{-- TRANSFER UI (optional; uses your existing controller method) --}}
    <div class="bg-white dark:bg-gray-800 rounded shadow p-4 lg:col-span-2">
      <h3 class="font-semibold mb-3">Transfer Employee</h3>

      @php
        $users   = \App\Models\User::orderBy('last_name')->orderBy('first_name')->get(['id','first_name','middle_name','last_name','department_id','department']);
        $depts   = \App\Models\Department::orderBy('name')->get();
      @endphp

      <form method="POST" action="{{ route('departments.transfer') }}" class="grid md:grid-cols-4 gap-3">
        @csrf
        <label class="md:col-span-2">
          <span class="text-sm text-gray-700">Employee</span>
          <select name="user_id" class="border rounded px-2 py-1 w-full" required>
            <option value="">-- choose --</option>
            @foreach($users as $u)
              <option value="{{ $u->id }}">
                {{ $u->last_name }}, {{ $u->first_name }} {{ $u->middle_name }}
                @if($u->department) ({{ $u->department }}) @endif
              </option>
            @endforeach
          </select>
        </label>

        <label>
          <span class="text-sm text-gray-700">To Department</span>
          <select name="to_id" class="border rounded px-2 py-1 w-full" required>
            <option value="">-- choose --</option>
            @foreach($depts as $d)
              <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
          </select>
        </label>

        <label>
          <span class="text-sm text-gray-700">Effective</span>
          <input type="datetime-local" name="effective_at" class="border rounded px-2 py-1 w-full" required>
        </label>

        <label class="md:col-span-4">
          <span class="text-sm text-gray-700">Reason (optional)</span>
          <input type="text" name="reason" class="border rounded px-2 py-1 w-full" placeholder="Reason for transfer">
        </label>

        <div class="md:col-span-4">
          <button class="px-4 py-2 bg-blue-600 text-white rounded">Transfer</button>
        </div>
      </form>
    </div>

  </div>
</x-app-layout>
