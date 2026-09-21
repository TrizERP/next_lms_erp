<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One statement in a True/False pool.
 */
class H5pTrueFalseQuestion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_true_false_questions';
    protected $guarded = [];

    protected $casts = [
        // See the migration: the column is a real boolean and H5P's param is
        // the string "true"/"false". The conversion happens once, in the
        // builder, and never in a comparison.
        'correct_answer' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(H5pTrueFalse::class, 'true_false_id');
    }

    /** The H5P param value for this answer: the string, not the boolean. */
    public function h5pCorrect(): string
    {
        return $this->correct_answer ? 'true' : 'false';
    }

    /**
     * The statement as plain text, for a message an author reads.
     *
     * `question_text` is HTML, and a publish blocker quoting raw markup back
     * at a teacher is worse than no message.
     */
    public function plainQuestion(int $limit = 60): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $this->question_text), ENT_QUOTES | ENT_HTML5));

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . "\u{2026}" : $text;
    }
}
