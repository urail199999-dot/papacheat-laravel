@extends('shop.layout')
@section('content')
<h1>Оформление заказа</h1><div class="panel"><h2>{{ $product->name }}</h2><p>{{ $product->category->name }} · {{ $product->term }}</p><div class="price">{{ number_format($product->price,0,',',' ') }} ₽</div><p>Покупатель: {{ auth()->user()->name }}. Номер заказа, оплата и чат появятся в личном кабинете.</p><form action="{{ route('checkout.store',$product) }}" method="post">@csrf<input type="hidden" name="token" value="{{ $token }}"><button>Создать заказ</button></form></div>
@endsection
