<?php

namespace App\Models\fees\fees_circular;

use Illuminate\Database\Eloquent\Model;

class feesCircularModel extends Model {
	protected $table = 'fees_circular_log';

	public $timestamps = false;

    protected $fillable = [
        'ID',
        'STUDENT_ID',
        'MONTH',
        'RECEIPT_BOOK_ID',
        'SUB_INSTITUTE_ID',
        'SYEAR',
        'AMOUNT',
        'FEES_CIRCULAR_HTML',
        'CREATED_BY',
        'CREATED_ON'
    ];
}
