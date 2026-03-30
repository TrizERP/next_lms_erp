<?php

namespace App\Models\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class H5pScenario extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_scenarios';

    protected $guarded = [];

    public function points()
    {
        return $this->hasMany(H5pScenarioPoint::class, 'scenario_id');
    }
}
