<?php

namespace App\Models\Eso;

use Illuminate\Database\Eloquent\Model;

/**
 * Adaptive Learning Engine — per-student, per-node mastery state.
 * See docs/ADAPTIVE_LEARNING_ENGINE_IMPLEMENTATION_PLAN.php §D/§L.5 for why
 * this is a new table rather than an extension of pal_competencies or
 * pal_concept_mastery.
 */
class LearnerNodeState extends Model
{
    protected $table = 'learner_node_state';

    public const STATUS_UNSEEN = 'unseen';
    public const STATUS_LEARNING = 'learning';
    public const STATUS_MASTERED = 'mastered';
    public const STATUS_RETAINED = 'retained';
    public const STATUS_MISCONCEPTION_FLAGGED = 'misconception_flagged';

    public const MODE_GUIDED = 'guided';
    public const MODE_INDEPENDENT = 'independent';

    protected $fillable = [
        'student_id', 'node_id', 'sub_institute_id', 'mastery_estimate',
        'attempts', 'consecutive_correct', 'practice_mode', 'hint_used_count',
        'status', 'active_misconception_id', 'last_seen_at', 'next_review_at',
    ];

    protected $casts = [
        'mastery_estimate' => 'float',
        'attempts' => 'integer',
        'consecutive_correct' => 'integer',
        'hint_used_count' => 'integer',
        'last_seen_at' => 'datetime',
        'next_review_at' => 'datetime',
    ];

    public function node()
    {
        return $this->belongsTo(\App\Models\PAL\ConceptNode::class, 'node_id');
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function isMastered(): bool
    {
        return in_array($this->status, [self::STATUS_MASTERED, self::STATUS_RETAINED], true);
    }
}
