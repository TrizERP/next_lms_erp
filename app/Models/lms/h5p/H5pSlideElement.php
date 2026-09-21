<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One positioned thing on a slide: a tagged union over geometry.
 *
 * See the migration for why the kind-specific payload is JSON in `options`
 * and the geometry is real columns.
 */
class H5pSlideElement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_slide_elements';
    protected $guarded = [];

    /** Static content: rendered, never scored. */
    public const STATIC_TYPES = ['text', 'image', 'video', 'audio'];

    /** Scored interactions. `drag_drop` embeds an existing activity by id. */
    public const SCORED_TYPES = ['multiple_choice', 'true_false', 'blanks', 'drag_drop'];

    /** Navigation. Not content and not scored. */
    public const NAVIGATION_TYPES = ['goto_slide'];

    /**
     * The closed set an element_type is validated against.
     *
     * Deliberately derived rather than written out a fourth time: adding a
     * kind to one of the three lists above is the whole change.
     */
    public const TYPES = [
        'text', 'image', 'video', 'audio',
        'multiple_choice', 'true_false', 'blanks', 'drag_drop',
        'goto_slide',
    ];

    protected $casts = [
        'position_x' => 'float',
        'position_y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'options' => 'array',
        'ref_content_id' => 'integer',
        'points' => 'integer',
        'sort_order' => 'integer',
    ];

    public function slide(): BelongsTo
    {
        return $this->belongsTo(H5pPresentationSlide::class, 'slide_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(H5pCoursePresentation::class, 'presentation_id');
    }

    public function isScored(): bool
    {
        return in_array($this->element_type, self::SCORED_TYPES, true);
    }

    /**
     * Is this element answerable as authored?
     *
     * Publish reports the first element that is not, by slide and position, so
     * a teacher is told "slide 4 has a multiple choice with no correct answer"
     * rather than "this deck cannot be published".
     */
    public function authoringProblem(): ?string
    {
        $options = (array) ($this->options ?? []);

        switch ($this->element_type) {
            case 'multiple_choice':
                $answers = (array) ($options['answers'] ?? []);
                if (count($answers) < 2) {
                    return 'a multiple choice with fewer than two answers';
                }
                if (! collect($answers)->contains(fn ($a) => (bool) ($a['correct'] ?? false))) {
                    return 'a multiple choice with no correct answer';
                }

                return null;

            case 'true_false':
                return array_key_exists('correct', $options)
                    ? null
                    : 'a true/false with no correct side chosen';

            case 'blanks':
                return str_contains((string) ($options['passage'] ?? ''), '*')
                    ? null
                    : 'a fill in the blanks with no *answer* marked in its passage';

            case 'drag_drop':
                return $this->ref_content_id
                    ? null
                    : 'an embedded drag and drop with no activity chosen';

            case 'goto_slide':
                return ($options['target_slide_id'] ?? null)
                    ? null
                    : 'a go-to-slide button with no destination';

            case 'image':
            case 'video':
            case 'audio':
                return trim((string) $this->media_path) !== ''
                    ? null
                    : 'a ' . $this->element_type . ' element with no file';

            default:
                return null;
        }
    }
}
