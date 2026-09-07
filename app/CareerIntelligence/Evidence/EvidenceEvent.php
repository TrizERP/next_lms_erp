<?php

namespace App\CareerIntelligence\Evidence;

use Illuminate\Database\Eloquent\Model;

/**
 * evidence_events (Phase 6 migration). APPEND-ONLY — see the migration's own
 * doc comment. The claim fields on an existing row (performance_level,
 * strength, competency_id, kasba_dimension, …) must never be updated once
 * written. The ONE sanctioned mutation is flipping a prior row's
 * `contested`/`superseded_by` when a newer observation supersedes it (see
 * AssessmentEvidenceAdapter::supersede()) — that bookkeeping update IS the
 * correction mechanism the migration describes, not an exception to it.
 */
class EvidenceEvent extends Model
{
    protected $table = 'evidence_events';
    protected $primaryKey = 'evidence_id';
    const UPDATED_AT = null; // no updated_at column — append-only by design

    protected $fillable = [
        'student_id',
        'academic_year',
        'grade',
        'source_type',
        'source_id',
        'competency_id',
        'kasba_dimension',
        'performance_level',
        'strength',
        'reliability',
        'validity_scope',
        'observed_at',
        'rater_id',
        'assessment_id',
        'verified',
        'contested',
        'superseded_by',
        'provenance',
    ];

    protected $casts = [
        'strength' => 'float',
        'reliability' => 'float',
        'observed_at' => 'datetime',
        'verified' => 'boolean',
        'contested' => 'boolean',
        'provenance' => 'array',
    ];
}
