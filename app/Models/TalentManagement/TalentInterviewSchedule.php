<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from `App\Models\talent\talent_interviewschedules` (hp_erp).
 */
class TalentInterviewSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'talent_interview_schedules';

    protected $casts = [
        'interviewer_id' => 'array',
    ];

    protected $fillable = [
        'job_id',
        'applicant_id',
        'round_no',
        'interview_date',
        'time',
        'duration',
        'location',
        'interviewer_id',
        'status',
        'rating',
        'feedback',
        'additional_notes',
        'panel_id',
        'sub_institute_id',
        'created_by',
        'updated_by',
    ];

    public function jobPosting()
    {
        return $this->belongsTo(TalentJobPosting::class, 'job_id');
    }

    public function applicant()
    {
        return $this->belongsTo(TalentJobApplication::class, 'applicant_id');
    }

    public function panel()
    {
        return $this->belongsTo(TalentInterviewPanel::class, 'panel_id');
    }
}
