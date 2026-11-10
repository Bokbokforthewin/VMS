<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    public const PERIODS = ['AM', 'PM'];
    public const STATUSES = ['pending', 'approved', 'rejected', 'completed'];

    protected $fillable = [
        'requester_id', 'destination_id', 'purpose', 'trip_date', 'period',
        'vehicle_id', 'driver_id', 'status', 'approved_by', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // Bookings that still hold a vehicle/driver for their slot.
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'approved']);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }
}