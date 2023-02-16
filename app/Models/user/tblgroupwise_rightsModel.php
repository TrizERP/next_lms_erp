<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tblgroupwise_rightsModel extends Model
{
    protected $table = 'tblgroupwise_rights';

    protected $fillable = [
        'menu_id',
        'profile_id',        
        'sub_institute_id'
    ];

    public $timestamps = false;
}
