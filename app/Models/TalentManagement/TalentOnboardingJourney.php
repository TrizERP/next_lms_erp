<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Onboarding\OnboardingJourney`.
 *
 * One new hire's onboarding journey, from accepted offer to confirmation.
 * Drives the profile sidebar, the breadcrumb journey code, the KPI cards and
 * the Probation & Confirmation tab of Talent Management -> Onboarding &
 * Employee Lifecycle Center.
 *
 * `employee_id` is nullable on purpose: a preboarding hire exists as a
 * talent_job_applications row long before they get a tbluser record, so the
 * candidate_* columns carry their details until day one.
 */
class TalentOnboardingJourney extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talent_onboarding_journeys';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'employee_id'      => 'integer',
        'offer_id'         => 'integer',
        'application_id'   => 'integer',
        'department_id'    => 'integer',
        'buddy_id'         => 'integer',
        'manager_id'       => 'integer',
        'joining_date'     => 'date',
        'completed_at'     => 'date',
        'probation_start'  => 'date',
        'probation_end'    => 'date',
        'extension_end'    => 'date',
        'confirmed_on'     => 'date',
        'confirmed_by'     => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function tasks()
    {
        return $this->hasMany(TalentOnboardingTask::class, 'journey_id');
    }

    public function stages()
    {
        return $this->hasMany(TalentOnboardingJourneyStage::class, 'journey_id');
    }

    public function documents()
    {
        return $this->hasMany(TalentOnboardingDocument::class, 'journey_id');
    }

    public function notes()
    {
        return $this->hasMany(TalentOnboardingNote::class, 'journey_id');
    }
}
