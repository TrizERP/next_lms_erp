<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per student attempt at a teacher-authored LMS Quiz
 * (`question_paper.exam_type = 'quiz'`). See the
 * `2026_10_10_140000_create_lms_quiz_attempts_table` migration for why this
 * table exists instead of reusing `lms_online_exam`.
 */
class LmsQuizAttemptModel extends Model
{
    protected $table = 'lms_quiz_attempts';

    protected $fillable = [
        'question_paper_id',
        'student_id',
        'sub_institute_id',
        'syear',
        'status',
        'total_questions',
        'correct_count',
        'wrong_count',
        'unanswered_count',
        'score_marks',
        'max_marks',
        'answers_json',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'answers_json' => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];
}
