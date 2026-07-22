<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'event_type_id',
        'interval_months',
        'next_due_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'next_due_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceEventType::class, 'event_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MaintenanceEvent::class, 'schedule_id');
    }

    /**
     * Advances next_due_date by this schedule's interval, from a given
     * completion date (normally the date of the event that just fulfilled it).
     */
    public function computeNextDueDate(\DateTimeInterface $fromDate): \Carbon\Carbon
    {
        return \Carbon\Carbon::instance($fromDate)->addMonths($this->interval_months);
    }
}
