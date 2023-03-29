<?php

namespace App\Models\admission;

use Illuminate\Database\Eloquent\Model;

class admissionRegistrationModel extends Model {
	protected $table = 'admission_registration';

	public $timestamps = false;

    protected $fillable = [
        'enquiry_id',
        'enquiry_no',
        'place_of_birth',
        'enrollment_no',
        'amount',
        'payment_mode',
        'bank_name',
        'bank_branch',
        'cheque_no',
        'cheque_date',
        'blood_group',
        'aadhar_number',
        'register_number',
        'mother_name',
        'mother_mobile_number',
        'admission_date',
        'admission_division',
        'remarks',
        'followup_date',
        'status',
        'student_quota',
        'admission_status',
        'date_of_payment',
        'sub_institute_id',
        'created_by',
        'created_on',
        'pri_div',
        'cast',
        'religion',
        'house_name'
    ];
}
