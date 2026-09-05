<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompetencyAssessment extends Model
{
    use SoftDeletes;

    protected $table = 's_competency_assessments';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'framework_id'     => 'integer',
        'cycle_id'         => 'integer',
        'user_id'          => 'integer',
        'assessor_id'      => 'integer',
        'department_id'    => 'integer',
        'score'            => 'decimal:2',
        'due_date'         => 'date',
        'completed_at'     => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(CompetencyAssessmentCycle::class, 'cycle_id');
    }
}
