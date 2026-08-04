<?php

namespace App\Models\onboarding;

use Illuminate\Database\Eloquent\Model;

class OnboardingStepModel extends Model
{
    protected $table = 'onboarding_step';

    protected $fillable = [
        'module_id',
        'step_key',
        'title',
        'description',
        'sort_order',
        'is_required',
        'status',
        'owner',
        'triz_role',
        'school_role',
        'proof_type',
        'proof_table',
        'proof_scope',
        'proof_min_rows',
        'proof_conditions',
        'action_route',
        'action_menu_id',
        'help_youtube_link',
        'help_pdf_link',
    ];

    protected $casts = [
        'proof_conditions' => 'array',
        'is_required' => 'integer',
        'status' => 'integer',
        'proof_min_rows' => 'integer',
        'sort_order' => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(OnboardingModuleModel::class, 'module_id');
    }

    public function isYearScoped(): bool
    {
        return $this->proof_scope === 'tenant_year';
    }
}
