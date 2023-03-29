<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class general_dataModel extends Model
{
    protected $table = "general_data";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'fieldname',
        'fieldvalue',
        'sub_institute_id',
        'client_id',
        'created_on'
    ];
}
