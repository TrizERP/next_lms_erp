<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_masterModel extends Model
{
    protected $table = "inventory_item_master"; 
    protected $fillable = [
        'syear',
        'category_id',
        'sub_category_id',
        'item_type_id',
        'title',
        'description',
        'opening_stock',
        'minimum_stock',
        'item_attachment',
        'item_status',
        'sub_institute_id'
    ];
    
}
