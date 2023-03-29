<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_receivableModel extends Model
{
    protected $table = "inventory_item_receivable_details";
    protected $fillable = [
        'ID',
        'SYEAR',
        'SUB_INSTITUTE_ID',
        'PURCHASE_ORDER_NO',
        'ITEM_ID',
        'ITEM_CODE',
        'ITEM_CATEGORY',
        'ORDER_QTY',
        'PREVIOUS_RECEIVED_QTY',
        'ACTUAL_RECEIVED_QTY',
        'PENDING_QTY',
        'REMARKS',
        'WARRANTY_START_DATE',
        'WARRANTY_END_DATE',
        'BILL_NO',
        'BILL_DATE',
        'CHALLAN_NO',
        'CHALLAN_DATE',
        'RECEIVED_BY',
        'RECEIVED_DATE',
        'CREATED_BY',
        'CREATED_ON',
        'CREATED_IP_ADDRESS',
        'GATEPASS_NO',
        'CHEQUE_NO',
        'BANK_NAME'
    ];
    public $timestamps = false;
}
