<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password_hash',
        'display_name',
        'role_id',
        'site_id',
        'locale',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Our schema column is `password_hash`, not Laravel's default `password`.
     * Override so the built-in auth guard hashes/checks against the right column
     * without renaming the schema to match the framework default.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Every role this user is permitted to act as (multi-hat support), via
     * the user_roles pivot. Always includes their default role — enforced at
     * the application layer whenever a user's roles are assigned/edited, same
     * pairing-integrity pattern as Device::retire().
     */
    public function availableRoles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function canActAs(string $roleName): bool
    {
        return $this->availableRoles()->where('name', $roleName)->exists();
    }

    /**
     * Grants an additional role. Does not touch the default role_id — use
     * setDefaultRole() for that, which also keeps this pivot in sync.
     */
    public function grantRole(string $roleName): void
    {
        $roleId = Role::where('name', $roleName)->value('id');
        $this->availableRoles()->syncWithoutDetaching([$roleId]);
    }

    /**
     * Revokes a role — but never the user's current default role, since that
     * would violate the "default role is always an available role" invariant.
     * Change the default role first (setDefaultRole()) if you need to revoke it.
     */
    public function revokeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();

        if (! $role || $role->id === $this->role_id) {
            return;
        }

        $this->availableRoles()->detach($role->id);
    }

    /**
     * Changes the user's default/primary role (what they land in at login),
     * ensuring it's also added to their available-roles set in the same
     * operation so the two never drift apart. Logged to the audit trail
     * (UC-32: Admin manages users & roles).
     */
    public function setDefaultRole(string $roleName, User $actor): void
    {
        $roleId = Role::where('name', $roleName)->value('id');
        $previousRoleName = $this->role?->name;

        \Illuminate\Support\Facades\DB::transaction(function () use ($roleId, $actor, $roleName, $previousRoleName) {
            $this->update(['role_id' => $roleId]);
            $this->availableRoles()->syncWithoutDetaching([$roleId]);

            \App\Models\AuditLog::recordForUser(
                $actor,
                'user.default_role_change',
                'user',
                $this->id,
                "Default role changed from {$previousRoleName} to {$roleName}"
            );
        });
    }

    /**
     * "Home" site — default/filter/notification-scope only, NOT an access boundary.
     * Nullable: a user need not have a home site assigned.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Convenience helper matching the permission matrix (Section 2 of the use-case doc).
     * Application authorization (Policies/Gates) will be built on top of this in a later batch.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_user_id');
    }

    /**
     * Resolve notification recipients for a role at a given site, with a
     * global fallback if no active user of that role is homed at the site —
     * per project decision, so a small site with nobody in a given role
     * doesn't silently drop an alert.
     *
     * Queries through the user_roles pivot (not just users.role_id), so a
     * multi-hat user is included for any role they can act as, not only
     * their default role — per project decision when multi-role support
     * was added. Verified against live Postgres: a user whose default role
     * is technician but who also holds scheduler_manager correctly appears
     * in a scheduler_manager-targeted query.
     */
    public static function recipientsForRoleAtSite(string $roleName, ?int $siteId): \Illuminate\Support\Collection
    {
        $siteScoped = self::query()
            ->whereHas('availableRoles', fn ($q) => $q->where('name', $roleName))
            ->where('is_active', true)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->get();

        if ($siteScoped->isNotEmpty()) {
            return $siteScoped;
        }

        return self::query()
            ->whereHas('availableRoles', fn ($q) => $q->where('name', $roleName))
            ->where('is_active', true)
            ->get();
    }
}
