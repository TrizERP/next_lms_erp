<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;

class tblcustomfieldsModel extends Model
{
    protected $table = "tblcustom_fields";

    protected $fillable = [
        'id',
        'table_name',
        'table_alias',
        'tab_sort_order',
        'field_name',
        'column_header',
        'field_label',
        'user_type',
        'status',
        'sort_order',
        'field_type',
        'field_message',
        'file_size_max',
        'required',
        'is_deleted',
        'common_to_all',
        'sub_institute_id'
    ];

    public $timestamps = false;
}
