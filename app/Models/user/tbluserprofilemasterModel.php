<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tbluserprofilemasterModel extends Model
{
    public $timestamps = false;

    protected  $table = "tbluserprofilemaster";

    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'status',
        'sort_order',
        'sub_institute_id'
    ];


}
