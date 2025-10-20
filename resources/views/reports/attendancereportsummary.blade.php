{{-- resources/views/reports/attendancereportsummary.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Attendance Report Summary</h2>
  </x-slot>

  <div class="py-6 max-w-7xl mx-auto" x-data="summaryPage()">

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
      <div class="md:col-span-4">
        <label class="text-xs text-gray-600">Employee</label>
        <select class="border rounded px-2 py-1 w-full" name="employee_id">
          <option value="">-- All Employees --</option>
          @foreach($employees as $emp)
            <option value="{{ $emp->id }}" @selected(request('employee_id') == $emp->id)>{{ $emp->name }} ({{ $emp->department }})</option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="text-xs text-gray-600">Department</label>
        <input class="border rounded px-2 py-1 w-full" type="text" name="dept" value="{{ request('dept') }}" placeholder="Department">
      </div>
      <div class="md:col-span-2 flex items-end gap-2">
        <button class="px-4 py-2 bg-blue-600 text-white rounded w-full">Filter</button>
      </div>
    </form>

    {{-- ACTIONS --}}
    <div class="flex gap-2 mb-3">
      <a class="px-4 py-2 bg-gray-800 text-white rounded"
         href="{{ route('reports.attendance.summary.pdf', request()->query()) }}"
         target="_blank">Print PDF</a>
    </div>

    @php
      $fmtTime = fn($ts)=> $ts ? \Carbon\Carbon::parse($ts)->format('g:i:s A') : '';
      $fmt2    = fn($n)=> number_format((float)$n, 2);
    @endphp

    {{-- ===== Load schedule + grace for the rows on this page ===== --}}
    @php
      // Items on this page (paginator or collection)
      $pageItems = method_exists($rows, 'items') ? collect($rows->items()) : collect($rows);

      // Shift IDs present in the page
      $shiftIds = $pageItems->pluck('shift_window_id')->filter()->unique()->values();

      // Grace minutes per shift
      $graceByShift = [];
      if ($shiftIds->isNotEmpty()) {
          $grows = \Illuminate\Support\Facades\DB::table('shift_windows')
              ->whereIn('id', $shiftIds)
              ->pluck('grace_minutes', 'id');
          foreach ($grows as $sid => $g) $graceByShift[(int)$sid] = (int)$g;
      }

      // Per-day schedule (DB: dow 1..7 → 0..6)
      $sched = []; // $sched[shift_id][dow0] = ['work'=>0/1,'am_in','am_out','pm_in','pm_out']
      if ($shiftIds->isNotEmpty()) {
          $drows = \Illuminate\Support\Facades\DB::table('shift_window_days')
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

      // ===== Helpers (hours = overlap with duty windows, never negative) =====
      $overlapMinutes = function (? \Carbon\Carbon $a1, ? \Carbon\Carbon $a2, ? \Carbon\Carbon $b1, ? \Carbon\Carbon $b2): int {
          if (!$a1 || !$a2 || !$b1 || !$b2) return 0;
          if ($a2->lte($a1) || $b2->lte($b1)) return 0;
          $s = max($a1->timestamp, $b1->timestamp);
          $e = min($a2->timestamp, $b2->timestamp);
          return $e > $s ? (int) floor(($e - $s)/60) : 0;
      };

      $computeHours = function($rec, $daySched) use ($overlapMinutes) {
          if (!$rec) return 0.00;
          $date = \Carbon\Carbon::parse($rec->work_date)->toDateString();

          $mins = 0;
          $hasAM = !empty($daySched['am_in']) && !empty($daySched['am_out']);
          $hasPM = !empty($daySched['pm_in']) && !empty($daySched['pm_out']);

          if ($hasAM || $hasPM) {
              if (!empty($rec->am_in) && !empty($rec->am_out) && $hasAM) {
                  $amIn  = \Carbon\Carbon::parse($rec->am_in);
                  $amOut = \Carbon\Carbon::parse($rec->am_out);
                  $wIn   = \Carbon\Carbon::parse("$date {$daySched['am_in']}");
                  $wOut  = \Carbon\Carbon::parse("$date {$daySched['am_out']}");
                  $mins += $overlapMinutes($amIn, $amOut, $wIn, $wOut);
              }
              if (!empty($rec->pm_in) && !empty($rec->pm_out) && $hasPM) {
                  $pmIn  = \Carbon\Carbon::parse($rec->pm_in);
                  $pmOut = \Carbon\Carbon::parse($rec->pm_out);
                  $wIn   = \Carbon\Carbon::parse("$date {$daySched['pm_in']}");
                  $wOut  = \Carbon\Carbon::parse("$date {$daySched['pm_out']}");
                  $mins += $overlapMinutes($pmIn, $pmOut, $wIn, $wOut);
              }
          } else {
              if (!empty($rec->am_in) && !empty($rec->am_out)) {
                  $a = \Carbon\Carbon::parse($rec->am_in);
                  $b = \Carbon\Carbon::parse($rec->am_out);
                  if ($b->gt($a)) $mins += $b->diffInMinutes($a);
              }
              if (!empty($rec->pm_in) && !empty($rec->pm_out)) {
                  $a = \Carbon\Carbon::parse($rec->pm_in);
                  $b = \Carbon\Carbon::parse($rec->pm_out);
                  if ($b->gt($a)) $mins += $b->diffInMinutes($a);
              }
          }

          return round(max(0, $mins)/60, 2);
      };

      $calcLate = function($rec,$daySched,$graceMin){
          if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
          $date = \Carbon\Carbon::parse($rec->work_date)->toDateString();
          $late = 0;
          if (!empty($rec->am_in) && !empty($daySched['am_in'])) {
            $sched = \Carbon\Carbon::parse("$date {$daySched['am_in']}")->addMinutes($graceMin);
            $act   = \Carbon\Carbon::parse($rec->am_in);
            if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
          }
          if (!empty($rec->pm_in) && !empty($daySched['pm_in'])) {
            $sched = \Carbon\Carbon::parse("$date {$daySched['pm_in']}")->addMinutes($graceMin);
            $act   = \Carbon\Carbon::parse($rec->pm_in);
            if ($act->gt($sched)) $late += $sched->diffInMinutes($act);
          }
          return (int)$late;
      };

      $calcUnder = function($rec,$daySched){
          if (!$rec || !$daySched || (int)($daySched['work']??1)===0) return 0;
          $date = \Carbon\Carbon::parse($rec->work_date)->toDateString();
          $ut = 0;
          if (!empty($rec->am_out) && !empty($daySched['am_out'])) {
            $sched = \Carbon\Carbon::parse("$date {$daySched['am_out']}");
            $act   = \Carbon\Carbon::parse($rec->am_out);
            if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
          }
          if (!empty($rec->pm_out) && !empty($daySched['pm_out'])) {
            $sched = \Carbon\Carbon::parse("$date {$daySched['pm_out']}");
            $act   = \Carbon\Carbon::parse($rec->pm_out);
            if ($act->lt($sched)) $ut += $act->diffInMinutes($sched);
          }
          return (int)$ut;
      };
    @endphp

    {{-- TABLE --}}
    <div class="bg-white rounded shadow overflow-x-auto">
      <style>
        .mono { font-family: ui-monospace, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .btn  { padding:.25rem .5rem; border:1px solid #ddd; border-radius:.375rem; background:#fff; }
        .chip { display:inline-block; padding:0 .4rem; border-radius:.375rem; font-size:.75rem; line-height:1.25rem }
      </style>

      <table class="min-w-full text-[13px]">
        <thead class="bg-gray-50">
          <tr class="text-left">
            <th class="px-3 py-2">Date</th>
            <th class="px-3 py-2">Employee</th>
            <th class="px-3 py-2">AM In</th>
            <th class="px-3 py-2">AM Out</th>
            <th class="px-3 py-2">PM In</th>
            <th class="px-3 py-2">PM Out</th>
            <th class="px-3 py-2 text-right">Late (min)</th>
            <th class="px-3 py-2 text-right">Undertime (min)</th>
            <th class="px-3 py-2 text-right">Hours</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2">Fix</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
          @php
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

            // Recompute using schedule + grace
            $sid   = (int)($r->shift_window_id ?? 0);
            $dow0  = \Carbon\Carbon::parse($r->work_date)->dayOfWeek; // 0..6
            $dSched= $sched[$sid][$dow0] ?? [
              'work'=>($dow0===\Carbon\Carbon::SUNDAY?0:1),'am_in'=>null,'am_out'=>null,'pm_in'=>null,'pm_out'=>null
            ];
            $grace = $graceByShift[$sid] ?? 0;

            $late  = $calcLate($r,$dSched,$grace);
            $under = $calcUnder($r,$dSched);
            $hours = $computeHours($r,$dSched);

            // PM-In duplication guard
            $pmInShow = $r->pm_in;
            if ($r->pm_in && $r->pm_out && !$r->am_out) {
              if (\Carbon\Carbon::parse($r->pm_in)->equalTo(\Carbon\Carbon::parse($r->pm_out))) $pmInShow = null;
            }
          @endphp
          <tr class="border-t">
            <td class="px-3 py-2">{{ $r->work_date }}</td>
            <td class="px-3 py-2">{{ $r->name }}</td>
            <td class="px-3 py-2 mono">{{ $fmtTime($r->am_in) }}</td>
            <td class="px-3 py-2 mono">{{ $fmtTime($r->am_out) }}</td>
            <td class="px-3 py-2 mono">{{ $fmtTime($pmInShow) }}</td>
            <td class="px-3 py-2 mono">{{ $fmtTime($r->pm_out) }}</td>
            <td class="px-3 py-2 text-right">{{ $late }}</td>
            <td class="px-3 py-2 text-right">{{ $fmt2($under) }}</td>
            <td class="px-3 py-2 text-right">{{ $fmt2($hours) }}</td>
            <td class="px-3 py-2"><span class="chip {{ $bg }}">{{ $st }}</span></td>
            <td class="px-3 py-2">
              <button type="button" class="btn"
                @click="openFix({{ $r->user_id }}, '{{ $r->work_date }}', '{{ addslashes($r->name) }}',
                               '{{ $r->am_in }}','{{ $r->am_out }}','{{ $r->pm_in }}','{{ $r->pm_out }}')">
                Fix / Edit
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="11" class="px-3 py-6 text-center text-gray-500">No records.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">
      {{ method_exists($rows,'withQueryString') ? $rows->withQueryString()->links() : '' }}
    </div>

    {{-- MODAL: Fix Scans + Raw logs --}}
    <div x-show="modalOpen" style="display:none" class="fixed inset-0 bg-black/40 z-50">
      <div class="bg-white rounded shadow max-w-3xl mx-auto mt-16 p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold">Fix Attendance — <span x-text="modalDate"></span> — <span x-text="modalName"></span></h3>
          <button class="px-2 py-1" @click="modalOpen=false">✕</button>
        </div>

        {{-- Raw logs --}}
        <div class="mt-3">
          <h4 class="font-semibold mb-2">Raw Logs</h4>
          <div class="mt-1" x-show="modalLoading">Loading…</div>
          <div class="mt-1" x-show="!modalLoading">
            <table class="w-full text-sm border">
              <thead class="bg-gray-50">
                <tr>
                  <th class="p-2 text-left">#</th>
                  <th class="p-2 text-left">Time</th>
                </tr>
              </thead>
              <tbody>
                <template x-if="modalRows.length === 0">
                  <tr><td class="p-2 text-center text-gray-500" colspan="2">No logs.</td></tr>
                </template>
                <template x-for="(row,idx) in modalRows" :key="row.id ?? ('raw'+idx)">
                  <tr>
                    <td class="p-2" x-text="idx+1"></td>
                    <td class="p-2" x-text="formatTime(row.punched_at)"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        {{-- Edit form --}}
        <form class="mt-4 grid grid-cols-2 gap-3" @submit.prevent="saveFix">
          @csrf
          <div>
            <label class="text-xs text-gray-600">AM In</label>
            <input class="border rounded px-2 py-1 w-full" type="time" step="1" x-model="form.am_in">
          </div>
          <div>
            <label class="text-xs text-gray-600">AM Out</label>
            <input class="border rounded px-2 py-1 w-full" type="time" step="1" x-model="form.am_out">
          </div>
          <div>
            <label class="text-xs text-gray-600">PM In</label>
            <input class="border rounded px-2 py-1 w-full" type="time" step="1" x-model="form.pm_in">
          </div>
          <div>
            <label class="text-xs text-gray-600">PM Out</label>
            <input class="border rounded px-2 py-1 w-full" type="time" step="1" x-model="form.pm_out">
          </div>
          <div class="col-span-2">
            <label class="text-xs text-gray-600">Remarks (optional)</label>
            <textarea class="border rounded px-2 py-1 w-full" x-model="form.remarks" rows="2" placeholder="Reason / notes"></textarea>
          </div>

          <div class="col-span-2 flex items-center justify-between mt-2">
            <div class="text-sm text-gray-500">Saving updates <code>attendance_days</code> via your existing API.</div>
            <div class="space-x-2">
              <button type="button" class="btn" @click="modalOpen=false">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">Save Fix</button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>

  <script src="https://unpkg.com/alpinejs" defer></script>
  <script>
    function summaryPage() {
      return {
        modalOpen: false,
        modalLoading: false,
        modalDate: null,
        modalUser: null,
        modalName: '',
        modalRows: [],
        form: { am_in:'', am_out:'', pm_in:'', pm_out:'', remarks:'' },

        openFix(userId, workDate, name, amIn, amOut, pmIn, pmOut) {
          this.modalUser  = userId;
          this.modalDate  = workDate;
          this.modalName  = name || '';
          this.form.am_in = amIn ? this.onlyTime(amIn) : '';
          this.form.am_out= amOut ? this.onlyTime(amOut): '';
          this.form.pm_in = pmIn ? this.onlyTime(pmIn) : '';
          this.form.pm_out= pmOut ? this.onlyTime(pmOut): '';
          this.form.remarks = '';
          this.modalOpen  = true;
          this.fetchRaw();
        },

        fetchRaw() {
          this.modalLoading = true;
          fetch(`{{ route('reports.attendance.raw') }}?user_id=${this.modalUser}&date=${this.modalDate}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => { this.modalRows = d.rows || []; })
            .catch(() => { this.modalRows = []; })
            .finally(() => { this.modalLoading = false; });
        },

        saveFix() {
          const body = {
            user_id: this.modalUser,
            date: this.modalDate,
            am_in: this.form.am_in || null,
            am_out: this.form.am_out || null,
            pm_in: this.form.pm_in || null,
            pm_out: this.form.pm_out || null,
            remarks: this.form.remarks || null
          };
          fetch(`{{ route('reports.attendance.day.update') }}`, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
          })
          .then(r => r.json())
          .then(d => {
            if (d.ok) {
              alert('Saved!');
              window.location.reload();
            } else {
              alert(d.message || 'Save failed.');
            }
          })
          .catch(() => alert('Request failed.'));
        },

        formatTime(ts) {
          if (!ts) return '';
          const parts = ts.replace('T',' ').split(' ');
          const t = (parts[1] || ts);
          const [h,m,s] = t.split(':').map(n => parseInt(n, 10));
          const d = new Date(2000,0,1,h,m,s||0);
          return d.toLocaleTimeString([], { hour:'numeric', minute:'2-digit', second:'2-digit' });
        },
        onlyTime(ts) {
          const p = ts?.replace('T',' ').split(' ') || [];
          const t = (p[1] || ts || '');
          if (!t) return '';
          const [h,m,s] = t.split(':');
          return [h?.padStart(2,'0')||'00', m?.padStart(2,'0')||'00', (s??'00').padStart(2,'0')].join(':');
        }
      }
    }
  </script>
</x-app-layout>
