<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ShiftWindow;

class ShiftWindowSeeder extends Seeder
{
    public function run(): void
    {
        ShiftWindow::firstOrCreate(
            ['name' => 'Default 8-5'],
            [
                'am_in_start'   => '07:30', 'am_in_end'   => '09:00',
                'am_out_start'  => '11:00', 'am_out_end'  => '12:00',
                'pm_in_start'   => '12:30', 'pm_in_end'   => '14:00',
                'pm_out_start'  => '16:00', 'pm_out_end'  => '18:00',
                'grace_minutes' => 10,
            ]
        );
    }
}
