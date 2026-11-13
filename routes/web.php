<?php

use Illuminate\Support\Facades\Route;

$products = [
    'tundra-web-radar' => [
        'name' => 'Tundra WEB Radar', 'game' => 'Arena Breakout: Infinite', 'price' => '500 ₽', 'term' => '1 день',
        'summary' => 'Браузерная карта рейда с игроками, ИИ, лутом, опасностями и выходами в реальном времени.',
        'description' => 'Tundra WEB Radar открывается во вкладке браузера на втором мониторе, компьютере или смартфоне. Радар показывает игроков, ботов, боссов, лут и выходы, а локация определяется автоматически.',
        'requirements' => ['Windows 10/11 x64', 'Процессор Intel или AMD', 'Официальный лаунчер или Steam', 'Браузер на ПК, втором мониторе или телефоне'],
        'features' => ['ЧВК и Дикие: ник, команда, уровень, дистанция и направление', 'Здоровье, оружие, патроны, броня и шлем', 'Боты, боссы, свита и элита с отдельными цветами', 'Лут с фильтром по цене и сундуки павших ЧВК', 'Гранаты, мины, клейморы и точки выхода', 'Ферма, Долина, Нортридж, Арсенал, Аэропорт и Телецентр'],
    ],
    'spoofer' => [
        'name' => 'SPOOFER', 'game' => 'Arena Breakout: Infinite', 'price' => '5 000 ₽', 'term' => 'один раз',
        'summary' => 'HWID Spoofer для Arena Breakout: Infinite с запуском на Windows 10/11 x64.',
        'description' => 'HWID Spoofer — это программа, которая подменяет аппаратные идентификаторы компьютера и помогает обойти бан по железу. Работает только с Arena Breakout: Infinite.',
        'requirements' => ['Windows 10 или Windows 11 (64-bit)', 'Клиент игры Arena Breakout: Infinite', 'Права администратора Windows'],
        'features' => ['Подмена аппаратных идентификаторов', 'Запуск перед игрой', 'Статус Undetected', 'Тариф на один запуск'],
    ],
    'spoofer-lifetime' => [
        'name' => 'SPOOFER — навсегда', 'game' => 'Arena Breakout: Infinite', 'price' => '12 000 ₽', 'term' => 'навсегда',
        'summary' => 'HWID Spoofer с ключом навсегда для Arena Breakout: Infinite.',
        'description' => 'HWID Spoofer подменяет аппаратные идентификаторы компьютера и помогает обойти бан по железу. После покупки ключ выдаётся навсегда.',
        'requirements' => ['Windows 10 или Windows 11 (64-bit)', 'Клиент игры Arena Breakout: Infinite', 'Права администратора Windows'],
        'features' => ['Подмена аппаратных идентификаторов', 'Ключ выдаётся навсегда', 'Запуск перед игрой', 'Статус Undetected'],
    ],
    'lag-switch-arena' => [
        'name' => 'Lag Switch', 'game' => 'Arena Breakout: Infinite', 'price' => '1 000 ₽', 'term' => 'навсегда',
        'summary' => 'Программа имитирует высокую задержку или вызывает кратковременную потерю пакетов.',
        'description' => 'Lag Switch управляет сетевой задержкой во время игры и позволяет быстро включать или отключать эффект через горячую клавишу.',
        'requirements' => ['Windows 10/11 x64', 'Arena Breakout: Infinite', 'Стабильное подключение к сети'],
        'features' => ['Имитация высокой задержки', 'Кратковременная потеря пакетов', 'Горячая клавиша включения', 'Статус Undetected'],
    ],
    'lag-switch-dayz' => [
        'name' => 'Lag Switch', 'game' => 'DayZ', 'price' => '1 000 ₽', 'term' => 'навсегда',
        'summary' => 'Программа имитирует высокую задержку или вызывает кратковременную потерю пакетов.',
        'description' => 'Lag Switch для DayZ управляет сетевой задержкой и позволяет быстро включать или отключать эффект во время игры.',
        'requirements' => ['Windows 10/11 x64', 'Клиент DayZ', 'Стабильное подключение к сети'],
        'features' => ['Имитация высокой задержки', 'Кратковременная потеря пакетов', 'Горячая клавиша включения', 'Статус Undetected'],
    ],
 ];

