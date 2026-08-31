<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Ported from `App\Models\talent\talent_jobapplication` (hp_erp).
 */
class TalentJobApplication extends Model
{
    use SoftDeletes;

    protected $table = 'talent_job_applications';

    protected $fillable = [
        'job_id',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'mobile',
        'current_location',
        'employment_type',
        'experience',
        'education',
        'expected_salary',
        'skills',
        'certifications',
        'resume_path',
        'applied_date',
        'status',
        'sub_institute_id',
        'created_by',
        'updated_by',
    ];

    /**
     * True once the candidate has an offer sent/accepted, or has already
     * reached the offer/hired stage on the application status itself.
     * Ported verbatim from the source model — used to block re-shortlisting
     * and re-scheduling interviews for candidates already past that point.
     */
    public function hasReachedOfferOrHiredStage(): bool
    {
        $status = strtolower(trim((string) $this->status));
        if (str_contains($status, 'hired') || str_contains($status, 'offer')) {
            return true;
        }

        return DB::table('talent_offers')
            ->where('application_id', $this->id)
            ->whereIn(DB::raw('LOWER(status)'), ['sent', 'accepted'])
            ->exists();
    }

    public function jobPosting()
    {
        return $this->belongsTo(TalentJobPosting::class, 'job_id');
    }
}
