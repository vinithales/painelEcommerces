<?php

namespace App\Models;

use App\Support\Redactor;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $guarded = [];
    protected $casts = ['parameters' => 'array'];

    
    public static function record(?int $store, string $resource, string $action, array $parameters = [], string $result = 'ok', ?string $error = null, ?int $actor = null): void
    {
        static::create(['user_id' => $actor ?? auth()->id(), 'store_id' => $store, 'resource' => $resource, 'action' => $action, 'result' => $result, 'parameters' => Redactor::clean($parameters), 'error' => Redactor::clean($error)]);
    }
}
