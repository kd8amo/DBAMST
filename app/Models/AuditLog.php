<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_log';

    // Immutable event record — no timestamps columns beyond occurred_at.
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'api_key_id',
        'action',
        'entity_type',
        'entity_id',
        'summary',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Record an audit entry attributed to a human user.
     */
    public static function recordForUser(User $user, string $action, string $entityType, int $entityId, ?string $summary = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => $summary,
        ]);
    }

    /**
     * Record an audit entry attributed to a system-to-system API key rather
     * than a human user (UC-35/38) — verified against live Postgres to
     * coexist cleanly with user-attributed entries in the same table.
     */
    public static function recordForApiKey(ApiKey $apiKey, string $action, string $entityType, int $entityId, ?string $summary = null): self
    {
        return self::create([
            'api_key_id' => $apiKey->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => $summary,
        ]);
    }
}
