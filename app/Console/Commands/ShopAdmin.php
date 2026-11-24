<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ShopAdmin extends Command
{
    protected $signature = 'shop:admin';

    protected $description = 'Create or update the only shop administrator, urail1999';

    public function handle(): int
    {
        $email = $this->ask('Email администратора');
        $password = $this->secret('Новый пароль (минимум 12 символов)');
        $validator = Validator::make(compact('email', 'password'), ['email' => 'required|email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return 1;
        }
        $user = User::where('name', 'urail1999')->first();
        if (User::where('email', $email)->when($user, fn ($q) => $q->where('id', '!=', $user->id))->exists()) {
            $this->error('Email уже занят другим пользователем.');

            return 1;
        }
        User::where('is_admin', true)->update(['is_admin' => false]);
        $user ??= new User;
        $user->name = 'urail1999';
        $user->email = $email;
        $user->password = $password;
        $user->is_admin = true;
        $user->save();
        $this->info('Администратор urail1999 сохранён. Вход: /login. Управление: /manage');

        return 0;
    }
}
