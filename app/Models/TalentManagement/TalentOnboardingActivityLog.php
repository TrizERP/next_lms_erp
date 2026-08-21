<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;

/**
 * Ported from G2G's `App\Models\Onboarding\OnboardingActivityLog`.
 *
 * Append-only feed behind the Lifecycle Timeline tab and the module audit
 * trail. `changes` holds the field-level diff [{field,label,old,new}].
 */
class TalentOnboardingActivityLog extends Model
{
    protected $table = 'talent_onboarding_activity_log';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'user_id'          => 'integer',
        'subject_id'       => 'integer',
        'journey_id'       => 'integer',
        'changes'          => 'array',
    ];
}
