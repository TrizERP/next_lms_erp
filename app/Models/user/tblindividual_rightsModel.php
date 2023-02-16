<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tblindividual_rightsModel extends Model
{
    protected $table = "tblindividual_rights";

    protected $fillable = [
        'user_id',
        'menu_id',
        'profile_id',        
        'sub_institute_id'
    ];

    public $timestamps = false;
}
