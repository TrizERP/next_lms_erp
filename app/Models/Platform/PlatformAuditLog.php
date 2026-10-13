<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/**
 * One immutable audit entry. Written only through AuditTrail::record(); the model
 * refuses update and delete so no caller can rewrite history by accident.
 */
class PlatformAuditLog extends Model
{
    protected $table = 'platform_audit_log';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'actor_user_id' => 'integer',
        'is_sample' => 'boolean',
    ];

    public function scopeForTenant($query, $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) $subInstituteId);
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \LogicException('The audit trail is append-only.');
        }

        return parent::save($options);
    }

    public function delete()
    {
        throw new \LogicException('The audit trail is append-only.');
    }
}
