<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from `App\Models\talent\TalentOfferTemplate` (hp_erp).
 */
class TalentOfferTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'talent_offer_templates';

    protected $fillable = [
        'sub_institute_id',
        'module_name',
        'title',
        'html_content',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];
}
