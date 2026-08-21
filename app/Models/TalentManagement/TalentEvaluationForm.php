<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from `App\Models\talent\feedback\TalentEvaluationForm` (hp_erp).
 * Backs the "feedback" endpoints (`/evaluation`, `/feedback*`).
 */
class TalentEvaluationForm extends Model
{
    use SoftDeletes;

    protected $table = 'talent_evaluation_form';

    protected $fillable = [
        'job_id',
        'candidate_id',
        'panel_id',
        'evaluation_criteria',
        'recommendation',
        'key_strengths',
        'areas_of_concern',
        'additional_comments',
        'notes',
        'status',
        'sub_institute_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'evaluation_criteria' => 'array',
    ];
}
