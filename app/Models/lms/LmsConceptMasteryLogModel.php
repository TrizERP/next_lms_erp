<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of mastery snapshots — one row per attempt processed
 * by updateConceptMastery(), so mastery over time can be reconstructed even
 * though lms_concept_mastery only keeps the latest value per student/concept.
 */
class LmsConceptMasteryLogModel extends Model
{
    protected $table = 'lms_concept_mastery_log';

    protected $fillable = [
        'student_id',
        'concept_id',
        'mastery_level',
        'total_attempts',
        'correct_attempts',
    ];

    protected $casts = [
        'mastery_level' => 'float',
        'total_attempts' => 'integer',
        'correct_attempts' => 'integer',
    ];

    public function concept()
    {
        return $this->belongsTo(LmsConceptModel::class, 'concept_id');
    }
}
