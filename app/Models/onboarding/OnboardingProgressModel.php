<?php

namespace App\Models\onboarding;

use Illuminate\Database\Eloquent\Model;

class OnboardingProgressModel extends Model
{
    protected $table = 'onboarding_progress';

    protected $fillable = [
        'sub_institute_id',
        'syear',
        'module_id',
        'step_id',
        'status',
        'notes',
        'assigned_to_id',
        'assigned_to_name',
        'updated_by_id',
        'updated_by_name',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'in_progress', 'completed', 'skipped', 'blocked'];
}
