<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_category_masterModel extends Model
{
    protected $table = "inventory_item_category_master"; 
    protected $fillable = [
        'syear',
        'title',
        'description',
        'status',
        'sub_institute_id'
    ];
}
