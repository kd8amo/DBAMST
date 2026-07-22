<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class MaintenanceEvent extends Model
{
    use HasFactory;

    // Historical record only — no updated_at, matching the schema (created_at only).
    const UPDATED_AT = null;

    protected $fillable = [
        'device_id',
        'event_type_id',
        'schedule_id',
        'performed_at',
        'performed_by_user_id',
        'performed_by_vendor',
        'result',
        'next_due_date',
        'description',
        'resulting_status_id',
        'fault_report_id',
        'created_by',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'next_due_date' => 'date',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceEventType::class, 'event_type_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'schedule_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function resultingStatus(): BelongsTo
    {
        return $this->belongsTo(DeviceStatus::class, 'resulting_status_id');
    }

    public function faultReport(): BelongsTo
    {
        return $this->belongsTo(FaultReport::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Log a calibration or preventive-maintenance event fulfilling a schedule.
     * Advances the schedule's next_due_date in the same transaction, per
     * project decision (UC-13/14/15: next_due_date is recalculated whenever
     * a fulfilling event is logged).
     */
    public static function logScheduledEvent(MaintenanceSchedule $schedule, array $data, User $actor): self
    {
        return DB::transaction(function () use ($schedule, $data, $actor) {
            $event = self::create(array_merge($data, [
                'device_id' => $schedule->device_id,
                'event_type_id' => $schedule->event_type_id,
                'schedule_id' => $schedule->id,
                'created_by' => $actor->id,
            ]));

            $schedule->update([
                'next_due_date' => $data['next_due_date'] ?? $schedule->computeNextDueDate($event->performed_at),
                'updated_by' => $actor->id,
            ]);

            return $event;
        });
    }

    /**
     * Log a repair event. If resulting_status_id is provided, the device's
     * actual status (and is_active, if the result is 'retired') is updated in
     * the same transaction — per project decision that the repair log drives
     * device status rather than requiring a separate manual status change.
     */
    public static function logRepair(array $data, User $actor): self
    {
        return DB::transaction(function () use ($data, $actor) {
            $event = self::create(array_merge($data, [
                'event_type_id' => MaintenanceEventType::where('name', MaintenanceEventType::REPAIR)->value('id'),
                'created_by' => $actor->id,
            ]));

            if (! empty($data['resulting_status_id'])) {
                $device = Device::findOrFail($event->device_id);
                $retiredStatusId = DeviceStatus::where('name', DeviceStatus::RETIRED)->value('id');

                $device->update([
                    'status_id' => $data['resulting_status_id'],
                    'is_active' => $data['resulting_status_id'] != $retiredStatusId ? $device->is_active : false,
                    'updated_by' => $actor->id,
                ]);
            }

            return $event;
        });
    }
}
