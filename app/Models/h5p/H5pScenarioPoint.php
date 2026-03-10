<?php

namespace App\Models\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class H5pScenarioPoint extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_scenario_points';

    protected $guarded = [];
}
