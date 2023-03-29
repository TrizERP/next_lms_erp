<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class divisionModel extends Model
{
    protected $table = "division";
    protected $fillable = [
        'id',
        'name',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];
}
