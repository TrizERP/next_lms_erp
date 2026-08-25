<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Guards marks entry once an exam's results have been published. No row for
 * a given (sub_institute_id, syear, exam_id) means unlocked (pre-existing
 * behaviour is preserved until someone explicitly locks an exam).
 */
class ResultLock extends Model
{
    protected $table = 'result_locks';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'exam_id' => 'integer',
        'locked_by' => 'integer',
        'locked_at' => 'datetime',
    ];

    public static function isLocked($subInstituteId, $syear, $examId): bool
    {
        if (empty($examId)) {
            return false;
        }

        return static::where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->where('exam_id', $examId)
            ->exists();
    }

    public static function lock($subInstituteId, $syear, $examId, $lockedBy): self
    {
        return static::updateOrCreate(
            ['sub_institute_id' => $subInstituteId, 'syear' => $syear, 'exam_id' => $examId],
            ['locked_by' => $lockedBy, 'locked_at' => now()]
        );
    }

    public static function unlock($subInstituteId, $syear, $examId): void
    {
        static::where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->where('exam_id', $examId)
            ->delete();
    }
}
