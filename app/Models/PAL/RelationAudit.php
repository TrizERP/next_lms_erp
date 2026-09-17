<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of edits to prerequisite edges.
 *
 * Written by App\Services\PAL\Coherence\RelationWriter on every create, approve,
 * reject and delete. Never updated, never deleted - a correction is a new row.
 *
 * `relation_source` says which table the edge lives in ('concept' ->
 * pal_concept_relations, 'learning' -> pal_learning_relations) because a single FK
 * cannot address both. Rows outlive the edge they describe, so relation_id may
 * dangle after a delete; from_ref/to_ref are denormalised so a deleted edge is still
 * readable from the audit alone.
 *
 * Deliberately NOT in App\Models\PAL\Models.php - see LearningRelation.
 */
class RelationAudit extends Model
{
    protected $table = 'pal_relation_audit';

    /** Only created_at exists; an audit row is never updated. */
    public $timestamps = false;

    protected $fillable = [
        'relation_source', 'relation_id', 'from_ref', 'to_ref', 'relation_type',
        'action', 'previous_status', 'new_status',
        'actor_user_id', 'sub_institute_id', 'note', 'created_at',
    ];

    protected $casts = [
        'relation_id' => 'integer',
        'actor_user_id' => 'integer',
        'sub_institute_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
