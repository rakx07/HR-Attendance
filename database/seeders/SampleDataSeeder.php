<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ShiftWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure a shift window exists
        $sw = ShiftWindow::firstOrCreate(
            ['name' => 'Default 8-5'],
            [
                'am_in_start'   => '07:30','am_in_end'   => '09:00',
                'am_out_start'  => '11:00','am_out_end'  => '12:00',
                'pm_in_start'   => '12:30','pm_in_end'   => '14:00',
                'pm_out_start'  => '16:00','pm_out_end'  => '18:00',
                'grace_minutes' => 10,
            ]
        );

        // Create a few employees
        $people = [
            ['Employee One',   'employee1@example.com', 101, 'IT'],
            ['Employee Two',   'employee2@example.com', 102, 'Registrar'],
            ['Employee Three', 'employee3@example.com', 103, 'Finance'],
            ['Employee Four',  'employee4@example.com', 104, 'HR'],
            ['Employee Five',  'employee5@example.com', 105, 'IT'],
        ];

        $users = [];
        foreach ($people as [$name,$email,$zk,$dept]) {
            $users[] = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('ChangeMe123!'),
                    'zkteco_user_id' => $zk,
                    'shift_window_id' => $sw->id,
                    'department' => $dept,
                    'active' => true,
                ]
            );
        }

        // Generate last week's punches Mon–Fri
        $start = Carbon::today()->startOfWeek(Carbon::MONDAY)->subWeek(); // last Mon
        $end   = $start->copy()->addDays(4); // Mon..Fri

        foreach ($users as $u) {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                // Times with slight jitter
                $amIn  = $d->copy()->setTime(8,0)->addMinutes(rand(-10,10));
                $amOut = $d->copy()->setTime(12,0)->addMinutes(rand(-5,5));
                $pmIn  = $d->copy()->setTime(13,0)->addMinutes(rand(-5,5));
                $pmOut = $d->copy()->setTime(17,0)->addMinutes(rand(-10,10));

                foreach ([$amIn,$amOut,$pmIn,$pmOut] as $t) {
                    DB::table('attendance_raw')->updateOrInsert(
                        ['device_user_id' => $u->zkteco_user_id, 'punched_at' => $t],
                        [
                            'user_id' => $u->id,
                            'state' => 0,
                            'device_ip' => 'sim',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        // Add a “double scan” to test cooldown logic
        $u = $users[0];
        $tuesday = $start->copy()->addDay(); // Tuesday
        DB::table('attendance_raw')->insert([
            'user_id' => $u->id,
            'device_user_id' => $u->zkteco_user_id,
            'punched_at' => $tuesday->copy()->setTime(8,0,3),
            'state' => 0,
            'device_ip' => 'sim',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
