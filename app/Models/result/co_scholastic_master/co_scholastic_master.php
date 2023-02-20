<?php

namespace App\Models\result\co_scholastic_master;

use Illuminate\Database\Eloquent\Model;

class co_scholastic_master extends Model {

//    protected $table = "co_scholastic_master";
    protected $table = "result_co_scholastic_parent";
    protected $fillable = [
        'id',
        'title',
        'sort_order',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
