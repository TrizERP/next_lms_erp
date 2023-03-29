<?php

namespace App\Models\fees\fees_cancel;

use Illuminate\Database\Eloquent\Model;

class feesRefundModel extends Model
{
    protected $table = "fees_refund";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'syear',
        'sub_institute_id',
        'receipt_no',
        'fees_html',
        'created_by',
        'created_ip_address',
        'payment_mode',
        'bank_branch',
        'receipt_date',
        'cheque_no',
        'bank_name',
        'cheque_date',
        'cheque_bank_name',
        'remarks',
        'created_date',
        'amount',
        'tution_fee',
        'admission_fee',
        'activity_fee',
        'term_fee',
        'deposit',
        'co_curriculam_fees',
        'computer_fees',
        'smart_class',
        'security_charges',
        'photograph',
        'cal_misc',
        'title_1',
        'title_2',
        'title_3',
        'title_4',
        'title_5',
        'title_6',
        'title_7',
        'title_8',
        'title_9',
        'title_10',
        'title_11',
        'title_12'
    ];
}
