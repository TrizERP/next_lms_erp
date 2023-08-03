<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class onlineAdmissionConfirmController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = session()->all();
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_name = $request->session()->get('user_profile_name');
        $syear = $request->session()->get('syear');

        if ($user_profile_name == 'Principal') {
            $result = DB::table('new_admission_inquiry_registration')
                ->selectRaw('new_admission_inquiry_registration.*,new_admission_inquiry_registration.id AS CHECKBOX')
                ->where('admin_status', 'Verified')
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
        } else {
            $result = DB::table('new_admission_inquiry_registration')
                ->selectRaw('new_admission_inquiry_registration.*,new_admission_inquiry_registration.id AS CHECKBOX')
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
        }

        $res['status_code'] = 1;
        $res['student_data'] = $result;
        $res['message'] = "Success";

        return is_mobile($type, "admission/show_online_admission_confirm", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $token_no = $request->input('token_no');
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $extra_query = '';


        $result = DB::table('new_admission_inquiry_registration')
            ->select('new_admission_inquiry_registration.*', 'new_admission_inquiry_registration.id as CHECKBOX')
            ->where('sub_institute_id', '=', $sub_institute_id)
            ->when($token_no != '', function ($q) use ($token_no) {
                $q->where('token', $token_no);
            })
            ->get()->toArray();


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $result;
        $res['token_no'] = $token_no;

        return is_mobile($type, "admission/show_online_admission_confirm", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        // dd($request);
        $students = $request->get('students');
        $admin_status = $request->get('admin_status');
        $principal_status = $request->get('principal_status');
        $account_status = $request->get('account_status');
        $type = $request->get('type');
        $token_no = $request->get('token_no');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $created_by = $request->session()->get('user_id');
        $created_on = date('Y-m-d');
        $created_ip = $_SERVER['REMOTE_ADDR'];
        $marking_period_id = session()->get('term_id');

        foreach ($students as $key => $student_id) {
            $res = array();
            if (isset($admin_status[$student_id]) && $admin_status[$student_id] != '') {
                $result_admin = DB::table('new_admission_inquiry_registration')->where('id', '=', $student_id)
                    ->where('sub_institute_id', '=', $sub_institute_id)
                    ->update(['admin_status' => $admin_status[$student_id]]);

                $res['status_code'] = "1";
                $res['message'] = "Admission Verified By Admin Successfully";
            }

            if (isset($principal_status[$student_id]) && $principal_status[$student_id] != '') {
                $result_principal = DB::table('new_admission_inquiry_registration')->where('id', '=', $student_id)
                    ->where('sub_institute_id', '=', $sub_institute_id)
                    ->update(['principal_status' => $principal_status[$student_id]]);

                $res['status_code'] = "1";
                $res['message'] = "Admission Approved by Principal Successfully";
            }

            if (isset($account_status[$student_id]) && $account_status[$student_id] != '') {
                $result_account = DB::table('new_admission_inquiry_registration')
                    ->where('id', '=', $student_id)
                    ->where('sub_institute_id', '=', $sub_institute_id)
                    ->update(['account_status' => $account_status[$student_id]]);

                if ($account_status[$student_id] == 'Confirm' && $result_account == 1) {

                    $students_check = tblstudentModel::select('admission_id')
                        ->where([
                            'sub_institute_id'   => $sub_institute_id, 'admission_id' => $student_id,
                            'admission_token_no' => $token_no,
                        ])
                        ->get()
                        ->toArray();


                    $enrollment_result = DB::table('tblstudent')
                        ->select('*', DB::raw('MAX(enrollment_no) as new_enrollment_no'))
                        ->where('sub_institute_id', '=', $sub_institute_id)
                        ->whereRaw("enrollment_no NOT LIKE '%SS%'")
                        ->get()->toArray();

                    $get_enrollment_no = substr($enrollment_result[0]->new_enrollment_no, 2, 6);
                    $new_enrollment_no = $get_enrollment_no + 1; //$enrollment_result[0]->new_enrollment_no;

                    if (count($students_check) == 0) {

                        $result = DB::table('new_admission_inquiry_registration as n')
                            ->select(['n.syear', 's.id', 'ss.id as standard_id', 's.sub_institute_id', 'ac.id as grade_id', 'dd.id as section_id', 'sq.id as student_quota'])
                            ->join('tblstudent as s', 's.admission_id', '=', 'n.id')
                            ->join('standard as ss', function ($join) {
                                $join->on('ss.name', '=', 'n.admission_std')
                                    ->on('ss.sub_institute_id', '=', 's.sub_institute_id');
                                    // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                                    //     $query->where('ss.marking_period_id');
                                    // });
                            })
                            ->join('academic_section as ac', function ($join) {
                                $join->on('ac.id', '=', 'ss.grade_id')
                                    ->on('ac.sub_institute_id', '=', 's.sub_institute_id');
                            })
                            ->join('division as dd', 'dd.sub_institute_id', '=', 's.sub_institute_id')
                            ->join('student_quota as sq', 'sq.sub_institute_id', '=', 's.sub_institute_id')
                            ->where('n.id', '=', $student_id)
                            ->where('s.admission_id', '=', $student_id)
                            ->limit(1);

                        $insert_enrollment = DB::table('tblstudent_enrollment')
                            ->insertUsing(['syear','student_id','standard_id','sub_institute_id','grade_id','section_id','student_quota',], $result);

                        $inserted_student_id = DB::table('tblstudent')
                            ->insertGetId(['admission_id' => DB::raw('n.id'),'first_name' => DB::raw('n.child_name'),'father_name' => DB::raw('n.father_name'),'mother_name' => DB::raw('n.mother_name'),'gender' => DB::raw('n.gender'),'dob' => DB::raw('n.date_of_birth'),'mobile' => DB::raw('n.mobile'),'mother_mobile' => DB::raw('n.mother_mobile_no'),'email' => DB::raw('n.mail'),'password' => DB::raw("MD5('student')"),'admission_year' => DB::raw('n.syear'),'admission_date' => DB::raw('CURDATE()'),'city' => DB::raw('n.city'),'state' => DB::raw('n.state'),'address' => DB::raw('n.address'),'pincode' => DB::raw('n.pin_code'),'sub_institute_id' => DB::raw("'" . $sub_institute_id . "'"),'status' => 1,'created_on' => DB::raw('Now()'),'aadhar_document_upload' => DB::raw('n.student_adharcard'),'birth_certificate' => DB::raw('n.birth_certificate'),'place_of_birth' => DB::raw('n.birth_place'),'religion' => DB::raw('n.religion'),'cast' => DB::raw('n.cast'),'subcast' => DB::raw('n.sub_cast'),'bloodgroup' => DB::raw('n.blood_group'),'father_dob' => DB::raw('n.father_dob'),'height' => DB::raw('n.height'),'admission_token_no' => DB::raw("CONCAT('PP', '" . $new_enrollment_no . "')"),'enrollment_no' => DB::raw("CONCAT('PP', '" . $new_enrollment_no . "')"),]);

// $inserted_student_id contains the ID of the inserted student record


                        $result = DB::table('tblstudent as s')
                            ->select("s.first_name,s.id AS student_id,n.birth_certificate,n.student_adharcard,
						n.student_cast_certificate,n.father_cast_certificate,n.student_passport_size_photo,n.family_photo,n.vaccination_record,
						n.medical_examination_report,n.father_adharcard,n.mother_adharcard,n.address_proof,
						n.father_signature,n.mother_signature,n.any_other_doc,n.other_doc")
                            ->join('new_admission_inquiry_registration as n', function ($join) {
                                $join->whereRaw('n.token = s.admission_token_no');
                            })
                            ->where('s.id', '=', $inserted_student_id)
                            ->whereNotNull('s.admission_token_no')
                            ->get()->toArray();

                        $result = $result[0];
                        if (isset($result) > 0) {
                            $return_array = array();
                            if ($result->birth_certificate != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Birth Certificate',
                                    $result->birth_certificate,
                                    '3'
                                );
                            }
                            if ($result->student_adharcard != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Student Adharcard',
                                    $result->student_adharcard,
                                    '2'
                                );
                            }
                            if ($result->student_cast_certificate != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Student Cast Certificate',
                                    $result->student_cast_certificate,
                                    '11'
                                );
                            }
                            if ($result->father_cast_certificate != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Father Cast Certificate',
                                    $result->father_cast_certificate,
                                    '30'
                                );
                            }
                            if ($result->student_passport_size_photo != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Student Passport Size Photo',
                                    $result->student_passport_size_photo,
                                    '43'
                                );
                            }
                            if ($result->family_photo != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Family Photo',
                                    $result->family_photo,
                                    '8'
                                );
                            }
                            if ($result->vaccination_record != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Vaccination Record',
                                    $result->vaccination_record,
                                    '7'
                                );
                            }
                            if ($result->medical_examination_report != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Medical Examination Report',
                                    $result->medical_examination_report,
                                    '6'
                                );
                            }
                            if ($result->father_adharcard != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Father Adharcard',
                                    $result->father_adharcard,
                                    '25'
                                );
                            }
                            if ($result->mother_adharcard != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Mother Adharcard',
                                    $result->mother_adharcard,
                                    '26'
                                );
                            }
                            if ($result->address_proof != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Address Proof',
                                    $result->address_proof,
                                    '10'
                                );
                            }
                            if ($result->father_signature != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Father Signature',
                                    $result->father_signature,
                                    '44'
                                );
                            }
                            if ($result->mother_signature != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Mother Signature',
                                    $result->mother_signature,
                                    '45'
                                );
                            }
                            if ($result->any_other_doc != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Any Other Doc',
                                    $result->any_other_doc,
                                    '46'
                                );
                            }
                            if ($result->other_doc != "") {
                                $return_array[$inserted_student_id][] = $this->insert_document(
                                    $inserted_student_id,
                                    'Other Doc',
                                    $result->other_doc,
                                    '47'
                                );
                            }
                        }

                        $res['status_code'] = "1";
                        $res['message'] = "Admission Confirm by Accounted Successfully";

                    } else {
                        $res['status_code'] = "0";
                        $res['message'] = "Admission Already Confirmed";
                    }
                }
            }
        }

        return is_mobile($type, "admission/show_online_admission_confirm", $res, "view");
    }

    public function onlineAdmissionReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

