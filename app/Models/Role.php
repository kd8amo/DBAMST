<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    // Fixed lookup table: engineer, technician, scheduler_manager, admin, auditor.
    // Not user-editable via the GUI, so no HasFactory/fillable beyond read use.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    // Role name constants so application code never hardcodes magic strings.
    public const ENGINEER = 'engineer';
    public const TECHNICIAN = 'technician';
    public const SCHEDULER_MANAGER = 'scheduler_manager';
    public const ADMIN = 'admin';
    public const AUDITOR = 'auditor';

    /**
     * Users whose DEFAULT role is this one. For "who can currently act as
     * this role" (multi-hat aware), use holders() instead.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Every user permitted to act as this role, via the user_roles pivot —
     * includes multi-hat users whose default role is something else.
     */
    public function holders(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
