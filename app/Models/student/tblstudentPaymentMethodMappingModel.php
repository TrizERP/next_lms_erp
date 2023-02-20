<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentPaymentMethodMappingModel extends Model
{
    protected $table = 'tblstudent_payment_method_mapping';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'student_id',
        'sub_institute_id',
        'month_id',
        'payment_method',
        'payment_date',
        'remarks',
        'created_by',
        'created_on'
    ];
}
