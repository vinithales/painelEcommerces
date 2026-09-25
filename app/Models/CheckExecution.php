<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_assignment_id',
        'definition_snapshot',
        'status',
        'started_at',
        'finished_at',
        'outcome',
        'result',
        'error_message',
    ];

    protected $casts = [
        'definition_snapshot' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function checkAssignment()
    {
        return $this->belongsTo(CheckAssignment::class);
    }
}
