<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceActivityLog`.
 *
 * The Performance module's own event feed - Activity Feed + Audit Trail tabs.
 *
 * Deliberately separate from s_competency_activity_log: that table's "Module"
 * label is derived from subject_type by the Competency Audit Center, so
 * performance rows would render there as unknown modules.
 */
class PerformanceActivityLog extends Model
{
    use HasFactory;

    protected $table = 's_performance_activity_log';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'user_id'          => 'integer',
        'subject_id'       => 'integer',
        'review_id'        => 'integer',
        'cycle_id'         => 'integer',
        // [{field, label, old, new}, ...] - the Audit Trail's change summary.
        'changes'          => 'array',
    ];
}
