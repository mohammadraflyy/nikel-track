<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'start_km',
        'end_km',
        'fuel_used',
        'notes'
    ];

    protected $casts = [
        'fuel_used' => 'decimal:2'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getDistanceAttribute()
    {
        return $this->end_km - $this->start_km;
    }
}