<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    // Only updated_at exists in the schema (no created_at) — settings rows
    // are seeded, not "created" in a meaningful sense.
    const CREATED_AT = null;

    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Read a setting's raw string value, with a fallback if the key doesn't
     * exist yet (defensive — every key used in application code should also
     * have a seed row, but this avoids a hard failure if one is ever missing).
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        return self::find($key)?->value ?? $default;
    }

    /**
     * Read a comma-separated setting (e.g. 'stale_booking_notify_days' =>
     * '7,14,21,30') as an array of integers.
     */
    public static function getIntList(string $key): array
    {
        $raw = self::getValue($key, '');

        return array_map('intval', array_filter(explode(',', $raw)));
    }

    public static function setValue(string $key, string $value, User $actor): self
    {
        return tap(self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $actor->id]
        ));
    }
}
