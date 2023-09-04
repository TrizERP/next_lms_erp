<?php

namespace App\Models\result\co_scholastic;

use Illuminate\Database\Eloquent\Model;

class co_scholastic extends Model {

    protected $table = "result_co_scholastic";
    protected $fillable = [
        'id',
        'term_id',
        'title',
        'sort_order',
        'parent_id',
        'mark_type',
        'max_mark',
        'co_grade',
        'sub_institute_id',
        'standard_id',        
        'created_at',
        'updated_at'
    ];

}
