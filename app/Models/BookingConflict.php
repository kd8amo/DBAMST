<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingConflict extends Model
{
    use HasFactory;

    // Detected-event record — no updated_at; overridden_at tracks the one
    // mutation this row ever receives.
    const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'conflict_type_id',
        'check_stage_id',
        'device_id',
        'detail',
        'detected_at',
        'overridden',
        'overridden_by',
        'overridden_at',
        'override_reason',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'overridden' => 'boolean',
        'overridden_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function conflictType(): BelongsTo
    {
        return $this->belongsTo(ConflictType::class);
    }

    public function checkStage(): BelongsTo
    {
        return $this->belongsTo(ConflictCheckStage::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /**
     * Override this conflict (UC-23). Role restriction (Scheduler/Manager/Admin
     * only) is app-enforced via a Policy, not here — this just records the act.
     */
    public function override(User $actor, string $reason): void
    {
        $this->update([
            'overridden' => true,
            'overridden_by' => $actor->id,
            'overridden_at' => now(),
            'override_reason' => $reason,
        ]);
    }
}
