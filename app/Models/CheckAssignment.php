<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_definition_id',
        'store_id',
        'interval_minutes',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'interval_minutes' => 'integer',
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

}
