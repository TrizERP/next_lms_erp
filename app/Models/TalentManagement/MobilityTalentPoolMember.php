<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;

/**
 * Ported from G2G's `App\Models\Mobility\MobilityTalentPoolMember`.
 * No soft deletes, matching the source model and its migration (the
 * `s_mobility_talent_pool_members` table has no `deleted_at` column).
 */
class MobilityTalentPoolMember extends Model
{
    protected $table = 's_mobility_talent_pool_members';

    protected $guarded = [];

    protected $casts = [
        'added_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\user\tbluserModel::class, 'user_id');
    }

    public function pool()
    {
        return $this->belongsTo(MobilityTalentPool::class, 'pool_id');
    }
}
