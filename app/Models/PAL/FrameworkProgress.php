<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class FrameworkProgress extends Model
{
    protected $table = 'pal_framework_progress';

    protected $fillable = [
        'learner_id',
        'framework_type',
        'framework_tag',
        'progress_score',
        'evidence_count',
        'last_evidenced_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'progress_score' => 'float',
        'evidence_count' => 'integer',
        'last_evidenced_at' => 'datetime',
        'metadata' => 'array',
    ];
}
