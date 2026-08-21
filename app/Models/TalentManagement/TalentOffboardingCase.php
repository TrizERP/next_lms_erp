<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\talent\TalentOffboardingCase`.
 *
 * One row per exit case. The clearance checklist, document checklist,
 * comment thread and activity log are self-contained JSON-in-text columns on
 * this same row (`clearance_tasks`, `documents`, `comments`, `activity_log`)
 * — there is no separate clearance or exit-interview table in the active
 * (`Api\Offboarding\OffboardingController`) implementation.
 */
class TalentOffboardingCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talent_offboarding_cases';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'employee_id' => 'integer',
        'department_id' => 'integer',
        'notice_date' => 'date',
        'last_working_day' => 'date',
        'exit_interview_done' => 'boolean',
        'exit_interview_date' => 'date',
        'manager_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];
}
