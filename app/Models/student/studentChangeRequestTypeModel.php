<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class studentChangeRequestTypeModel extends Model {
	protected $table = "STUDENT_CHANGE_REQ_TYPE";

	public $timestamps = false;

    protected $fillable = [
        'ID',
        'SYEAR',
        'SUB_INSTITUTE_ID',
        'REQUEST_TITLE',
        'PROOF_DOCUMENT_REQUIED',
        'PROOF_DOCUMENT_NAME',
        'CREATED_BY',
        'CREATED_ON',
        'AMOUNT'
    ];
}
