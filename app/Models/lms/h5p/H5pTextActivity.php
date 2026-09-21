<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P text-passage activity: Drag the Words, Fill in the Blanks or Mark
 * the Words. Which one is the `content_type` column -- see the migration for
 * why all three share a table.
 *
 * Shaped like H5pDragDrop: guarded = [], soft deletes, tenant column on the
 * row, children eager-loadable because every read path needs the answer key.
 */
class H5pTextActivity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_text_activity';
    protected $guarded = [];

    /**
     * The three types this table holds, mapped to the registry code they share
     * with config/pal_h5p.php and config/h5p_libraries.php.
     *
     * Nothing outside this constant should hard-code the strings: a controller
     * resolves its type through here, so a typo is a failed lookup rather than
     * a silently empty list.
     */
    public const TYPES = ['drag_text', 'fill_in_the_blanks', 'mark_the_words'];

    /** Human labels, for messages and the export manifest title fallback. */
    public const LABELS = [
        'drag_text' => 'Drag the Words',
        'fill_in_the_blanks' => 'Fill in the Blanks',
        'mark_the_words' => 'Mark the Words',
    ];

    protected $casts = [
        'enable_retry' => 'boolean',
        'enable_show_solution' => 'boolean',
        'enable_check' => 'boolean',
        'case_sensitive' => 'boolean',
        'accept_spelling_errors' => 'boolean',
        'instant_feedback' => 'boolean',
        'show_score_points' => 'boolean',
        'separate_lines' => 'boolean',
        'solution_requires_input' => 'boolean',
        'points_per_blank' => 'integer',
        'pass_percentage' => 'integer',
        'feedback_bands' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Every answer slot, distractors included, in author order.
     *
     * Ordered by blank_index rather than id so the order survives a re-parse
     * that reuses no ids -- which is what every save does.
     */
    public function blanks(): HasMany
    {
        return $this->hasMany(H5pTextActivityBlank::class, 'text_activity_id')->orderBy('blank_index');
    }

    /** The scorable slots -- distractors are draggable, never markable. */
    public function scorableBlanks(): HasMany
    {
        return $this->blanks()->where('is_distractor', false);
    }

    public function scopeOfType(Builder $query, string $contentType): Builder
    {
        return $query->where('content_type', $contentType);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function label(): string
    {
        return self::LABELS[$this->content_type] ?? 'Text activity';
    }

    /**
     * What a full-marks attempt is worth.
     *
     * Reads the loaded relation when there is one so a list page that already
     * eager-loaded blanks does not fire a query per row.
     */
    public function maxScore(): int
    {
        $count = $this->relationLoaded('blanks')
            ? $this->blanks->where('is_distractor', false)->count()
            : $this->scorableBlanks()->count();

        return $count * max(1, (int) $this->points_per_blank);
    }
}
