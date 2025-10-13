{{-- resources/views/reports/attendance.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Attendance Report</h2>
  </x-slot>

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

      {{-- EMPLOYEE PICKER --}}
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
          @foreach(['Present','Absent','Incomplete','Holiday','Late','Late/Undertime','Undertime'] as $s)
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

    {{-- SORT HELPERS --}}
    @php
      $sort = request('sort', 'date');
      $dir  = request('dir', $sort === 'name' ? 'asc' : 'desc');
      $toggle = fn($col) => request()->fullUrlWithQuery([
          'sort' => $col,
          'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
      ]);
      $arrow = function($col) use ($sort, $dir) {
          if ($sort !== $col) return '';
          return $dir === 'asc' ? '▲' : '▼';
      };
    @endphp

    {{-- ========== PAGE-SCOPED LOOKUPS (holidays + working-day flags) ========== --}}
    @php
      // Work on either paginator items or direct collection
      $pageItems = method_exists($rows, 'items') ? collect($rows->items()) : collect($rows);

      // Determine date window on THIS page
      $dates = $pageItems->pluck('work_date')->filter()->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString());
      $minDate = $dates->min();
      $maxDate = $dates->max();

      // Holidays for this page: map Y-m-d => (object)
      $holidayByDate = collect();
      if ($minDate && $maxDate) {
          $holidayByDate = \Illuminate\Support\Facades\DB::table('holiday_calendars as hc')
              ->join('holiday_dates as hd', 'hd.holiday_calendar_id', '=', 'hc.id')
              ->where('hc.status', 'active')
              ->whereBetween('hd.date', [$minDate, $maxDate])
              ->get(['hd.date','hd.name','hd.is_non_working'])
              ->keyBy(fn($h) => \Carbon\Carbon::parse($h->date)->toDateString());
      }

      // Build working-day map from shift_window_days:  $map[shift_window_id][dow] = 0/1
      $shiftIds = $pageItems->pluck('shift_window_id')->filter()->unique()->values();
      $shiftDayMap = []; // plain PHP array to avoid "indirect modification" issues
      if ($shiftIds->isNotEmpty()) {
          $rowsDays = \Illuminate\Support\Facades\DB::table('shift_window_days')
              ->whereIn('shift_window_id', $shiftIds)
              ->get(['shift_window_id','dow','is_working']);
          foreach ($rowsDays as $r) {
              $sid = (int)$r->shift_window_id;
              $dow = (int)$r->dow;           // 0=Sun..6=Sat
              $isW = (int)$r->is_working;    // 0/1
              if (!isset($shiftDayMap[$sid])) $shiftDayMap[$sid] = [];
              $shiftDayMap[$sid][$dow] = $isW;
          }
      }

      // Helper: is working day for this shift? default = Sunday off when unknown
      $isWorkingDay = function (?int $shiftId, \Carbon\Carbon $day) use ($shiftDayMap): bool {
          $dow = $day->dayOfWeek; // 0..6 (Sun..Sat)
          if ($shiftId && isset($shiftDayMap[$shiftId]) && array_key_exists($dow, $shiftDayMap[$shiftId])) {
              return (int)$shiftDayMap[$shiftId][$dow] === 1;
          }
          return $dow !== \Carbon\Carbon::SUNDAY;
      };
    @endphp

    {{-- TABLE --}}
    <div class="bg-white rounded shadow overflow-x-auto">
      <style>
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .nowrap { white-space: nowrap; }
        .chip { display:inline-block; padding:0 .4rem; border-radius:.375rem; font-size:.75rem; line-height:1.25rem }
      </style>

      @php
        $sumLate = 0; $sumUnder = 0; $sumHours = 0.0;
      @endphp

      <table class="min-w-full text-[13px]">
        <thead class="bg-gray-50">
        <tr class="text-left">
          <th class="px-3 py-2">
            <a href="{{ $toggle('date') }}" class="inline-flex items-center gap-1">
              Date <span class="text-gray-400">{{ $arrow('date') }}</span>
            </a>
          </th>
          <th class="px-3 py-2">
            <a href="{{ $toggle('name') }}" class="inline-flex items-center gap-1">
              Name <span class="text-gray-400">{{ $arrow('name') }}</span>
            </a>
          </th>
          <th class="px-3 py-2">Dept</th>
          <th class="px-3 py-2">AM In</th>
          <th class="px-3 py-2">AM Out</th>
          <th class="px-3 py-2">PM In</th>
          <th class="px-3 py-2">PM Out</th>
          <th class="px-3 py-2 text-right">Late (min)</th>
          <th class="px-3 py-2 text-right">Undertime (min)</th>
          <th class="px-3 py-2 text-right">Hours</th>
          <th class="px-3 py-2">Status</th>
          <th class="px-3 py-2">Remarks</th>
        </tr>
        </thead>

        <tbody>
        @forelse($rows as $r)
          @php
            $fmt = fn($ts) => $ts ? \Carbon\Carbon::parse($ts)->format('g:i:s A') : '';

            $hAmOut = $r->am_out ? \Carbon\Carbon::parse($r->am_out) : null;
            $hPmIn  = $r->pm_in  ? \Carbon\Carbon::parse($r->pm_in)  : null;
            $hPmOut = $r->pm_out ? \Carbon\Carbon::parse($r->pm_out) : null;

            $d      = \Carbon\Carbon::parse($r->work_date);
            $t1130  = $d->copy()->setTime(11,30,0);
            $t1259  = $d->copy()->setTime(12,59,59);
            $t1300  = $d->copy()->setTime(13,0,0);
            $t1700  = $d->copy()->setTime(17,0,0);

            // Page-level holiday + working-day overrides
            $hol = $holidayByDate->get($d->toDateString());
            $isHolidayNonWorking = $hol && (int)$hol->is_non_working === 1;
            $workingToday = $isWorkingDay((int)($r->shift_window_id ?? null), $d);
            $hasScans = (bool)($r->am_in || $r->am_out || $r->pm_in || $r->pm_out);

            // Final status to show
            if (!empty($r->status)) {
                $statusShow = $r->status;
            } elseif ($isHolidayNonWorking && !$hasScans) {
                $statusShow = 'Holiday' . (!empty($hol->name) ? ': '.$hol->name : '');
            } elseif (!$workingToday && !$hasScans) {
                $statusShow = 'No Duty';
            } else {
                $statusShow = 'Absent';
            }

            // Remarks (sequence logic hinting)
            $remarks = [];
            if (!$hPmIn && !$hPmOut && $hAmOut && $hAmOut->betweenIncluded($t1130, $t1259)) {
              $remarks[] = 'Morning only';
            }
            if ($hPmIn && $hPmIn->gte($t1300)) {
              $remarks[] = 'Late PM In (≥ 1:00 PM)';
            }
            if ($hPmOut && $hPmOut->lt($t1700)) {
              $remarks[] = 'Undertime PM Out (< 5:00 PM)';
            }

            // Totals
            $sumLate  += (int)($r->late_minutes ?? 0);
            $sumUnder += (int)($r->undertime_minutes ?? 0);
            $sumHours += (float)($r->total_hours ?? 0);
          @endphp

          <tr class="border-t">
            <td class="px-3 py-2">{{ $r->work_date }}</td>
            <td class="px-3 py-2">{{ $r->name }}</td>
            <td class="px-3 py-2">{{ $r->department }}</td>

            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->am_in) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->am_out) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->pm_in) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->pm_out) }}</td>

            <td class="px-3 py-2 text-right">{{ $r->late_minutes ?? 0 }}</td>
            <td class="px-3 py-2 text-right">{{ $r->undertime_minutes ?? 0 }}</td>
            <td class="px-3 py-2 text-right">{{ number_format((float)($r->total_hours ?? 0),2) }}</td>

            <td class="px-3 py-2">
              @php
                $st = (string)$statusShow;
                $bg = 'bg-gray-200 text-gray-800';
                if(str_contains($st,'Late') && str_contains($st,'Under')) $bg='bg-amber-200 text-amber-900';
                elseif($st==='Present') $bg='bg-emerald-200 text-emerald-900';
                elseif(str_starts_with($st,'Holiday')) $bg='bg-sky-200 text-sky-900';
                elseif($st==='No Duty') $bg='bg-slate-200 text-slate-800';
                elseif($st==='Absent')  $bg='bg-rose-200 text-rose-900';
                elseif(str_contains($st,'Late')) $bg='bg-yellow-200 text-yellow-900';
                elseif(str_contains($st,'Under')) $bg='bg-orange-200 text-orange-900';
              @endphp
              <span class="chip {{ $bg }}">{{ $st }}</span>
            </td>

            <td class="px-3 py-2">
              @if($remarks)
                @foreach($remarks as $rem)
                  <span class="chip bg-indigo-100 text-indigo-800 mr-1">{{ $rem }}</span>
                @endforeach
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="12" class="px-3 py-6 text-center text-gray-500">
              No records{{ request('from') && request('to') ? " for ".(request('mode')==='employee' ? 'selected employee' : 'all active')." between ".request('from')." and ".request('to') : '' }}.
            </td>
          </tr>
        @endforelse
        </tbody>

        {{-- PAGE SUBTOTALS --}}
        @if((method_exists($rows,'count') ? $rows->count() : count($rows)))
          <tfoot class="bg-gray-50">
            <tr class="font-semibold border-t">
              <td colspan="7" class="px-3 py-2 text-right">Page totals:</td>
              <td class="px-3 py-2 text-right">{{ $sumLate }}</td>
              <td class="px-3 py-2 text-right">{{ $sumUnder }}</td>
              <td class="px-3 py-2 text-right">{{ number_format($sumHours,2) }}</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>

    <div class="mt-3">{{ method_exists($rows,'withQueryString') ? $rows->withQueryString()->links() : '' }}</div>

    <p class="text-xs text-gray-500 mt-2">
      Display assumes <em>sequence</em> consolidation logic (AM-out only from 11:30–12:59, PM-in ≥ 11:45 preferred, late if ≥ 1:00 PM, PM-out last scan; undertime if PM-out &lt; 5:00 PM).
    </p>
  </div>

  <script src="https://unpkg.com/alpinejs" defer></script>
</x-app-layout>
