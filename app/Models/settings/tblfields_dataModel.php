<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;

class tblfields_dataModel extends Model
{
    protected $table = "tblfields_data";

    protected $fillable = [
        'field_id',
        'display_text',
        'display_value',
        'created_on'
    ];

    public $timestamps = false;
}
