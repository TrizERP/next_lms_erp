<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * One syllabus, for one year — "CBSE Mathematics Grade 8, 2026-27".
 *
 * Content is tagged against one of these rather than against a mutable record,
 * so a board revision can create a successor without rewriting what an earlier
 * cohort was taught.
 */
class CurriculumVersion extends Model
{
    protected $table = 'pal_curriculum_versions';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'sub_institute_id', 'board', 'standard_id', 'subject_id',
        'academic_year', 'label', 'status', 'superseded_by_id', 'effective_from',
    ];

    protected $casts = [
        'standard_id' => 'integer',
        'subject_id' => 'integer',
        'superseded_by_id' => 'integer',
        'effective_from' => 'date',
    ];

    /** The version a learner is currently taught from. */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        return $subInstituteId === null ? $query : $query->where('sub_institute_id', $subInstituteId);
    }

    /**
     * The scope this version covers. `subject_id` is nullable so a version can
     * cover a whole standard where a board publishes one syllabus for it.
     */
    public function scopeForScope($query, string $board, int $standardId, ?int $subjectId)
    {
        return $query
            ->where('board', $board)
            ->where('standard_id', $standardId)
            ->where('subject_id', self::subjectKey($subjectId));
    }

    /**
     * The stored value for a subject scope. Null — "the whole standard" —
     * is stored as 0.
     *
     * A sentinel rather than NULL because MySQL treats NULLs as distinct in a
     * unique index, so the standard-wide case was the one the scope key failed
     * to constrain. 0 is not a valid `subject.id`, so it cannot collide with a
     * real subject.
     */
    public static function subjectKey(?int $subjectId): int
    {
        return $subjectId === null || $subjectId <= 0 ? 0 : $subjectId;
    }

    /** True when this version covers a whole standard rather than one subject. */
    public function isStandardWide(): bool
    {
        return (int) $this->subject_id === 0;
    }

    public function isSuperseded(): bool
    {
        return $this->status === self::STATUS_SUPERSEDED;
    }

    /** Content authored against a superseded version is history, not a candidate to serve. */
    public function isServable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
