<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HolidayCalendar extends Model
{
    protected $fillable = ['year','status','activated_at'];

    public function dates()
    {
        return $this->hasMany(HolidayDate::class);
    }

    /** Get active calendar for a given year (or null). */
    public static function activeForYear(int $year): ?self
    {
        return static::where('year', $year)->where('status','active')->first();
    }
}
