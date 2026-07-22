<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaultReportStatus extends Model
{
    // Fixed lookup table (3 statuses) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const RESOLVED = 'resolved';

    public function faultReports(): HasMany
    {
        return $this->hasMany(FaultReport::class, 'status_id');
    }
}
