<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCheckAlert extends Model
{
    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime'];

    public function check()
    {
        return $this->belongsTo(StoreCheck::class, 'store_check_id');
    }
}
