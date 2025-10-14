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

    {{-- TABLE --}}
    <div class="bg-white rounded shadow overflow-x-auto">
      <style>
        .mono { font-family: ui-monospace, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .nowrap { white-space: nowrap; }
        .chip { display:inline-block; padding:0 .4rem; border-radius:.375rem; font-size:.75rem; line-height:1.25rem }
        .btn  { padding:.25rem .5rem; border:1px solid #ddd; border-radius:.375rem; background:#fff; }
        .btn-primary { @apply bg-blue-600 text-white; }
        .btn[disabled] { opacity:.5; cursor:not-allowed; }
        .divider { height:1px; background-color:#e5e7eb; }
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
            $sumLate  += (int)($r->late_minutes ?? 0);
            $sumUnder += (int)($r->undertime_minutes ?? 0);
            $sumHours += (float)($r->total_hours ?? 0);

            $pmInShow = $r->pm_in;
            if ($r->pm_in && $r->pm_out && !$r->am_out) {
              if (\Carbon\Carbon::parse($r->pm_in)->equalTo(\Carbon\Carbon::parse($r->pm_out))) {
                $pmInShow = null;
              }
            }

            $st = (string)($r->status ?? 'Present');
            $bg = 'bg-gray-200 text-gray-800';
            if(str_contains($st,'Late') && str_contains($st,'Under')) $bg='bg-amber-200 text-amber-900';
            elseif($st==='Present') $bg='bg-emerald-200 text-emerald-900';
            elseif(str_starts_with($st,'Holiday')) $bg='bg-sky-200 text-sky-900';
            elseif($st==='No Duty') $bg='bg-slate-200 text-slate-800';
            elseif($st==='Absent')  $bg='bg-rose-200 text-rose-900';
            elseif(str_contains($st,'Late')) $bg='bg-yellow-200 text-yellow-900';
            elseif(str_contains($st,'Under')) $bg='bg-orange-200 text-orange-900';

            $preset = [
              'am_in'  => $r->am_in  ? \Carbon\Carbon::parse($r->am_in)->format('H:i:s')  : null,
              'am_out' => $r->am_out ? \Carbon\Carbon::parse($r->am_out)->format('H:i:s') : null,
              'pm_in'  => $pmInShow  ? \Carbon\Carbon::parse($pmInShow)->format('H:i:s') : null,
              'pm_out' => $r->pm_out ? \Carbon\Carbon::parse($r->pm_out)->format('H:i:s') : null,
            ];
          @endphp
          <tr class="border-t">
            <td class="px-3 py-2">{{ $r->work_date }}</td>
            <td class="px-3 py-2">{{ $r->name }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->am_in) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->am_out) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($pmInShow) }}</td>
            <td class="px-3 py-2 mono nowrap">{{ $fmt($r->pm_out) }}</td>
            <td class="px-3 py-2 text-right">{{ $r->late_minutes ?? 0 }}</td>
            <td class="px-3 py-2 text-right">{{ $r->undertime_minutes ?? 0 }}</td>
            <td class="px-3 py-2 text-right">{{ number_format((float)($r->total_hours ?? 0),2) }}</td>
            <td class="px-3 py-2"><span class="chip {{ $bg }}">{{ $st }}</span></td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2">
              <button
                type="button"
                class="btn"
                x-on:click="openFromEvent($event)"
                data-user="{{ (int) $r->user_id }}"
                data-date="{{ $r->work_date }}"
                data-name="{{ e($r->name) }}"
                data-preset='@json($preset)'
              >
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
              <td class="px-3 py-2 text-right">{{ number_format($sumHours,2) }}</td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>

    <div class="mt-3">
      {{ method_exists($rows,'withQueryString') ? $rows->withQueryString()->links() : '' }}
    </div>

    {{-- MODAL: Edit consolidated + Raw Logs --}}
    <div x-show="modalOpen" style="display:none" class="fixed inset-0 bg-black/40 z-50">
      <div class="bg-white rounded shadow max-w-4xl mx-auto mt-16 p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold">
            <span x-text="modalName"></span>
            — <span x-text="modalDate"></span>
          </h3>
          <button class="px-2 py-1" @click="modalOpen=false">✕</button>
        </div>

        {{-- EDIT CONSOLIDATED --}}
        <div class="mt-4">
          <h4 class="font-semibold mb-2">Edit Consolidated</h4>
          <form @submit.prevent="saveDay">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs text-gray-600">AM In</label>
                <input type="time" step="1" class="border rounded px-2 py-1 w-full" x-model="edit.am_in">
              </div>
              <div>
                <label class="text-xs text-gray-600">AM Out</label>
                <input type="time" step="1" class="border rounded px-2 py-1 w-full" x-model="edit.am_out">
              </div>
              <div>
                <label class="text-xs text-gray-600">PM In</label>
                <input type="time" step="1" class="border rounded px-2 py-1 w-full" x-model="edit.pm_in">
              </div>
              <div>
                <label class="text-xs text-gray-600">PM Out</label>
                <input type="time" step="1" class="border rounded px-2 py-1 w-full" x-model="edit.pm_out">
              </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <button class="px-3 py-2 bg-blue-600 text-white rounded" :disabled="saving">
                <span x-show="!saving">Save</span>
                <span x-show="saving">Saving…</span>
              </button>
              <span class="text-sm" x-text="saveMsg"></span>
            </div>
          </form>
        </div>

        <div class="divider my-4"></div>

        {{-- RAW LOGS --}}
        <div>
          <h4 class="font-semibold mb-2">Raw Logs (12-hour)</h4>

          <div class="border rounded max-h-64 overflow-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 sticky top-0">
                <tr>
                  <th class="p-2 text-left w-16">#</th>
                  <th class="p-2 text-left">Time</th>
                </tr>
              </thead>
              <tbody>
                <template x-if="modalRows.length === 0">
                  <tr><td class="p-2 text-center text-gray-500" colspan="2">No logs.</td></tr>
                </template>
                <template x-for="(row,idx) in modalRows" :key="row.id ?? 'new'+idx">
                  <tr class="border-t">
                    <td class="p-2" x-text="(meta.per_page*(meta.current_page-1))+idx+1"></td>
                    <td class="p-2" x-text="formatTime12(row.punched_at)"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

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

        // edit state
        edit: { am_in: null, am_out: null, pm_in: null, pm_out: null },
        saving: false,
        saveMsg: '',

        // SAFER opener (no inline JSON)
        openFromEvent(evt) {
          const el = evt.currentTarget;
          const userId  = parseInt(el.dataset.user, 10);
          const workDate= el.dataset.date || '';
          const name    = el.dataset.name || '';
          let preset = {};
          try { preset = JSON.parse(el.dataset.preset || '{}'); } catch (_) {}

          this.openLogs(userId, workDate, name, preset);
        },

        openLogs(userId, workDate, name, preset = {}) {
          this.modalUser  = userId;
          this.modalDate  = workDate;
          this.modalName  = name || '';
          this.modalOpen  = true;
          this.saveMsg = '';

          // 1) Load consolidated day (prefill edit fields)
          const dayUrl = `{{ route('reports.attendance.day') }}?user_id=${userId}&date=${workDate}`;
          fetch(dayUrl, { headers: { 'Accept':'application/json' } })
            .then(r => r.json())
            .then(d => {
              const day = d.day || {};
              // Convert possible "YYYY-MM-DD HH:mm:ss" to "HH:mm:ss" for input[type=time]
              this.edit.am_in  = this.onlyTime(day.am_in)  ?? (preset.am_in  || null);
              this.edit.am_out = this.onlyTime(day.am_out) ?? (preset.am_out || null);
              this.edit.pm_in  = this.onlyTime(day.pm_in)  ?? (preset.pm_in  || null);
              this.edit.pm_out = this.onlyTime(day.pm_out) ?? (preset.pm_out || null);
            })
            .catch(() => {
              // fall back to preset if API fails
              this.edit.am_in  = preset.am_in  || null;
              this.edit.am_out = preset.am_out || null;
              this.edit.pm_in  = preset.pm_in  || null;
              this.edit.pm_out = preset.pm_out || null;
            });

          // 2) Load raw logs
          this.fetchPage(`{{ route('reports.attendance.raw') }}?user_id=${userId}&date=${workDate}`);
        },

        fetchPage(url) {
          this.modalLoading = true;
          fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => {
              this.modalRows = d.rows || [];
              this.meta = d.meta || this.meta;
            })
            .catch(() => { this.modalRows = []; })
            .finally(() => { this.modalLoading = false; });
        },

        goto(url) {
          if (!url) return;
          this.fetchPage(url);
        },

        // Save consolidated day (HH:mm:ss → server)
        saveDay() {
          this.saving = true;
          this.saveMsg = '';
          const body = new URLSearchParams({
            user_id: this.modalUser,
            date: this.modalDate,
            am_in:  this.edit.am_in  || '',
            am_out: this.edit.am_out || '',
            pm_in:  this.edit.pm_in  || '',
            pm_out: this.edit.pm_out || '',
            _token: '{{ csrf_token() }}',
          });

          fetch(`{{ route('reports.attendance.day.update') }}`, {
            method: 'POST',
            headers: { 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded' },
            body
          })
          .then(async (r) => {
            if (!r.ok) throw new Error(await r.text());
            return r.json();
          })
          .then(() => {
            this.saveMsg = 'Saved.';
          })
          .catch(() => { this.saveMsg = 'Save failed.'; })
          .finally(() => { this.saving = false; });
        },

        // Helpers
        onlyTime(ts) {
          if (!ts) return null;
          // Accepts "YYYY-MM-DD HH:mm:ss" or "HH:mm:ss"
          const s = ts.replace('T',' ');
          if (s.includes(' ')) return s.split(' ')[1];
          // If just HH:mm or HH:mm:ss, return as-is (ensure seconds)
          if (/^\d{2}:\d{2}(:\d{2})?$/.test(s)) {
            return s.length === 5 ? s + ':00' : s;
          }
          return null;
        },

        formatTime12(ts) {
          // For raw logs display in 12-hour
          if (!ts) return '';
          const s = ts.replace('T',' ');
          const time = (s.includes(' ') ? s.split(' ')[1] : s);
          // time "HH:mm:ss"
          const [H,M,S='00'] = time.split(':');
          let h = parseInt(H,10);
          const ampm = h >= 12 ? 'PM' : 'AM';
          h = h % 12; if (h === 0) h = 12;
          return `${h}:${M}:${S} ${ampm}`;
        }
      }
    }
  </script>
</x-app-layout>
