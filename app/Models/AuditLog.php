<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Append-only WHO/WHAT/WHEN/OLD-VALUE/NEW-VALUE record for sensitive
 * operations (fees, marks, student status, permissions) that previously had
 * no audit trail at all. See system_audit_logs migration.
 *
 * record() must never break the mutation it documents, so failures here are
 * logged and swallowed rather than thrown.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'system_audit_logs';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'actor_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * @param array{module:string,action:string,entity_type?:string,entity_id?:string|int,old_values?:mixed,new_values?:mixed,reason?:string} $attributes
     */
    public static function record(array $attributes): void
    {
        try {
            foreach (['old_values', 'new_values'] as $key) {
                if (array_key_exists($key, $attributes) && ! is_string($attributes[$key])) {
                    $attributes[$key] = json_encode($attributes[$key]);
                }
            }

            static::create(array_merge([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'actor_id' => session()->get('user_id'),
                'actor_name' => session()->get('name') ?: session()->get('user_name'),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ], $attributes));
        } catch (Throwable $e) {
            Log::error('AuditLog::record failed: ' . $e->getMessage());
        }
    }
}
