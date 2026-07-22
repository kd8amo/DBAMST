<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'scope',
        'is_active',
        'created_by',
        'revoked_by',
        'revoked_at',
        'last_used_at',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected $casts = [
        'scope' => 'array',
        'is_active' => 'boolean',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function auditLogEntries(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Generate a new key (Admin only — app-enforced), returning the raw plaintext
     * key ONCE so it can be shown to the user, alongside the persisted model
     * (which stores only the hash). Caller is responsible for displaying the
     * plaintext key exactly once and never persisting it elsewhere.
     */
    public static function generate(string $name, array $scope, User $actor): array
    {
        $rawKey = 'tsk_'.Str::random(40);

        $model = self::create([
            'name' => $name,
            'key_hash' => Hash::make($rawKey),
            'key_prefix' => Str::limit($rawKey, 10, ''),
            'scope' => $scope,
            'created_by' => $actor->id,
        ]);

        return ['key' => $rawKey, 'model' => $model];
    }

    public function revoke(User $actor): void
    {
        $this->update([
            'is_active' => false,
            'revoked_by' => $actor->id,
            'revoked_at' => now(),
        ]);
    }

    public function canRead(string $resource): bool
    {
        return in_array($resource, $this->scope['read'] ?? [], true);
    }

    public function canWrite(string $resource): bool
    {
        return in_array($resource, $this->scope['write'] ?? [], true);
    }

    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
