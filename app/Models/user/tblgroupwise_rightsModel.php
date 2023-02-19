<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tblgroupwise_rightsModel extends Model
{
    protected $table = 'tblgroupwise_rights';

    protected $fillable = [
        'id',
        'menu_id',
        'profile_id',
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
        'created_at',
        'sub_institute_id'
    ];

    public $timestamps = false;
}
