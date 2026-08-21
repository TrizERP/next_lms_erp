<?php

namespace App\Models\OrganizationManagement;

use App\Models\HrmsDepartment;
use App\Models\user\tbluserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\settings\discliplinaryManagementModel`
 * (table `discliplinary_management`) onto the new `org_disciplinary_library`
 * table. Source uses the misspelling "discliplinary" throughout; this is a new
 * module, not bound to G2G's exact strings, so the correct spelling
 * "disciplinary" is used for every symbol here.
 *
 * BUG FIX vs. source: G2G's `updatedUser()` and `deletedUser()` both incorrectly
 * bind to `created_by`. Fixed here to bind to `updated_by` / `deleted_by`
 * respectively.
 */
class DisciplinaryRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'org_disciplinary_library';

    protected $fillable = [
        'department_id',
        'employee_id',
        'incident_datetime',
        'location',
        'misconduct_type',
        'description',
        'witness_id',
        'action_taken',
        'remarks',
        'reported_by',
        'date_of_report',
        'sub_institute_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'incident_datetime' => 'datetime',
        'date_of_report'    => 'date',
    ];

    public function departmentData()
    {
        return $this->belongsTo(HrmsDepartment::class, 'department_id', 'id');
    }

    public function employeeData()
    {
        return $this->belongsTo(tbluserModel::class, 'employee_id', 'id');
    }

    public function witnessData()
    {
        return $this->belongsTo(tbluserModel::class, 'witness_id', 'id');
    }

    public function reportByData()
    {
        return $this->belongsTo(tbluserModel::class, 'reported_by', 'id');
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
