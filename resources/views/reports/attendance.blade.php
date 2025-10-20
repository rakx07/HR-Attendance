{{-- resources/views/reports/attendance.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Attendance Report</h2>
  </x-slot>

  <div class="py-6 max-w-7xl mx-auto" x-data="attendancePage()">

    {{-- FILTERS --}}
    <form method="GET" class="bg-white p-4 rounded shadow grid md:grid-cols-12 gap-3 mb-4">
      <div class="md:col-span-2">
        <label class="text-xs text-gray-600">From</label>
        <input class="border rounded px-2 py-1 w-full" type="date" name="from" value="{{ request('from') }}">
      </div>
      <div class="md:col-span-2">
        <label class="text-xs text-gray-600">To</label>
        <input class="border rounded px-2 py-1 w-full" type="date" name="to" value="{{ request('to') }}">
      </div>

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

      <div class="md:col-span-3" x-show="mode === 'employee'">
        <label class="text-xs text-gray-600">Employee</label>
        <select class="border rounded px-2 py-1 w-full" name="employee_id">
          <option value="">-- Select Employee --</option>
          @foreach($employees as $emp)
            <option value="{{ $emp->id }}" @selected(request('employee_id') == $emp->id)>{{ $emp->name }} ({{ $emp->department }})</option>
          @endforeach
        </select>
      </div>

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
      $sort   = request('sort','date');
      $dir    = request('dir', $sort === 'name' ? 'asc' : 'desc');
      $toggle = fn($col) => request()->fullUrlWithQuery([
        'sort'=>$col, 'dir'=>($sort===$col && $dir==='asc') ? 'desc' : 'asc',
      ]);
      $arrow = fn($col) => ($sort===$col ? ($dir==='asc'?'▲':'▼') : '');
      $fmt   = fn($ts)=> $ts ? \Carbon\Carbon::parse($ts)->format('g:i:s A') : '';
    @endphp

    {{-- ===== Page-scoped schedule + grace (compute late/under/hours on the fly) ===== --}}
    @php
      // Items on this page (paginator or collection)
      $pageItems = method_exists($rows, 'items') ? collect($rows->items()) : collect($rows);

      // Shift IDs present
      $shiftIds = $pageItems->pluck('shift_window_id')->filter()->unique()->values();

      // Grace per shift
      $graceByShift = [];
      if ($shiftIds->isNotEmpty()) {
          $grows = DB::table('shift_windows')
              ->whereIn('id', $shiftIds)
              ->pluck('grace_minutes', 'id');
          foreach ($grows as $sid => $g) $graceByShift[(int)$sid] = (int)$g;
      }

      // Per-day schedule (DB: dow 1..7 → 0..6)
      $sched = [];
      if ($shiftIds->isNotEmpty()) {
          $drows = DB::table('shift_window_days')
              ->whereIn('shift_window_id', $shiftIds)
              ->get(['shift_window_id','dow','is_working','am_in','am_out','pm_in','pm_out']);
          foreach ($drows as $d) {
              $sid   = (int)$d->shift_window_id;
              $dowDb = (int)$d->dow;       // 1..7 (Mon..Sun)
              $dow0  = $dowDb % 7;         // 0..6 (Sun..Sat)
              $isWork= isset($d->is_working) ? (int)$d->is_working
                                             : ((is_null($d->am_in) && is_null($d->pm_in)) ? 0 : 1);
              $sched[$sid][$dow0] = [
                  'work'=>$isWork,
                  'am_in'=>$d->am_in, 'am_out'=>$d->am_out,
                  'pm_in'=>$d->pm_in, 'pm_out'=>$d->pm_out,
              ];
          }
      }

      // Helpers (same rules as PDF)
      $computeHours = function($rec,$daySched){
          if (!$rec) return 0.0;
          $date = $rec->work_date;

          $start = $rec->am_in ? \Carbon\Carbon::parse($rec->am_in)
                               : ($rec->pm_in ? \Carbon\Carbon::parse($rec->pm_in) : null);
          $end   = $rec->pm_out ? \Carbon\Carbon::parse($rec->pm_out)
                                : ($rec->am_out ? \Carbon\Carbon::parse($rec->am_out) : null);
          if (!$start || !$end || $end->lessThanOrEqualTo($start)) return 0.0;

          $mins = $end->diffInMinutes($start);

          // Subtract overlap with lunch break window if defined in schedule
          if ($daySched && $daySched['am_out'] && $daySched['pm_in']) {
              $ls = \Carbon\Carbon::parse("$date {$daySched['am_out']}");
              $le = \Carbon\Carbon::parse("$date {$daySched['pm_in']}");
              $ov = max(0, min($end->timestamp, $le->timestamp) - max($start->timestamp, $ls->timestamp));
              $mins -= (int) floor($ov/60) * 60;
          }

          // Return as hours rounded to 2 decimals
          return round($mins/60, 2);
      };

      $calcLate = function($rec,$daySched,$graceMin){
          if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
          $date = $rec->work_date; $late = 0;
          if ($rec->am_in && $daySched['am_in']) {
            $schedIn = \Carbon\Carbon::parse("$date {$daySched['am_in']}")->addMinutes($graceMin);
            $late += max(0, \Carbon\Carbon::parse($rec->am_in)->diffInMinutes($schedIn, false));
          }
          if ($rec->pm_in && $daySched['pm_in']) {
            $schedIn = \Carbon\Carbon::parse("$date {$daySched['pm_in']}")->addMinutes($graceMin);
            $late += max(0, \Carbon\Carbon::parse($rec->pm_in)->diffInMinutes($schedIn, false));
          }
          return (int)$late;
      };

      $calcUnder = function($rec,$daySched){
          if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
          $date = $rec->work_date;
          if ($rec->pm_out && $daySched['pm_out']) {
            return max(0, \Carbon\Carbon::parse("$date {$daySched['pm_out']}")->diffInMinutes(\Carbon\Carbon::parse($rec->pm_out), false));
          }
          return 0;
      };
    @endphp

    {{-- TABLE --}}
    <div class="bg-white rounded shadow overflow-x-auto">
      <style>
        .mono { font-family: ui-monospace, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .nowrap { white-space: nowrap; }
        .chip { display:inline-block; padding:0 .4rem; border-radius:.375rem; font-size:.75rem; line-height:1.25rem }
        .btn  { padding:.25rem .5rem; border:1px solid #ddd; border-radius:.375rem; background:#fff; }
      </style>

      @php $sumLate=0; $sumUnder=0; $sumHours=0.0; @endphp

      <table class="min-w-full text-[13px]">
        <thead class="bg-gray-50">
          <tr class="text-left">
            <th class="px-3 py-2">
              <a href="{{ $toggle('date') }}" class="inline-flex items-center gap-1">Date <span class="text-gray-400">{{ $arrow('date') }}</span></a>
            </th>
            <th class="px-3 py-2">
              <a href="{{ $toggle('name') }}" class="inline-flex items-center gap-1">Name <span class="text-gray-400">{{ $arrow('name') }}</span></a>
            </th>
            <th class="px-3 py-2">AM In</th>
            <th class="px-3 py-2">AM Out</th>
            <th class="px-3 py-2">PM In</th>
            <th class="px-3 py-2">PM Out</th>
            <th class="px-3 py-2 text-right">Late (min)</th>
            <th class="px-3 py-2 text-right">Undertime (min)</th>
            <th class="px-3 py-2 text-right">Hours</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2">Remarks</th>
            <th class="px-3 py-2">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($rows as $r)
          @php
            // PM-In duplication guard
            $pmInShow = $r->pm_in;
            if ($r->pm_in && $r->pm_out && !$r->am_out) {
              if (\Carbon\Carbon::parse($r->pm_in)->equalTo(\Carbon\Carbon::parse($r->pm_out))) {
                $pmInShow = null;
              }
            }

            // Status chip color
            $st = (string)($r->status ?? 'Present');
            $bg = 'bg-gray-200 text-gray-800';
            if(str_contains($st,'Late') && str_contains($st,'Under')) $bg='bg-amber-200 text-amber-900';
            elseif($st==='Present') $bg='bg-emerald-200 text-emerald-900';
            elseif(str_starts_with($st,'Holiday')) $bg='bg-sky-200 text-sky-900';
            elseif($st==='No Duty') $bg='bg-slate-200 text-slate-800';
            elseif($st==='Absent')  $bg='bg-rose-200 text-rose-900';
            elseif(str_contains($st,'Late')) $bg='bg-yellow-200 text-yellow-900';
            elseif(str_contains($st,'Under')) $bg='bg-orange-200 text-orange-900';

            // Compute Late/Under/Hours (prefer stored > 0, else compute)
            $sid   = (int)($r->shift_window_id ?? 0);
            $dow0  = \Carbon\Carbon::parse($r->work_date)->dayOfWeek; // 0..6
            $dSched= $sched[$sid][$dow0] ?? ['work'=>($dow0===\Carbon\Carbon::SUNDAY?0:1),'am_in'=>null,'am_out'=>null,'pm_in'=>null,'pm_out'=>null];
            $grace = $graceByShift[$sid] ?? 0;

            $late  = (isset($r->late_minutes)      && $r->late_minutes      > 0) ? (int)$r->late_minutes
                    : $calcLate($r,$dSched,$grace);
            $under = (isset($r->undertime_minutes) && $r->undertime_minutes > 0)
        ? round((float)$r->undertime_minutes, 2)
        : round($calcUnder($r, $dSched), 2);


            // ↓ Ensure 2dp for both stored and computed hours
            $hours = (isset($r->total_hours) && $r->total_hours > 0)
                    ? round((float)$r->total_hours, 2)
                    : $computeHours($r,$dSched);

            $sumLate  += (int)$late;
            $sumUnder += (int)$under;
            $sumHours += (float)$hours;
          @endphp

          <tr class="border-t">
            <td class="px-3 py-2">{{ $r->work_date }}</td>
            <td class="px-3 py-2">{{ $r->name }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->am_in) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->am_out) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($pmInShow) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->pm_out) }}</td>
            <td class="px-3 py-2 text-right">{{ $late }}</td>
            <td class="px-3 py-2 text-right">{{ number_format($under, 2) }}</td>

            <td class="px-3 py-2 text-right">{{ number_format($hours, 2) }}</td>
            <td class="px-3 py-2"><span class="chip {{ $bg }}">{{ $st }}</span></td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2">
              <button type="button" class="btn"
                @click="openLogs({{ $r->user_id }}, '{{ $r->work_date }}', '{{ addslashes($r->name) }}')">
                View / Edit Logs
              </button>
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

        @if((method_exists($rows,'count') ? $rows->count() : count($rows)))
          <tfoot class="bg-gray-50">
            <tr class="font-semibold border-t">
              <td colspan="6" class="px-3 py-2 text-right">Page totals:</td>
              <td class="px-3 py-2 text-right">{{ $sumLate }}</td>
              <td class="px-3 py-2 text-right">{{ $sumUnder }}</td>
              <td class="px-3 py-2 text-right">{{ number_format($sumHours, 2) }}</td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>

    <div class="mt-3">
      {{ method_exists($rows,'withQueryString') ? $rows->withQueryString()->links() : '' }}
    </div>

    {{-- MODAL: Raw Logs (simple list) --}}
    <div x-show="modalOpen" style="display:none" class="fixed inset-0 bg-black/40 z-50">
      <div class="bg-white rounded shadow max-w-3xl mx-auto mt-16 p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold">
            Raw Logs — <span x-text="modalDate"></span> — <span x-text="modalName"></span>
          </h3>
          <button class="px-2 py-1" @click="modalOpen=false">✕</button>
        </div>

        <div class="mt-3" x-show="modalLoading">Loading…</div>

        <div class="mt-3" x-show="!modalLoading">
          <table class="w-full text-sm border">
            <thead class="bg-gray-50">
              <tr>
                <th class="p-2 text-left">#</th>
                <th class="p-2 text-left">Time</th>
                <th class="p-2 text-left">Type</th>
                <th class="p-2 text-left">Source</th>
                <th class="p-2 text-left">Device</th>
              </tr>
            </thead>
            <tbody>
              <template x-if="modalRows.length === 0">
                <tr><td class="p-2 text-center text-gray-500" colspan="5">No logs.</td></tr>
              </template>
              <template x-for="(row,idx) in modalRows" :key="row.id ?? 'new'+idx">
                <tr>
                  <td class="p-2" x-text="(meta.per_page*(meta.current_page-1))+idx+1"></td>
                  <td class="p-2" x-text="formatTime(row.punched_at)"></td>
                  <td class="p-2" x-text="row.punch_type ?? ''"></td>
                  <td class="p-2" x-text="row.source ?? ''"></td>
                  <td class="p-2" x-text="row.device_sn ?? ''"></td>
                </tr>
              </template>
            </tbody>
          </table>

          <div class="mt-3 flex items-center justify-between">
            <div>
              <span x-text="`Showing ${modalRows.length} of ${meta.total} (page ${meta.current_page}/${meta.last_page})`"></span>
            </div>
            <div class="space-x-2">
              <button class="btn" :disabled="!meta.prev" @click="goto(meta.prev)">Prev</button>
              <button class="btn" :disabled="!meta.next" @click="goto(meta.next)">Next</button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://unpkg.com/alpinejs" defer></script>
  <script>
    function attendancePage() {
      return {
        mode: '{{ request('mode','all_active') }}',
        modalOpen: false,
        modalLoading: false,
        modalDate: null,
        modalUser: null,
        modalName: '',
        modalRows: [],
        meta: { current_page:1, last_page:1, per_page:25, total:0, next:null, prev:null },

        openLogs(userId, workDate, name) {
          this.modalUser  = userId;
          this.modalDate  = workDate;
          this.modalName  = name || '';
          this.modalOpen  = true;
          this.fetchPage(`{{ route('reports.attendance.raw') }}?user_id=${userId}&date=${workDate}`);
        },

        fetchPage(url) {
          this.modalLoading = true;
          fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => {
              this.modalRows = d.rows || [];
              this.meta      = d.meta || this.meta;
            })
            .catch(() => { this.modalRows = []; })
            .finally(() => { this.modalLoading = false; });
        },

        goto(url) {
          if (!url) return;
          this.fetchPage(url);
        },

        formatTime(ts) {
          if (!ts) return '';
          const parts = ts.replace('T',' ').split(' ');
          const t = (parts[1] || ts);
          const [h,m,s] = t.split(':').map(n => parseInt(n, 10));
          const d = new Date(2000,0,1,h,m,s||0);
          return d.toLocaleTimeString([], { hour:'numeric', minute:'2-digit', second:'2-digit' });
        }
      }
    }
  </script>
</x-app-layout>
