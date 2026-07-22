<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSystemStatus extends Model
{
    // Fixed lookup table (3 statuses) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const ACTIVE = 'active';
    public const IN_MAINTENANCE = 'in_maintenance';
    public const RETIRED = 'retired';

    public function testSystems(): HasMany
    {
        return $this->hasMany(TestSystem::class, 'status_id');
    }
}
