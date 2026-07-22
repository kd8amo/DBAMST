<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceStatus extends Model
{
    // Fixed lookup table (4 statuses) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const UNASSIGNED = 'unassigned';
    public const ASSIGNED = 'assigned';
    public const OUT_FOR_CALIBRATION = 'out_for_calibration';
    public const RETIRED = 'retired';

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'status_id');
    }
}
