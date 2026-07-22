<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceCategory extends Model
{
    // Fixed lookup table (5 categories) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
        'prefix',
    ];

    public const MEASUREMENT = 'measurement';
    public const LOAD_EMULATION = 'load_emulation';
    public const EMULATION = 'emulation';
    public const AUTOMOTIVE_COMMS = 'automotive_comms';
    public const SIGNAL_GENERATION = 'signal_generation';

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'category_id');
    }
}
