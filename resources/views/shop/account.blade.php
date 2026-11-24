@extends('shop.layout')
@section('content')
<h1>{{ auth()->user()->name }} · Личный кабинет</h1><h2>Заказы</h2><div class="grid">@forelse($orders as $order)<a href="/orders/{{ $order->number }}" class="panel"><strong>{{ $order->number }}</strong><h3>{{ $order->product_name }}</h3><p>{{ $order->term }} · {{ number_format($order->amount,0,',',' ') }} ₽</p><span>{{ ['new'=>'Новый','processing'=>'В обработке','completed'=>'Выполнен','not_completed'=>'Не выполнен'][$order->status] }}</span></a>@empty<p>Пока нет заказов. <a href="/">Открыть каталог →</a></p>@endforelse</div>
<h2>Уведомления</h2><form action="/notifications/read" method="post">@csrf<button class="subtle">Отметить прочитанными</button></form>@foreach($alerts as $alert)<p>{{ $alert->read_at?'':'● ' }}{{ $alert->body }} <small>{{ $alert->created_at }}</small></p>@endforeach
@endsection
