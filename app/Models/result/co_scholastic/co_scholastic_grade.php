<?php

namespace App\Models\result\co_scholastic;

use Illuminate\Database\Eloquent\Model;

class co_scholastic_grade extends Model {

    protected $table = "result_co_scholastic_grades";
    protected $fillable = [
        'id',
        'map_id',
        'title',
        'break_off',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
