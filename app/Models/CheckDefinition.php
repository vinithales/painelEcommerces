<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'level',
        'executor_type',
        'configuration',
        'assertions',
    ];

    protected $casts = [
        'configuration' => 'array',
        'assertions' => 'array',
    ];

    public function assignments()
    {
        return $this->hasMany(CheckAssignment::class);
    }
}
