<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['transaction_date' => 'datetime', 'completed_at' => 'datetime', 'inventory_deducted_at' => 'datetime', 'inventory_managed' => 'boolean'];
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'transaction_services')
            ->withPivot(['service_name', 'unit_price', 'quantity']);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'transaction_products')->withPivot('quantity');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'completed')->whereHas('payment', fn ($payment) => $payment->where('verification_status', 'verified'));
    }
}
