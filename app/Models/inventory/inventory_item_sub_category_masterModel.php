<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_sub_category_masterModel extends Model
{
    protected $table = "inventory_item_sub_category_master"; 
    protected $fillable = [
        'syear',
        'category_id',
        'title',
        'description',
        'status',
        'sub_institute_id'
    ];
    
    public function category(){
        return $this->belongsTo('App\Models\inventory\inventory_item_category_masterModel');
    }
}
