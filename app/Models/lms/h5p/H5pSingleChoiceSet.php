<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P Single Choice Set (H5P.SingleChoiceSet): a run of questions, each
 * with exactly one right answer.
 *
 * Shaped like the rest of the family -- guarded = [], soft deletes, tenant
 * column on the row, children eager-loadable because no read path wants the
 * parent alone: a set with no questions is neither renderable nor publishable.
 */
class H5pSingleChoiceSet extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_single_choice_set';
    protected $guarded = [];

    /** The registry code and the config/h5p_libraries.php key. */
    public const REGISTRY_CODE = 'single_choice_set';

    /**
     * The widest set this type accepts.
     *
     * Not a database constraint -- it is a judgement about the format. Past
     * roughly this many questions the one-at-a-time rhythm stops being a
     * quick set and becomes an exam, which is a different content type with
     * different reporting. Enforced in validation so the ceiling can move
     * without a migration.
     */
    public const MAX_QUESTIONS = 50;

    /** Options per question, as H5P.SingleChoiceSet itself allows. */
    public const MIN_OPTIONS = 2;
    public const MAX_OPTIONS = 6;

    protected $casts = [
        'auto_continue' => 'boolean',
        'timeout_correct_ms' => 'integer',
        'timeout_wrong_ms' => 'integer',
        'sound_effects' => 'boolean',
        'enable_retry' => 'boolean',
        'enable_show_solution' => 'boolean',
        'randomize_questions' => 'boolean',
        'randomize_answers' => 'boolean',
        'points_per_question' => 'integer',
        'pass_percentage' => 'integer',
        'show_progress' => 'boolean',
        'feedback_bands' => 'array',
        'published_at' => 'datetime',
    ];

    /** Questions in author order, each with its options. */
    public function questions(): HasMany
    {
        return $this->hasMany(H5pSingleChoiceQuestion::class, 'set_id')->orderBy('sort_order');
    }

    /**
     * Every option in the set, flat.
     *
     * The denormalised `set_id` on the options table is what makes this one
     * query. The publish check and the child cascade both want it, and neither
     * cares which question an option sits under.
     */
    public function options(): HasMany
    {
        return $this->hasMany(H5pSingleChoiceOption::class, 'set_id')->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * What a full-marks attempt is worth: every question answered correctly.
     *
     * Reads the loaded relation when there is one so a list page that eager
     * loaded questions does not fire a query per row.
     */
    public function maxScore(): int
    {
        $count = $this->relationLoaded('questions')
            ? $this->questions->count()
            : $this->questions()->count();

        return $count * max(1, (int) $this->points_per_question);
    }
}
