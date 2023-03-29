<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_allocationModel extends Model
{
    protected $table = "inventory_allocation_details";
    protected $fillable = [
        'ID',
        'SYEAR',
        'SUB_INSTITUTE_ID',
        'REQUISITION_DETAILS_ID',
        'REQUISITION_ID',
        'LOCATION_OF_MATERIAL',
        'PERSON_RESPONSIBLE',
        'ITEM_ID',
        'CREATED_BY',
        'CREATED_ON',
        'CREATED_IP_ADDRESS'
    ];
    public $timestamps = false;
}
