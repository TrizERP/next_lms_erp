<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\user\tbluserModel;
use App\Models\settings\organizationSisterDetails;

class organizationDetails extends Model
{
    use HasFactory;
    protected $table = "org_details";
    protected $guarded = [];

    public function sistersOrg()
    {
        // Correct relationship definition
        return $this->hasMany(organizationSisterDetails::class, 'org_id', 'id');
    }

    public function createdUser()
    {
        return $this->belongsTo(tbluserModel::class, 'created_by', 'id');
    }
}
