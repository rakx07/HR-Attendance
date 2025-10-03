<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Department History — {{ $user->last_name }}, {{ $user->first_name }}</h2></x-slot>

  <div class="p-6 bg-white dark:bg-gray-800 rounded shadow max-w-4xl mx-auto">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">Effective</th>
            <th class="px-3 py-2 text-left">From</th>
            <th class="px-3 py-2 text-left">To</th>
            <th class="px-3 py-2 text-left">Reason</th>
            <th class="px-3 py-2 text-left">By</th>
          </tr>
        </thead>
        <tbody>
          @forelse($history as $h)
            <tr class="border-t">
              <td class="px-3 py-2">{{ $h->effective_at->format('Y-m-d H:i') }}</td>
              <td class="px-3 py-2">{{ $h->from?->name ?? '—' }}</td>
              <td class="px-3 py-2">{{ $h->to?->name }}</td>
              <td class="px-3 py-2">{{ $h->reason }}</td>
              <td class="px-3 py-2">{{ $h->author?->first_name }} {{ $h->author?->last_name }}</td>
            </tr>
          @empty
            <tr><td class="px-3 py-6 text-gray-500" colspan="5">No transfers yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
