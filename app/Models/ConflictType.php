<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConflictType extends Model
{
    // Fixed lookup table (3 types) — not user-editable via the GUI.
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public const MAINTENANCE_WINDOW = 'maintenance_window';
    public const OPEN_FAULT = 'open_fault';
    public const SITE_TRANSFER = 'site_transfer';
}
