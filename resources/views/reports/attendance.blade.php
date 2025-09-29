<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Attendance Report
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" class="bg-white p-4 rounded-md shadow mb-4 grid grid-cols-1 md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-sm text-gray-600">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="w-full border rounded px-2 py-1">
                </div>
                <div>
                    <label class="block text-sm text-gray-600">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="w-full border rounded px-2 py-1">
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Department</label>
                    <input type="text" name="dept" value="{{ request('dept') }}" placeholder="e.g. IT" class="w-full border rounded px-2 py-1">
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Status</label>
                    <select name="status" class="w-full border rounded px-2 py-1">
                        <option value="">-- All --</option>
                        @foreach(['Present','Absent','Incomplete'] as $s)
                            <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Filter</button>
                </div>
            </form>

            {{-- Table --}}
            <div class="bg-white overflow-x-auto shadow rounded-md">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left">Department</th>
                            <th class="px-3 py-2 text-right">Late (min)</th>
                            <th class="px-3 py-2 text-right">Undertime (min)</th>
                            <th class="px-3 py-2 text-right">Hours</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr class="border-t">
                                <td class="px-3 py-2">{{ $r->work_date }}</td>
                                <td class="px-3 py-2">{{ $r->name }}</td>
                                <td class="px-3 py-2">{{ $r->department }}</td>
                                <td class="px-3 py-2 text-right">{{ $r->late_minutes }}</td>
                                <td class="px-3 py-2 text-right">{{ $r->undertime_minutes }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($r->total_hours,2) }}</td>
                                <td class="px-3 py-2">{{ $r->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-gray-500">No data for selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $rows->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
