<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Shift Windows</h2></x-slot>
  <div class="py-6 max-w-5xl mx-auto">
    @if(session('success'))
      <div class="mb-3 rounded border border-green-300 bg-green-50 text-green-800 px-3 py-2 text-sm">
        {{ session('success') }}
      </div>
    @endif

    <div class="mb-3">
      <a href="{{ route('shiftwindows.create') }}" class="px-3 py-2 bg-blue-600 text-white rounded">Add Shift</a>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="p-2 text-left">Name</th>
            <th class="p-2 text-left">Grace (min)</th>
            <th class="p-2 text-left">AM Window</th>
            <th class="p-2 text-left">PM Window</th>
            <th class="p-2 text-right">Days</th>
            <th class="p-2 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @foreach($rows as $s)
            <tr>
              <td class="p-2">{{ $s->name }}</td>
              <td class="p-2">{{ $s->grace_minutes }}</td>
              <td class="p-2">{{ $s->am_in_start }}–{{ $s->am_in_end }} / {{ $s->am_out_start }}–{{ $s->am_out_end }}</td>
              <td class="p-2">{{ $s->pm_in_start }}–{{ $s->pm_in_end }} / {{ $s->pm_out_start }}–{{ $s->pm_out_end }}</td>
              <td class="p-2 text-right">{{ $s->days_count }}</td>
              <td class="p-2 text-center">
                <a href="{{ route('shiftwindows.edit',$s) }}" class="px-2 py-1 bg-yellow-500 text-white rounded">Edit</a>
                <form action="{{ route('shiftwindows.destroy',$s) }}" method="POST" class="inline"
                      onsubmit="return confirm('Delete this shift?');">
                  @csrf @method('DELETE')
                  <button class="px-2 py-1 bg-red-600 text-white rounded">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $rows->links() }}</div>
  </div>
</x-app-layout>
