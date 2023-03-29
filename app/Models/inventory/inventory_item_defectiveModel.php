<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_defectiveModel extends Model
{
     protected $table = "inventory_item_defective_details";
     protected $fillable = [
         'ID',
         'SYEAR',
         'SUB_INSTITUTE_ID',
         'CATEGORY_ID',
         'SUB_CATEGORY_ID',
         'ITEM_CODE',
         'ITEM_NAME',
         'ITEM_ID',
         'WARRANTY_START_DATE',
         'WARRANTY_END_DATE',
         'DEFECT_REMARKS',
         'ITEM_GIVEN_TO',
         'ESTIMATED_RECEIVED_DATE',
         'ACTUAL_RECEIVED_DATE',
         'REMARKS',
         'CREATED_BY',
         'CREATED_ON',
         'CREATED_IP_ADDRESS'
    ];

    public $timestamps = false;
}
