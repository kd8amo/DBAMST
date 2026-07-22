<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FaultReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'test_system_id',
        'status_id',
        'severity',
        'needed_by_date',
        'description',
        'reported_by',
        'reported_at',
        'resolved_at',
    ];

    protected $casts = [
        'needed_by_date' => 'date',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function testSystem(): BelongsTo
    {
        return $this->belongsTo(TestSystem::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(FaultReportStatus::class, 'status_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * The repair event that closed this fault, if any (UC-19: triage links a
     * fault to the eventual repair record that resolves it).
     */
    public function closingMaintenanceEvent(): HasOne
    {
        return $this->hasOne(MaintenanceEvent::class);
    }

    /**
     * A fault has a hard repair deadline if needed_by_date is set — this is
     * the structured signal UC-22 conflict detection reasons against, distinct
     * from the free-text `severity` description.
     */
    public function hasDeadline(): bool
    {
        return ! is_null($this->needed_by_date);
    }
}
