<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_negotiate_poModel extends Model
{
    protected $table = "inventory_negotiate_po_details";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'po_number',
        'item_id',
        'vendor_id',
        'price',
        'qty',
        'amount',
        'dis_per',
        'dis_amount_value',
        'after_dis_amount',
        'tax_per',
        'tax_amount_value',
        'after_tax_amount',
        'amount_per_item',
        'transportation_charge',
        'installation_charge',
        'payment_terms',
        'remarks',
        'delivery_time',
        'po_approval_status',
        'po_approved_by',
        'po_approval_remark',
        'po_approved_date',
        'created_by',
        'created_on',
        'created_ip_address',
        'po_additional_charges_ids',
        'po_place_of_delivery'
    ];

    public $timestamps = false;
}
