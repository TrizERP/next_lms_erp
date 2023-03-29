<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_quotationModel extends Model
{
    protected $table = "inventory_item_quotation_details";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'item_id',
        'vendor_id',
        'transportation_charge',
        'installation_charge',
        'qty',
        'price',
        'total',
        'unit',
        'tax',
        'remarks',
        'approved_status',
        'approved_by',
        'approved_date',
        'approved_remarks',
        'created_by',
        'created_on',
        'created_ip_address'
    ];

    public $timestamps = false;
}
