<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class admission_category_masterModel extends Model
{
    protected $table = "admission_category_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'title',
        'description',
        'created_at',
        'updated_at'
    ];
}
