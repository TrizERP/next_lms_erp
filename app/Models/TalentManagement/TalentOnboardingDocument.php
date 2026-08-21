<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Onboarding\OnboardingDocument`.
 *
 * A document requested from a new hire, and where that request stands.
 * Backs the sidebar Documents card. Separate from `staff_document` (which
 * stores an already-filed HR document with no status lifecycle);
 * `document_type_id` points at the same `document_type` master so both share
 * one vocabulary.
 */
class TalentOnboardingDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talent_onboarding_documents';
    protected $guarded = ['id'];

    protected $casts = [
        'journey_id'       => 'integer',
        'sub_institute_id' => 'integer',
        'document_type_id' => 'integer',
        'is_mandatory'     => 'boolean',
        'due_date'         => 'date',
        'requested_at'     => 'datetime',
        'submitted_at'     => 'datetime',
        'verified_at'      => 'datetime',
        'verified_by'      => 'integer',
        'sort_order'       => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function journey()
    {
        return $this->belongsTo(TalentOnboardingJourney::class, 'journey_id');
    }
}
