<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;

/** No soft deletes - the source table (`competency_assessment_question`) has no `deleted_at`. */
class CompetencyAssessmentQuestion extends Model
{
    protected $table = 'competency_assessment_question';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'test_id'          => 'integer',
        'kasba_item_id'    => 'integer',
        'options'          => 'array',
        'max_score'        => 'integer',
        'sort_order'       => 'integer',
    ];

    public function test()
    {
        return $this->belongsTo(CompetencyAssessmentTest::class, 'test_id');
    }

    public function responses()
    {
        return $this->hasMany(CompetencyAssessmentResponse::class, 'question_id');
    }
}
