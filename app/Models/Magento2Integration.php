<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Magento2Integration extends Model
{
    protected $hidden = ['consumer_key', 'consumer_secret', 'access_token', 'access_token_secret', 'username', 'password'];
    use HasFactory;

    protected $fillable = [
        'store_id',
        'consumer_key',
        'consumer_secret',
        'access_token',
        'access_token_secret',
        'username',
        'password',
    ];

    protected $casts = [
        'consumer_key' => 'encrypted',
        'consumer_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'access_token_secret' => 'encrypted',
        'username' => 'encrypted',
        'password' => 'encrypted',
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
            'access_token' => $this->access_token,
            'access_token_secret' => $this->access_token_secret,
            'username' => $this->username,
            'password' => $this->password,
        ]);
    }
}
