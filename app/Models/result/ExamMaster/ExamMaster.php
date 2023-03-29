<?php

namespace App\Models\result\ExamMaster;

use Illuminate\Database\Eloquent\Model;

class ExamMaster extends Model {

    //
    protected $table = "result_exam_master";
    protected $fillable = [
        'Id',
        'Code',
        'ExamType',
        'ExamTitle',
        'SortOrder',
        'SubInstituteId',
        'created_at',
        'updated_at'
    ];

}
