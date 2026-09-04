<?php

namespace App\Models\PAL;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    protected $table = 'pal_competencies';
    
    protected $fillable = [
        'learner_id',
        'subject_id',
        'concept_id',
        'mastery_score',
        'bloom_level',
        'proficiency_trend',
        'last_assessed',
    ];
    
    protected $casts = [
        'mastery_score' => 'float',
        'bloom_level' => 'integer',
    ];

    /**
     * Per-request memo for the two lookups below. Both are called repeatedly
     * inside a single learner-state build (every engine re-derives the same
     * learner's grain and anchor), and both are pure reads of rows that cannot
     * change mid-request.
     *
     * @var array<string, mixed>
     */
    protected static array $grainMemo = [];
    protected static array $anchorMemo = [];

    public function getMasteryStatus(): string
    {
        return match(true) {
            $this->mastery_score >= 80 => 'mastered',
            $this->mastery_score >= 50 => 'learning',
            default => 'new',
        };
    }
    
    public function concept()
    {
        return $this->belongsTo(Concept::class);
    }
    
    public function learner()
    {
        return $this->belongsTo(\App\Models\User::class, 'learner_id');
    }
    
    /**
     * pal_learner_misconceptions has no competency_id column -- it's keyed by
     * learner_id (+ concept_id), not by this competency row's own id. learner_id
     * is the only column both tables actually share, so that's what this joins on;
     * it will include the learner's misconceptions across all subjects/concepts,
     * not just this one competency's.
     */
    public function misconceptions()
    {
        return $this->hasMany(LearnerMisconception::class, 'learner_id', 'learner_id');
    }

    /**
     * pal_competencies holds TWO grains and they must never be mixed:
     *
     *   concept_id IS NULL  -> learner x subject, written live by
     *                          palController::recomputeSubjectCompetency()
     *                          (BKT on every /lms/pal submission)
     *   concept_id = chapter -> learner x chapter, written in bulk by
     *                          `php artisan pal:derive-competencies`
     *
     * Both derive from the same answers, so averaging across them
     * double-counts the same evidence (a learner with subject 100% and
     * chapter 71.43% reads as a meaningless 85.72%).
     *
     * The rule is finest grain available *per learner*: prefer the chapter
     * rows, and fall back to the subject rows for learners who only ever used
     * the live quiz path. Excluding the NULL grain outright would blank those
     * learners entirely.
     */
    public function scopeAtFinestGrain($query, int $learnerId)
    {
        $query->where('learner_id', $learnerId);

        return static::learnerHasConceptGrain($learnerId)
            ? $query->whereNotNull('concept_id')
            : $query->whereNull('concept_id');
    }

    /**
     * Rows that are keyed by a concept/chapter, for cross-learner queries that
     * group by concept_id. This is NOT a per-learner grain filter -- use
     * scopeAtFinestGrain() for that.
     */
    public function scopeMeasurable($query)
    {
        return $query->whereNotNull('concept_id');
    }

    public static function learnerHasConceptGrain(int $learnerId): bool
    {
        return static::$grainMemo[$learnerId] ??= static::query()
            ->where('learner_id', $learnerId)
            ->whereNotNull('concept_id')
            ->exists();
    }

    /**
     * The learner's own most recent evidence timestamp.
     *
     * `pal:derive-competencies` deliberately backdates updated_at to the
     * answer that produced the row, so the estate's evidence runs from 2023 to
     * the current term. Anchoring analysis windows to now() therefore returns
     * an empty window for essentially every learner -- which is what made the
     * whole Intelligence screen read as zeroes. Every window must be measured
     * relative to this instead.
     *
     * Returns null when the learner has no competency rows at all.
     */
    public static function evidenceAnchor(int $learnerId): ?Carbon
    {
        if (array_key_exists($learnerId, static::$anchorMemo)) {
            return static::$anchorMemo[$learnerId];
        }

        $max = static::query()
            ->atFinestGrain($learnerId)
            ->max('updated_at');

        return static::$anchorMemo[$learnerId] = $max ? Carbon::parse($max) : null;
    }

    /** Test/CLI hook -- the memos above are per-request by design. */
    public static function flushGrainMemo(): void
    {
        static::$grainMemo = [];
        static::$anchorMemo = [];
    }
}
