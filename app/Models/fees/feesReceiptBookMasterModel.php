<?php

namespace App\Models\fees;

use Illuminate\Database\Eloquent\Model;

class feesReceiptBookMasterModel extends Model
{
    protected $table = "fees_receipt_book_master";

    protected $fillable = [
        'id',
        'syear',
        'receipt_id',
        'receipt_line_1',
        'receipt_line_2',
        'receipt_line_3',
        'receipt_line_4',
        'receipt_prefix',
        'receipt_postfix',
        'receipt_logo',
        'account_number',
        'sort_order',
        'last_receipt_number',
        'grade_id',
        'standard_id',
        'fees_head_id',
        'status',
        'pan',
        'bank_logo',
        'branch',
        'sub_institute_id',
        'created_on',
        'created_by'
    ];
}
