<?php

namespace App\Models\OrganizationManagement;

use App\Models\HrmsDepartment;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `master_compliance` table / the `complaince_library` branch
 * of `App\Http\Controllers\settings\instituteDetailController` (raw DB::table
 * queries there - no Eloquent model existed in G2G). This is a new proper model
 * for the new `org_compliance_library` table (deliberately NOT the unrelated
 * `master_compliance` SQAA table already present in LMS-K12).
 *
 * Compliance Management foundation pass (2026-09-28): extended in place with
 * category/department_id/status/priority/recurrence columns - see
 * 2026_09_28_100100_add_compliance_management_fields_to_org_compliance_library.
 * The controller/API do not read or write any of these new fields yet (data
 * model only, per product decision); `deriveStatus()`/`calculateNextDueDate()`
 * are ready for that next pass to call.
 */
class ComplianceLibraryRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'org_compliance_library';

    public const STATUS_UPCOMING = 'Upcoming';
    public const STATUS_DUE_SOON = 'Due Soon';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_PENDING_VERIFICATION = 'Pending Verification';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_OVERDUE = 'Overdue';
    public const STATUS_EXPIRED = 'Expired';
    public const STATUS_NOT_APPLICABLE = 'Not Applicable';

    public const STATUSES = [
        self::STATUS_UPCOMING,
        self::STATUS_DUE_SOON,
        self::STATUS_IN_PROGRESS,
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_COMPLETED,
        self::STATUS_OVERDUE,
        self::STATUS_EXPIRED,
        self::STATUS_NOT_APPLICABLE,
    ];

    /**
     * Statuses a person (or a completion/verification action) set on
     * purpose. `deriveStatus()` never overwrites one of these with a
     * time-based guess - only the remaining, purely time-driven statuses
     * (Upcoming/Due Soon/Overdue) are ever auto-computed.
     */
    public const MANUALLY_CONTROLLED_STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_COMPLETED,
        self::STATUS_EXPIRED,
        self::STATUS_NOT_APPLICABLE,
    ];

    public const PRIORITY_LOW = 'Low';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_HIGH = 'High';
    public const PRIORITY_CRITICAL = 'Critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    /**
     * 'Daily'/'Weekly' are kept for backward compatibility with rows/UI
     * already using them (the ported frontend's `Frequency` union type);
     * 'Half-Yearly' is new, added per the product brief.
     */
    public const FREQUENCY_ONE_TIME = 'One-Time';
    public const FREQUENCY_DAILY = 'Daily';
    public const FREQUENCY_WEEKLY = 'Weekly';
    public const FREQUENCY_MONTHLY = 'Monthly';
    public const FREQUENCY_QUARTERLY = 'Quarterly';
    public const FREQUENCY_HALF_YEARLY = 'Half-Yearly';
    public const FREQUENCY_YEARLY = 'Yearly';
    public const FREQUENCY_CUSTOM = 'Custom';

    public const FREQUENCIES = [
        self::FREQUENCY_ONE_TIME,
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_QUARTERLY,
        self::FREQUENCY_HALF_YEARLY,
        self::FREQUENCY_YEARLY,
        self::FREQUENCY_CUSTOM,
    ];

    protected $fillable = [
        'name',
        'description',
        'standard_name',
        'category_id',
        'department',
        'department_id',
        'assigned_to',
        'duedate',
        'next_due_date',
        'attachment',
        'frequency',
        'custom_frequency_details',
        'status',
        'priority',
        'completed_at',
        'completed_by',
        'parent_compliance_id',
        'sub_institute_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'duedate' => 'date',
        'next_due_date' => 'date',
        'completed_at' => 'datetime',
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

    public function completedByUser()
    {
        return $this->belongsTo(tbluserModel::class, 'completed_by', 'id');
    }

    public function category()
    {
        return $this->belongsTo(ComplianceCategory::class, 'category_id');
    }

    /** The real FK - `department` (free-text) stays for the transition; see the field's own migration docblock. */
    public function departmentData()
    {
        return $this->belongsTo(HrmsDepartment::class, 'department_id', 'id');
    }

    public function evidence()
    {
        return $this->hasMany(ComplianceEvidence::class, 'compliance_id');
    }

    /** The cycle this one was generated from, when this row is a recurrence of an earlier compliance. */
    public function parentCycle()
    {
        return $this->belongsTo(self::class, 'parent_compliance_id');
    }

    /** Cycles generated from this one, newest first - the audit-facing "2025 -> 2026 -> 2027" history. */
    public function childCycles()
    {
        return $this->hasMany(self::class, 'parent_compliance_id')->orderByDesc('duedate');
    }

    /**
     * Time-based status, honouring any status a person (or the
     * complete/verify actions) already set on purpose - see
     * MANUALLY_CONTROLLED_STATUSES. Pure function of $this's own columns;
     * callers persist the result themselves if/when they want it stored.
     */
    public function deriveStatus(): string
    {
        if ($this->status && in_array($this->status, self::MANUALLY_CONTROLLED_STATUSES, true)) {
            return $this->status;
        }

        if (!$this->duedate) {
            return self::STATUS_UPCOMING;
        }

        $due = $this->duedate instanceof Carbon ? $this->duedate : Carbon::parse($this->duedate);
        $today = Carbon::today();

        if ($due->lt($today)) {
            return self::STATUS_OVERDUE;
        }

        if ($due->lte($today->copy()->addDays(30))) {
            return self::STATUS_DUE_SOON;
        }

        return self::STATUS_UPCOMING;
    }

    /**
     * Next cycle's due date for a recurring compliance, anchored to this
     * cycle's OWN due date (not its completion date) - a Quarterly
     * inspection due 10 Jan is next due 10 Apr whether it was actually
     * completed on 9 Jan or 15 Jan, per the product brief's worked example.
     * That keeps the schedule from drifting late every time completion runs
     * a few days behind the due date.
     *
     * Returns null for One-Time (nothing to generate) and for Custom
     * without a parseable `custom_frequency_details` date (the admin sets
     * the next date by hand in that case).
     */
    public function calculateNextDueDate(): ?Carbon
    {
        if (!$this->duedate) {
            return null;
        }

        $due = $this->duedate instanceof Carbon ? $this->duedate : Carbon::parse($this->duedate);

        return match ($this->frequency) {
            self::FREQUENCY_DAILY => $due->copy()->addDay(),
            self::FREQUENCY_WEEKLY => $due->copy()->addWeek(),
            self::FREQUENCY_MONTHLY => $due->copy()->addMonthNoOverflow(),
            self::FREQUENCY_QUARTERLY => $due->copy()->addMonthsNoOverflow(3),
            self::FREQUENCY_HALF_YEARLY => $due->copy()->addMonthsNoOverflow(6),
            self::FREQUENCY_YEARLY => $due->copy()->addYearNoOverflow(),
            self::FREQUENCY_CUSTOM => $this->custom_frequency_details && strtotime($this->custom_frequency_details) !== false
                ? Carbon::parse($this->custom_frequency_details)
                : null,
            default => null, // One-Time, or an unrecognised value
        };
    }
}
