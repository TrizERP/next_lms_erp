<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P Arithmetic Quiz (H5P.ArithmeticQuiz).
 *
 * The only type in this family with no child relation: it stores the rule that
 * generates questions, not the questions. See the migration.
 */
class H5pArithmeticQuiz extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_arithmetic_quiz';
    protected $guarded = [];

    public const REGISTRY_CODE = 'arithmetic_quiz';

    public const OPERATIONS = ['addition', 'subtraction', 'multiplication', 'division'];

    /**
     * Difficulty -> operand ranges.
     *
     * The generator that actually draws the numbers lives in the frontend
     * (lib/h5p/arithmetic-quiz.ts) so a drill needs no round trip per
     * question. This copy is what the EXPORTED package and the server-side
     * preview describe a level as, and the two are asserted equal by
     * H5PArithmeticQuizBuilderTest -- so a change to one that is not made to
     * the other fails a test rather than shipping two different quizzes.
     */
    public const DIFFICULTY_RANGES = [
        1 => ['min' => 1, 'max' => 10, 'label' => 'Easy'],
        2 => ['min' => 2, 'max' => 25, 'label' => 'Medium'],
        3 => ['min' => 5, 'max' => 100, 'label' => 'Hard'],
    ];

    protected $casts = [
        'show_intro' => 'boolean',
        'operations' => 'array',
        'difficulty_level' => 'integer',
        'max_questions' => 'integer',
        'enable_timer' => 'boolean',
        'time_limit_seconds' => 'integer',
        'points_per_question' => 'integer',
        'pass_percentage' => 'integer',
        'enable_retry' => 'boolean',
        'max_attempts' => 'integer',
        'feedback_bands' => 'array',
        'published_at' => 'datetime',
    ];

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** The operations in play, always a clean list of known values. */
    public function activeOperations(): array
    {
        $operations = array_values(array_intersect(
            (array) ($this->operations ?? []),
            self::OPERATIONS
        ));

        // A quiz that draws from nothing would generate nothing and score 0/0.
        // Addition is the floor rather than an error, because this is a read
        // path and a row that old is a row someone still has to open.
        return $operations !== [] ? $operations : ['addition'];
    }

    public function difficultyRange(): array
    {
        return self::DIFFICULTY_RANGES[(int) $this->difficulty_level] ?? self::DIFFICULTY_RANGES[1];
    }

    public function maxScore(): int
    {
        return max(1, (int) $this->max_questions) * max(1, (int) $this->points_per_question);
    }
}
