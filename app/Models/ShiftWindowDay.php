<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftWindowDay extends Model
{
    protected $fillable = ['dow','is_working','am_in','am_out','pm_in','pm_out'];

    public function shiftWindow()
    {
        return $this->belongsTo(ShiftWindow::class);
    }
}
