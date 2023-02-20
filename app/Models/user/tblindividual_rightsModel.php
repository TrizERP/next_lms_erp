<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tblindividual_rightsModel extends Model
{
    protected $table = "tblindividual_rights";

    protected $fillable = [
        'id',
        'user_id',
        'menu_id',
        'profile_id',
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
        'created_at',
        'sub_institute_id',
        'client_id'
    ];

    public $timestamps = false;
}
