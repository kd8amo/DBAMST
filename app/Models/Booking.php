<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_system_id',
        'status_id',
        'starts_at',
        'ends_at',
        'purpose',
        'requested_by',
        'requested_at',
        'confirmed_by',
        'confirmed_at',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function testSystem(): BelongsTo
    {
        return $this->belongsTo(TestSystem::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(BookingStatus::class, 'status_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deviceSnapshots(): HasMany
    {
        return $this->hasMany(BookingDeviceSnapshot::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(BookingConflict::class);
    }

    public function requestStageConflicts(): HasMany
    {
        return $this->conflicts()->whereHas(
            'checkStage',
            fn (Builder $q) => $q->where('name', ConflictCheckStage::REQUESTED)
        );
    }

    public function confirmStageConflicts(): HasMany
    {
        return $this->conflicts()->whereHas(
            'checkStage',
            fn (Builder $q) => $q->where('name', ConflictCheckStage::CONFIRMED)
        );
    }

    /**
     * Bookings still awaiting Scheduler/Manager/Admin action, older than the
     * given threshold — feeds the escalating stale-request notification job
     * (project decision: nag on an escalating cadence, never auto-cancel).
     */
    public function scopeStaleRequests(Builder $query, int $olderThanDays): Builder
    {
        return $query
            ->whereHas('status', fn (Builder $q) => $q->where('name', BookingStatus::REQUESTED))
            ->where('requested_at', '<=', now()->subDays($olderThanDays));
    }

    /**
     * Confirm a booking (UC-21). Snapshots the test system's currently
     * assigned devices AT THIS MOMENT — per project decision, the snapshot is
     * deliberately deferred to confirm time (not request time), since a
     * system's roster commonly changes between request and confirmation.
     * Role restriction (Scheduler/Manager/Admin only) is app-enforced via a
     * Policy, not here.
     */
    public function confirm(User $actor): void
    {
        DB::transaction(function () use ($actor) {
            $confirmedStatusId = BookingStatus::where('name', BookingStatus::CONFIRMED)->value('id');

            $this->update([
                'status_id' => $confirmedStatusId,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $currentDeviceIds = Assignment::where('test_system_id', $this->test_system_id)
                ->whereNull('ended_at')
                ->pluck('device_id');

            foreach ($currentDeviceIds as $deviceId) {
                BookingDeviceSnapshot::firstOrCreate([
                    'booking_id' => $this->id,
                    'device_id' => $deviceId,
                ]);
            }
        });
    }

    public function cancel(User $actor): void
    {
        $cancelledStatusId = BookingStatus::where('name', BookingStatus::CANCELLED)->value('id');

        $this->update([
            'status_id' => $cancelledStatusId,
            'updated_by' => $actor->id,
        ]);
    }
}
