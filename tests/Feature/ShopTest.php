<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $name = 'buyer'): User
    {
        return User::create(['name' => $name, 'email' => $name.'@example.test', 'password' => 'long-password-123']);
    }

    private function admin(): User
    {
        $u = $this->customer('urail1999');
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    private function product(): Product
    {
        $c = Category::create(['name' => 'Arena']);

        return Product::create(['category_id' => $c->id, 'slug' => 'radar', 'name' => 'Radar', 'price' => 500, 'term' => '1 день', 'summary' => 'Кратко', 'description' => 'Описание', 'requirements' => 'Windows', 'features' => 'Карта']);
    }

    private function order(User $u, Product $p): Order
    {
        return Order::create(['user_id' => $u->id, 'product_id' => $p->id, 'number' => 'PC-'.Str::random(12), 'product_name' => $p->name, 'amount' => $p->price, 'term' => $p->term]);
    }

    public function test_registration_reserves_admin_and_login_uses_hashed_password(): void
    {
        $this->post('/register', ['name' => 'urail1999', 'email' => 'admin@example.test', 'password' => 'long-password-123', 'password_confirmation' => 'long-password-123'])->assertSessionHasErrors('name');
        $this->post('/register', ['name' => 'customer', 'email' => 'customer@example.test', 'password' => 'long-password-123', 'password_confirmation' => 'long-password-123'])->assertRedirect('/account');
        $u = User::firstOrFail();
        $this->assertFalse($u->is_admin);
        $this->assertTrue(Hash::check('long-password-123', $u->password));
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['login' => 'customer', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'customer', 'password' => 'long-password-123'])->assertRedirect('/account');
    }

    public function test_admin_editor_changes_real_catalog_and_hidden_links_are_blocked(): void
    {
        $p = $this->product();
        $buyer = $this->customer();
        $this->actingAs($buyer)->get('/manage')->assertForbidden();
        $this->actingAs($buyer)->post('/manage/products/'.$p->slug.'/hide')->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/manage/products/'.$p->slug, ['name' => 'Changed', 'category_id' => $p->category_id, 'price' => 1200, 'term' => 'Навсегда', 'summary' => 'Summary', 'description' => 'Description', 'requirements' => 'Windows', 'features' => 'Features', 'status' => 'Detectable', 'hidden' => 1])->assertRedirect('/manage');
        $this->assertDatabaseHas('products', ['id' => $p->id, 'name' => 'Changed', 'price' => 1200, 'status' => 'Detectable', 'hidden' => true]);
        $this->actingAs($buyer)->get('/')->assertDontSee('Changed');
        $this->get('/product/'.$p->slug)->assertNotFound();
        $this->get('/checkout/'.$p->slug)->assertNotFound();
        $this->post('/checkout/'.$p->slug, ['token' => (string) Str::uuid()])->assertNotFound();
        $this->actingAs($admin)->get('/product/'.$p->slug)->assertOk();
    }

    public function test_checkout_persists_orders_and_notifications_and_duplicate_form_is_idempotent(): void
    {
        $admin = $this->admin();
        $buyer = $this->customer();
        $p = $this->product();
        $token = (string) Str::uuid();
        $this->actingAs($buyer)->post('/checkout/'.$p->slug, ['token' => $token])->assertRedirect();
        $this->post('/checkout/'.$p->slug, ['token' => $token])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $o = Order::firstOrFail();
        $this->assertStringStartsWith('PC-', $o->number);
        $this->assertEquals(500, $o->amount);
        $p->update(['price' => 900]);
        $this->assertEquals(500, $o->fresh()->amount);
        $this->assertDatabaseHas('alerts', ['user_id' => $admin->id, 'order_id' => $o->id]);
        $this->get('/account')->assertSee($o->number);
        $this->post('/orders/'.$o->number.'/paid')->assertRedirect();
        $this->assertEquals('reported', $o->fresh()->payment_status);
    }

    public function test_only_order_owner_and_admin_can_access_chat_and_attachments(): void
    {
        Storage::fake('local');
        $owner = $this->customer();
        $other = $this->customer('other');
        $admin = $this->admin();
        $p = $this->product();
        $o = $this->order($owner, $p);
        $this->actingAs($other)->get('/orders/'.$o->number)->assertForbidden();
        $this->post('/orders/'.$o->number.'/messages', ['body' => 'Hack'])->assertForbidden();
        $this->actingAs($owner)->post('/orders/'.$o->number.'/messages', ['body' => 'Help', 'file' => UploadedFile::fake()->create('screen.png', 12, 'image/png')])->assertRedirect();
        $message = Message::firstOrFail();
        $this->assertDatabaseHas('alerts', ['user_id' => $admin->id, 'order_id' => $o->id]);
        $this->actingAs($other)->get('/attachments/'.$message->id)->assertForbidden();
        $this->actingAs($owner)->get('/attachments/'.$message->id)->assertOk();
        $this->actingAs($admin)->get('/orders/'.$o->number)->assertOk()->assertSee('Help');
        $this->post('/orders/'.$o->number.'/messages', ['file' => UploadedFile::fake()->create('script.html', 2, 'text/html')])->assertSessionHasErrors('file');
    }

    public function test_status_requires_claim_and_active_orders_cannot_be_deleted(): void
    {
        $a = $this->admin();
        $u = $this->customer();
        $p = $this->product();
        $o = $this->order($u, $p);
        $this->actingAs($a)->post('/manage/orders/'.$o->number.'/status', ['status' => 'completed', 'payment_status' => 'confirmed'])->assertStatus(409);
        $this->delete('/manage/orders/'.$o->number)->assertStatus(409);
        $this->post('/manage/orders/'.$o->number.'/claim')->assertRedirect();
        $this->post('/manage/orders/'.$o->number.'/status', ['status' => 'completed', 'payment_status' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $o->id, 'assigned_to' => $a->id, 'status' => 'completed', 'payment_status' => 'confirmed']);
        $this->delete('/manage/orders/'.$o->number)->assertRedirect('/manage');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_categories_prevent_cycles_and_deletion_with_products_and_allow_product_deletion(): void
    {
        $a = $this->admin();
        $p = $this->product();
        $c = $p->category;
        $child = Category::create(['name' => 'Child', 'parent_id' => $c->id]);
        $this->actingAs($a)->post('/manage/categories/'.$c->id, ['name' => 'Arena', 'parent_id' => $child->id])->assertStatus(422);
        $this->delete('/manage/categories/'.$c->id)->assertSessionHasErrors('category');
        $this->delete('/manage/products/'.$p->slug)->assertRedirect();
        $this->assertDatabaseCount('products', 0);
        $this->delete('/manage/categories/'.$child->id)->assertRedirect();
        $this->delete('/manage/categories/'.$c->id)->assertRedirect();
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_all_management_pages_render_and_count_online_sessions(): void
    {
        $a = $this->admin();
        $this->product();
        $this->actingAs($a);
        foreach (['/', '/manage', '/manage/products/new', '/manage/products/radar/edit', '/account', '/product/radar', '/checkout/radar'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->getJson('/pulse')->assertOk()->assertJsonPath('online', 1);
    }

    public function test_admin_can_create_product(): void
    {
        Storage::fake('public');
        $a = $this->admin();
        $category = Category::create(['name' => 'DayZ']);
        $this->actingAs($a)->post('/manage/products', [
            'name' => 'New program', 'category_id' => $category->id, 'price' => 1000, 'term' => 'Навсегда',
            'summary' => 'Summary', 'description' => 'Description', 'requirements' => 'Requirements',
            'features' => 'Features', 'status' => 'Undetected',
        ])->assertRedirect('/manage');
        $product = Product::firstOrFail();
        $this->assertNotEmpty($product->slug);
        $this->get('/product/'.$product->slug)->assertOk()->assertSee('New program');
    }

    public function test_admin_name_alone_does_not_grant_permission(): void
    {
        $u = $this->customer('urail1999');
        $this->actingAs($u)->get('/manage')->assertForbidden();
    }
}
