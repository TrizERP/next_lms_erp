<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_lostModel extends Model
{
     protected $table = "inventory_item_lost_details";
    protected $fillable = [
        'ID',
        'SYEAR',
        'SUB_INSTITUTE_ID',
        'ITEM_ID',
        'REQUISITION_BY',
        'LOST_DATE',
        'REMARKS',
        'CREATED_BY',
        'CREATED_ON',
        'CREATED_IP_ADDRESS'
    ];

    public $timestamps = false;
}
