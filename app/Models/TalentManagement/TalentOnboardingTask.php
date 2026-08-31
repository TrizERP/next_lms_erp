<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Onboarding\OnboardingTask`.
 *
 * A preboarding / onboarding task on a journey.
 *
 * `category` doubles as the workstream key, so the "Onboarding Integrations &
 * Tasks" cards are a rollup over this column instead of a second table:
 * documents | compliance | it | learning | payroll | benefits | personal.
 *
 * `owner_id` is the assigned tbluser; `owner_label` covers the team owners the
 * screen shows ("HR Team", "IT Team") that are not one person.
 */
class TalentOnboardingTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talent_onboarding_tasks';
    protected $guarded = ['id'];

    protected $casts = [
        'journey_id'       => 'integer',
        'sub_institute_id' => 'integer',
        'owner_id'         => 'integer',
        'due_date'         => 'date',
        'completed_at'     => 'datetime',
        'sort_order'       => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function journey()
    {
        return $this->belongsTo(TalentOnboardingJourney::class, 'journey_id');
    }
}
