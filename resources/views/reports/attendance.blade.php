{{-- resources/views/reports/attendance.blade.php --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Attendance Report</h2></x-slot>

    <div class="py-6 max-w-7xl mx-auto" x-data="{ mode: '{{ request('mode', 'all_active') }}' }">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white p-4 rounded shadow grid md:grid-cols-12 gap-3 mb-4">
            {{-- DATE RANGE --}}
            <div class="md:col-span-2">
                <label class="text-xs text-gray-600">From</label>
                <input class="border rounded px-2 py-1 w-full" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-gray-600">To</label>
                <input class="border rounded px-2 py-1 w-full" type="date" name="to" value="{{ request('to') }}">
            </div>

            {{-- MODE TOGGLE --}}
            <div class="md:col-span-3">
                <label class="text-xs text-gray-600 block mb-1">Mode</label>
                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center gap-1">
                        <input type="radio" name="mode" value="all_active" x-model="mode"> <span>All Active</span>
                    </label>
                    <label class="inline-flex items-center gap-1">
                        <input type="radio" name="mode" value="employee" x-model="mode"> <span>Single Employee</span>
                    </label>
                </div>
            </div>

            {{-- EMPLOYEE PICKER (only when Single Employee) --}}
            <div class="md:col-span-3" x-show="mode === 'employee'">
                <label class="text-xs text-gray-600">Employee</label>
                <select class="border rounded px-2 py-1 w-full" name="employee_id">
                    <option value="">-- Select Employee --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" @selected(request('employee_id') == $emp->id)>
                            {{ $emp->name }} ({{ $emp->department }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- OPTIONAL FILTERS --}}
            <div class="md:col-span-2">
                <label class="text-xs text-gray-600">Department</label>
                <input class="border rounded px-2 py-1 w-full" type="text" name="dept" placeholder="Department" value="{{ request('dept') }}">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-gray-600">Status</label>
                <select class="border rounded px-2 py-1 w-full" name="status">
                    <option value="">-- Status --</option>
                    @foreach(['Present','Absent','Incomplete'] as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            {{-- SUBMIT --}}
            <div class="md:col-span-2 flex items-end">
                <button class="px-4 py-2 bg-blue-600 text-white rounded w-full">Filter</button>
            </div>
        </form>

        {{-- EXPORT ACTIONS --}}
        <div class="flex gap-2 mb-3">
            @can('reports.export')
                <a class="px-4 py-2 bg-green-600 text-white rounded"
                   href="{{ route('reports.attendance.export', request()->query()) }}">
                    Download Excel
                </a>
            @endcan

            @can('reports.export')
                <a class="px-4 py-2 bg-gray-800 text-white rounded"
                   href="{{ route('reports.attendance.pdf', request()->query()) }}"
                   target="_blank">
                    Print PDF
                </a>
            @endcan
        </div>

        {{-- TABLE --}}
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Dept</th>
                    <th class="px-3 py-2">AM In</th>
                    <th class="px-3 py-2">AM Out</th>
                    <th class="px-3 py-2">PM In</th>
                    <th class="px-3 py-2">PM Out</th>
                    <th class="px-3 py-2 text-right">Late</th>
                    <th class="px-3 py-2 text-right">Undertime</th>
                    <th class="px-3 py-2 text-right">Hours</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $r->work_date }}</td>
                        <td class="px-3 py-2">{{ $r->name }}</td>
                        <td class="px-3 py-2">{{ $r->department }}</td>

                        {{-- 12-hour format with AM/PM --}}
                        <td class="px-3 py-2">{{ $r->am_in  ? \Carbon\Carbon::parse($r->am_in)->format('g:i A')  : '' }}</td>
                        <td class="px-3 py-2">{{ $r->am_out ? \Carbon\Carbon::parse($r->am_out)->format('g:i A') : '' }}</td>
                        <td class="px-3 py-2">{{ $r->pm_in  ? \Carbon\Carbon::parse($r->pm_in)->format('g:i A')  : '' }}</td>
                        <td class="px-3 py-2">{{ $r->pm_out ? \Carbon\Carbon::parse($r->pm_out)->format('g:i A') : '' }}</td>

                        <td class="px-3 py-2 text-right">{{ $r->late_minutes }}</td>
                        <td class="px-3 py-2 text-right">{{ $r->undertime_minutes }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($r->total_hours,2) }}</td>
                        <td class="px-3 py-2">{{ $r->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-3 py-6 text-center text-gray-500">
                            No records{{ request('from') && request('to') ? " for ".(request('mode')==='employee' ? 'selected employee' : 'all active')." between ".request('from')." and ".request('to') : '' }}.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $rows->links() }}</div>
    </div>

    {{-- Alpine.js (if you don’t already load it globally) --}}
    <script src="https://unpkg.com/alpinejs" defer></script>
</x-app-layout>
