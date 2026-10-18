<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Barber extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'hired_date',
        'status',
        'availability',
        'phone_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')->where('availability', 'available')
            ->whereDoesntHave('transactions', fn ($transactions) => $transactions->whereIn('status', ['queued', 'in_progress', 'ready']));
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? trim($this->first_name.' '.$this->last_name);
    }
}
