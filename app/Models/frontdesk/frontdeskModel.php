<?php

namespace App\Models\frontdesk;

use Illuminate\Database\Eloquent\Model;

class frontdeskModel extends Model {
	protected $table = "front_desk";
	//  protected $table = "task";

    protected $fillable = [
        'VISITOR_TYPE',
        'DATE',
        'IN_TIME',
        'OUT_TIME',
        'OUT_DATE',
        'TITLE',
        'DESCRIPTION',
        'STUDENT_ID',
        'VISITOR_PHOTO',
        'FILE_SIZE',
        'FILE_TYPE',
        'TO_WHOM_MEET',
        'SYEAR',
        'MARKING_PERID_ID',
        'sub_institute_id'
    ];
    
    public function complaint(){
        return $this->belongsTo('App\Models\frontdesk\complaintModel');
    }
    
    public function frontdesk(){
        return $this->belongsTo('App\Models\frontdesk\frontdeskModel');
    }
      public function PettycashMaster(){
        return $this->belongsTo('App\Models\frontdesk\pettycashMasterModel');

          public function Pettycash(){
        return $this->belongsTo('App\Models\frontdesk\PettycashModel');
    }

    }


	//public $timestamps = false;
}


	
