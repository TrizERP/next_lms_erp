<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per content-authoring attempt through the consolidated endpoint.
 *
 * See the migration 2026_09_08_120000_create_lms_content_authoring_audit_table for why
 * this exists. Written only by App\Services\lms\Content\ContentAuthoringService.
 */
class ContentAuthoringAudit extends Model
{
    protected $table = 'lms_content_authoring_audit';

    protected $fillable = [
        'idempotency_key', 'actor_user_id', 'sub_institute_id', 'module',
        'authoring_type', 'mode', 'provider', 'model', 'chapter_id', 'concept_id',
        'status', 'entity_type', 'entity_ids',
        'input_tokens', 'output_tokens', 'latency_ms', 'error',
    ];

    protected $casts = [
        'entity_ids'    => 'array',
        'actor_user_id' => 'integer',
        'chapter_id'    => 'integer',
        'concept_id'    => 'integer',
    ];
}
