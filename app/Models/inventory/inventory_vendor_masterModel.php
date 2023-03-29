<?php

namespace App\Models\inventory;

use Illuminate\Database\Eloquent\Model;

class inventory_vendor_masterModel extends Model
{
    protected $table = "inventory_vendor_master";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'vendor_name',
        'contact_number',
        'short_name',
        'sort_order',
        'address',
        'email',
        'file_number',
        'file_location',
        'company_name',
        'business_type',
        'office_address',
        'office_contact_person',
        'office_number',
        'office_email',
        'tin_no',
        'tin_date',
        'registration_no',
        'registration_date',
        'serivce_tax_no',
        'serivce_tax_date',
        'pan_no',
        'bank_account_no',
        'bank_name',
        'bank_branch',
        'bank_ifsc_code',
        'created_by',
        'created_on',
        'created_ip_address'
    ];

    public $timestamps = false;
}
