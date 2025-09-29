<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftWindow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'am_in_start', 'am_in_end',
        'am_out_start', 'am_out_end',
        'pm_in_start', 'pm_in_end',
        'pm_out_start', 'pm_out_end',
        'grace_minutes',
    ];
}
