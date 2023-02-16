<?php

namespace App\Models\frontdesk;

use Illuminate\Database\Eloquent\Model;

class taskModel extends Model
{
    protected $table = "task";

    protected $fillable = [
        'TASK_TITLE',
        'TASK_DESCRIPTION',
        'TASK_ATTACHMENT',
        'FILE_SIZE',
        'FILE_TYPE',
        'TASK_DATE',
        'STATUS',
        'TASK_ALLOCATED',
        'TASK_ALLOCATED_TO',
        'ADRESS',
        'SYEAR',
        'MARKING_PERID_ID',
        'sub_institute_id'
    ];
    
    public $timestamps = false;

    public function complaint(){
        return $this->belongsTo('App\Models\frontdesk\complaintModel');
    }
    
    public function frontdesk(){
        return $this->belongsTo('App\Models\frontdesk\frontdeskModel');
    }
    
    public function PettycashMaster(){
        return $this->belongsTo('App\Models\frontdesk\pettycashMasterModel');
    }

    public function Pettycash(){
        return $this->belongsTo('App\Models\frontdesk\PettycashModel');
    }

}
