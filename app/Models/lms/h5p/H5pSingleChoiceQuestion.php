<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One question in a Single Choice Set.
 */
class H5pSingleChoiceQuestion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_single_choice_questions';
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(H5pSingleChoiceSet::class, 'set_id');
    }

    /** Options in author order. The player may still shuffle them. */
    public function options(): HasMany
    {
        return $this->hasMany(H5pSingleChoiceOption::class, 'question_id')->orderBy('sort_order');
    }

    /**
     * The one right option, or null when the question is not yet valid.
     *
     * Null is a real state: a draft is allowed to be half-written, and this is
     * what the publish check tests. Every caller has to handle it, which is
     * the point -- a helper that invented a correct answer would turn an
     * unfinished question into a silently wrong one.
     */
    public function correctOption(): ?H5pSingleChoiceOption
    {
        $options = $this->relationLoaded('options') ? $this->options : $this->options()->get();

        return $options->first(fn (H5pSingleChoiceOption $option) => (bool) $option->is_correct);
    }

    /** How many options claim to be correct. Exactly one is the invariant. */
    public function correctCount(): int
    {
        $options = $this->relationLoaded('options') ? $this->options : $this->options()->get();

        return $options->filter(fn (H5pSingleChoiceOption $option) => (bool) $option->is_correct)->count();
    }

    /**
     * The question as plain text, for a message an author reads.
     *
     * `question_text` is HTML, and a publish blocker that says
     * "Question 3 (&lt;p&gt;What is …) has no correct answer" is worse than no
     * message. Truncated because these appear in one-line banners.
     */
    public function plainQuestion(int $limit = 60): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $this->question_text), ENT_QUOTES | ENT_HTML5));

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . "\u{2026}" : $text;
    }
}
