<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;

class ImportCatalog extends Command
{
    protected $signature = 'shop:import';

    protected $description = 'Import legacy JSON or bundled catalog once, preserving existing products';

    public function handle(): int
    {
        if (Product::exists()) {
            $this->info('Каталог уже существует. Повторный импорт пропущен.');

            return 0;
        }
        $path = storage_path('app/products.json');
        $data = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : require database_path('catalog.php');
        foreach ($data as $slug => $p) {
            $cat = Category::firstOrCreate(['name' => $p['game'], 'parent_id' => null]);
            Product::firstOrCreate(['slug' => $slug], ['category_id' => $cat->id, 'name' => $p['name'], 'price' => (int) preg_replace('/\D/', '', $p['price']),
                'term' => $p['term'], 'summary' => $p['summary'], 'description' => $p['description'],
                'requirements' => is_array($p['requirements']) ? implode("\n", $p['requirements']) : $p['requirements'],
                'features' => is_array($p['features']) ? implode("\n", $p['features']) : $p['features'],
                'status' => $p['status'] ?? 'Undetected', 'hidden' => $p['hidden'] ?? false]);
        } $this->info('Каталог импортирован.');

        return 0;
    }
}
