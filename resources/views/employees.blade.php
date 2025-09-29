<x-app-layout>
  <h2 class="text-xl font-bold mb-4">Employees</h2>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

  <form action="{{ route('employees.upload') }}" method="POST" enctype="multipart/form-data" class="mb-6">
    @csrf
    <label>Upload Excel (.xlsx): <input type="file" name="file" required></label>
    <button class="btn btn-primary">Import</button>
    <a href="{{ asset('templates/employee_upload_template.xlsx') }}" class="ml-3">Download Template</a>
  </form>

  <form action="{{ route('employees.store') }}" method="POST" class="mb-8">
    @csrf
    <input name="name" placeholder="Name" required>
    <input name="email" placeholder="Email" required>
    <input name="temp_password" placeholder="Temp Password" required>
    <input name="zkteco_user_id" placeholder="ZKTeco ID">
    <select name="shift_window_id">
      <option value="">(no shift)</option>
      @foreach($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
    </select>
    <input name="flexi_start" placeholder="Flexi start (HH:MM)">
    <input name="flexi_end" placeholder="Flexi end (HH:MM)">
    <button class="btn btn-success">Create Employee</button>
  </form>

  <table class="table">
    <tr><th>Name</th><th>Email</th><th>ZK ID</th><th>Shift</th><th>Flexi</th></tr>
    @foreach($users as $u)
      <tr>
        <td>{{ $u->name }}</td>
        <td>{{ $u->email }}</td>
        <td>{{ $u->zkteco_user_id }}</td>
        <td>{{ optional($u->shiftWindow)->name }}</td>
        <td>{{ $u->flexi_start }}–{{ $u->flexi_end }}</td>
      </tr>
    @endforeach
  </table>
  {{ $users->links() }}
</x-app-layout>
