<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyUpgradeTest extends TestCase
{
    public function test_legacy_schema_upgrade_preserves_records_and_can_be_retried(): void
    {
        Schema::dropAllTables();
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('parent_id')->nullable()->constrained('categories');
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('game');
            $t->string('price');
            $t->string('term');
            $t->text('summary');
            $t->longText('description');
            $t->json('requirements');
            $t->json('features');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('contact');
            $t->string('status')->default('new');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $user = User::create(['name' => 'urail1999', 'email' => 'owner@example.test', 'password' => 'existing-password-123']);
        $user->is_admin = true;
        $user->save();
        $hash = $user->password;
        $id = DB::table('products')->insertGetId(['slug' => 'legacy', 'name' => 'Old program', 'game' => 'DayZ', 'price' => '5 000 ₽', 'term' => 'Навсегда', 'summary' => 'Old summary', 'description' => 'Old description', 'requirements' => json_encode(['Windows']), 'features' => json_encode(['One', 'Two']), 'is_active' => false]);
        $orderId = DB::table('orders')->insertGetId(['number' => 'PC-OLD', 'product_id' => $id, 'contact' => 'owner@example.test', 'status' => 'new', 'notes' => 'Retain these notes']);
        $migration = require database_path('migrations/2026_11_24_000001_create_shop_tables.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('products', ['id' => $id, 'price' => '5000', 'legacy_price' => '5 000 ₽', 'hidden' => 1]);
        $this->assertEquals($hash, $user->fresh()->password);
        $this->assertTrue($user->fresh()->is_admin);
        $product = Product::findOrFail($id);
        $this->assertSame("One\nTwo", $product->features);
        $product->features = "Edited\nAnother";
        $product->save();
        $this->assertSame("Edited\nAnother", $product->fresh()->features);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'user_id' => null, 'contact' => 'owner@example.test', 'notes' => 'Retain these notes']);
        $this->actingAs($user)->get('/manage')->assertOk()->assertSee('PC-OLD');
        $this->get('/orders/PC-OLD')->assertOk()->assertSee('Историческая сумма неизвестна');
        $this->post('/manage/orders/PC-OLD/claim')->assertRedirect();
        $this->post('/manage/orders/PC-OLD/status', ['status' => 'completed', 'payment_status' => 'pending'])->assertRedirect();
        $this->get('/account')->assertOk()->assertDontSee('PC-OLD');
        // New products must work despite the old NOT NULL game and JSON columns.
        $new = Product::create(['slug' => 'new-program', 'name' => 'New', 'category_id' => $product->category_id, 'price' => 1000, 'term' => '1 день', 'summary' => 'S', 'description' => 'D', 'requirements' => 'Windows', 'features' => 'A']);
        Order::create(['number' => 'PC-NEW', 'user_id' => $user->id, 'product_id' => $new->id, 'product_name' => $new->name, 'term' => $new->term, 'amount' => $new->price]);
        $this->get('/account')->assertOk()->assertSee('PC-NEW')->assertDontSee('PC-OLD');
    }

    protected function tearDown(): void
    {
        Schema::dropAllTables();
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }
}
