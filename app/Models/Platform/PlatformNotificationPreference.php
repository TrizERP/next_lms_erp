<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/**
 * One institute's settings for one notification event.
 *
 * Absence is meaningful, exactly as in PlatformNotificationChannel: a
 * notification with no row here is delivered per the defaults in
 * config/platform_services.php. NotificationController merges rows over defaults
 * and marks each result `customised` so the screen can show which is which.
 *
 * `module` and `component` are derived from `event_key` on write and exist only
 * so the screen can filter without a LIKE. The key stays authoritative.
 */
class PlatformNotificationPreference extends Model
{
    protected $table = 'platform_notification_preferences';

    protected $fillable = [
        'sub_institute_id',
        'event_key',
        'module',
        'component',
        'enabled',
        'channels',
        'updated_by',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'enabled' => 'boolean',
        // Cast rather than hand-decoded: the shape is fixed and validated in the
        // controller before it ever reaches here.
        'channels' => 'array',
    ];

    /** Tenant scope — every read path must go through this. */
    public function scopeForTenant($query, $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) $subInstituteId);
    }

    public function scopeForModule($query, ?string $module)
    {
        return $module ? $query->where('module', $module) : $query;
    }

    public function scopeForComponent($query, ?string $component)
    {
        return $component ? $query->where('component', $component) : $query;
    }
}
