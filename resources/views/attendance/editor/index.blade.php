{{-- resources/views/attendance/editor/index.blade.php --}}
<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Edit Attendance</h2></x-slot>

  <div class="p-6 max-w-5xl mx-auto bg-white rounded shadow">
    <form method="GET" action="{{ route('attendance.editor') }}" id="filterForm" class="grid md:grid-cols-6 gap-3 items-end">
      {{-- Employee --}}
      <label class="col-span-2">
        <span class="block text-sm text-gray-700 mb-1">Employee</span>
        <select name="user_id" id="user_id" class="border rounded px-2 py-1 w-full">
          <option value="">-- choose --</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
          @endforeach
        </select>
      </label>

      {{-- Range --}}
      <label>
        <span class="block text-sm text-gray-700 mb-1">Range</span>
        <select name="range" id="range" class="border rounded px-2 py-1 w-full">
          @php $range = $filters['range'] ?? 'day'; @endphp
          <option value="day"   @selected($range==='day')>Day</option>
          <option value="week"  @selected($range==='week')>Week</option>
          <option value="month" @selected($range==='month')>Month</option>
          <option value="custom"@selected($range==='custom')>Custom</option>
        </select>
      </label>

      {{-- Anchor date (for day/week/month) --}}
      <label id="anchorWrap" class="@if(($filters['range'] ?? 'day')==='custom') hidden @endif">
        <span class="block text-sm text-gray-700 mb-1">Date</span>
        <input type="date" name="date" id="date" class="border rounded px-2 py-1 w-full"
               value="{{ $filters['date'] ?? now()->toDateString() }}">
      </label>

      {{-- Custom range --}}
      <label id="fromWrap" class="@if(($filters['range'] ?? 'day')!=='custom') hidden @endif">
        <span class="block text-sm text-gray-700 mb-1">From</span>
        <input type="date" name="from" class="border rounded px-2 py-1 w-full" value="{{ $filters['from'] ?? '' }}">
      </label>
      <label id="toWrap" class="@if(($filters['range'] ?? 'day')!=='custom') hidden @endif">
        <span class="block text-sm text-gray-700 mb-1">To</span>
        <input type="date" name="to" class="border rounded px-2 py-1 w-full" value="{{ $filters['to'] ?? '' }}">
      </label>

      <div class="md:col-span-1">
        <button class="px-4 py-2 bg-blue-600 text-white rounded w-full">Filter</button>
      </div>
    </form>
  </div>

  @if(($filters['user_id'] ?? null) && $rows->count())
    <div class="mt-4 p-6 max-w-5xl mx-auto bg-white rounded shadow">
      <div class="mb-3 text-sm text-gray-600">
        Showing <strong>{{ $rows->total() }}</strong> record(s)
        @if(($filters['from'] ?? null) && ($filters['to'] ?? null))
          from <strong>{{ $filters['from'] }}</strong> to <strong>{{ $filters['to'] }}</strong>.
        @endif
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">AM In</th>
              <th class="px-3 py-2 text-left">AM Out</th>
              <th class="px-3 py-2 text-left">PM In</th>
              <th class="px-3 py-2 text-left">PM Out</th>
              <th class="px-3 py-2 text-right">Hours</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
          @foreach($rows as $r)
            <tr class="border-t">
              <td class="px-3 py-2">{{ $r->work_date }}</td>
              <td class="px-3 py-2">{{ $r->am_in }}</td>
              <td class="px-3 py-2">{{ $r->am_out }}</td>
              <td class="px-3 py-2">{{ $r->pm_in }}</td>
              <td class="px-3 py-2">{{ $r->pm_out }}</td>
              <td class="px-3 py-2 text-right">{{ number_format($r->total_hours, 2) }}</td>
              <td class="px-3 py-2">{{ $r->status }}</td>
              <td class="px-3 py-2">
                <a class="text-blue-600 underline"
                   href="{{ route('attendance.editor.edit', [$filters['user_id'], $r->work_date]) }}">
                  Edit
                </a>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $rows->links() }}
      </div>
    </div>
  @elseif(($filters['user_id'] ?? null))
    <div class="mt-4 p-6 max-w-5xl mx-auto bg-white rounded shadow text-gray-600">
      No attendance found for the selected period.
    </div>
  @endif

  <script>
    // Toggle anchor date vs. custom range
    (function(){
      const range = document.getElementById('range');
      const anchorWrap = document.getElementById('anchorWrap');
      const fromWrap = document.getElementById('fromWrap');
      const toWrap = document.getElementById('toWrap');

      function toggle() {
        const isCustom = range.value === 'custom';
        anchorWrap.classList.toggle('hidden', isCustom);
        fromWrap.classList.toggle('hidden', !isCustom);
        toWrap.classList.toggle('hidden', !isCustom);
      }
      range.addEventListener('change', toggle);
      toggle();
    })();
  </script>
</x-app-layout>
