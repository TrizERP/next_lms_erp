<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Mobility\MobilitySuccessionPlan`.
 *
 * The source model's `criticalJobrole()` relation targeted
 * `App\Models\libraries\userJobroleModel` (the `s_user_jobrole` table),
 * which does not exist in this project's `App\Models` tree. It now points at
 * the small stand-in `MobilityJobRole` added alongside this port (see that
 * class's docblock) so `MobilitySuccessionController@index`'s
 * `->with('criticalJobrole')` eager-load keeps working unchanged.
 */
class MobilitySuccessionPlan extends Model
{
    use SoftDeletes;

    protected $table = 's_mobility_succession_plans';

    protected $guarded = [];

    protected $casts = [
        'emergency_successor' => 'boolean',
    ];

    public function successor()
    {
        return $this->belongsTo(\App\Models\user\tbluserModel::class, 'successor_user_id');
    }

    public function criticalJobrole()
    {
        return $this->belongsTo(MobilityJobRole::class, 'critical_jobrole_id');
    }
}
