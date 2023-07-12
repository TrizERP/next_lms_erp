<?php

namespace App\Models\student;

use App\Models\LibraryBookCirculation;
use Illuminate\Database\Eloquent\Model;

class tblstudentModel extends Model
{
    protected $table = "tblstudent";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'enrollment_no',
        'roll_no',
        'admission_year',
        'first_name',
        'middle_name',
        'last_name',
        'father_name',
        'mother_name',
        'gender',
        'dob',
        'mobile',
        'mother_mobile',
        'student_mobile',
        'email',
        'username',
        'password',
        'user_profile_id',
        'admission_id',
        'admission_date',
        'city',
        'state',
        'address',
        'pincode',
        'otp',
        'image',
        'file_size',
        'file_type',
        'sub_institute_id',
        'status',
        'created_on',
        'aadhar_document_upload',
        'birth_certificate',
        'student_inactive',
        'enrollment_status',
        'inactive_date',
        'place_of_birth',
        'house',
        'conduct',
        'since_when',
        'is_physically_handicaped',
        'economy_backward',
        'reserve_categorey',
        'student_achievement',
        'religion',
        'cast',
        'subcast',
        'bloodgroup',
        'adharnumber',
        'anuualincome',
        'uniqueid',
        'studentbatch',
        'optionalsubject',
        'height',
        'weight',
        'school_code',
        'parent_bank_details',
        'subject_offered',
        'percentage_scored_in_last_class',
        'disability_if_any',
        'academic_achievement_s',
        'exceptional_achievement',
        'language_learned',
        'mother_tongue',
        'nationality',
        'present_grade',
        'first_language',
        'second_language',
        'third_language',
        'emergency_contact_no_religion_to_student',
        'form_no',
        'dise_uid',
        'previous_school_name',
        'affiliation_no',
        'transportation',
        'type_of_school',
        'medium_of_interaction',
        'siblings_in_school',
        'number_of_children',
        'caste',
        'bank_account_number',
        'account_holder',
        'ifsc_code',
        'micr_code',
        'admission_token_no',
        'udise_no',
        'admission_docket_no',
        'registration_no',
        'admission_under',
        'distance_from_school',
        'updated_on',
        'father_dob',
        'expire_date',
        'marking_period_id'
    ];

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function issuedBook()
    {
        return $this->hasMany(LibraryBookCirculation::class, 'student_id', 'id');
    }
}
