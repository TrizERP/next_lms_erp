<?php

namespace App\Models\result\result_master;

use Illuminate\Database\Eloquent\Model;

class result_master_confrigration extends Model {

    //
    protected $table = "result_master_confrigration";
    protected $fillable = [
	'term_id',
	'sub_institute_id',
	'syear',
	'section_id',
	'standard_id',
	'division_id',
	'result_date',
	'reopen_date',
	'vaction_start_date',
	'vaction_end_date',
	'teacher_sign',
	'principal_sign',
	'director_signatiure',
	'result_remark',
	'optional_subject_display',
	'remove_fail_per',
    ];

}
