<?php

namespace App\Models\result\create_exam;

use Illuminate\Database\Eloquent\Model;

class exam_creation extends Model {

    protected $table = "result_create_exam";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'term_id',
        'medium',
        'exam_id',
        'standard_id',
        'app_disp_status',
        'subject_id',
        'title',
        'points',
        'con_point',
        'marks_type',
        'report_card_status',
        'sort_order',
        'exam_date',
        'created_at',
        'updated_at'
    ];

}
