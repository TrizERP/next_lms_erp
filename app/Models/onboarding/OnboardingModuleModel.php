<?php

namespace App\Models\onboarding;

use Illuminate\Database\Eloquent\Model;

class OnboardingModuleModel extends Model
{
    protected $table = 'onboarding_module';

    protected $fillable = [
        'module_key',
        'module_name',
        'menu_title',
        'description',
        'icon',
        'sort_order',
        'status',
        'sub_institute_id',
    ];

    public function steps()
    {
        return $this->hasMany(OnboardingStepModel::class, 'module_id')->orderBy('sort_order');
    }

    /**
     * Resolve the journey definition visible to one tenant: the global template
     * (sub_institute_id = 0) plus any tenant-specific override, with the
     * override winning on `module_key`. Mirrors the `requirement_gathering`
     * convention already used in the codebase.
     */
    public static function forTenant($subInstituteId)
    {
        $rows = static::where('status', 1)
            ->whereIn('sub_institute_id', [0, (int) $subInstituteId])
            ->orderBy('sort_order')
            ->orderBy('module_name')
            ->get();

        $resolved = [];
        foreach ($rows as $row) {
            $existing = $resolved[$row->module_key] ?? null;
            if (! $existing || (int) $row->sub_institute_id !== 0) {
                $resolved[$row->module_key] = $row;
            }
        }

        return collect(array_values($resolved))->sortBy('sort_order')->values();
    }
}
