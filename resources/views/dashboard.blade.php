{{-- resources/views/dashboard.blade.php --}}
<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Dashboard</h2></x-slot>

  <div class="py-6 max-w-7xl mx-auto grid md:grid-cols-2 gap-6">

    {{-- GIA Staff: Download reports --}}
    @role('GIA Staff|Administrator|IT Admin')
    <div class="bg-white rounded shadow p-5">
      <h3 class="text-lg font-semibold mb-2">Reports (GIA)</h3>
      <p class="text-sm text-gray-600 mb-3">Download attendance with filters.</p>
      <a href="{{ route('reports.attendance') }}" class="px-4 py-2 bg-blue-600 text-white rounded">Open Reports</a>
    </div>
    @endrole

    {{-- HR Officer: Employees, Schedules, Upload, Edit Attendance --}}
    @role('HR Officer|Administrator|IT Admin')
    <div class="bg-white rounded shadow p-5">
      <h3 class="text-lg font-semibold mb-2">HR: Employees</h3>
      <div class="space-x-2">
        <a href="{{ route('employees.index') }}" class="btn">Manage Employees</a>
        <a href="{{ route('shiftwindows.index') }}" class="btn">Duty Schedules</a>
        <a href="{{ route('attendance.editor') }}" class="btn">Edit Attendance</a>
      </div>
      <style>.btn{ @apply inline-block px-3 py-2 bg-gray-800 text-white rounded hover:bg-black; }</style>
    </div>
    @endrole

  </div>
</x-app-layout>
