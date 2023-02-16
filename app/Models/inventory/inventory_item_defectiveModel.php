<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_defectiveModel extends Model
{
     protected $table = "inventory_item_defective_details"; 
     protected $fillable = [
        'SYEAR',
        'SUB_INSTITUTE_ID',
        'ITEM_ID',
        'WARRANTY_START_DATE',
        'WARRANTY_END_DATE',
        'DEFECT_REMARKS',
        'ITEM_GIVEN_TO',
        'ESTIMATED_RECEIVED_DATE',
        'CREATED_BY',
        'CREATED_ON',
        'CREATED_IP_ADDRESS'
    ];
    
    public $timestamps = false;
}
