<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_tag',
        'category_id',
        'manufacturer',
        'model',
        'serial_number',
        'site_id',
        'status_id',
        'total_usage_hours',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_usage_hours' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class, 'category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DeviceStatus::class, 'status_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
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
     * Full assignment history for this device (open and closed), oldest first.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * The single currently-open assignment, if any (UC-8's "which system am I in right now").
     * Enforced at the DB level to be at most one via a partial unique index.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->whereNull('ended_at');
    }

    /**
     * Site transfer history (UC-10), most recent first is left to the query caller.
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    /**
     * Fault reports naming this device (UC-18). A device fault may or may not
     * also name the test system it's in — see FaultReport's flexible target design.
     */
    public function faultReports(): HasMany
    {
        return $this->hasMany(FaultReport::class);
    }

    /**
     * Recurring calibration/PM schedules for this device (UC-13). At most one
     * active schedule per event type — enforced at the DB level.
     */
    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    /**
     * Full logged history of calibration, PM, and repair events (UC-14/15/16).
     */
    public function maintenanceEvents(): HasMany
    {
        return $this->hasMany(MaintenanceEvent::class);
    }

    /**
     * Retire a device: flips is_active to false and status to 'retired' together,
     * per the project decision that these two fields must never drift out of sync.
     * The only status transition that also touches is_active.
     */
    public function retire(User $actor): void
    {
        $retiredStatusId = DeviceStatus::where('name', DeviceStatus::RETIRED)->value('id');

        $this->update([
            'status_id' => $retiredStatusId,
            'is_active' => false,
            'updated_by' => $actor->id,
        ]);
    }
}
