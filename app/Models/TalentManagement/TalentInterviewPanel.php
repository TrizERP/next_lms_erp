<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from `App\Models\talent\interview_panel\TalentInterviewPanel` (hp_erp).
 */
class TalentInterviewPanel extends Model
{
    use SoftDeletes;

    protected $table = 'talent_interview_panel';

    protected $fillable = [
        'sub_institute_id',
        'panel_name',
        'target_positions',
        'description',
        'available_interviewers',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function schedules()
    {
        return $this->hasMany(TalentInterviewSchedule::class, 'panel_id');
    }
}
