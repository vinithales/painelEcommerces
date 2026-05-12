<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkEditTemplate extends Model
{
    protected $fillable = [
        'name',
        'resource_type',
        'attribute_changes',
        'store_id',
    ];

    protected $casts = [
        'attribute_changes' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isGlobal(): bool
    {
        return $this->store_id === null;
    }
}
