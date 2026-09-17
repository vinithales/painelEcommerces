<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceIntegration extends Model
{
    protected $hidden = ['consumer_key', 'consumer_secret'];

    use HasFactory;

    protected $table = 'woocommerce_integrations';

    protected $fillable = [
        'store_id',
        'consumer_key',
        'consumer_secret',
    ];

    protected $casts = [
        'consumer_key' => 'encrypted',
        'consumer_secret' => 'encrypted',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function credentials(): array
    {
        return array_filter([
            'consumer_key' => $this->consumer_key,
            'consumer_secret' => $this->consumer_secret,
        ]);
    }
}
