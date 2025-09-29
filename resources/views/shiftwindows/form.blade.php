{{-- resources/views/shiftwindows/form.blade.php --}}
<x-app-layout>
 <x-slot name="header"><h2 class="font-semibold text-xl">Shift Window</h2></x-slot>
 <div class="p-6 max-w-3xl mx-auto bg-white rounded shadow">
  <form method="POST" action="{{ $sw->exists ? route('shiftwindows.update',$sw) : route('shiftwindows.store') }}">
    @csrf @if($sw->exists) @method('PUT') @endif
    <div class="grid md:grid-cols-2 gap-3">
      <label> Name <input class="w-full border rounded px-2 py-1" name="name" value="{{ old('name',$sw->name) }}"></label>
      <label> Grace (min) <input class="w-full border rounded px-2 py-1" name="grace_minutes" type="number" value="{{ old('grace_minutes',$sw->grace_minutes) }}"></label>

      @foreach(['am_in_start','am_in_end','am_out_start','am_out_end','pm_in_start','pm_in_end','pm_out_start','pm_out_end'] as $f)
        <label class="capitalize">{{ str_replace('_',' ',$f) }}
        <input class="w-full border rounded px-2 py-1" name="{{ $f }}" type="time" value="{{ old($f,$sw->$f) }}"></label>
      @endforeach
    </div>
    <div class="mt-4">
      <button class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
    </div>
  </form>
 </div>
</x-app-layout>
