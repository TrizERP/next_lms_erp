<?php

namespace App\Models\OrganizationManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Compliance Management, frontend-completion pass (see
 * 2026_09_28_150000_create_compliance_templates_table). Same tenant-or-global
 * shape as ComplianceCategory - always query through scopeAvailableFor().
 */
class ComplianceTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'compliance_templates';

    protected $fillable = [
        'sub_institute_id',
        'name',
        'description',
        'category_id',
        'default_frequency',
        'default_custom_frequency_details',
        'default_priority',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'category_id' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    public function scopeAvailableFor(Builder $query, int $subInstituteId): Builder
    {
        return $query->where(function (Builder $inner) use ($subInstituteId) {
            $inner->where('sub_institute_id', $subInstituteId)
                ->orWhereNull('sub_institute_id');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function category()
    {
        return $this->belongsTo(ComplianceCategory::class, 'category_id');
    }
}
