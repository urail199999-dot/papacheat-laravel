<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $t->timestamps();
        });
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
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
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
        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('body');
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('visitors', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->timestamp('seen_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['visitors', 'alerts', 'messages', 'orders', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
