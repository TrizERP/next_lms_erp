<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One slide in a Course Presentation.
 */
class H5pPresentationSlide extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_presentation_slides';
    protected $guarded = [];

    protected $casts = [
        'slide_index' => 'integer',
        'next_slide_id' => 'integer',
    ];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(H5pCoursePresentation::class, 'presentation_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(H5pSlideElement::class, 'slide_id')->orderBy('sort_order');
    }

    /** The slide this one branches to, if it overrides the sequence. */
    public function nextSlide(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_slide_id');
    }

    /** The name shown in the keyword rail and announced to a screen reader. */
    public function displayTitle(): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : 'Slide ' . ((int) $this->slide_index + 1);
    }
}
