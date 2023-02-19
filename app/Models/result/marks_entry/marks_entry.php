<?php

namespace App\Models\result\marks_entry;

use Illuminate\Database\Eloquent\Model;

class marks_entry extends Model {

    protected $table = "result_marks";
    protected $fillable = [
        'id',
        'student_id',
        'exam_id',
        'points',
        'grade',
        'per',
        'comment',
        'is_absent',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
