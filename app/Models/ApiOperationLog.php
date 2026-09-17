<?php

namespace App\Models;

use App\Support\Redactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiOperationLog extends Model
{
    protected static function booted(): void
    {
        static::retrieved(function ($log) {
            foreach (['payload', 'response', 'error_message'] as $field) {
                $log->$field = Redactor::clean($log->$field);
            }
        });
        static::saving(function ($log) {
            foreach (['payload', 'response', 'error_message'] as $field) {
                $log->$field = Redactor::clean($log->$field);
            }
        });
    }

    protected $fillable = [
        'store_id',
        'operation_type',
        'resource_type',
        'resource_id',
        'payload',
        'response',
        'status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
