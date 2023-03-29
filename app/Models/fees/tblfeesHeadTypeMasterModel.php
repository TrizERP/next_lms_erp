<?php

namespace App\Models\fees;

use Illuminate\Database\Eloquent\Model;

class tblfeesHeadTypeMasterModel extends Model
{
    protected $table = 'fees_head_master';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'code',
        'head_title',
        'description',
        'mandatory',
        'syear',
        'sub_institute_id',
        'created_by',
        'created_on'
    ];
}
