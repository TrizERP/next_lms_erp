<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use App\Models\admission\admissionRegistrationModel;
use App\Models\school_setup\bloodgroupModel;
use App\Models\school_setup\standardModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use App\Models\student\studentQuotaModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;

class admissionRegistrationController extends Controller
{
    use GetsJwtToken;
    
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = session()->get("syear");
        $marking_period_id = session()->get('term_id');
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }

        $data = DB::table('admission_enquiry as ae')
            ->join('admission_form as af', function ($join) {
                $join->whereRaw('ae.id = af.enquiry_id');
            })->leftJoin('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.admission_id = ae.id AND ts.admission_year = ae.syear AND ts.sub_institute_id = ae.sub_institute_id');
            })->leftJoin('standard as s', function ($join) use($marking_period_id) {
                $join->whereRaw('ts.admission_id = ae.id AND ts.admission_year = ae.syear AND ts.sub_institute_id = ae.sub_institute_id');
                // ->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->selectRaw("ae.*,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name")
            ->where('ae.sub_institute_id', $sub_institute_id)
            ->where('ae.syear', $syear)->groupBy('ae.id')->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, 'admission/registration/show_admission_registration', $res, 'view');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        if ($sub_institute_id == 198) // For Mahaeshvari school
        {
            $data = DB::table('admission_enquiry as ae')
                ->join('admission_form as af', function ($join) {
                    $join->whereRaw('ae.id = af.enquiry_id LEFT JOIN admission_registration ar ON ae.id = ar.enquiry_id');
                })
                ->selectRaw("ae.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,CONCAT_WS(',',ae.house_no,
                    ae.`building_name_appratment_name_society_name`,ae.district_name,ae.pin_code,ae.state) AS address,
			        ae.previous_standard,ae.mother_name,ae.mobile_number_mother ,ae.place_of_birth,ar.enquiry_id as registration_enquiry_id")
                ->where('ae.id', $id)->get()->toArray();
        } else {
            $data = DB::table('admission_enquiry as ae')
                ->join('admission_form as af', function ($join) {
                    $join->whereRaw('ae.id = af.enquiry_id');
                })->leftJoin('admission_registration as ar', function ($join) {
                    $join->whereRaw('ae.id = ar.enquiry_id');
                })
                ->selectRaw("ae.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,ar.enquiry_id as registration_enquiry_id")
                ->where('ae.id', $id)->get()->toArray();
        }

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $editData = $data;
        $checkStudent = tblstudentModel::where(['admission_id' => $id])->get()->toArray();

        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_registration"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }


        if (count($checkStudent) > 0) {
            $res['display_save_student'] = '0';
        } else {
            $res['display_save_student'] = '1';
        }

        $category = studentQuotaModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        if (isset($editData[0]['enrollment_no']) && $editData[0]['enrollment_no'] != '') {
            $res['new_enrollment_no'] = $editData[0]['enrollment_no'];
        } else {
            $res['new_enrollment_no'] = $this->max_enrollment_no($sub_institute_id, $editData[0]['admission_standard']);
        }

        $standard = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $bloodgroupData = bloodgroupModel::select()->get();

        $getDiv = DB::table('std_div_map as sdm')
            ->join('standard as s', function ($join) use($marking_period_id) {
                $join->whereRaw('s.id =sdm.standard_id AND s.sub_institute_id = sdm.sub_institute_id');
                // ->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('tblstudent.marking_period_id',$marking_period_id);
                // });
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = sdm.division_id AND d.sub_institute_id = sdm.sub_institute_id');
            })->selectRaw('d.id,d.name,sdm.standard_id')
            ->where('sdm.sub_institute_id', $sub_institute_id)
            ->where('sdm.standard_id', $editData[0]['admission_standard'])->get()->toArray();

        $getDiv = array_map(function ($value) {
            return (array) $value;
        }, $getDiv);

        $res['status_code'] = "1";
        $res['message'] = "Successfully";
        $res['editData'] = $editData['0'];
        $res['standard'] = $standard;
        $res['bloodgroup_data'] = $bloodgroupData;
        $res['custom_fields'] = $dataCustomFields;
        if (count($getDiv) > 0) {
            $res['division'] = $getDiv;
        }
        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }
        if (count($category) > 0) {
            $res['category'] = $category;
        }

        return is_mobile($type, 'admission/registration/edit_admission_registration', $res, 'view');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $user_id = $request->session()->get("user_id");
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');   
            $user_id = $request->get('user_id');                                 
        }
        $editdata['first_name'] = $request->input("first_name");
        $editdata['middle_name'] = $request->input("middle_name");
        $editdata['last_name'] = $request->input("last_name");
        $editdata['mobile'] = $request->input("mobile");
        $editdata['email'] = $request->input("email");
        $editdata['date_of_birth'] = $request->input("date_of_birth");
        $editdata['age'] = $request->input("age");
        $editdata['address'] = $request->input("address");
        $editdata['previous_school_name'] = $request->input("previous_school_name");
        $editdata['source_of_enquiry'] = $request->input("source_of_enquiry");

        admissionEnquiryModel::where(['id' => $id, 'sub_institute_id' => $sub_institute_id])->update($editdata);

        $data = $request->except([
            '_method', '_token','token','syear','sub_institute_id','user_id', 'submit', 'type', 'first_name', 'middle_name', 'last_name', 'mobile', 'email',
            'date_of_birth', 'age', 'address', 'previous_school_name', 'previous_standard', 'source_of_enquiry',
            'admission_standard',
        ]); //,'remarks','followup_date'

        $checkForm = admissionRegistrationModel::where(['enquiry_id' => $id])->get()->toArray();
        if (count($checkForm) > 0) {
            $data['enquiry_id'] = $id;
            $data['created_by'] = $user_id;
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['sub_institute_id'] = $sub_institute_id;

            admissionRegistrationModel::where(['enquiry_id' => $id])->update($data);
        } else {
            $data['enquiry_id'] = $id;
            $data['created_by'] = $user_id;
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['sub_institute_id'] = $sub_institute_id;

            admissionRegistrationModel::insert($data);
        }

        $res['status_code'] = "1";
        $res['message'] = "Added successfully";

        return is_mobile($type, "admission_registration.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    public function saveStudent(Request $request)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $term_id = $request->session()->get("term_id");
        $syear = $request->session()->get("syear");
        $id = $request->input("id");

        $user_profile_result = DB::table('tbluserprofilemaster')->select('id')
            ->where('name', 'Student')
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
        $user_profile_id = $user_profile_result[0]->id;

        $data = DB::table('admission_enquiry as ae')
            ->join('admission_form as af', function ($join) {
                $join->whereRaw('ae.id = af.enquiry_id');
            })->join('admission_registration as ar', function ($join) {
                $join->whereRaw('ae.id = ar.enquiry_id');
            })->selectRaw("ae.*,af.*,ae.id as id,ar.*")
            ->where('ae.id', $id)->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        if (count($data) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "Please complete admission enquiry process";

            return is_mobile($type, "admission_registration.index", $res);
        }

        $data = $data['0'];
        $standardDetails = standardModel::where(['id' => $data['admission_standard']])->get()->toArray();

        $grade_id = $standardDetails['0']['grade_id'];

        $studentArray = array();
        $studentEnrollmentArray = array();

        $studentArray['admission_id'] = $id;
        $studentArray['first_name'] = $data['first_name'];
        $studentArray['middle_name'] = $data['middle_name'];
        $studentArray['last_name'] = $data['last_name'];
        $studentArray['gender'] = $data['gender'];
        $studentArray['mobile'] = $data['mobile'];
        $studentArray['email'] = $data['email'];
        $studentArray['address'] = $data['address'];
        $studentArray['username'] = $data['enrollment_no'];
        $studentArray['user_profile_id'] = $user_profile_id;
        $studentArray['admission_year'] = $syear;//date('Y');
        $studentArray['since_when'] = $syear;//date('Y');
        $studentArray['admission_date'] = $data['admission_date'];//date('Y-m-d');
        $studentArray['sub_institute_id'] = $sub_institute_id;
        $studentArray['status'] = "1";
        $studentArray['place_of_birth'] = $data['place_of_birth'];
        $studentArray['adharnumber'] = $data['aadhar_number'];
        $studentArray['mother_name'] = $data['mother_name'];
        $studentArray['mother_mobile'] = $data['mother_mobile_number'];
        $studentArray['father_name'] = $data['father_name'];
        $studentArray['dob'] = $data['date_of_birth'];
        $studentArray['anuualincome'] = $data['annual_income'];
        $studentArray['bloodgroup'] = $data['blood_group'];
        $studentArray['admission_docket_no'] = $data['admission_docket_no'];
        $studentArray['registration_no'] = $data['registration_no'];

        if (isset($data['enrollment_no']) && $data['enrollment_no'] != '') {
            $enrollment_no_sql_new = $data['enrollment_no'];

            DB::table('tblstudent')
                ->insert([
                    'admission_id'        => $studentArray['admission_id'],
                    'first_name'          => $studentArray['first_name'],
                    'middle_name'         => $studentArray['middle_name'],
                    'last_name'           => $studentArray['last_name'],
                    'gender'              => $studentArray['gender'],
                    'mobile'              => $studentArray['mobile'],
                    'email'               => $studentArray['email'],
                    'address'             => $studentArray['address'],
                    'username'            => $studentArray['username'],
                    'user_profile_id'     => $studentArray['user_profile_id'],
                    'admission_year'      => $studentArray['admission_year'],
                    'since_when'          => $studentArray['since_when'],
                    'admission_date'      => $studentArray['admission_date'],
                    'sub_institute_id'    => $studentArray['sub_institute_id'],
                    'status'              => $studentArray['status'],
                    'place_of_birth'      => $studentArray['place_of_birth'],
                    'adharnumber'         => $studentArray['adharnumber'],
                    'mother_name'         => $studentArray['mother_name'],
                    'mother_mobile'       => $studentArray['mother_mobile'],
                    'father_name'         => $studentArray['father_name'],
                    'dob'                 => $studentArray['dob'],
                    'anuualincome'        => $studentArray['anuualincome'],
                    'bloodgroup'          => $studentArray['bloodgroup'],
                    'admission_docket_no' => $studentArray['admission_docket_no'],
                    'registration_no'     => $studentArray['registration_no'],
                    'enrollment_no'       => $enrollment_no_sql_new,
                ]);

            $student_id = DB::getPdo()->lastInsertId();

        } else {
            $enrollment_no_sql_new = $this->max_enrollment_no_new($sub_institute_id, $data['admission_standard']);

            DB::table('tblstudent')
                ->insert([
                    'admission_id'        => $studentArray['admission_id'],
                    'first_name'          => $studentArray['first_name'],
                    'middle_name'         => $studentArray['middle_name'],
                    'last_name'           => $studentArray['last_name'],
                    'gender'              => $studentArray['gender'],
                    'mobile'              => $studentArray['mobile'],
                    'email'               => $studentArray['email'],
                    'address'             => $studentArray['address'],
                    'username'            => $studentArray['username'],
                    'user_profile_id'     => $studentArray['user_profile_id'],
                    'admission_year'      => $studentArray['admission_year'],
                    'since_when'          => $studentArray['since_when'],
                    'admission_date'      => $studentArray['admission_date'],
                    'sub_institute_id'    => $studentArray['sub_institute_id'],
                    'status'              => $studentArray['status'],
                    'place_of_birth'      => $studentArray['place_of_birth'],
                    'adharnumber'         => $studentArray['adharnumber'],
                    'mother_name'         => $studentArray['mother_name'],
                    'mother_mobile'       => $studentArray['mother_mobile'],
                    'father_name'         => $studentArray['father_name'],
                    'dob'                 => $studentArray['dob'],
                    'anuualincome'        => $studentArray['anuualincome'],
                    'bloodgroup'          => $studentArray['bloodgroup'],
                    'admission_docket_no' => $studentArray['admission_docket_no'],
                    'registration_no'     => $studentArray['registration_no'],
                    'enrollment_no'       => $enrollment_no_sql_new,
                ]);

            $student_id = DB::getPdo()->lastInsertId();
        }

        $studentEnrollmentArray['syear'] = $syear;
        $studentEnrollmentArray['student_id'] = $student_id;
        $studentEnrollmentArray['grade_id'] = $grade_id;
        $studentEnrollmentArray['standard_id'] = $data['admission_standard'];
        $studentEnrollmentArray['section_id'] = $data['admission_division'];
        $studentEnrollmentArray['student_quota'] = $data['student_quota'];
        $studentEnrollmentArray['start_date'] = date('Y-m-d');
        $studentEnrollmentArray['enrollment_code'] = "1";
        $studentEnrollmentArray['term_id'] = $term_id;
        $studentEnrollmentArray['admission_fees'] = $data['amount'];
        $studentEnrollmentArray['sub_institute_id'] = $sub_institute_id;

        tblstudentEnrollmentModel::insert($studentEnrollmentArray);

        $res['status_code'] = 1;
        $res['message'] = "Student added successfully";//with Enrollment Number - ".$studentArray['enrollment_no'];

        return is_mobile($type, "admission_registration.index", $res);
    }

    public function max_enrollment_no($sub_institute_id, $admission_standard_id)
    {

        if ($sub_institute_id == 47)//Generate Enrollment No for MMISERP
        {
            // $get_prefix = "SELECT * FROM enrollment_prefix_master
			// 		   WHERE sub_institute_id = '" . $sub_institute_id . "'
			// 		   AND FIND_IN_SET ('" . $admission_standard_id . "',standards) ";

            // $get_prefix_result = DB::select($get_prefix);
            
            $get_prefix_result = DB::table('enrollment_prefix_master')
                ->select('enrollment_prefix_master.*')
                ->whereRaw("sub_institute_id = '" . $sub_institute_id . "' AND FIND_IN_SET ('" . $admission_standard_id . "',standards) ")
                ->get()->toArray();

            $prefix = $get_prefix_result[0]->prefix;

            if ($prefix != '') {
                $enrollment_result = DB::table('tblstudent')
                    ->selectRaw('*,MAX(enrollment_no) as new_enrollment_no')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->whereRaw("enrollment_no LIKE '%" . $prefix . "%'")->get()->toArray();
                $get_enrollment_no = substr($enrollment_result[0]->new_enrollment_no, 2, 6);
                $new_enrollment_number = $get_enrollment_no + 1;
                $new_enrollment_no = $prefix.$new_enrollment_number;
            } else {
                $get_prefix_null_result = DB::table('enrollment_prefix_master')
                    ->selectRaw('GROUP_CONCAT(prefix) as all_prefix')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->get()->toArray();
                $get_prefix_null_result = $get_prefix_null_result[0];
                $prefix_expload = explode(',', $get_prefix_null_result->all_prefix);

                $enrollment_result = DB::table('tblstudent')
                    ->selectRaw('*,MAX(enrollment_no) as new_enrollment_no')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->when(! empty($prefix_expload), function ($q) use ($prefix_expload) {
                        foreach ($prefix_expload as $key => $value) {
                            $q->whereRaw("enrollment_no NOT LIKE '%".$value."%'");
                        }
                    })->get()->toArray();
                $get_enrollment_no = $enrollment_result[0]->new_enrollment_no;
                $new_enrollment_no = $get_enrollment_no + 1;
            }
        } else {
            $maxEnrollment = DB::table('tblstudent')
                ->selectRaw('(MAX(CAST(enrollment_no AS INT)) + 1) AS new_enrollment_no')
                ->where('sub_institute_id', $sub_institute_id)
                ->orderBy('id', "DESC")->limit(1)->get()->toArray();

            $maxEnrollment = array_map(function ($value) {
                return (array) $value;
            }, $maxEnrollment);

            $new_enrollment_no = $maxEnrollment['0']['new_enrollment_no'];

        }

        return $new_enrollment_no;
    }

    // This function return max enrollment query
    public function max_enrollment_no_new($sub_institute_id, $admission_standard_id)
    {

        if ($sub_institute_id == 47)//Generate Enrollment No for MMISERP
        {
            $get_prefix_result = DB::table('enrollment_prefix_master')
                ->select('enrollment_prefix_master.*')
                ->whereRaw("sub_institute_id = '" . $sub_institute_id . "' AND FIND_IN_SET ('" . $admission_standard_id . "',standards) ")
                ->get()->toArray();
            $prefix = $get_prefix_result[0]->prefix;

            if ($prefix != '') {
                $enrollment_no_sql = "SELECT concat_Ws('','" . $prefix . "',substr(MAX(enrollment_no),3) + 1) as new_enrollment_no
				FROM tblstudent as s
				WHERE sub_institute_id = '".$sub_institute_id."' AND enrollment_no LIKE '%".$prefix."%'";
            } else {
                $get_prefix_null_result = DB::table('enrollment_prefix_master')
                    ->selectRaw('GROUP_CONCAT(prefix) as all_prefix')
                    ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
                $get_prefix_null_result = $get_prefix_null_result[0];
                $prefix_expload = explode(',', $get_prefix_null_result->all_prefix);

                $extra_query = "";
                foreach ($prefix_expload as $key => $value) {
                    $extra_query .= " AND enrollment_no NOT LIKE '%".$value."%'";
                }

                $enrollment_no_sql = "SELECT (MAX(enrollment_no) + 1) as new_enrollment_no
				FROM tblstudent as s
				WHERE sub_institute_id = '".$sub_institute_id."' $extra_query ";
            }
        } else {
            $enrollment_no_sql = "SELECT MAX(CAST(enrollment_no as int) + 1) as new_enrollment_no FROM tblstudent as s
                WHERE sub_institute_id = '" . $sub_institute_id . "'";
        }

        return $enrollment_no_sql;
    }

    public function ajax_getDivision(Request $request)
    {
        $standard_id = $request->input("standard_id");
        $sub_institute_id = session()->get("sub_institute_id");
        $marking_period_id = session()->get('term_id');
        return DB::table('std_div_map as sdm')
            ->join('standard ad s', function ($join) use($marking_period_id) {
                $join->whereRaw('s.id =sdm.standard_id AND s.sub_institute_id = sdm.sub_institute_id');
                // ->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })->join('division ad d', function ($join) {
                $join->whereRaw('d.id = sdm.division_id AND d.sub_institute_id = sdm.sub_institute_id');
            })->selectRaw("d.id,d.name,sdm.standard_id")
            ->where('sdm.sub_institute_id', $sub_institute_id)
            ->where('sdm.standard_id', $standard_id)->get()->toArray();
    }
}
