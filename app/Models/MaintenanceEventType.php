<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceEventType extends Model
{
    // Fixed lookup table (3 types) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const CALIBRATION = 'calibration';
    public const PREVENTIVE_MAINTENANCE = 'preventive_maintenance';
    public const REPAIR = 'repair';

    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class, 'event_type_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MaintenanceEvent::class, 'event_type_id');
    }
}
