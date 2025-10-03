<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentTransfer extends Model
{
    protected $fillable = [
        'user_id','from_department_id','to_department_id','reason','effective_at','created_by'
    ];

    protected $casts = ['effective_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function from(): BelongsTo { return $this->belongsTo(Department::class, 'from_department_id'); }
    public function to(): BelongsTo   { return $this->belongsTo(Department::class, 'to_department_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
