<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;

class tblcustomfieldsModel extends Model
{
    protected $table = "tblcustom_fields";

    protected $fillable = [
        'id',
        'table_name',
        'field_name',
        'field_label',
        'status',
        'sort_order',
        'field_type',
        'field_message',
        'file_size_max',
        'required',
        'common_to_all',
        'sub_institute_id'
    ];

    public $timestamps = false;
}
