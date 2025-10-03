<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\ShiftWindow; // <-- important

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'name','email','password',
        'zkteco_user_id','shift_window_id',
        'flexi_start','flexi_end','department','active',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function shiftWindow()
    {
        return $this->belongsTo(ShiftWindow::class, 'shift_window_id');
    }
    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    public function departmentTransfers()
    {
        return $this->hasMany(\App\Models\DepartmentTransfer::class);
    }

}
