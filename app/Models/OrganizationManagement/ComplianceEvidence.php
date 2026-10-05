<?php

namespace App\Models\OrganizationManagement;

use App\Models\user\tbluserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Compliance Management foundation (see 2026_09_28_100200_create_compliance_evidence_table).
 * One compliance record can carry many evidence documents, each
 * independently trackable and verifiable - replaces the legacy single
 * `org_compliance_library.attachment` column (still present, untouched;
 * existing attachments were copied in as the first evidence row per
 * compliance by that migration).
 */
class ComplianceEvidence extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'compliance_evidence';

    public const STATUS_PENDING = 'Pending Verification';
    public const STATUS_VERIFIED = 'Verified';
    public const STATUS_REJECTED = 'Rejected';

    public const VERIFICATION_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'compliance_id',
        'sub_institute_id',
        'file_path',
        'file_name',
        'document_type',
        'description',
        'expiry_date',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'uploaded_by',
    ];

    protected $casts = [
        'compliance_id' => 'integer',
        'sub_institute_id' => 'integer',
        'expiry_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function compliance()
    {
        return $this->belongsTo(ComplianceLibraryRecord::class, 'compliance_id');
    }

    public function uploadedByUser()
    {
        return $this->belongsTo(tbluserModel::class, 'uploaded_by', 'id');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(tbluserModel::class, 'verified_by', 'id');
    }
}
