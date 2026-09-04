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
        'retention_stage', 'taught_at', 'cfu_passed_at', 'cfu_attempts',
    ];

    protected $casts = [
        'mastery_estimate' => 'float',
        'attempts' => 'integer',
        'consecutive_correct' => 'integer',
        'hint_used_count' => 'integer',
        'retention_stage' => 'integer',
        'cfu_attempts' => 'integer',
        'last_seen_at' => 'datetime',
        'next_review_at' => 'datetime',
        'taught_at' => 'datetime',
        'cfu_passed_at' => 'datetime',
    ];

    /**
     * Bumped on every write to this table.
     *
     * EsoPolicyService batches this table's reads (one query for a learner
     * instead of one per concept — the database is remote, so a round trip
     * costs far more than the rows), and a batched read of learner state is
     * only safe if it can never outlive a write. Rather than trusting ~15
     * `save()` call sites to remember to invalidate, the model itself
     * invalidates: any create/update/delete moves the version, and a reader
     * holding an older version must discard it.
     *
     * This is complete for this table because nothing in app/ writes it
     * through the query builder — every write goes through this model, so
     * every write passes through these events.
     */
    protected static int $writeVersion = 0;

    public static function writeVersion(): int
    {
        return self::$writeVersion;
    }

    protected static function booted(): void
    {
        $bump = static function (): void {
            self::$writeVersion++;
        };

        static::saved($bump);
        static::deleted($bump);
    }

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
