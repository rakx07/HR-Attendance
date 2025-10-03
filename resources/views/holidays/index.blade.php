<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Holiday Calendars</h2></x-slot>

  <div class="py-6 max-w-6xl mx-auto space-y-6">

    @if(session('success'))
      <div class="rounded border border-green-300 bg-green-50 text-green-800 px-3 py-2 text-sm">
        {{ session('success') }}
      </div>
    @endif

    {{-- Create / Copy --}}
    <div class="bg-white dark:bg-gray-800 rounded shadow p-4">
      <form method="POST" action="{{ route('holidays.store') }}" class="grid md:grid-cols-6 gap-3">
        @csrf
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Year</label>
          <input type="number" name="year" required class="w-full border rounded px-2 py-1"
                 value="{{ old('year', now()->year) }}">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Status</label>
          <select name="status" class="w-full border rounded px-2 py-1">
            <option value="draft">Draft</option>
            <option value="active">Active</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Copy from year (optional)</label>
          <input type="number" name="copy_from_year" class="w-full border rounded px-2 py-1"
                 placeholder="{{ now()->year - 1 }}">
        </div>
        <div class="md:col-span-6 flex justify-end">
          <button class="px-3 py-2 bg-blue-600 text-white rounded">Create</button>
        </div>
      </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900">
        <tr>
          <th class="p-2 text-left">Year</th>
          <th class="p-2 text-left">Status</th>
          <th class="p-2 text-right">Holidays</th>
          <th class="p-2 text-center">Actions</th>
        </tr>
        </thead>
        <tbody class="divide-y dark:divide-gray-700">
        @foreach($calendars as $c)
          <tr>
            <td class="p-2">{{ $c->year }}</td>
            <td class="p-2">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs
                {{ $c->status === 'active'
                      ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                      : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                {{ ucfirst($c->status) }}
              </span>
            </td>
            <td class="p-2 text-right">{{ $c->dates_count }}</td>
            <td class="p-2 text-center">
              <a class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded"
                 href="{{ route('holidays.show',$c) }}">Manage</a>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    {{ $calendars->links() }}
  </div>
</x-app-layout>
