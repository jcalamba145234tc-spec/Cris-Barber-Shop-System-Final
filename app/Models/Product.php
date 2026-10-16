<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'stock' => 'decimal:3', 'low_stock_threshold' => 'decimal:3', 'cost' => 'decimal:2'];
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_products')->withPivot('quantity');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getStockStatusAttribute(): string
    {
        return $this->stock <= 0 ? 'Out of Stock' : ($this->stock <= $this->low_stock_threshold ? 'Low Stock' : 'In Stock');
    }
}
