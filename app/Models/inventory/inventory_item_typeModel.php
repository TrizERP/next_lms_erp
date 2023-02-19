<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_item_typeModel extends Model
{
    protected $table = "inventory_item_type";

    protected $fillable = [
        'id',
        'sub_institute_id',
        'title',
        'created_at',
        'updated_at'
    ];
}
