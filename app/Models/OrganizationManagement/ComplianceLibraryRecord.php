<?php

namespace App\Models\OrganizationManagement;

use App\Models\user\tbluserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `master_compliance` table / the `complaince_library` branch
 * of `App\Http\Controllers\settings\instituteDetailController` (raw DB::table
 * queries there - no Eloquent model existed in G2G). This is a new proper model
 * for the new `org_compliance_library` table (deliberately NOT the unrelated
 * `master_compliance` SQAA table already present in LMS-K12).
 */
class ComplianceLibraryRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'org_compliance_library';

    protected $fillable = [
        'name',
        'description',
        'standard_name',
        'department',
        'assigned_to',
        'duedate',
        'attachment',
        'frequency',
        'custom_frequency_details',
        'sub_institute_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'duedate' => 'date',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(tbluserModel::class, 'assigned_to', 'id');
    }

    public function createdUser()
    {
        return $this->belongsTo(tbluserModel::class, 'created_by', 'id');
    }

    public function updatedUser()
    {
        return $this->belongsTo(tbluserModel::class, 'updated_by', 'id');
    }

    public function deletedUser()
    {
        return $this->belongsTo(tbluserModel::class, 'deleted_by', 'id');
    }
}
