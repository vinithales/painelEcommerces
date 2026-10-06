<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CheckAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_definition_id',
        'store_id',
        'interval_minutes',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'interval_minutes' => 'integer',
    ];

    public function checkDefinition()
    {
        return $this->belongsTo(CheckDefinition::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function executions()
    {
        return $this->hasMany(CheckExecution::class);
    }

    public function latestExecution(): HasOne
    {
        return $this->hasOne(CheckExecution::class)->latestOfMany();
    }

    public function healthStatus(): string
    {
        return match (true) {
            $this->latestExecution?->status === 'error', $this->latestExecution?->outcome === 'failed' => 'CRITICAL',
            $this->latestExecution?->outcome === 'success' => 'OK',
            default => 'UNKNOWN',
        };
    }

}
