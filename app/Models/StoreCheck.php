<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCheck extends Model
{
    protected $guarded = [];

    protected $casts = ['configuration' => 'array', 'enabled' => 'boolean', 'notify_on_failure' => 'boolean', 'notify_on_recovery' => 'boolean', 'last_run_at' => 'datetime', 'next_run_at' => 'datetime', 'last_alert_at' => 'datetime', 'claimed_until' => 'datetime'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function runs()
    {
        return $this->hasMany(StoreCheckRun::class);
    }

    public function alerts()
    {
        return $this->hasMany(StoreCheckAlert::class);
    }
}
