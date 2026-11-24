@extends('shop.layout')
@section('content')
<h1>Личный кабинет</h1><div class="two"><form class="panel" action="/login" method="post">@csrf<h2>Вход</h2><label for="login">Логин или email</label><input id="login" name="login" autocomplete="username" required><label for="password">Пароль</label><input id="password" type="password" name="password" autocomplete="current-password" required><p><button>Войти</button></p></form>
<form class="panel" action="/register" method="post">@csrf<h2>Регистрация</h2><label>Логин (латинские буквы, цифры, _)</label><input name="name" minlength="3" maxlength="32" required><label>Email</label><input name="email" type="email" required><label>Пароль (от 12 символов)</label><input name="password" type="password" minlength="12" autocomplete="new-password" required><label>Повторите пароль</label><input name="password_confirmation" type="password" autocomplete="new-password" required><p><button>Создать аккаунт</button></p></form></div>
@endsection
