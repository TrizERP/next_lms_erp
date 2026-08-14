<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * The misconception library (spec §4.3).
 *
 * `tag` is a permanent identifier — it appears in learner history, so it is
 * deprecated, never renamed.
 */
class MisconceptionLibrary extends Model
{
    protected $table = 'pal_misconception_library';

    protected $fillable = [
        'tag', 'sub_institute_id', 'scope', 'concept_code', 'concept_ref_id', 'chapter_ref_id',
        'subject', 'grade_band', 'description', 'error_pattern', 'corrective_action',
        'error_regex', 'typical_wrong_answers', 'prevalence_rate', 'teacher_confirmed',
        'corrective_format', 'priority_level', 'quality_status', 'tagged_by',
        'reviewed_by', 'reviewed_at', 'detection_count',
    ];

    protected $casts = [
        'typical_wrong_answers' => 'array',
        'prevalence_rate' => 'float',
        'teacher_confirmed' => 'boolean',
        'priority_level' => 'integer',
        'detection_count' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function correctives()
    {
        return $this->hasMany(MisconceptionCorrective::class, 'misconception_id');
    }

    public function scopeServable($query)
    {
        return $query->whereIn('quality_status', config('pal_content.servable_statuses', ['approved']));
    }

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }

    /**
     * CONTENT LAW C6 — a misconception with no corrective content is dead weight
     * and may not be served. Detecting an error and then showing nothing is worse
     * than not detecting it.
     */
    public function scopeWithCorrective($query)
    {
        return $query->whereExists(function ($q) {
            $q->selectRaw(1)
                ->from('pal_misconception_corrective as c')
                ->whereColumn('c.misconception_id', 'pal_misconception_library.id')
                ->whereIn('c.quality_status', config('pal_content.servable_statuses', ['approved']));
        });
    }
}
