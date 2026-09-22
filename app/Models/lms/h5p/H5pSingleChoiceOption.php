<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One answer option on a Single Choice Set question.
 *
 * `is_correct` is cast to boolean rather than read raw: MySQL hands a TINYINT
 * back as the string "1", and `$option->is_correct === true` on a string is
 * false -- which would mark a correct answer wrong in whichever caller
 * forgot. Casting once here is the only place that can be got right.
 */
class H5pSingleChoiceOption extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_single_choice_options';
    protected $guarded = [];

    protected $casts = [
        'is_correct' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(H5pSingleChoiceQuestion::class, 'question_id');
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(H5pSingleChoiceSet::class, 'set_id');
    }
}
