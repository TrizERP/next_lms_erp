<?php

namespace App\Models\result\co_scholastic_marks_entry;

use Illuminate\Database\Eloquent\Model;

class co_scholastic_marks_entry extends Model {

    protected $table = "result_co_scholastic_marks_entries";
    protected $fillable = [
        'id',
        'grade_id',
        'standard_id',
        'term_id',
        'student_id',
        'co_scholastic_id',
        'grade',
        'points',
        'sub_institute_id',
        'syear',
        'created_at',
        'updated_at'
    ];

}
