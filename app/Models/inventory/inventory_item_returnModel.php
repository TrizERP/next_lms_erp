<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_returnModel extends Model
{
    protected $table = "inventory_item_return_details"; 
    protected $fillable = [
        'SYEAR',
        'SUB_INSTITUTE_ID',
        'REQUISITION_DETAILS_ID',
        'ITEM_ID',
        'RETURN_DATE',
        'REMARKS',
        'RETURN_QTY',
        'REQUISITION_BY',
        'CREATED_BY',
        'CREATED_ON',
        'CREATED_IP_ADDRESS'
    ];

    public $timestamps = false;
}
