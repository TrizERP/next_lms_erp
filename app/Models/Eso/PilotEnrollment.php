<?php

namespace App\Models\Eso;

use Illuminate\Database\Eloquent\Model;

/**
 * Pilot cohort / Arm A-B assignment — see
 * docs/CHAPTER_1014_PILOT_MEASUREMENT_PLAN.md §3/§10.
 */
class PilotEnrollment extends Model
{
    protected $table = 'pal_pilot_enrollments';

    public const ARM_A = 'A';
    public const ARM_B = 'B';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'student_id', 'sub_institute_id', 'chapter_id', 'arm',
        'cohort_label', 'status', 'enrolled_at', 'enrolled_by',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    public function scopeForChapter($query, int $chapterId)
    {
        return $query->where('chapter_id', $chapterId);
    }

    public function scopeArm($query, string $arm)
    {
        return $query->where('arm', $arm);
    }

    /** Enrollments that actually count toward a metric — see plan §8. */
    public function scopeCountable($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_COMPLETED]);
    }
}
