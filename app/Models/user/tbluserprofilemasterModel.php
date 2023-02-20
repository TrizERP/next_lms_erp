<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tbluserprofilemasterModel extends Model
{
    public $timestamps = false;

    protected  $table = "tbluserprofilemaster";

    protected $fillable = [
        'id',
        'parent_id',
        'name',
        'description',
        'sort_order',
        'status',
        'sub_institute_id',
        'client_id'
    ];


}
