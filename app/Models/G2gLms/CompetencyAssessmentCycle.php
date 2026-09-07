<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompetencyAssessmentCycle extends Model
{
    use SoftDeletes;

    protected $table = 's_competency_assessment_cycles';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'framework_id'     => 'integer',
        'start_date'       => 'date',
        'end_date'         => 'date',
    ];

    public function assessments()
    {
        return $this->hasMany(CompetencyAssessment::class, 'cycle_id');
    }
}