//		$query = "SELECT *,id AS CHECKBOX FROM new_admission_inquiry_registration WHERE syear = '" . $syear . "' AND sub_institute_id = '".$sub_institute_id."' ";

        $result = DB::table('new_admission_inquiry_registration')
            ->selectRaw('*, id as CHECKBOX')
            ->where('syear', '=', $syear)
            ->where('sub_institute_id', '=', $sub_institute_id)
            ->get()->toArray();

        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);
        // dd($result);
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['report_data'] = $result;

        return is_mobile($type, "admission/show_online_admission_report", $res, "view");
    }

    public function ajax_AdmissionConfirmReport(Request $request)
    {
        $type = $request->get('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $students = $request->get('students');

        foreach ($students as $key => $student_id) {
            DB::table('new_admission_inquiry_registration')
                ->where('id', '=', $student_id)
                ->where('sub_institute_id', '=', $sub_institute_id)
                ->update(['eligible_status' => 'Yes']);
        }
        $res['status_code'] = "1";
        $res['message'] = "Student Eligibility Update Successfully";

        return is_mobile($type, "admission/show_online_admission_report", $res, "view");
    }

    public function insert_document($student_id, $document_title, $file_name, $document_type_id)
    {

        $records_uploaded = $records_not_uploaded = 0;
        $problematic_reocords = "";
        $return_array = array();

        $checkresult = DB::table('tblstudent_document')
            ->where('student_id', '=', $student_id)
            ->where('document_type_id', '=', $document_type_id)
            ->get()->toArray();

        if (count($checkresult) == 0) {
            $result = DB::table('tblstudent_document')
                ->insert([
                    'student_id'       => $student_id,
                    'document_type_id' => $document_type_id,
                    'document_title'   => $document_title,
                    'file_name'        => $file_name,
                    'sub_institute_id' => 47,
                    'created_on'       => now(),
                ]);
            if ($result) {
                $records_uploaded++;
            } else {
                $records_not_uploaded++;
                $problematic_reocords .= "Student ID -  ".$student_id." Document Title -  ".$document_title."File Name -  ".$file_name."<br><br>";
            }
            $return_array['PROBLEMATIC_RECORDS'] = $problematic_reocords;
        }


        return $return_array;
    }
}
