<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\lms\contentModel;

class ContentResourceMetadata extends Model
{
    protected $table = 'lms_content_resource_metadata';

    protected $fillable = [
        'content_master_id',
        'sub_institute_id',
        'scope',
        'purpose',
        'audience',
        'delivery_mode',
        'metadata',
        'version',
        'quality_status',
        'tagged_by',
        'reviewed_by',
        'reviewed_at',
        'last_reviewed',
        'usage_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'usage_count' => 'integer',
        'version' => 'integer',
    ];

    public function contentMaster(): BelongsTo
    {
        return $this->belongsTo(contentModel::class, 'content_master_id');
    }
}
