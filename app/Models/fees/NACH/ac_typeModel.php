<?php

namespace App\Models\fees\NACH;

use Illuminate\Database\Eloquent\Model;

class ac_typeModel extends Model
{
    protected $table = "NACH_ac_type";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'type_id',
        'type_name',
        'sub_institute_id',
        'created_on'
    ];
}
