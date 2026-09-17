<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('operate', fn (User $u) => in_array($u->role, ['n1', 'n2', 'admin'], true));
        Gate::define('manage', fn (User $u) => in_array($u->role, ['n2', 'admin'], true));
        Gate::define('configure', fn (User $u) => $u->role === 'admin');
    }
}
