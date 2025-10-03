<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HolidayDate extends Model
{
    protected $fillable = ['holiday_calendar_id','date','name','is_non_working'];

    public function calendar()
    {
        return $this->belongsTo(HolidayCalendar::class,'holiday_calendar_id');
    }
}
