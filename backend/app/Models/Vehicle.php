<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public const STATUSES = ['available', 'under_repair', 'out_of_service'];

    protected $fillable = ['plate_no', 'brand', 'model', 'seats', 'status'];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'owner_id')->where('doc_type', 'vehicle');
    }
}