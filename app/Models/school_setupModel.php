<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class school_setupModel extends Model
{
        protected $table = "school_setup";
    protected $fillable = [
        'Id',
        'SchoolName',
        'ShortCode',
        'ContactPerson',
        'Mobile',
        'Email',
        'ReceiptHeader',
        'ReceiptAddress',
        'FeeEmail',
        'ReceiptContact',
        'SortOrder',
        'Logo',
        'created_at',
        'created_by',
        'created_ip',
        'updated_at',
        'client_id',
        'is_lms',
        'cheque_return_charges',
        'syear',
        'expire_date',
        'given_space_mb',
        'institute_type'
    ];
    public $timestamps = false;
}
