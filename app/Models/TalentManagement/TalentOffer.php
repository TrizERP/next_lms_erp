<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from `App\Models\talent\TalentOffer` (hp_erp).
 */
class TalentOffer extends Model
{
    use SoftDeletes;

    protected $table = 'talent_offers';

    protected $fillable = [
        'application_id',
        'job_id',
        'template_id',
        'position',
        'salary',
        'start_date',
        'expires_at',
        'status',
        'offer_letter_url',
        'notes',
        'sub_institute_id',
        'created_by',
        'reportmanager',
        'punchintime',
        'punchouttime',
        'sent_at',
        'rejected_at',
        'updated_by',
    ];
}
