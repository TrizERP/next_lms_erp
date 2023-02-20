<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_direct_purchaseModel extends Model
{
    protected $table = "inventory_item_direct_purchase";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'syear',
        'vendor_id',
        'category_id',
        'sub_category_id',
        'item_id',
        'item_qty',
        'price',
        'amount',
        'challan_no',
        'challan_date',
        'bill_no',
        'bill_date',
        'remarks',
        'created_by',
        'created_on',
        'created_ip'
    ];
    public $timestamps = false;
}
