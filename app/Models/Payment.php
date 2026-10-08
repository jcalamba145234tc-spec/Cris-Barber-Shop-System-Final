<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'cash_received' => 'decimal:2', 'change_amount' => 'decimal:2'];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
