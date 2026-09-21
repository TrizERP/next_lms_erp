<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P True/False item (H5P.TrueFalse): a POOL of statements, each answered
 * true or false.
 *
 * The official library holds one question. This holds many, for the reason the
 * migration records. Everything else about the row -- tenancy, draft state,
 * package exchange, feedback bands -- is the family's shape unchanged.
 */
class H5pTrueFalse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_true_false';
    protected $guarded = [];

    /** The registry code and the config/h5p_libraries.php key. */
    public const REGISTRY_CODE = 'true_false';

    /**
     * The widest pool this type accepts.
     *
     * Higher than Single Choice Set's ceiling because a pool is authored once
     * and drawn from many times -- a hundred statements asked ten at a time is
     * a term of bell-ringers, not a hundred-question exam.
     */
    public const MAX_QUESTIONS = 100;

    protected $casts = [
        'enable_retry' => 'boolean',
        'enable_show_solution' => 'boolean',
        'enable_check_button' => 'boolean',
        'auto_check' => 'boolean',
        'confirm_check_dialog' => 'boolean',
        'confirm_retry_dialog' => 'boolean',
        'randomize_questions' => 'boolean',
        'questions_to_ask' => 'integer',
        'points_per_question' => 'integer',
        'pass_percentage' => 'integer',
        'show_progress' => 'boolean',
        'feedback_bands' => 'array',
        'published_at' => 'datetime',
    ];

    /** The pool, in author order. */
    public function questions(): HasMany
    {
        return $this->hasMany(H5pTrueFalseQuestion::class, 'true_false_id')->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * How many questions one attempt actually asks.
     *
     * `questions_to_ask` is what the author asked for; the pool is what they
     * wrote. A pool of six with "ask ten" asks six -- clamping here rather
     * than refusing, because an author who later deletes four questions has
     * not thereby broken a published activity.
     */
    public function questionsPerAttempt(): int
    {
        $pool = $this->relationLoaded('questions') ? $this->questions->count() : $this->questions()->count();
        $asked = max(0, (int) $this->questions_to_ask);

        return $asked > 0 ? min($asked, $pool) : $pool;
    }

    /**
     * What a full-marks attempt is worth.
     *
     * Counted from `questionsPerAttempt()`, not from the pool: a learner asked
     * ten of twenty is scored out of ten. Scoring against the pool would
     * report every attempt as a half-mark failure.
     */
    public function maxScore(): int
    {
        return $this->questionsPerAttempt() * max(1, (int) $this->points_per_question);
    }
}
