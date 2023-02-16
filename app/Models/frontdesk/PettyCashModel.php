<?php

namespace App\Models\frontdesk;

use Illuminate\Database\Eloquent\Model;

class PettyCashModel extends Model
{
    protected $table = "petty_cash";

     protected $fillable = [
        'title_id',
        'description',
        'amount',
        'user_id',
        'bill_image',
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
    }
         // public function Pettycash(){
        //return $this->belongsTo('App\Models\frontdesk\PettycashModel');
   // }

    



	//public $timestamps = false;
}
