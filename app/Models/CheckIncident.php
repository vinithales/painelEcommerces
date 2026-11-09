<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_assignment_id',
        'opened_at',
        'resolved_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function checkAssignment()
    {
        return $this->belongsTo(CheckAssignment::class);
    }

}
