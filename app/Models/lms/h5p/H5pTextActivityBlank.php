<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One answer slot inside a text activity's passage.
 *
 * Derived from the `*answer*` markup on every save and never edited on its
 * own -- see the migration for why it is a table rather than a JSON column.
 */
class H5pTextActivityBlank extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_text_activity_blanks';
    protected $guarded = [];

    protected $casts = [
        'blank_index' => 'integer',
        'alternatives' => 'array',
        'is_distractor' => 'boolean',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(H5pTextActivity::class, 'text_activity_id');
    }

    /**
     * Every string that scores for this slot: the canonical solution first,
     * then the alternatives, with blanks and duplicates removed.
     *
     * @return list<string>
     */
    public function acceptedAnswers(): array
    {
        $answers = array_merge([(string) $this->solution], (array) ($this->alternatives ?? []));
        $answers = array_map(fn ($a) => trim((string) $a), $answers);

        return array_values(array_unique(array_filter($answers, fn ($a) => $a !== '')));
    }
}
