<?php

namespace App\Models\fees\other_fees_collect;

use Illuminate\Database\Eloquent\Model;

class other_fees_collect extends Model
{

    protected $table = "fees_other_collection";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'receipt_id',
        'syear',
        'sub_institute_id',
        'student_id',
        'deduction_date',
        'deduction_head_id',
        'deduction_remarks',
        'deduction_amount',
        'payment_mode',
        'bank_name',
        'bank_branch',
        'cheque_dd_no',
        'cheque_dd_date',
        'paid_fees_html',
        'is_deleted',
        'created_by',
        'created_on',
        'updated_on',
        'created_ip'
    ];

}
