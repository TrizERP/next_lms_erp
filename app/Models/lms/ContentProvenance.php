<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * Ownership/provenance sidecar spanning every LMS content estate.
 *
 * One row per (entity_type, entity_id, owner_sub_institute_id). See the migration
 * 2026_09_07_170000_create_lms_content_provenance_table for why this is a sidecar
 * rather than columns on content_master, and why it is separate from
 * pal_content_metadata.
 *
 * Writes go through App\Services\lms\Content\ContentProvenanceService, which is the
 * only place closed-set validation and tenancy enforcement live. Do not mass-assign
 * this model directly from a request.
 */
class ContentProvenance extends Model
{
    protected $table = 'lms_content_provenance';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'owner_sub_institute_id',
        'ownership',
        'authored_by_user_id',
        'authored_by_profile',
        'authoring_mode',
        'generation_source',
        'derived_from_entity_id',
        'visibility',
        'status',
    ];

    protected $casts = [
        'entity_id'              => 'integer',
        'owner_sub_institute_id' => 'integer',
        'authored_by_user_id'    => 'integer',
        'derived_from_entity_id' => 'integer',
    ];
}
