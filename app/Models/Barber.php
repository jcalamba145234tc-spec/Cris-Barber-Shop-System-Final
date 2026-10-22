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

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'restored_at' => 'datetime'];
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('archived_at');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

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
        return $query->current()->where('status', 'active')->where('availability', 'available')
            ->whereDoesntHave('transactions', fn ($transactions) => $transactions->whereIn('status', ['queued', 'in_progress', 'ready']));
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? trim($this->first_name.' '.$this->last_name);
    }
}
