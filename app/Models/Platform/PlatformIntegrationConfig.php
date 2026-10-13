<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class PlatformIntegrationConfig extends Model
{
    protected $table = 'platform_integration_configs';

    protected $fillable = [
        'sub_institute_id', 'provider_key', 'display_name', 'category', 'description',
        'status', 'config_json', 'last_tested_at', 'last_tested_by', 'updated_by', 'is_sample',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'is_sample' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function scopeForTenant($query, $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) $subInstituteId);
    }
}
