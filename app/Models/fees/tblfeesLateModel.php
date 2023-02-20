<?php

namespace App\Models\fees;

use Illuminate\Database\Eloquent\Model;

class tblfeesLateModel extends Model
{
    protected $table = "fees_late_master";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'late_date',
        'standard_id',
        'syear',
        'term_id',
        'sub_institute_id',
        'created_by',
        'created_on'
    ];
}
