<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'next_run_at',
        'alert_enabled',
        'failure_threshold',
        'recovery_threshold',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'interval_minutes' => 'integer',
        'next_run_at' => 'datetime',
        'alert_enabled' => 'boolean',
        'failure_threshold' => 'integer',
        'recovery_threshold' => 'integer',
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

    public function latestStartedExecution(): HasOne
    {
        return $this->hasOne(CheckExecution::class)->ofMany(['id' => 'max'], fn($query) => $query->whereNotNull('started_at'));
    }

    public function scopeReportStatus(Builder $query, string $status): Builder
    {
        if ($status === 'never') {
            return $query->whereDoesntHave('executions');
        }

        return $query->whereHas('latestExecution', function (Builder $execution) use ($status) {
            match ($status) {
                'attention' => $execution->where(function (Builder $result) {
                        $result->whereIn('status', ['error', 'failed'])
                        ->orWhere(fn($completed) => $completed->where('status', 'completed')->where('outcome', 'failed'));
                    }),
                'failed' => $execution->where(fn($result) => $result->where('status', 'failed')
                    ->orWhere(fn($completed) => $completed->where('status', 'completed')->where('outcome', 'failed'))),
                'error' => $execution->where('status', 'error'),
                'success' => $execution->where('status', 'completed')->whereIn('outcome', ['success', 'passed']),
                'active' => $execution->whereIn('status', ['pending', 'running']),
                'pending', 'running' => $execution->where('status', $status),
                default => $execution->whereRaw('1 = 0'),
            };
        });
    }

    public function healthStatus(): string
    {
        return match (true) {
            $this->latestExecution?->status === 'error', $this->latestExecution?->outcome === 'failed' => 'CRITICAL',
            $this->latestExecution?->outcome === 'success' => 'OK',
            default => 'UNKNOWN',
        };
    }
    public function incidents()
    {
        return $this->hasMany(CheckIncident::class);
    }
}
