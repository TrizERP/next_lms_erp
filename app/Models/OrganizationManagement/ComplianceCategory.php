<?php

namespace App\Models\OrganizationManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Compliance Management foundation (see 2026_09_28_100000_create_compliance_categories_table).
 * `sub_institute_id === null` rows are platform-wide defaults; a school may
 * add its own via a row scoped to its own tenant. Always query through
 * scopeAvailableFor() rather than filtering on sub_institute_id directly, or
 * every school will silently lose the global defaults.
 */
class ComplianceCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'compliance_categories';

    protected $fillable = [
        'sub_institute_id',
        'name',
        'description',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    /** Global defaults plus this tenant's own categories. */
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

    public function complianceRecords()
    {
        return $this->hasMany(ComplianceLibraryRecord::class, 'category_id');
    }
}
