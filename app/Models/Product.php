<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean', 'screenshots' => 'array', 'price' => 'integer'];
    }

    protected function requirements(): Attribute
    {
        return $this->linesAttribute();
    }

    protected function features(): Attribute
    {
        return $this->linesAttribute();
    }

    private function linesAttribute(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $decoded = json_decode((string) $value, true);

                return is_array($decoded) ? implode("\n", $decoded) : (string) $value;
            },
            set: fn ($value) => json_encode(is_array($value) ? $value : explode("\n", (string) $value), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
