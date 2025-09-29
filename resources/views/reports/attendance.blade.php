{{-- resources/views/reports/attendance.blade.php --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Attendance Report</h2></x-slot>
    <div class="py-6 max-w-7xl mx-auto">
        <form method="GET" class="bg-white p-4 rounded shadow grid md:grid-cols-6 gap-3 mb-4">
            <input class="border rounded px-2 py-1" type="date" name="from" value="{{ request('from') }}">
            <input class="border rounded px-2 py-1" type="date" name="to" value="{{ request('to') }}">
            <input class="border rounded px-2 py-1" type="text" name="dept" placeholder="Department" value="{{ request('dept') }}">
            <input class="border rounded px-2 py-1" type="number" name="employee_id" placeholder="Employee ID" value="{{ request('employee_id') }}">
            <select class="border rounded px-2 py-1" name="status">
                <option value="">-- Status --</option>
                @foreach(['Present','Absent','Incomplete'] as $s)
                  <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 bg-blue-600 text-white rounded">Filter</button>
        </form>

        @can('reports.export')
        <a class="px-4 py-2 bg-green-600 text-white rounded"
           href="{{ route('reports.attendance.export', request()->query()) }}">
            Download Excel
        </a>
        @endcan

        <div class="mt-3 bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2">Date</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Dept</th>
                    <th class="px-3 py-2">AM In</th><th class="px-3 py-2">AM Out</th>
                    <th class="px-3 py-2">PM In</th><th class="px-3 py-2">PM Out</th>
                    <th class="px-3 py-2 text-right">Late</th><th class="px-3 py-2 text-right">Undertime</th>
                    <th class="px-3 py-2 text-right">Hours</th><th class="px-3 py-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                  <tr class="border-t">
                    <td class="px-3 py-2">{{ $r->work_date }}</td>
                    <td class="px-3 py-2">{{ $r->name }}</td>
                    <td class="px-3 py-2">{{ $r->department }}</td>
                    <td class="px-3 py-2">{{ $r->am_in }}</td>
                    <td class="px-3 py-2">{{ $r->am_out }}</td>
                    <td class="px-3 py-2">{{ $r->pm_in }}</td>
                    <td class="px-3 py-2">{{ $r->pm_out }}</td>
                    <td class="px-3 py-2 text-right">{{ $r->late_minutes }}</td>
                    <td class="px-3 py-2 text-right">{{ $r->undertime_minutes }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($r->total_hours,2) }}</td>
                    <td class="px-3 py-2">{{ $r->status }}</td>
                  </tr>
                @empty
                  <tr><td colspan="11" class="px-3 py-6 text-center text-gray-500">No records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
