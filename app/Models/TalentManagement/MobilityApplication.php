<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Mobility\MobilityApplication`.
 */
class MobilityApplication extends Model
{
    use SoftDeletes;

    protected $table = 's_mobility_applications';

    protected $guarded = [];

    protected $casts = [
        'applied_on' => 'date',
    ];

    public function job()
    {
        return $this->belongsTo(MobilityJob::class, 'job_posting_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\user\tbluserModel::class, 'user_id');
    }
}
