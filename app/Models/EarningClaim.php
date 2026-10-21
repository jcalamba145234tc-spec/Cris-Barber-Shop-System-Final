<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EarningClaim extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'period_start' => 'date', 'period_end' => 'date', 'confirmed_at' => 'datetime'];
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'claim_id');
    }
}
