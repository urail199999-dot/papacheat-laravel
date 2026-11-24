<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ShopAuthController extends Controller
{
    public function form()
    {
        return view('shop.auth');
    }

    public function register(Request $r)
    {
        $data = $r->validate(['name' => ['required', 'regex:/^[A-Za-z0-9_]{3,32}$/', 'unique:users,name', 'not_in:urail1999'], 'email' => 'required|email|max:254|unique:users,email', 'password' => 'required|string|min:12|confirmed']);
        $user = User::create($data);
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/account');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['login' => 'required|string|max:254', 'password' => 'required|string']);
        $field = str_contains($data['login'], '@') ? 'email' : 'name';
        if (! Auth::attempt([$field => $data['login'], 'password' => $data['password']])) {
            throw ValidationException::withMessages(['login' => 'Неверный логин или пароль.']);
        }
        $r->session()->regenerate();

        return redirect()->intended('/account');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }
}
