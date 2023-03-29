<?php

namespace App\Models\admission;

use Illuminate\Database\Eloquent\Model;

class admissionFormModel extends Model {
	protected $table = 'admission_form';

	public $timestamps = false;

    protected $fillable = [
        'enquiry_id',
        'enquiry_no',
        'form_no',
        'status',
        'remarks',
        'stop_for_transport',
        'counciler_name',
        'last_exam_name',
        'last_exam_percentage',
        'followup_date',
        'annual_income',
        'father_education_qualification',
        'father_occupation',
        'mother_education_qualification',
        'mother_occupation',
        'admission_standard',
        'sub_institute_id',
        'previous_division',
        'send_sms',
        'sms_message',
        'admission_form_fee',
        'receipt_id',
        'receipt_html',
        'admission_docket_no',
        'registration_no'
    ];
}
