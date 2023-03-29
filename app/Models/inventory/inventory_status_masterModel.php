<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_status_masterModel extends Model
{
    protected $table = "inventory_requisition_status_master"; 
    protected $fillable = [
        'id',
        'title',
        'sort_order'
    ];
}
