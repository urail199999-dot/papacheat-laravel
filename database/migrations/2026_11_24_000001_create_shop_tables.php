<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        }
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $t) {
                $t->id();
                $t->string('slug')->unique();
                $t->foreignId('category_id')->constrained()->restrictOnDelete();
                $t->string('name');
                $t->unsignedInteger('price');
                $t->string('term');
                $t->string('status')->default('Undetected');
                $t->boolean('hidden')->default(false);
                $t->text('summary');
                $t->text('description');
                $t->text('requirements');
                $t->text('features');
                $t->json('screenshots')->nullable();
                $t->timestamps();
            });
        } else {
            $hiddenAdded = ! Schema::hasColumn('products', 'hidden');
            $this->column('products', 'category_id', fn (Blueprint $t) => $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete());
            $this->column('products', 'status', fn (Blueprint $t) => $t->string('status')->default('Undetected'));
            $this->column('products', 'hidden', fn (Blueprint $t) => $t->boolean('hidden')->default(false));
            $this->column('products', 'screenshots', fn (Blueprint $t) => $t->json('screenshots')->nullable());
            if (Schema::hasColumn('products', 'game')) {
                $this->column('products', 'legacy_price', fn (Blueprint $t) => $t->string('legacy_price')->nullable());
                // Keep the original game and price values. New products no longer need a game column.
                Schema::table('products', fn (Blueprint $t) => $t->string('game')->nullable()->change());
                DB::table('products')->orderBy('id')->chunkById(100, function ($rows) use ($hiddenAdded) {
                    foreach ($rows as $p) {
                        $changes = [];
                        if (! $p->category_id) {
                            $name = trim((string) $p->game) ?: 'Без раздела';
                            $id = DB::table('categories')->where('name', $name)->whereNull('parent_id')->value('id');
                            $changes['category_id'] = $id ?: DB::table('categories')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                        }
                        if ($p->legacy_price === null) {
                            $changes['legacy_price'] = (string) $p->price;
                            $changes['price'] = (int) preg_replace('/[^0-9]/', '', (string) $p->price);
                        }
                        if ($hiddenAdded && property_exists($p, 'is_active')) {
                            $changes['hidden'] = ! $p->is_active;
                        }
                        if ($changes) {
                            DB::table('products')->where('id', $p->id)->update($changes);
                        }
                    }
                });
            }
        }
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $t) {
                $t->id();
                $t->string('number')->unique();
                $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
                $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $t->string('product_name');
                $t->string('term');
                $t->unsignedInteger('amount');
                $t->string('status')->default('new');
                $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
                $t->string('payment_status')->default('pending');
                $t->boolean('archived')->default(false);
                $t->timestamps();
            });
        } else {
            $this->column('orders', 'user_id', fn (Blueprint $t) => $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete());
            $this->column('orders', 'product_name', fn (Blueprint $t) => $t->string('product_name')->default('Старый заказ'));
            $this->column('orders', 'term', fn (Blueprint $t) => $t->string('term')->default(''));
            $this->column('orders', 'amount', fn (Blueprint $t) => $t->unsignedInteger('amount')->default(0));
            $this->column('orders', 'assigned_to', fn (Blueprint $t) => $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete());
            $this->column('orders', 'payment_status', fn (Blueprint $t) => $t->string('payment_status')->default('pending'));
            $this->column('orders', 'archived', fn (Blueprint $t) => $t->boolean('archived')->default(false));
            if (Schema::hasColumn('orders', 'contact')) {
                Schema::table('orders', fn (Blueprint $t) => $t->string('contact')->nullable()->change());
                $this->column('orders', 'legacy_status', fn (Blueprint $t) => $t->string('legacy_status')->nullable());
                $this->column('orders', 'legacy_migrated_at', fn (Blueprint $t) => $t->timestamp('legacy_migrated_at')->nullable());
                DB::table('orders')->whereNull('user_id')->whereNull('legacy_migrated_at')->orderBy('id')->chunkById(100, function ($rows) {
                    foreach ($rows as $o) {
                        $p = $o->product_id ? DB::table('products')->where('id', $o->product_id)->first() : null;
                        // Guest contact text is not proof of account ownership. Do not link by email.
                        // The old schema had no price snapshot; zero means historical price is unknown.
                        DB::table('orders')->where('id', $o->id)->update([
                            'product_name' => $p?->name ?? 'Старый заказ (товар недоступен)',
                            'term' => $p?->term ?? '', 'legacy_status' => $o->status,
                            'status' => in_array($o->status, ['new', 'processing', 'completed', 'not_completed'], true) ? $o->status : 'new',
                            'legacy_migrated_at' => now(),
                        ]);
                    }
                });
            }
        }
        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('order_id')->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->restrictOnDelete();
                $t->text('body')->nullable();
                $t->string('file_path')->nullable();
                $t->string('file_name')->nullable();
                $t->string('mime')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('body');
                $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('visitors')) {
            Schema::create('visitors', function (Blueprint $t) {
                $t->string('id')->primary();
                $t->timestamp('seen_at')->index();
            });
        }
    }

    private function column(string $table, string $column, Closure $definition): void
    {
        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, $definition);
        }
    }

    public function down(): void
    {
        // This migration may extend pre-existing user data: automatic rollback would destroy it.
        throw new RuntimeException('Restore the SQL backup to roll back this compatibility migration.');
    }
};
