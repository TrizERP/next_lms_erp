<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_master_setupModel extends Model
{
    protected $table = "inventory_master_setup"; 
    protected $fillable = [
        'SYEAR',
        'GST_REGISTRATION_NO',
        'GST_REGISTRATION_DATE',
        'CST_REGISTRATION_NO',
        'CST_REGISTRATION_DATE',
        'LOGO',
        'PO_NO_PREFIX',
        'ITEM_SETTING_FOR_REQUISITION',
        'SUB_INSTITUTE_ID'
    ];
}
