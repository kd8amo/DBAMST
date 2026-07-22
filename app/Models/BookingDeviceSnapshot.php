<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDeviceSnapshot extends Model
{
    // Point-in-time snapshot row — immutable once written, no timestamps.
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'device_id',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
