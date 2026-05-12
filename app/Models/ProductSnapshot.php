<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSnapshot extends Model
{
    protected $fillable = [
        'store_id',
        'resource_id',
        'platform',
        'data_before',
        'data_after',
        'reverted_at',
    ];

    protected $casts = [
        'data_before' => 'array',
        'data_after' => 'array',
        'reverted_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function wasReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
