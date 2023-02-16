<?php

namespace App\Models\result\ExamMaster;

use Illuminate\Database\Eloquent\Model;

class ExamMaster extends Model {

    //
    protected $table = "result_exam_master";
    protected $fillable = [
        'Code',
        'ExamType',
        'ExamTitle',
        'SortOrder',
        'SubInstituteId'
    ];

}
