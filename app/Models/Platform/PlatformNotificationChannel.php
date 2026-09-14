<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/**
 * One institute's master switch for one delivery channel.
 *
 * Absence is meaningful: no row means "whatever config/platform_services.php
 * says", not "off". Every read path therefore goes through
 * PlatformRegistry::channelsFor(), which merges these rows over the config
 * defaults — reading this table directly and treating a missing row as false
 * would switch email off for every school that never opened the screen.
 */
class PlatformNotificationChannel extends Model
{
    protected $table = 'platform_notification_channels';

    protected $fillable = [
        'sub_institute_id',
        'channel',
        'enabled',
        'updated_by',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'enabled' => 'boolean',
    ];

    /** Tenant scope — every read path must go through this. */
    public function scopeForTenant($query, $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) $subInstituteId);
    }
}
