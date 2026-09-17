<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateOperator extends Command
{
    protected $signature = 'operator:create';

    protected $description = 'Criar operador sem senha padrão';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nome'), 'email' => $this->ask('E-mail'), 'role' => $this->choice('Perfil', ['n1', 'n2', 'admin'], 0), 'password' => $this->secret('Senha (mínimo 12 caracteres)')];
        $v = Validator::make($data, ['name' => 'required|max:255', 'email' => 'required|email|unique:users', 'password' => 'required|min:12', 'role' => 'in:n1,n2,admin']);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return 1;
        }
        $u = new User($data);
        $u->role = $data['role'];
        $u->password = Hash::make($data['password']);
        $u->save();
        $this->info('Operador criado.');

        return 0;
    }
}
