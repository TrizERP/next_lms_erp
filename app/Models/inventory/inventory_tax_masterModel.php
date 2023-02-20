<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_tax_masterModel extends Model
{
    protected $table = "inventory_tax_master";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'title',
        'amount_percentage',
        'description_1',
        'status',
        'sort_order',
        'created_by',
        'created_on',
        'created_ip_address'
    ];

    public $timestamps = false;
}
