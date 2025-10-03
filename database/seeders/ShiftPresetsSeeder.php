<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ShiftWindow;

class ShiftPresetsSeeder extends Seeder
{
    public function run(): void
    {
        // 8–5 Standard
        $s = ShiftWindow::firstOrCreate(['name'=>'8-5 Standard'],[
            'grace_minutes'=>0,
            'am_in_start'=>'07:30:00','am_in_end'=>'08:30:00',
            'am_out_start'=>'11:30:00','am_out_end'=>'12:30:00',
            'pm_in_start'=>'13:00:00','pm_in_end'=>'14:00:00',
            'pm_out_start'=>'17:00:00','pm_out_end'=>'18:00:00',
        ]);
        foreach ([1,2,3,4,5] as $d) $s->days()->updateOrCreate(['dow'=>$d],
            ['is_working'=>1,'am_in'=>'07:30:00','am_out'=>'11:30:00','pm_in'=>'13:00:00','pm_out'=>'17:00:00']);
        $s->days()->updateOrCreate(['dow'=>6], ['is_working'=>1,'am_in'=>'07:30:00','am_out'=>'11:30:00','pm_in'=>null,'pm_out'=>null]);
        $s->days()->updateOrCreate(['dow'=>7], ['is_working'=>0]);

        // Flexi 1
        $f = ShiftWindow::firstOrCreate(['name'=>'Flexi 1'],[
            'grace_minutes'=>0,
            'am_in_start'=>'07:30:00','am_in_end'=>'09:00:00',
            'am_out_start'=>'12:00:00','am_out_end'=>'12:30:00',
            'pm_in_start'=>'13:00:00','pm_in_end'=>'14:00:00',
            'pm_out_start'=>'17:30:00','pm_out_end'=>'18:00:00',
        ]);
        foreach ([1,2,3,4] as $d) $f->days()->updateOrCreate(['dow'=>$d],
            ['is_working'=>1,'am_in'=>'07:30:00','am_out'=>'12:00:00','pm_in'=>'13:00:00','pm_out'=>'17:30:00']);
        $f->days()->updateOrCreate(['dow'=>5],
            ['is_working'=>1,'am_in'=>'07:30:00','am_out'=>'11:30:00','pm_in'=>'13:00:00','pm_out'=>'17:00:00']);
        $f->days()->updateOrCreate(['dow'=>6], ['is_working'=>0]);
        $f->days()->updateOrCreate(['dow'=>7], ['is_working'=>0]);
    }
}
