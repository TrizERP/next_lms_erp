<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from `App\Models\talent\talent_screening_results` (hp_erp).
 */
class TalentScreeningResult extends Model
{
    use SoftDeletes;

    protected $table = 'talent_screening_results';

    protected $fillable = [
        'candidate_id',
        'competency_match',
        'cultural_fit',
        'predicted_success',
        'overall_fit_score',
        'ranking_score',
        'skill_gaps',
        'strengths',
        'recommendation',
        'deepseek_analysis',
        'sub_institute_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'skill_gaps' => 'array',
        'strengths' => 'array',
        'deepseek_analysis' => 'array',
    ];

    public function candidate()
    {
        return $this->belongsTo(TalentJobApplication::class, 'candidate_id');
    }
}
