<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationType extends Model
{
    // Fixed lookup table — not user-editable via the GUI. New types are added
    // via migration as new notification triggers are built, same as every
    // other lookup table in this schema.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const CALIBRATION_DUE = 'calibration_due';
    public const CALIBRATION_OVERDUE = 'calibration_overdue';
    public const BOOKING_OVERRIDE = 'booking_override';
    public const FAULT_REPORTED = 'fault_reported';
    public const STALE_BOOKING_REQUEST = 'stale_booking_request';

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'notification_type_id');
    }
}
