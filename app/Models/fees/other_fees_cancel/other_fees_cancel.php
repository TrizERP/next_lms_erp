<?php

namespace App\Models\fees\other_fees_cancel;

use Illuminate\Database\Eloquent\Model;

class other_fees_cancel extends Model
{

    protected $table = "fees_other_cancel";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'receipt_id',
        'syear',
        'sub_institute_id',
        'fees_other_collection_id',
        'deduction_head_id',
        'student_id',
        'cancellation_date',
        'cancellation_remarks',
        'cancellation_amount',
        'created_by',
        'created_on',
        'created_ip'
    ];
}
