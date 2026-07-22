<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSystem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'site_id',
        'status_id',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TestSystemStatus::class, 'status_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Full assignment history for this system (open and closed).
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Only the currently open assignments — i.e. the system's live device roster (UC-9).
     */
    public function currentAssignments(): HasMany
    {
        return $this->assignments()->whereNull('ended_at');
    }

    /**
     * Fault reports naming this system directly (UC-18) — separate from faults
     * that name one of its devices, which may or may not also name the system.
     */
    public function faultReports(): HasMany
    {
        return $this->hasMany(FaultReport::class);
    }

    /**
     * Reservations for this system (UC-21), across all statuses. Filter by
     * status via the relationship as needed (e.g. requested-only for the
     * availability calendar's pending-approval view).
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
