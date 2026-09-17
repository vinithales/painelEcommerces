<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerConnection extends Model
{
    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = ['token' => 'encrypted'];
}
