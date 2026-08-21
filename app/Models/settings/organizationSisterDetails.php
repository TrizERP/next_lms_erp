<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\user\tbluserModel;

class organizationSisterDetails extends Model
{
    use HasFactory;
    protected $table = "org_sister_details";
    protected $guarded = [];

    public function createdUser()
    {
        return $this->belongsTo(tbluserModel::class, 'created_by', 'id');
    }
}
