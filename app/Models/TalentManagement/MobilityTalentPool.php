<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Mobility\MobilityTalentPool`.
 */
class MobilityTalentPool extends Model
{
    use SoftDeletes;

    protected $table = 's_mobility_talent_pools';

    protected $guarded = [];

    public function members()
    {
        return $this->hasMany(MobilityTalentPoolMember::class, 'pool_id');
    }
}
