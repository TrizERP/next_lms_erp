<?php

namespace App\Models\admission;

use Illuminate\Database\Eloquent\Model;

class admissionEnquiryModel extends Model
{
    protected $table = 'admission_enquiry';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'enquiry_no',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'mobile',
        'email',
        'address',
        'date_of_birth',
        'age',
        'syear',
        'previous_school_name',
        'previous_standard',
        'admission_standard',
        'remarks',
        'followup_date',
        'source_of_enquiry',
        'category',
        'send_sms',
        'sms_message',
        'created_on',
        'created_by',
        'sub_institute_id',
        'previous_division',
        'mobile2',
        'test',
        'father_name',
        'mother_tongue',
        'religion',
        'nationality',
        'whether_belongs_to',
        'percentage',
        'mother_name',
        'father_qualification',
        'father_occupation',
        'mother_qualification',
        'mother_occupation',
        'guardian_name',
        'counciler_name',
        'place_of_birth',
        'mobile_number_father',
        'mobile_number_mother',
        'guardian_relation',
        'annual_income',
        'house_no',
        'street_name',
        'building_name',
        'district_name',
        'pin_code',
        'state',
        'aadharcard_number',
        'building_name_appratment_name_society_name',
        'admission_fees',
        'receipt_id',
        'receipt_html',
        'fees_amount',
        'fees_remark',
        'fees_circular_html',
        'fees_circular_form_no',
        'interaction_date',
        'interaction_remarks'
    ];
}
