<?php

namespace App\Models\fees\fees_circular;

use Illuminate\Database\Eloquent\Model;

class feesCircularMasterModel extends Model {
	protected $table = 'fees_circular_master';

	public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'grade_id',
        'standard_id',
        'bank_name',
        'address_line1',
        'address_line2',
        'account_no',
        'paid_collection',
        'shift',
        'form_no',
        'branch',
        'created_on',
        'created_by',
        'created_ip_address',
        'updated_on',
        'updated_by'
    ];
}
