<?php

namespace App\Models\result\ExamTypeMaster;

use Illuminate\Database\Eloquent\Model;

class ExamTypeMater extends Model {

    //

    protected $table = "result_exam_type_master";
    protected $fillable = [
        'Code',
        'ExamType',
        'ShortName',
        'SortOrder',
        'SubInstituteId',
    ];

}
