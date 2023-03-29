<?php

namespace App\Models\fees;

use Illuminate\Database\Eloquent\Model;

class tblfeesConfigModel extends Model
{
    protected $table = "fees_config_master";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'late_fees_amount',
        'send_sms',
        'send_email',
        'fees_receipt_template',
        'fees_bank_challan_template',
        'fees_receipt_note',
        'institute_name',
        'pan_no',
        'account_to_be_credited',
        'cms_client_code',
        'auto_head_counting',
        'nach_account_type',
        'nach_registration_charge',
        'nach_transaction_charge',
        'nach_failed_charge',
        'bank_logo',
        'syear',
        'sub_institute_id',
        'created_by',
        'created_on'
    ];
}
