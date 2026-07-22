<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'notification_type_id',
        'recipient_user_id',
        'entity_type',
        'entity_id',
        'message',
        'is_read',
        'read_at',
        'emailed_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function markRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Dispatch a notification to every recipient of a given role, scoped to
     * the site of the entity this notification is about, with a global
     * fallback if nobody of that role is homed there (see
     * User::recipientsForRoleAtSite). Fans out one row per recipient — proven
     * against live Postgres — rather than a single shared row, so read/unread
     * state and the in-app notification center (UC-31) are per-user.
     *
     * Applies uniformly to every notification type, including Admin-tier
     * escalations, per project decision (no special-casing).
     */
    public static function dispatchToRole(
        string $roleName,
        ?int $siteId,
        string $notificationType,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null
    ): Collection {
        $typeId = NotificationType::where('name', $notificationType)->value('id');
        $recipients = User::recipientsForRoleAtSite($roleName, $siteId);

        $created = $recipients->map(fn (User $user) => self::create([
            'notification_type_id' => $typeId,
            'recipient_user_id' => $user->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
        ]));

        return new Collection($created->all());
    }
}
