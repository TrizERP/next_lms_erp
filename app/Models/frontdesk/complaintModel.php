<?php

namespace App\Models\frontdesk;

use Illuminate\Database\Eloquent\Model;

class complaintModel extends Model {
	protected $table = "complaint";

	 protected $fillable = [
         'ID',
         'SUB_INSTITUTE_ID',
         'DATE',
         'TITLE',
         'DESCRIPTION',
         'ATTACHEMENT',
         'FILE_SIZE',
         'FILE_TYPE',
         'COMPLAINT_BY',
         'COMPLAINT_SOLUTION',
         'COMPLAINT_SOLUTION_BY',
         'COMPLAINT_SOLUTION_USER_GROUP_ID',
         'CREATED_DATE',
         'CREATED_IP',
         'UPDATED_ON',
         'SYEAR',
         'MARKING_PERIOD_ID'
    ];

    public $timestamps = false;

    // public function complaint(){
    //     return $this->belongsTo('App\Models\frontdesk\complaintModel');
    // }

    // public function frontdesk(){
    //     return $this->belongsTo('App\Models\frontdesk\frontdeskModel');
    // }

    // public function PettycashMaster(){
    //   return $this->belongsTo('App\Models\frontdesk\pettycashMasterModel');
    // }

    // public function Pettycash(){
    //     return $this->belongsTo('App\Models\frontdesk\PettycashModel');
    // }

	  //public $timestamps = false;
}
