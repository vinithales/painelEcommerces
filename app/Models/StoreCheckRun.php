<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCheckRun extends Model
{
    protected $guarded = [];

    protected $casts = ['details' => 'array', 'metrics' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'manual' => 'boolean'];
}
