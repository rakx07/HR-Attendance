{{-- resources/views/attendance/editor/index.blade.php --}}
<x-app-layout>
  <x-slot name="header"><h2 class="font-semibold text-xl">Edit Attendance</h2></x-slot>

  <div class="p-6 max-w-5xl mx-auto bg-white rounded shadow">
    <form method="GET" action="{{ route('attendance.editor') }}" id="filterForm" class="grid md:grid-cols-6 gap-3 items-end">
      {{-- Employee --}}
      <label class="col-span-2">
        <span class="block text-sm text-gray-700 mb-1">Employee</span>
        <select name="user_id" id="user_id" class="border rounded px-2 py-1 w-full">
          <option value="">-- choose --</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
          @endforeach
        </select>
      </label>

      {{-- Range --}}
      <label>
        <span class="block text-sm text-gray-700 mb-1">Range</span>
        <select name="range" id="range" class="border rounded px-2 py-1 w-full">
          @php $range = $filters['range'] ?? 'day'; @endphp
          <option value="day"   @selected($range==='day')>Day</option>
          <option value="week"  @selected($range==='week')>Week</option>
          <option value="month" @selected($range==='month')>Month</option>
          <option value="custom"@selected($range==='custom')>Custom</option>
        </select>
      </label>

      {{-- Anchor date (for day/week/month) --}}
      <label id="anchorWrap" class="@if(($filters['range'] ?? 'day')==='custom') hidden @endif">
        <span class="block text-sm text-gray-700 mb-1">Date</span>
        <input type="date" name="date" id="date" class="border rounded px-2 py-1 w-full"
               value="{{ $filters['date'] ?? now()->toDateString() }}">
      </label>

      {{-- Custom range --}}
      <label id="fromWrap" class="@if(($filters['range'] ?? 'day')!=='custom') hidden @endif">
        <span class="block text-sm text-gray-700 mb-1">From</span>
        <input type="date" name="from" class="border rounded px-2 py-1 w-full" value="{{ $filters['from'] ?? '' }}">
      </label>
      <label id="toWrap" class="@if(($filters['range'] ?? 'day')!=='custom') hidden @endif">
        <span class="block text-sm text-gray-700 mb-1">To</span>
        <input type="date" name="to" class="border rounded px-2 py-1 w-full" value="{{ $filters['to'] ?? '' }}">
      </label>

      <div class="md:col-span-1">
        <button class="px-4 py-2 bg-blue-600 text-white rounded w-full">Filter</button>
      </div>
    </form>
  </div>

  @if(($filters['user_id'] ?? null) && $rows->count())
    {{-- Listing + Modal Controller --}}
    <div class="mt-4 p-6 max-w-5xl mx-auto bg-white rounded shadow"
         x-data="attendanceEditor({
            baseRoute: @js(route('attendance.editor.update', [$filters['user_id'] ?? 0, 'DATE_PLACEHOLDER'])),
            csrf: @js(csrf_token())
         })">

      <div class="mb-3 text-sm text-gray-600">
        Showing <strong>{{ $rows->total() }}</strong> record(s)
        @if(($filters['from'] ?? null) && ($filters['to'] ?? null))
          from <strong>{{ $filters['from'] }}</strong> to <strong>{{ $filters['to'] }}</strong>.
        @endif
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">AM In</th>
              <th class="px-3 py-2 text-left">AM Out</th>
              <th class="px-3 py-2 text-left">PM In</th>
              <th class="px-3 py-2 text-left">PM Out</th>
              <th class="px-3 py-2 text-right">Hours</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
          @foreach($rows as $r)
            <tr class="border-t">
              <td class="px-3 py-2">{{ $r->work_date }}</td>
              <td class="px-3 py-2">{{ $r->am_in }}</td>
              <td class="px-3 py-2">{{ $r->am_out }}</td>
              <td class="px-3 py-2">{{ $r->pm_in }}</td>
              <td class="px-3 py-2">{{ $r->pm_out }}</td>
              <td class="px-3 py-2 text-right">{{ number_format($r->total_hours, 2) }}</td>
              <td class="px-3 py-2">{{ $r->status }}</td>
              <td class="px-3 py-2">
                <button type="button"
                        class="px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700"
                        @click="open({
                          date:        '{{ $r->work_date }}',
                          am_in:       @js($r->am_in),
                          am_out:      @js($r->am_out),
                          pm_in:       @js($r->pm_in),
                          pm_out:      @js($r->pm_out),
                          status:      @js($r->status),
                        })">
                  Edit
                </button>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $rows->links() }}
      </div>

      {{-- Modal --}}
      <div
        x-show="show"
        x-transition.opacity
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/40"
        style="display:none"
        @keydown.escape.window="close()"
      >
        <div class="bg-white w-full max-w-lg mx-3 rounded-xl shadow-xl z-50"
             @click.outside="close()">
          <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold">Edit Attendance — <span x-text="form.date"></span></h3>
            <button class="text-gray-500 hover:text-gray-700" @click="close()">&times;</button>
          </div>

          <form method="POST" :action="action" class="p-5 space-y-4">
            <input type="hidden" name="_token" :value="csrf">

            <div class="grid grid-cols-2 gap-3">
              <label class="text-sm">
                <span class="block mb-1">AM In</span>
                <input type="datetime-local" name="am_in" class="border rounded px-2 py-1 w-full"
                       x-model="form.am_in">
              </label>
              <label class="text-sm">
                <span class="block mb-1">AM Out</span>
                <input type="datetime-local" name="am_out" class="border rounded px-2 py-1 w-full"
                       x-model="form.am_out">
              </label>

              <label class="text-sm">
                <span class="block mb-1">PM In</span>
                <input type="datetime-local" name="pm_in" class="border rounded px-2 py-1 w-full"
                       x-model="form.pm_in">
              </label>
              <label class="text-sm">
                <span class="block mb-1">PM Out</span>
                <input type="datetime-local" name="pm_out" class="border rounded px-2 py-1 w-full"
                       x-model="form.pm_out">
              </label>
            </div>

            <label class="text-sm block">
              <span class="block mb-1">Status</span>
              <select name="status" class="border rounded px-2 py-1 w-full" x-model="form.status">
                <option value="">— leave as is —</option>
                <option value="Present">Present</option>
                <option value="Late">Late</option>
                <option value="Undertime">Undertime</option>
                <option value="Late/Undertime">Late/Undertime</option>
                <option value="Absent">Absent</option>
                <option value="Incomplete">Incomplete</option>
              </select>
            </label>

            <label class="text-sm block">
              <span class="block mb-1">Reason (for audit, optional)</span>
              <input type="text" name="reason" class="border rounded px-2 py-1 w-full" x-model="form.reason">
            </label>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" class="px-4 py-2 rounded border" @click="close()">Cancel</button>
              <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
                Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
      {{-- /Modal --}}
    </div>
  @elseif(($filters['user_id'] ?? null))
    <div class="mt-4 p-6 max-w-5xl mx-auto bg-white rounded shadow text-gray-600">
      No attendance found for the selected period.
    </div>
  @endif

  <script>
    // Toggle anchor date vs. custom range
    (function(){
      const range = document.getElementById('range');
      const anchorWrap = document.getElementById('anchorWrap');
      const fromWrap = document.getElementById('fromWrap');
      const toWrap = document.getElementById('toWrap');

      function toggle() {
        const isCustom = range.value === 'custom';
        anchorWrap.classList.toggle('hidden', isCustom);
        fromWrap.classList.toggle('hidden', !isCustom);
        toWrap.classList.toggle('hidden', !isCustom);
      }
      range.addEventListener('change', toggle);
      toggle();
    })();

    // Alpine-powered modal state + helpers
    function attendanceEditor({ baseRoute, csrf }) {
      return {
        show: false,
        csrf,
        action: '#',
        form: {
          date: '',
          am_in: '',
          am_out: '',
          pm_in: '',
          pm_out: '',
          status: '',
          reason: '',
        },
        // Convert "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM" for datetime-local
        toLocal(dt) {
          if (!dt) return '';
          // Accept "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DDTHH:MM:SS"
          const t = dt.replace(' ', 'T');
          // keep only minutes precision
          return t.substring(0,16);
        },
        open(row) {
          this.form.date   = row.date;
          this.form.am_in  = this.toLocal(row.am_in);
          this.form.am_out = this.toLocal(row.am_out);
          this.form.pm_in  = this.toLocal(row.pm_in);
          this.form.pm_out = this.toLocal(row.pm_out);
          this.form.status = row.status || '';
          this.form.reason = '';

          // Build action URL by replacing placeholder
          this.action = baseRoute.replace('DATE_PLACEHOLDER', row.date);

          this.show = true;
        },
        close() {
          this.show = false;
        }
      }
    }
  </script>
</x-app-layout>
