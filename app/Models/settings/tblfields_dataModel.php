<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;

class tblfields_dataModel extends Model
{
    protected $table = "tblfields_data";

    protected $fillable = [
        'id',
        'sub_institute_id',
        'title',
        'html_content',
        'status',
        'created_by',
        'created_at',
        'updated_at'
    ];

    public $timestamps = false;
}
