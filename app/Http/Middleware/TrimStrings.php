<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected function transform($key, $value)
    {
        if ($key === 'ssh_password' || str_ends_with($key, '.ssh_password')) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}
