<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        // Columns that map to your users table & import rules
        return [
            'school_id',       // e.g., 1001
            'zkteco_user_id',  // e.g., 2019545  (can be blank; link later)
            'email',           // required & unique
            'temp_password',   // required (min 8) - used only on creation
            'last_name',       // required
            'first_name',      // required
            'middle_name',     // optional
            'department_id',   // optional (FK)
            'shift_window_id', // optional; if blank, system uses default first shift
            'flexi_start',     // optional HH:MM
            'flexi_end',       // optional HH:MM
            'active',          // 1 or 0
        ];
    }

    public function array(): array
    {
        // a couple of example rows (safe to remove)
        return [
            ['1001','2019545','employee1@example.com','TempPass#1','Dela Cruz','Juan','Santos', null, null,'08:00','17:00',1],
            ['1002','2001856','employee2@example.com','TempPass#2','Santos','Maria',null,       null, null,null,  null, 1],
        ];
    }
}
