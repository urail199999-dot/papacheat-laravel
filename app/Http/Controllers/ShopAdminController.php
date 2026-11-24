<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopAdminController extends Controller
{
    public function index(Request $r)
    {
        return view('shop.admin', ['products' => Product::with('category')->get(), 'categories' => Category::with('parent')->get(),
            'orders' => Order::with('user', 'assignee')->where('archived', false)->latest()->get(),
            'customers' => User::all(), 'total' => Order::count()]);
    }

    public function edit(?Product $product = null)
    {
        return view('shop.edit', ['product' => $product ?? new Product, 'categories' => Category::with('parent')->get()]);
    }

    public function save(Request $r, ?Product $product = null)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'category_id' => 'required|exists:categories,id', 'price' => 'required|integer|min:1|max:10000000',
            'term' => 'required|string|max:100', 'summary' => 'required|string|max:500', 'description' => 'required|string|max:20000',
            'requirements' => 'required|string|max:10000', 'features' => 'required|string|max:20000', 'status' => 'required|in:Undetected,Detectable', 'hidden' => 'nullable|boolean',
            'screenshots' => 'nullable|array|max:8', 'screenshots.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120', 'clear_screenshots' => 'nullable|boolean']);
        unset($data['screenshots'],$data['clear_screenshots']);
        $data['hidden'] = $r->boolean('hidden');
        if (! $product) {
            $product = new Product;
            $data['slug'] = (Str::slug($data['name']) ?: 'product').'-'.strtolower(Str::random(6));
        }
        $images = $r->boolean('clear_screenshots') ? [] : ($product->screenshots ?? []);
        abort_if(count($images) + count($r->file('screenshots', [])) > 8, 422);
        foreach ($r->file('screenshots', []) as $file) {
            $images[] = $file->store('products', 'public');
        }
        $data['screenshots'] = $images;
        $product->fill($data)->save();

        return redirect('/manage')->with('success', 'Товар сохранён.');
    }

    public function delete(Product $product)
    {
        $product->delete();

        return back()->with('success', 'Товар удалён. История заказов сохранена.');
    }

    public function toggle(Product $product)
    {
        $product->update(['hidden' => ! $product->hidden]);

        return back();
    }

    public function category(Request $r, ?Category $category = null)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'parent_id' => 'nullable|exists:categories,id']);
        if ($category) {
            $parent = $data['parent_id'] ?? null;
            while ($parent) {
                abort_if((int) $parent === $category->id, 422, 'Раздел не может быть вложен в себя.');
                $parent = Category::findOrFail($parent)->parent_id;
            }
        }
        ($category ?? new Category)->fill($data)->save();

        return back()->with('success', 'Раздел сохранён.');
    }

    public function deleteCategory(Category $category)
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            throw ValidationException::withMessages(['category' => 'Сначала переместите товары и подразделы.']);
        }
        $category->delete();

        return back();
    }

    public function claim(Request $r, Order $order)
    {
        DB::transaction(function () use ($r, $order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->assigned_to && $locked->assigned_to !== $r->user()->id, 409);
            $locked->update(['assigned_to' => $r->user()->id, 'status' => 'processing']);
            Alert::create(['user_id' => $locked->user_id, 'order_id' => $locked->id, 'body' => 'Заказ '.$locked->number.' принят в обработку: '.$r->user()->name]);
        });

        return back();
    }

    public function status(Request $r, Order $order)
    {
        $data = $r->validate(['status' => 'required|in:processing,completed,not_completed', 'payment_status' => 'required|in:pending,reported,confirmed']);
        abort_unless($order->assigned_to === $r->user()->id, 409, 'Сначала возьмите заказ в обработку.');
        $order->update($data);
        Alert::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'body' => 'Обновлён статус заказа '.$order->number]);

        return back();
    }

    public function archive(Order $order)
    {
        $order->update(['archived' => true]);

        return back()->with('success', 'Заказ убран из списка администратора, история покупателя сохранена.');
    }

    public function purge(Order $order)
    {
        abort_if(in_array($order->status, ['new', 'processing']), 409, 'Активный заказ нельзя удалить.');
        $order->delete();

        return redirect('/manage')->with('success','История заказа удалена.');
    }
}
