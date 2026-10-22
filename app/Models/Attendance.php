<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'time_in' => 'datetime', 'time_out' => 'datetime'];
    }

    protected function date(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->toDateString());
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }

    public function getHoursAttribute(): ?float
    {
        return $this->time_in && $this->time_out ? round($this->time_in->diffInMinutes($this->time_out) / 60, 2) : null;
    }

    /** Human-readable worked time, for example "0h 31m". Null until the barber has clocked out. */
    public function getHoursLabelAttribute(): ?string
    {
        if (! $this->time_in || ! $this->time_out) {
            return null;
        }
        $minutes = (int) floor(abs($this->time_in->diffInMinutes($this->time_out)));

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }
}
