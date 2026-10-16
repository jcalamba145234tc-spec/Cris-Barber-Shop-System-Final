<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'price' => 'decimal:2'];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'service_products')->withPivot('quantity');
    }

    public function scopeAvailable($query)
    {
        return $query->where('active', true)->whereDoesntHave('products', function ($products) {
            $products->where('products.active', false)->orWhereColumn('products.stock', '<', 'service_products.quantity')
                ->orWhereRaw('products.stock != ROUND(products.stock, 0)')
                ->orWhereRaw('service_products.quantity != ROUND(service_products.quantity, 0)')
                ->orWhere('service_products.quantity', '<=', 0);
        });
    }
}
