{{-- resources/views/attendance/editor/edit.blade.php --}}
<x-app-layout>
 <x-slot name="header"><h2 class="font-semibold text-xl">Edit {{ $user->name }} — {{ $date }}</h2></x-slot>
 <div class="p-6 max-w-3xl mx-auto bg-white rounded shadow">
  @if(session('success')) <div class="mb-3 text-green-700">{{ session('success') }}</div> @endif
  <form method="POST" action="{{ route('attendance.editor.update', [$user->id, $date]) }}">
    @csrf
    <div class="grid md:grid-cols-2 gap-3">
      <label>AM In <input type="datetime-local" name="am_in" value="{{ $row?->am_in ? \Carbon\Carbon::parse($row->am_in)->format('Y-m-d\TH:i') : '' }}" class="border rounded px-2 py-1 w-full"></label>
      <label>AM Out<input type="datetime-local" name="am_out" value="{{ $row?->am_out ? \Carbon\Carbon::parse($row->am_out)->format('Y-m-d\TH:i') : '' }}" class="border rounded px-2 py-1 w-full"></label>
      <label>PM In <input type="datetime-local" name="pm_in" value="{{ $row?->pm_in ? \Carbon\Carbon::parse($row->pm_in)->format('Y-m-d\TH:i') : '' }}" class="border rounded px-2 py-1 w-full"></label>
      <label>PM Out<input type="datetime-local" name="pm_out" value="{{ $row?->pm_out ? \Carbon\Carbon::parse($row->pm_out)->format('Y-m-d\TH:i') : '' }}" class="border rounded px-2 py-1 w-full"></label>
    </div>
    <label class="block mt-3">Reason (for audit)
      <input name="reason" class="border rounded px-2 py-1 w-full">
    </label>
    <button class="mt-4 px-4 py-2 bg-blue-600 text-white rounded">Save Changes</button>
  </form>
 </div>
</x-app-layout>
