<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'driver_id',
        'start_date',
        'end_date',
        'purpose',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    public function getApprovalStatusAttribute()
    {
        $level1 = $this->approvals->where('level', 1)->first();
        $level2 = $this->approvals->where('level', 2)->first();
        
        if ($level2 && $level2->status === 'approved') {
            return 'fully_approved';
        }
        
        if ($level1 && $level1->status === 'approved' && $level2) {
            return 'approved_level1';
        }
        
        if ($level1 && $level1->status === 'approved' && !$level2) {
            return 'fully_approved';
        }
        
        return 'pending';
    }

    public function scopeActiveAt($query, Carbon $date)
    {
        return $query->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                    ->where('status', 'approved');
    }

    public function usageLog()
    {
        return $this->hasOne(UsageLog::class);
    }
}