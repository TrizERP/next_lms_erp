<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P Course Presentation (H5P.CoursePresentation): a deck of slides of
 * positioned elements.
 */
class H5pCoursePresentation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_course_presentation';
    protected $guarded = [];

    public const REGISTRY_CODE = 'course_presentation';

    /** Named token sets the design system defines. Never raw colours. */
    public const THEMES = ['default', 'slate', 'indigo', 'warm', 'high-contrast'];

    public const TRANSITIONS = ['none', 'fade', 'slide'];

    protected $casts = [
        'show_progress_bar' => 'boolean',
        'show_keywords' => 'boolean',
        'show_summary_slide' => 'boolean',
        'enable_print' => 'boolean',
        'active_surface' => 'boolean',
        'enable_retry' => 'boolean',
        'enable_show_solution' => 'boolean',
        'pass_percentage' => 'integer',
        'feedback_bands' => 'array',
        'published_at' => 'datetime',
    ];

    /** Slides in deck order, each with its elements. */
    public function slides(): HasMany
    {
        return $this->hasMany(H5pPresentationSlide::class, 'presentation_id')->orderBy('slide_index');
    }

    /**
     * Every element in the deck, flat.
     *
     * The denormalised `presentation_id` on the elements table is what makes
     * this one query -- scoring and the publish check both want "all scored
     * elements" and neither cares which slide they sit on.
     */
    public function elements(): HasMany
    {
        return $this->hasMany(H5pSlideElement::class, 'presentation_id')->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * What a full-marks attempt is worth: the sum of the scored elements.
     *
     * Static elements carry 0 points and so contribute nothing without a
     * special case. A deck with no scored element has a max score of 0, which
     * is a legitimate deck -- a lecture -- and is why publish does not require
     * one here the way it does for drag and drop.
     */
    public function maxScore(): int
    {
        $elements = $this->relationLoaded('elements') ? $this->elements : $this->elements()->get();

        return (int) $elements->sum(fn (H5pSlideElement $element) => $element->isScored() ? (int) $element->points : 0);
    }
}
