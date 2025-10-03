@php
  $labels = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
@endphp

<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">{{ $sw->exists ? 'Edit Shift' : 'Create Shift' }}</h2>
  </x-slot>

  <div class="py-6 max-w-5xl mx-auto">
    <div class="bg-white rounded shadow p-6 space-y-4">

      @if($errors->any())
        <div class="rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm">
          <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif

      <form method="POST" action="{{ $sw->exists ? route('shiftwindows.update',$sw) : route('shiftwindows.store') }}">
        @csrf
        @if($sw->exists) @method('PUT') @endif

        <div class="grid md:grid-cols-3 gap-3">
          <div>
            <label class="block text-sm mb-1">Name</label>
            <input class="w-full border rounded px-2 py-1" name="name" value="{{ old('name',$sw->name) }}" required>
          </div>
          <div>
            <label class="block text-sm mb-1">Grace (minutes)</label>
            <input type="number" min="0" class="w-full border rounded px-2 py-1" name="grace_minutes" value="{{ old('grace_minutes',$sw->grace_minutes ?? 0) }}">
          </div>
        </div>

        <h3 class="font-semibold mt-4">Global Windows (validation window)</h3>
        <div class="grid md:grid-cols-4 gap-3">
          <div><label class="block text-sm mb-1">AM In Start</label>
            <input class="w-full border rounded px-2 py-1" name="am_in_start" value="{{ old('am_in_start',$sw->am_in_start) }}" placeholder="07:00" required></div>
          <div><label class="block text-sm mb-1">AM In End</label>
            <input class="w-full border rounded px-2 py-1" name="am_in_end" value="{{ old('am_in_end',$sw->am_in_end) }}" placeholder="09:00" required></div>
          <div><label class="block text-sm mb-1">AM Out Start</label>
            <input class="w-full border rounded px-2 py-1" name="am_out_start" value="{{ old('am_out_start',$sw->am_out_start) }}" placeholder="11:00" required></div>
          <div><label class="block text-sm mb-1">AM Out End</label>
            <input class="w-full border rounded px-2 py-1" name="am_out_end" value="{{ old('am_out_end',$sw->am_out_end) }}" placeholder="12:30" required></div>

          <div><label class="block text-sm mb-1">PM In Start</label>
            <input class="w-full border rounded px-2 py-1" name="pm_in_start" value="{{ old('pm_in_start',$sw->pm_in_start) }}" placeholder="13:00" required></div>
          <div><label class="block text-sm mb-1">PM In End</label>
            <input class="w-full border rounded px-2 py-1" name="pm_in_end" value="{{ old('pm_in_end',$sw->pm_in_end) }}" placeholder="14:00" required></div>
          <div><label class="block text-sm mb-1">PM Out Start</label>
            <input class="w-full border rounded px-2 py-1" name="pm_out_start" value="{{ old('pm_out_start',$sw->pm_out_start) }}" placeholder="17:00" required></div>
          <div><label class="block text-sm mb-1">PM Out End</label>
            <input class="w-full border rounded px-2 py-1" name="pm_out_end" value="{{ old('pm_out_end',$sw->pm_out_end) }}" placeholder="18:00" required></div>
        </div>

        <h3 class="font-semibold mt-6">Per-day Schedule</h3>
        <div class="overflow-x-auto border rounded">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr><th class="p-2">Day</th><th class="p-2">Working?</th><th class="p-2">AM In</th><th class="p-2">AM Out</th><th class="p-2">PM In</th><th class="p-2">PM Out</th></tr>
            </thead>
            <tbody class="divide-y">
              @for($d=1;$d<=7;$d++)
                @php $day = $sw->exists ? optional($sw->days->firstWhere('dow',$d)) : null; @endphp
                <tr>
                  <td class="p-2">{{ $labels[$d] }}</td>
                  <td class="p-2">
                    <input type="hidden" name="days[{{ $d }}][is_working]" value="0">
                    <input type="checkbox" name="days[{{ $d }}][is_working]" value="1"
                           @checked($day ? $day->is_working : in_array($d,[1,2,3,4,5]))>
                  </td>
                  <td class="p-2"><input class="border rounded px-2 py-1 w-28" name="days[{{ $d }}][am_in]"  value="{{ old('days.'.$d.'.am_in',  $day->am_in ?? '') }}"  placeholder="07:30"></td>
                  <td class="p-2"><input class="border rounded px-2 py-1 w-28" name="days[{{ $d }}][am_out]" value="{{ old('days.'.$d.'.am_out', $day->am_out ?? '') }}" placeholder="11:30"></td>
                  <td class="p-2"><input class="border rounded px-2 py-1 w-28" name="days[{{ $d }}][pm_in]"  value="{{ old('days.'.$d.'.pm_in',  $day->pm_in ?? '') }}"  placeholder="13:00"></td>
                  <td class="p-2"><input class="border rounded px-2 py-1 w-28" name="days[{{ $d }}][pm_out]" value="{{ old('days.'.$d.'.pm_out', $day->pm_out ?? '') }}" placeholder="17:00"></td>
                </tr>
              @endfor
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex gap-2">
          <a href="{{ route('shiftwindows.index') }}" class="px-3 py-2 bg-gray-600 text-white rounded">Cancel</a>
          <button class="px-3 py-2 bg-blue-600 text-white rounded">{{ $sw->exists ? 'Save' : 'Create' }}</button>
        </div>
      </form>
    </div>
  </div>
</x-app-layout>
