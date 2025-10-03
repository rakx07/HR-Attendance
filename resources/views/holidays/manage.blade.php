<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">
      Holidays — {{ $calendar->year }}
      <span class="ml-2 text-sm px-2 py-0.5 rounded
        {{ $calendar->status === 'active'
            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
            : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
        {{ ucfirst($calendar->status) }}
      </span>
    </h2>
  </x-slot>

  <div class="py-6 max-w-5xl mx-auto space-y-6">

    @if(session('success'))
      <div class="rounded border border-green-300 bg-green-50 text-green-800 px-3 py-2 text-sm">
        {{ session('success') }}
      </div>
    @endif

    <div class="flex items-center justify-between">
      <a href="{{ route('holidays.index') }}" class="px-3 py-2 bg-gray-600 text-white rounded">Back</a>

      @if($calendar->status !== 'active')
        <form method="POST" action="{{ route('holidays.activate',$calendar) }}">
          @csrf @method('PATCH')
          <button class="px-3 py-2 bg-emerald-600 text-white rounded"
                  onclick="return confirm('Activate this calendar?');">
            Set Active
          </button>
        </form>
      @endif
    </div>

    {{-- Add holiday --}}
    <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
      <form method="POST" action="{{ route('holidays.dates.store',$calendar) }}"
            class="grid md:grid-cols-6 gap-3">
        @csrf
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Date</label>
          <input type="date" name="date" class="w-full border rounded px-2 py-1"
                 min="{{ $calendar->year }}-01-01" max="{{ $calendar->year }}-12-31" required>
        </div>
        <div class="md:col-span-3">
          <label class="block text-sm mb-1">Name</label>
          <input name="name" class="w-full border rounded px-2 py-1" placeholder="Holiday name" required>
        </div>
        <div>
          <label class="block text-sm mb-1">Non-working?</label>
          <select name="is_non_working" class="w-full border rounded px-2 py-1">
            <option value="1" selected>Yes (exempt if no scans)</option>
            <option value="0">No (working day)</option>
          </select>
        </div>
        <div class="md:col-span-6 flex justify-end">
          <button class="px-3 py-2 bg-blue-600 text-white rounded">Add</button>
        </div>
      </form>
    </div>

    {{-- List --}}
    <div class="bg-white dark:bg-gray-800 rounded shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
        <tr>
          <th class="p-2 text-left">Date</th>
          <th class="p-2 text-left">Name</th>
          <th class="p-2 text-left">Type</th>
          <th class="p-2 text-center">Actions</th>
        </tr>
        </thead>
        <tbody class="divide-y dark:divide-gray-700">
        @forelse($calendar->dates as $d)
          <tr>
            <td class="p-2">{{ $d->date }}</td>
            <td class="p-2">{{ $d->name }}</td>
            <td class="p-2">
              {{ $d->is_non_working ? 'Non-working' : 'Working' }}
            </td>
            <td class="p-2 text-center">
              {{-- Inline edit --}}
              <form action="{{ route('holidays.dates.update', [$calendar, $d]) }}" method="POST" class="inline-flex items-center gap-2">
                @csrf @method('PATCH')
                <input type="date" name="date" value="{{ $d->date }}"
                       class="border rounded px-2 py-1" min="{{ $calendar->year }}-01-01" max="{{ $calendar->year }}-12-31">
                <input name="name" value="{{ $d->name }}" class="border rounded px-2 py-1">
                <select name="is_non_working" class="border rounded px-2 py-1">
                  <option value="1" @selected($d->is_non_working)>Non-working</option>
                  <option value="0" @selected(!$d->is_non_working)>Working</option>
                </select>
                <button class="px-2 py-1 bg-yellow-500 text-white rounded">Save</button>
              </form>

              <form action="{{ route('holidays.dates.destroy', [$calendar, $d]) }}" method="POST" class="inline"
                    onsubmit="return confirm('Delete this holiday?');">
                @csrf @method('DELETE')
                <button class="px-2 py-1 bg-red-600 text-white rounded">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="p-4 text-center text-gray-500">No holidays yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
