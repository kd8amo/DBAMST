<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use HasFactory;

    // This table IS the history — no separate audit/history table. "Current"
    // assignment for a device is simply the row with ended_at = NULL. The DB
    // enforces at most one open assignment per device via a partial unique
    // index (idx_one_open_assignment_per_device) — proven in the schema tests.
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'test_system_id',
        'assignment_type',
        'started_at',
        'ended_at',
        'created_by',
        'ended_by',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public const TYPE_FIXED = 'fixed';
    public const TYPE_SWAPPABLE = 'swappable';

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function testSystem(): BelongsTo
    {
        return $this->belongsTo(TestSystem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function isOpen(): bool
    {
        return is_null($this->ended_at);
    }

    /**
     * Closes this assignment (sets ended_at/ended_by). Pair with creating a
     * new Assignment row to perform a reassignment (UC-6), same close+open
     * pattern proven against Postgres during schema design.
     */
    public function close(User $actor): void
    {
        $this->update([
            'ended_at' => now(),
            'ended_by' => $actor->id,
        ]);
    }
}
