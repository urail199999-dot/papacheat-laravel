<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Category;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public function catalog(Request $r)
    {
        $query = Product::with('category')->where('hidden', false);
        if ($r->filled('category')) {
            $ids = collect([(int) $r->category]);
            $pending = $ids;
            while ($pending->isNotEmpty()) {
                $pending = Category::whereIn('parent_id', $pending)->pluck('id');
                $ids = $ids->merge($pending);
            }
            $query->whereIn('category_id', $ids);
        }
        if ($r->filled('q')) {
            $query->where('name', 'like', '%'.$r->string('q').'%');
        }

        return view('shop.catalog', ['products' => $query->orderBy('id')->get(), 'categories' => Category::with('parent')->get()]);
    }

    public function product(Product $product)
    {
        $this->visible($product);

        return view('shop.product', compact('product'));
    }

    private function visible(Product $product): void
    {
        abort_if($product->hidden && ! (auth()->user()?->is_admin && auth()->user()->name === 'urail1999'), 404);
    }

    public function checkout(Product $product)
    {
        $this->visible($product);

        return view('shop.checkout', ['product' => $product, 'token' => (string) Str::uuid()]);
    }

    public function buy(Request $r, Product $product)
    {
        abort_if($product->hidden, 404);
        $data = $r->validate(['token' => 'required|uuid']);
        $key = 'checkout.'.$data['token'];
        if ($number = $r->session()->get($key)) {
            return redirect('/orders/'.$number);
        }
        $order = DB::transaction(function () use ($r, $product) {
            $o = Order::create(['number' => 'PC-'.strtoupper(Str::random(12)), 'user_id' => $r->user()->id, 'product_id' => $product->id, 'product_name' => $product->name, 'term' => $product->term, 'amount' => $product->price]);
            Alert::create(['user_id' => $r->user()->id, 'order_id' => $o->id, 'body' => 'Создан заказ '.$o->number]);
            foreach (User::where('is_admin', true)->where('name', 'urail1999')->get() as $admin) {
                Alert::create(['user_id' => $admin->id, 'order_id' => $o->id, 'body' => 'Новый заказ '.$o->number]);
            }

            return $o;
        });
        $r->session()->put($key, $order->number);

        return redirect('/orders/'.$order->number);
    }

    public function account(Request $r)
    {
        return view('shop.account', ['orders' => Order::where('user_id', $r->user()->id)->latest()->get(), 'alerts' => Alert::where('user_id', $r->user()->id)->latest()->limit(50)->get()]);
    }

    private function access(Order $order): void
    {
        abort_unless(auth()->id() === $order->user_id || (auth()->user()?->is_admin && auth()->user()->name === 'urail1999'), 403);
    }

    public function order(Order $order)
    {
        $this->access($order);
        $order->load('messages.user', 'assignee');

        return view('shop.order', compact('order'));
    }

    public function message(Request $r, Order $order)
    {
        $this->access($order);
        $r->validate(['body' => 'nullable|string|max:4000', 'file' => 'nullable|file|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm|max:51200']);
        abort_unless(trim((string) $r->body) !== '' || $r->hasFile('file'), 422);
        $path = $r->hasFile('file') ? $r->file('file')->store('chat', 'local') : null;
        $order->messages()->create(['user_id' => $r->user()->id, 'body' => $r->body, 'file_path' => $path, 'file_name' => $r->file('file')?->getClientOriginalName(), 'mime' => $r->file('file')?->getMimeType()]);
        $recipients = $r->user()->id === $order->user_id ? User::where('is_admin', true)->where('name', 'urail1999')->pluck('id') : collect([$order->user_id]);
        foreach ($recipients->filter() as $id) {
            Alert::create(['user_id' => $id, 'order_id' => $order->id, 'body' => 'Сообщение в заказе '.$order->number]);
        }

        return back();
    }

    public function attachment(Message $message)
    {
        $this->access($message->order);
        abort_unless($message->file_path, 404);

        return Storage::disk('local')->response($message->file_path, $message->file_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function paid(Order $order)
    {
        $this->access($order);
        if ($order->payment_status === 'pending') {
            $order->update(['payment_status' => 'reported']);
        }

        return back()->with('success', 'Заявка отправлена. Оплату проверяет администратор.');
    }

    public function pulse(Request $r)
    {
        DB::table('visitors')->updateOrInsert(['id' => hash('sha256', $r->session()->getId())], ['seen_at' => now()]);
        DB::table('visitors')->where('seen_at', '<', now()->subMinutes(10))->delete();
        $alerts = $r->user() ? Alert::where('user_id', $r->user()->id)->whereNull('read_at')->count() : 0;
        $latest = $r->user() ? Alert::where('user_id', $r->user()->id)->max('id') : 0;

        return response()->json(['online' => DB::table('visitors')->where('seen_at', '>', now()->subSeconds(90))->count(), 'unread' => $alerts, 'latest' => $latest ?? 0]);
    }

    public function read(Request $r)
    {
        Alert::where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
