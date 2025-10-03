<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Departments</h2></x-slot>

  <div class="p-6 bg-white dark:bg-gray-800 rounded shadow max-w-5xl mx-auto">
    @if(session('success')) <div class="mb-3 text-green-700">{{ session('success') }}</div> @endif

    {{-- Create --}}
    <form method="POST" action="{{ route('departments.store') }}" class="grid md:grid-cols-5 gap-3 mb-6">
      @csrf
      <input name="name" class="border rounded px-2 py-1 md:col-span-2" placeholder="Name" required>
      <input name="code" class="border rounded px-2 py-1" placeholder="Code (optional)">
      <input name="description" class="border rounded px-2 py-1 md:col-span-2" placeholder="Description (optional)">
      <label class="flex items-center gap-2"><input type="checkbox" name="active" checked> Active</label>
      <button class="px-4 py-2 bg-blue-600 text-white rounded md:col-start-5">Add</button>
    </form>

    {{-- List --}}
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">Name</th>
            <th class="px-3 py-2 text-left">Code</th>
            <th class="px-3 py-2 text-left">Active</th>
            <th class="px-3 py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $d)
          <tr class="border-t">
            <td class="px-3 py-2">{{ $d->name }}</td>
            <td class="px-3 py-2">{{ $d->code }}</td>
            <td class="px-3 py-2">{{ $d->active ? 'Yes' : 'No' }}</td>
            <td class="px-3 py-2">
              <form method="POST" action="{{ route('departments.update',$d) }}" class="inline">
                @csrf @method('PATCH')
                <input type="hidden" name="name" value="{{ $d->name }}">
                <input type="hidden" name="code" value="{{ $d->code }}">
                <input type="hidden" name="description" value="{{ $d->description }}">
                <input type="hidden" name="active" value="{{ $d->active ? 0 : 1 }}">
                <button class="text-indigo-600 underline mr-3" title="Toggle Active">
                  {{ $d->active ? 'Deactivate' : 'Activate' }}
                </button>
              </form>

              <form method="POST" action="{{ route('departments.destroy',$d) }}" class="inline" onsubmit="return confirm('Delete department?')">
                @csrf @method('DELETE')
                <button class="text-red-600 underline">Delete</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $rows->links() }}</div>

    <hr class="my-6">

    {{-- Quick transfer widget --}}
    <h3 class="font-semibold mb-2">Transfer Employee</h3>
    <form method="POST" action="{{ route('departments.transfer') }}" class="grid md:grid-cols-6 gap-3">
      @csrf
      <select name="user_id" class="border rounded px-2 py-1 md:col-span-2" required>
        <option value="">-- choose employee --</option>
        @foreach(\App\Models\User::orderBy('last_name')->orderBy('first_name')->get(['id','first_name','middle_name','last_name']) as $u)
          <option value="{{ $u->id }}">{{ $u->last_name }}, {{ $u->first_name }} {{ $u->middle_name }}</option>
        @endforeach
      </select>

      <select name="to_id" class="border rounded px-2 py-1 md:col-span-2" required>
        <option value="">-- to department --</option>
        @foreach(\App\Models\Department::where('active',1)->orderBy('name')->get() as $d)
          <option value="{{ $d->id }}">{{ $d->name }}</option>
        @endforeach
      </select>

      <input type="datetime-local" name="effective_at" class="border rounded px-2 py-1" value="{{ now()->format('Y-m-d\TH:i') }}" required>
      <button class="px-4 py-2 bg-blue-600 text-white rounded">Transfer</button>

      <textarea name="reason" class="border rounded px-2 py-1 md:col-span-6" placeholder="Reason (optional)"></textarea>
    </form>
  </div>
</x-app-layout>
