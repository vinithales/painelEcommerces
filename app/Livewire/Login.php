<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function login()
    {
        $this->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', strtolower($this->email).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Muitas tentativas. Aguarde um minuto.');

            return;
        }
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($key, 60);
            $this->password = '';
            $this->addError('email', 'Credenciais inválidas.');

            return;
        }
        RateLimiter::clear($key);
        request()->session()->regenerate();
        $this->password = '';

        return redirect()->intended('/stores');
    }

    public function render()
    {
        return view('livewire.login');
    }
}
