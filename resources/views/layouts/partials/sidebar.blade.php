{{-- Role-aware sidebar --}}
<aside class="hidden md:block w-64 shrink-0 bg-white border-r h-screen sticky top-0">
  <div class="p-4 border-b">
    <div class="text-lg font-semibold">HR-Attendance</div>
    <div class="text-xs text-gray-500">v1</div>
  </div>

  <nav class="p-3 text-sm space-y-4">

    {{-- Always visible --}}
    <div>
      <div class="px-3 text-gray-500 uppercase text-xs mb-1">General</div>
      <a href="{{ route('dashboard') }}"
         class="block px-3 py-2 rounded {{ request()->routeIs('dashboard') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' }}">
        Dashboard
      </a>
    </div>

    {{-- Reports: GIA Staff + HR + Admins --}}
    @can('reports.view.org')
  <div>
    <div class="px-3 text-gray-500 uppercase text-xs mb-1">Reports</div>

    {{-- Attendance Report (detailed) --}}
    <a href="{{ route('reports.attendance') }}"
       class="block px-3 py-2 rounded {{ request()->routeIs('reports.attendance') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' }}">
      Attendance Report
    </a>

    {{-- Attendance Report Summary (fix/check page) --}}
    <a href="{{ route('reports.attendance.summary') }}"
       class="block px-3 py-2 rounded {{ request()->routeIs('reports.attendance.summary') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' }}">
      Attendance Report Summary
    </a>

    @can('reports.export')
      <a href="{{ route('reports.attendance', request()->query()) }}"
         class="block px-3 py-2 rounded hover:bg-gray-50">
        Download (Excel)
      </a>
    @endcan
  </div>
@endcan

    {{-- HR Officer modules --}}
  @role('HR Officer|Administrator|IT Admin')
  <div>
    <div class="px-3 text-gray-500 uppercase text-xs mb-1">HR Modules</div>

    <a href="{{ route('employees.index') }}"
      class="block px-3 py-2 rounded {{ request()->routeIs('employees.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' }}">
      Employees
    </a>


    @can('schedules.manage')
    <a href="{{ route('shiftwindows.index') }}"
      class="block px-3 py-2 rounded {{ request()->routeIs('shiftwindows.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' }}">
      Duty Schedules
    </a>
    @endcan

    {{-- NEW: Departments --}}
    @can('departments.manage')
    <a href="{{ route('departments.index') }}"
      class="block px-3 py-2 rounded {{ request()->routeIs('departments.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' }}">
      Departments
    </a>
    @endcan

        {{-- NEW: Holidays --}}
    @can('holidays.manage')
      <a href="{{ route('holidays.index') }}"
        class="block px-3 py-2 rounded
                {{ request()->routeIs('holidays.*')
                    ? 'bg-gray-100 dark:bg-gray-700 font-medium'
                    : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}">
        Holidays
      </a>
    @endcan




    @can('attendance.edit')
    <a href="{{ route('attendance.editor') }}"
      class="block px-3 py-2 rounded {{ request()->routeIs('attendance.editor*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-50' }}">
      Edit Attendance
    </a>
    @endcan
  </div>
  @endrole


    {{-- Admin / IT (optional extra area) --}}
    @role('Administrator|IT Admin')
    <div>
      <div class="px-3 text-gray-500 uppercase text-xs mb-1">Administration</div>
      {{-- Placeholder links you can wire later --}}
      <a href="#" class="block px-3 py-2 rounded hover:bg-gray-50">Device Settings</a>
      <a href="#" class="block px-3 py-2 rounded hover:bg-gray-50">Roles & Permissions</a>
    </div>
    @endrole

  </nav>
</aside>
