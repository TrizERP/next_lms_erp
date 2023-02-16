<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;

class tblcustomfieldsModel extends Model
{
    protected $table = "tblcustom_fields";

    protected $fillable = [
        'table_name',
        'field_name',
        'field_label',
        'field_type',
        'field_message',
        'status',
        'sort_order',
        'file_size_max',
        'sub_institute_id',
        'required',
        'common_to_all'
    ];

    public $timestamps = false;
}
