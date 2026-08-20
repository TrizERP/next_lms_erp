<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * A student's current mastery level for a concept — the "as of now" figure,
 * upserted by updateConceptMastery() every time a new attempt comes in.
 * lms_concept_mastery_log (LmsConceptMasteryLogModel) is the append-only
 * history this is derived from.
 */
class LmsConceptMasteryModel extends Model
{
    protected $table = 'lms_concept_mastery';

    protected $fillable = [
        'student_id',
        'concept_id',
        'mastery_level',
        'mastered_at',
    ];

    protected $casts = [
        'mastery_level' => 'float',
        'mastered_at' => 'datetime',
    ];

    public function concept()
    {
        return $this->belongsTo(LmsConceptModel::class, 'concept_id');
    }
}
