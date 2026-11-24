<?php

use App\Http\Controllers\ShopAdminController as Admin;
use App\Http\Controllers\ShopAuthController as Accounts;
use App\Http\Controllers\ShopController as Shop;
use App\Http\Middleware\ShopAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [Shop::class, 'catalog'])->name('home');
Route::get('/product/{product}', [Shop::class, 'product'])->name('product.show');
Route::get('/login', [Accounts::class, 'form'])->name('login');
Route::post('/login', [Accounts::class, 'login'])->middleware('throttle:6,1');
Route::post('/register', [Accounts::class, 'register'])->middleware('throttle:5,1');
Route::post('/logout', [Accounts::class, 'logout'])->middleware('auth');
Route::get('/pulse', [Shop::class, 'pulse'])->middleware('throttle:120,1');
Route::middleware('auth')->group(function () {
    Route::get('/account', [Shop::class, 'account']);
    Route::post('/notifications/read', [Shop::class, 'read']);
    Route::get('/checkout/{product}', [Shop::class, 'checkout'])->name('checkout.show');
    Route::post('/checkout/{product}', [Shop::class, 'buy'])->middleware('throttle:10,1')->block(10, 10)->name('checkout.store');
    Route::get('/orders/{order}', [Shop::class, 'order']);
    Route::post('/orders/{order}/messages', [Shop::class, 'message'])->middleware('throttle:30,1');
    Route::post('/orders/{order}/paid', [Shop::class, 'paid'])->middleware('throttle:5,1');
    Route::get('/attachments/{message}', [Shop::class, 'attachment']);
});
// /manage does not conflict with an optional local Filament panel at /admin.
Route::redirect('/admin', '/manage');
Route::redirect('/admin/login', '/login');
Route::middleware(['auth', ShopAdmin::class])->prefix('manage')->group(function () {
    Route::get('/', [Admin::class, 'index'])->name('admin.dashboard');
    Route::get('/products/new', [Admin::class, 'edit']);
    Route::post('/products', [Admin::class, 'save']);
    Route::get('/products/{product}/edit', [Admin::class, 'edit'])->name('admin.products.edit');
    Route::post('/products/{product}', [Admin::class, 'save']);
    Route::post('/products/{product}/hide', [Admin::class, 'toggle']);
    Route::delete('/products/{product}', [Admin::class, 'delete']);
    Route::post('/categories', [Admin::class, 'category']);
    Route::post('/categories/{category}', [Admin::class, 'category']);
    Route::delete('/categories/{category}', [Admin::class, 'deleteCategory']);
    Route::post('/orders/{order}/claim', [Admin::class, 'claim']);
    Route::post('/orders/{order}/status', [Admin::class, 'status']);
    Route::post('/orders/{order}/archive', [Admin::class, 'archive']);
    Route::delete('/orders/{order}',[Admin::class, 'purge']);
});
