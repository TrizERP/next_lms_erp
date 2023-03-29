<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class requisitionModel extends Model
{
    protected $table = "inventory_requisition_details";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'marking_period_id',
        'requisition_no',
        'requisition_by',
        'requisition_date',
        'item_id',
        'item_qty',
        'item_unit',
        'approved_qty',
        'item_qty_in_stock',
        'expected_delivery_time',
        'requisition_status',
        'remarks',
        'requisition_approved_by',
        'requisition_approved_remarks',
        'requisition_approved_date',
        'department_id',
        'user_group_id',
        'created_by',
        'created_ip_address',
        'created_at',
        'updated_at'
    ];
}
