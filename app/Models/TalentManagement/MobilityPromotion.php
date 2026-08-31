<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Mobility\MobilityPromotion`.
 */
class MobilityPromotion extends Model
{
    use SoftDeletes;

    protected $table = 's_mobility_promotions';

    protected $guarded = [];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\user\tbluserModel::class, 'user_id');
    }
}
