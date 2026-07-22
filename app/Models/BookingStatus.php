<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingStatus extends Model
{
    // Fixed lookup table (3 statuses) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const REQUESTED = 'requested';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'status_id');
    }
}
