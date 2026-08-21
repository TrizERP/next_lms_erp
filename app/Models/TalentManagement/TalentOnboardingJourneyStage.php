<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Onboarding\OnboardingJourneyStage`.
 *
 * One step on the "Onboarding Journey Progress" timeline. Seeded with seven
 * defaults when a journey is created (offer_accepted -> confirmation) and
 * editable afterwards. Stage dates are stored rather than derived from
 * joining_date, so the timeline shows real planning data instead of fixed
 * invented offsets.
 */
class TalentOnboardingJourneyStage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talent_onboarding_journey_stages';
    protected $guarded = ['id'];

    protected $casts = [
        'journey_id'       => 'integer',
        'sub_institute_id' => 'integer',
        'start_date'       => 'date',
        'end_date'         => 'date',
        'sort_order'       => 'integer',
        'completed_at'     => 'datetime',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function journey()
    {
        return $this->belongsTo(TalentOnboardingJourney::class, 'journey_id');
    }
}
