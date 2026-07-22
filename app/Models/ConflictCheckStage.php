<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConflictCheckStage extends Model
{
    // Fixed lookup table (2 stages) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const REQUESTED = 'requested';
    public const CONFIRMED = 'confirmed';
}
