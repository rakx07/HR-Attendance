@php
  // Display helper for 12h
  $to12 = function ($t) {
    if (!$t) return '—';
    [$h,$m] = array_pad(explode(':',$t,3),2,'00');
    $h = (int)$h; $m = (int)$m;
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h = $h % 12; if ($h === 0) $h = 12;
    return sprintf('%d:%02d %s', $h, $m, $ampm);
  };
@endphp

<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Shift Windows</h2></x-slot>

  <div class="py-6 max-w-6xl mx-auto">
    @if(session('success'))
      <div class="mb-3 rounded border border-green-300 bg-green-50 text-green-800 px-3 py-2 text-sm">
        {{ session('success') }}
      </div>
    @endif

    <div class="mb-3">
      <a href="{{ route('shiftwindows.create') }}" class="px-3 py-2 bg-blue-600 text-white rounded">Add Shift</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="p-2 text-left">Name</th>
            <th class="p-2 text-left">Grace (min)</th>
            <th class="p-2 text-left">AM Window</th>
            <th class="p-2 text-left">PM Window</th>
            <th class="p-2 text-right">Days</th>
            <th class="p-2 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y dark:divide-gray-700">
          @foreach($rows as $s)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
              <td class="p-2">{{ $s->name }}</td>
              <td class="p-2">{{ $s->grace_minutes }}</td>
              <td class="p-2">
                {{ $to12($s->am_in_start) }}–{{ $to12($s->am_in_end) }}
                /
                {{ $to12($s->am_out_start) }}–{{ $to12($s->am_out_end) }}
              </td>
              <td class="p-2">
                {{ $to12($s->pm_in_start) }}–{{ $to12($s->pm_in_end) }}
                /
                {{ $to12($s->pm_out_start) }}–{{ $to12($s->pm_out_end) }}
              </td>
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
