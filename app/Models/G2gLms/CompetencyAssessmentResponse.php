<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;

/** No soft deletes - the source table (`competency_assessment_response`) has no `deleted_at`. */
class CompetencyAssessmentResponse extends Model
{
    protected $table = 'competency_assessment_response';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'test_id'          => 'integer',
        'question_id'      => 'integer',
        'user_id'          => 'integer',
        'score'            => 'decimal:2',
        'answered_at'      => 'datetime',
    ];

    public function question()
    {
        return $this->belongsTo(CompetencyAssessmentQuestion::class, 'question_id');
    }
}
