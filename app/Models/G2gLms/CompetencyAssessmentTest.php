<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompetencyAssessmentTest extends Model
{
    use SoftDeletes;

    protected $table = 'competency_assessment_test';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'jobrole_id'       => 'integer',
        'generated_by'     => 'integer',
        'published_at'     => 'datetime',
    ];

    public function questions()
    {
        return $this->hasMany(CompetencyAssessmentQuestion::class, 'test_id')->orderBy('sort_order');
    }
}
