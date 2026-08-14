<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * CORRECTS_WITH — the corrective content served when a misconception fires.
 */
class MisconceptionCorrective extends Model
{
    protected $table = 'pal_misconception_corrective';

    protected $fillable = [
        'misconception_id', 'sub_institute_id', 'scope', 'content_master_id',
        'content_id_ref', 'title', 'body', 'media_url', 'format', 'h5p_type',
        'language', 'language_variants_available', 'estimated_duration_minutes',
        'priority_level', 'quality_status', 'tagged_by', 'reviewed_by', 'reviewed_at',
        'served_count', 'resolution_rate',
    ];

    protected $casts = [
        'language_variants_available' => 'array',
        'estimated_duration_minutes' => 'integer',
        'priority_level' => 'integer',
        'served_count' => 'integer',
        'resolution_rate' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function misconception()
    {
        return $this->belongsTo(MisconceptionLibrary::class, 'misconception_id');
    }

    public function scopeServable($query)
    {
        return $query->whereIn('quality_status', config('pal_content.servable_statuses', ['approved']));
    }

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }
}
