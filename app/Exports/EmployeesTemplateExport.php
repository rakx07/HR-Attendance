<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesTemplateExport implements FromArray, WithHeadings
{
    /**
     * Define Excel column headers that match the import fields.
     */
    public function headings(): array
    {
        return [
            'school_id',        // optional; used if email is blank
            'zkteco_user_id',   // optional
            'email',            // optional (can be blank)
            'temp_password',    // optional (default: ChangeMe123!)
            'last_name',        // required
            'first_name',       // required
            'middle_name',      // optional
            'department_id',    // optional (FK)
            'shift_window_id',  // optional; if blank → default shift = ID 2
            'shift_window',     // optional by name (e.g., "Morning Shift")
            'flexi_start',      // optional HH:MM (e.g., 08:00)
            'flexi_end',        // optional HH:MM (e.g., 17:00)
            'active',           // 1 = active, 0 = inactive
        ];
    }

    /**
     * Optionally include sample rows.
     * These can be deleted by the user before upload.
     */
    public function array(): array
    {
        return [
            [
                '1001',      // school_id
                '2019545',   // zkteco_user_id
                'juan.dcruz@example.com', // email
                'TempPass#1',             // temp_password
                'Dela Cruz',              // last_name
                'Juan',                   // first_name
                'Santos',                 // middle_name
                1,                        // department_id (optional)
                null,                     // shift_window_id (blank → defaults to ID 2)
                'Morning Shift',           // shift_window name (optional)
                '08:00',                  // flexi_start
                '17:00',                  // flexi_end
                1,                        // active
            ],
            [
                '1002',
                '2001856',
                '',                       // blank email allowed
                '',                       // no temp password → uses ChangeMe123!
                'Santos',
                'Maria',
                null,
                null,
                null,
                null,
                null,
                null,
                1,
            ],
        ];
    }
}
