<?php

namespace App\Models\fees\fees_cancel;

use Illuminate\Database\Eloquent\Model;

class feesCancelModel extends Model
{
   public $timestamps = false;
    protected $table = 'fees_cancel';

    protected $fillable = [
        'id',
        'reciept_id',
        'syear',
        'sub_institute_id',
        'student_id',
        'standard_id',
        'term_id',
        'amountpaid',
        'received_date',
        'cancel_date',
        'cancel_type',
        'cancel_remark',
        'cancelled_by',
        'ip_address'
    ];
}
