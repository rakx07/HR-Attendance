<?php

namespace App\Imports;

use App\Models\User;
use App\Models\ShiftWindow;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeesImport implements ToModel, WithHeadingRow {
  public function model(array $row) {
    $shift = ShiftWindow::firstWhere('name', $row['shift_window'] ?? 'Default');

    return User::updateOrCreate(
      ['email' => strtolower($row['email'])],
      [
        'name' => $row['name'],
        'password' => Hash::make($row['temp_password'] ?? 'ChangeMe123!'),
        'zkteco_user_id' => $row['zkteco_user_id'] ?? null,
        'department' => $row['department'] ?? null,
        'shift_window_id' => $shift?->id,
        'flexi_start' => $row['flexi_start'] ?? null,
        'flexi_end'   => $row['flexi_end'] ?? null,
        'active' => true,
      ]
    );
  }
}