$catalog = function () use (&$products) {
    $path = storage_path('app/products.json');
    if (!is_file($path)) {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : $products;
};

$saveCatalog = function (array $data) {
    $path = storage_path('app/products.json');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
};

Route::get('/', function () use ($catalog) {
    $products = array_filter($catalog(), fn ($p) => empty($p['hidden']));
    return view('welcome', compact('products'));
});

Route::get('/product/{slug}', function (string $slug) use ($catalog) {
    $products = $catalog();
    abort_unless(isset($products[$slug]), 404);
    return view('product', ['product' => $products[$slug], 'slug' => $slug]);
})->name('product.show');

Route::get('/checkout/{slug}', function (string $slug) use ($catalog) {
    $products = $catalog();
    abort_unless(isset($products[$slug]), 404);
    return view('checkout', ['product' => $products[$slug], 'slug' => $slug]);
})->name('checkout.show');

Route::post('/checkout/{slug}', function (\Illuminate\Http\Request $request, string $slug) use ($catalog) {
    $products = $catalog();
    abort_unless(isset($products[$slug]), 404);
    $data = $request->validate(['contact' => ['required', 'string', 'max:120']]);
    $order = 'PC-' . strtoupper(substr(str_replace('-', '', (string) \Illuminate\Support\Str::uuid()), 0, 10));
    session()->put('last_order', ['number' => $order, 'contact' => $data['contact'], 'product' => $products[$slug]['name']]);
    return redirect()->route('checkout.show', $slug)->with('success', 'Заказ создан. Администратор свяжется с вами после подтверждения оплаты.');
})->name('checkout.store');

Route::get('/admin/login', function () {
    return view('admin.login');
})->name('admin.login');

Route::post('/admin/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate(['username' => ['required'], 'password' => ['required']]);
    if ($credentials['username'] !== env('ADMIN_USER', 'urail1999') || !hash_equals((string) env('ADMIN_PASSWORD', 'papacheat'), (string) $credentials['password'])) {
        return back()->withErrors(['username' => 'Неверный логин или пароль.']);
    }
    $request->session()->put('is_admin', true);
    return redirect()->route('admin.dashboard');
})->name('admin.login.store');

Route::get('/admin', function (\Illuminate\Http\Request $request) use ($catalog) {
    if (!$request->session()->get('is_admin')) return redirect()->route('admin.login');
    $products = $catalog();
    return view('admin.dashboard', ['products' => $products, 'lastOrder' => $request->session()->get('last_order')]);
})->name('admin.dashboard');

Route::get('/admin/products/{slug}/edit', function (\Illuminate\Http\Request $request, string $slug) use ($catalog) {
    abort_unless($request->session()->get('is_admin'), 403);
    $products = $catalog(); abort_unless(isset($products[$slug]), 404);
    return view('admin.edit', ['product' => $products[$slug], 'slug' => $slug]);
})->name('admin.products.edit');

Route::post('/admin/products/{slug}', function (\Illuminate\Http\Request $request, string $slug) use ($catalog, $saveCatalog) {
    abort_unless($request->session()->get('is_admin'), 403);
    $products = $catalog(); abort_unless(isset($products[$slug]), 404);
    $data = $request->validate(['name' => ['required','string','max:120'], 'price' => ['required','string','max:40'], 'status' => ['required','in:Undetected,Detectable'], 'hidden' => ['nullable']]);
    $products[$slug]['name'] = $data['name']; $products[$slug]['price'] = $data['price']; $products[$slug]['status'] = $data['status']; $products[$slug]['hidden'] = $request->boolean('hidden');
    $saveCatalog($products); return redirect()->route('admin.dashboard')->with('success', 'Товар сохранён.');
})->name('admin.products.update');

Route::post('/admin/logout', function (\Illuminate\Http\Request $request) {
    $request->session()->forget('is_admin');
    return redirect()->route('admin.login');
})->name('admin.logout');
