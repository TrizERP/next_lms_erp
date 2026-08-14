<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * QA pipeline audit trail (spec §7.1). Every status transition lands here so an
 * approval can always be traced to a person — the mitigation for the
 * rubber-stamping risk (plan §8, R4).
 */
class ContentReviewLog extends Model
{
    protected $table = 'pal_content_review_log';

    protected $fillable = [
        'entity_type', 'entity_id', 'sub_institute_id', 'from_status', 'to_status',
        'reviewed_by', 'actor_type', 'note', 'changed_fields',
    ];

    protected $casts = ['changed_fields' => 'array'];
}
