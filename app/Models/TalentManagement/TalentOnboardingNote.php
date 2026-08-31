<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Onboarding\OnboardingNote`.
 *
 * An authored note on a journey (the sidebar Notes card).
 * talent_onboarding_journeys.notes is a single TEXT column that cannot record
 * who wrote what and when, which is what the card's "Add Note" flow needs.
 */
class TalentOnboardingNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talent_onboarding_notes';
    protected $guarded = ['id'];

    protected $casts = [
        'journey_id'       => 'integer',
        'sub_institute_id' => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function journey()
    {
        return $this->belongsTo(TalentOnboardingJourney::class, 'journey_id');
    }
}
