<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use App\Models\fees\NACH\ac_typeModel;
use App\Models\school_setup\batchModel;
use App\Models\school_setup\bloodgroupModel;
use App\Models\school_setup\casteModel;
use App\Models\school_setup\religionModel;
use App\Models\school_setup\student_optional_subjectModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use App\Models\student\documentTypeModel;
use App\Models\student\houseModel;
use App\Models\student\studentHealthModel;
use App\Models\student\studentHWModel;
use App\Models\student\studentInfirmaryModel;
use App\Models\student\studentQuotaModel;
use App\Models\student\studentVaccinationModel;
use App\Models\student\tblcityModel;
use App\Models\student\tblstateModel;
use App\Models\student\tblstudentDocumentModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentFamilyHistoryModel;
use App\Models\student\tblstudentFeesDetailModel;
use App\Models\student\tblstudentModel;
use App\Models\student\tblstudentParentFeedbackModel;
use App\Models\student\tblstudentPastEducationModel;
use App\Models\student\tblstudentPaymentMethodMappingModel;
use App\Models\student\tblstudentTcModel;
use App\Models\student\Anacdotal;
use App\Models\transportation\add_vehicle\add_transport_kilometer_rate;
use App\Models\user\tbluserprofilemasterModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\is_mobile;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Http\Controllers\fees\fees_report\feesReportController;
use App\Http\Controllers\transportation\map_student\map_student_controller;
use Illuminate\Support\Facades\Session;
use GuzzleHttp\Client;
use function App\Helpers\accesslog_json;

class tblstudentController extends Controller
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

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
				$data['message'] = $data_arr['message'];
			}
		}

		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
        if(in_array($type,['API','web'])){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
		$data = tblstudentModel::where(['sub_institute_id' => $sub_institute_id, 'status' => "1"])->get();

		$dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "tblstudent"])
			->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1) and user_type= "" ')
			->get();

		$fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = [];
		foreach ($fieldsData as $key => $value) {
			$finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
			$finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
			$i++;
        }

        $studentQuota = studentQuotaModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $bloodgroupData = bloodgroupModel::select()->get();
        $religionData = religionModel::select()->get();
        $houseData = houseModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $casteData = casteModel::select()->get();
        $document_type = documentTypeModel::select()->get();
        $transport_kilometer_data = add_transport_kilometer_rate::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get();
        $stateData = tblstateModel::get()->toArray();
        $cityData = [];

        $maxEnrollment = DB::table('tblstudent')->selectRaw("(MAX(CAST(enrollment_no AS INT)) + 1) AS new_enrollment_no")
            ->where('sub_institute_id', $sub_institute_id)->orderBy('id')->limit(1)->get()->toArray();

        $maxEnrollment = array_map(function ($value) {
            return (array) $value;
        }, $maxEnrollment);

        $new_enrollment_no = $maxEnrollment['0']['new_enrollment_no'];
        $admission_year = DB::table(DB::raw("(SELECT ".$syear." AS year
            UNION ALL SELECT ".$syear - 1 ."
            UNION ALL SELECT ".$syear - 2 ."
            UNION ALL SELECT ".$syear - 3 ."
            UNION ALL SELECT ".$syear - 4 ."
            UNION ALL SELECT ".$syear - 5 ."
            ) AS subquery"))
            ->select('year')
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['custom_fields'] = $dataCustomFields;
		if (count($finalfieldsData) > 0) {
			$res['data_fields'] = $finalfieldsData;
        }
        $res['admission_year'] = $admission_year;
		$res['student_quota'] = $studentQuota;
		$res['bloodgroup_data'] = $bloodgroupData;
		$res['religion_data'] = $religionData;
		$res['house_data'] = $houseData;
		$res['transport_kilometer_data'] = $transport_kilometer_data;
		$res['caste_data'] = $casteData;
		$res['document_type_data'] = $document_type;
		$res['state_data'] = $stateData;
		$res['city_data'] = $cityData;
		$res['new_enrollment_no'] = $new_enrollment_no;

        if($type=='web'){
            $res['grades'] = DB::table('academic_section')->where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
            
            $res['standards'] = DB::table('standard')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->get(['id', 'name', 'grade_id'])
            ->groupBy('grade_id')
            ->map(function ($items) {
                return $items->mapWithKeys(function ($item) {
                    return [$item->id => $item->name];
                });
            })
            ->toArray();
            
            $res['divisions'] = DB::table('std_div_map as sdm')
            ->join('division as d', 'd.id', '=', 'sdm.division_id')
            ->where(['sdm.sub_institute_id' => $sub_institute_id])
            ->get(['d.id as id', 'd.name as name', 'sdm.standard_id as standard_id'])
            ->groupBy('standard_id')
            ->map(function ($items) {
                return $items->mapWithKeys(function ($item) {
                    return [$item->id => $item->name];
                });
            })
            ->toArray();        
        
            return is_mobile($type, "student/add_student_cn", $res, "view");
        }else{
		    return is_mobile($type, "student/add_student", $res, "view");  
        }     
	}

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
	public function create()
	{
		//
	}

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
	public function store(Request $request)
	{
        // echo "<pre>";print_r($request->all());exit;
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$term_id = $request->session()->get('term_id');
		$syear = $request->session()->get('syear');
        $type = $request->input('type');
        $marking_period_id = session()->get('term_id');
		$validator = Validator::make($request->all(), [
			'student_image' => 'size:1000',
		]);

		$file_name = $ext = $file_size = "";

		if ($request->hasFile('student_image')) {
			$file = $request->file('student_image');
			$originalname = $file->getClientOriginalName();
			$file_size = $file->getSize();
			if ($file_size > 500000) {
                $res['status_code'] = 0;
                $res['message'] = "Student image not uploaded,Please select file up to 500 KB size.";
                $res['data'] = [];

                return is_mobile($type, "search_student.index", $res);
            } else {
                $name = $id;
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
				$path = $file->storeAs('public/student/', $file_name);
			}
            $name = $request->input('user_name').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/student/', $file_name);
        }

        $request->request->add(['image' => $file_name]); //add request
        $request->request->add(['file_size' => $file_size]); //add request
        $request->request->add(['file_type' => $ext]); //add request

        $dataCustomFields = tblcustomfieldsModel::select('field_name')
            ->where(['status' => "1", 'table_name' => "tblstudent", 'field_type' => "file"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1) and user_type= "" ')
            ->get()
            ->toArray();

        foreach ($dataCustomFields as $key => $value) {
            $file_name = '';

            if ($request->hasFile($value['field_name'])) {
                $file = $request->file($value['field_name']);
                $originalname = $file->getClientOriginalName();
                $name = $value['field_name']."_".$request->input('user_name').date('YmdHis');
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
				$path = $file->storeAs('public/student/', $file_name);
				$request->files->remove($value['field_name']);
				$request->request->add([$value['field_name'] => $file_name]); //add request
			}
		}

		$data = $this->saveData($request);
		$student_id = $data;

		//START Save Optional Subject
		// if ($request->input('optional_subject')) {
		// 	$optional_subject['student_id'] = $student_id;
		// 	$optional_subject['sub_institute_id'] = $sub_institute_id;
		// 	$optional_subject['syear'] = $syear;
		// 	foreach ($request->input('optional_subject') as $key => $val) {
		// 		$optional_subject['subject_id'] = $val;
		// 		student_optional_subjectModel::insert($optional_subject);
		// 	}
		// }
        if(session()->get('sub_institute_id') != 254){
            if ($request->input('optional_subject')) {
                $optional_subject['student_id'] = $student_id;
                $optional_subject['sub_institute_id'] = $sub_institute_id;
                $optional_subject['syear'] = $syear;
                foreach ($request->input('optional_subject') as $key => $val) {
                    $optional_subject['subject_id'] = $val;
                    student_optional_subjectModel::insert($optional_subject);
                }
            }
        }else{
            if ($request->input('optional_subject4')) {
                $optional_subject4['student_id'] = $student_id;
                $optional_subject4['sub_institute_id'] = $sub_institute_id;
                $optional_subject4['syear'] = $syear;
                foreach ($request->input('optional_subject4') as $key => $val) {
                    $optional_subject4['subject_id'] = $val;
                    $checkSubject = student_optional_subjectModel::where(["sub_institute_id" => $sub_institute_id, 'student_id' => $student_id, 'syear' => $syear])->where('subject_id',$optional_subject4['subject_id'])->first();
                    if(empty($checkSubject)){
                    $optional_subject4['level'] = 4;
                    student_optional_subjectModel::insert($optional_subject4);
                    }
                }
            }
            if ($request->input('optional_subject5')) {
                $optional_subject5['student_id'] = $student_id;
                $optional_subject5['sub_institute_id'] = $sub_institute_id;
                $optional_subject5['syear'] = $syear;
                foreach ($request->input('optional_subject5') as $key => $val) {
                    $optional_subject5['subject_id'] = $val;
                    $checkSubject5 = student_optional_subjectModel::where(["sub_institute_id" => $sub_institute_id, 'student_id' => $student_id, 'syear' => $syear])->where('subject_id',$optional_subject5['subject_id'])->first();
                    if(empty($checkSubject5)){
                    $optional_subject5['level'] = 5;
                    student_optional_subjectModel::insert($optional_subject5);
                    }
                }
            }

            if ($request->input('optional_subject6')) {
                $optional_subject6['student_id'] = $student_id;
                $optional_subject6['sub_institute_id'] = $sub_institute_id;
                $optional_subject6['syear'] = $syear;
                foreach ($request->input('optional_subject6') as $key => $val) {
                    $optional_subject6['subject_id'] = $val;
                    $checkSubject6 = student_optional_subjectModel::where(["sub_institute_id" => $sub_institute_id, 'student_id' => $student_id, 'syear' => $syear])->where('subject_id',$optional_subject6['subject_id'])->first();
                    if(empty($checkSubject6)){
                    $optional_subject6['level'] = 6;
                    student_optional_subjectModel::insert($optional_subject6);
                    }
                }
            }
        }
		//END Save Optional Subject

		$studentEnrollment['standard_id'] = $request->input('standard');
		$studentEnrollment['section_id'] = $request->input('division');
		$studentEnrollment['grade_id'] = $request->input('grade');
		$studentEnrollment['syear'] = $syear;
		$studentEnrollment['student_id'] = $student_id;
		$studentEnrollment['student_quota'] = $request->input('student_quota');
		$studentEnrollment['house_id'] = $request->input('house');
		$studentEnrollment['start_date'] = date('Y-m-d');
		$studentEnrollment['term_id'] = $term_id;
		$studentEnrollment['enrollment_code'] = 1;
		$studentEnrollment['sub_institute_id'] = $sub_institute_id;
        $studentEnrollment['roll_no'] = $request->roll_no;

		tblstudentEnrollmentModel::insert($studentEnrollment);

		$res['status_code'] = 1;
		$res['message'] = "Student successfully created.";
		$res['data'] = $data;
        if($type=='web'){
            return redirect('add_students?sub_institute_id='.$sub_institute_id.'&syear='.$syear.'&type=web&success');
        }else{
            return is_mobile($type, "search_student.index", $res);
        }
	}

	public function saveData(Request $request)
	{
		$newRequest = $request->post();

		$sub_institute_id = $request->session()->get('sub_institute_id');
		$finalArray['sub_institute_id'] = $sub_institute_id;
		$finalArrayAdmission['sub_institute_id'] = $sub_institute_id;
        $finalArray['marking_period_id']=session()->get('term_id');
		$studentUserProfile = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id, 'name' => 'Student'])->get()->toArray();

		$finalArray['password'] = md5('student');
		$finalArray['user_profile_id'] = $studentUserProfile[0]['id'];
		$finalArray['status'] = 1;

		unset($newRequest['student_image']);

		foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'type' && $key != 'submit' && $key != 'grade' && $key != 'standard'
                && $key != 'division' && $key != 'student_quota' && $key != 'optional_subject' && $key != 'previous_school_gr_no'
                && $key != 'house' && $key != 'father_occupation' && $key != 'father_qualification' && $key != 'mother_occupation'
                && $key != 'mother_qualification' && $key != 'guardian_name' && $key != 'guardian_relation' && $key != 'house_no'
                && $key != 'building_name_appratment_name_society_name' && $key != 'district_name' && $key != 'roll_no' && $key != 'editable') { //&& $key != 'place_of_birth' && $key != 'previous_school_name'
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
            // added on 19-04-24 by uma for cn
            if($key=="enrollment_no" && $sub_institute_id==257){
                $maxEnrollment = DB::table('tblstudent')->selectRaw("(MAX(CAST(enrollment_no AS INT)) + 1) AS new_enrollment_no")
                ->where('sub_institute_id', $sub_institute_id)->orderBy('id')->limit(1)->get()->toArray();

                $maxEnrollment = array_map(function ($value) {
                    return (array) $value;
                }, $maxEnrollment);

                $finalArray[$key] = $maxEnrollment['0']['new_enrollment_no'];
            }
            // 19-04-24
            // 05-04-2022 START if city is not exist in table then insert city in table
            if ($key == 'state') {
                $get_state_data = tblstateModel::where(['state_name' => $value])->get()->toArray();
                if (count($get_state_data) > 0) {
                    $state_id = $get_state_data[0]['id'];
                    $state_name = $get_state_data[0]['state_name'];
                }
            }

            if ($key == 'city') {
                $check_exist_city = tblcityModel::where(['city_name' => $value])->get()->toArray();
                if (count($check_exist_city) == 0) {
                    $city_data['city_name'] = $finalArray[$key];
                    $city_data['state_id'] = $state_id;
                    $city_data['state_name'] = $state_name;
                    tblcityModel::insert($city_data);
                }
            }
            // 05-04-2022 END if city is not exist in table then insert city in table

		}

		if ($sub_institute_id == 198) {
			if (
				$key == 'place_of_birth' || $key == 'previous_school_name'
				|| $key  == 'father_occupation' || $key  == 'father_qualification' || $key  == 'mother_occupation'
				|| $key  == 'mother_qualification' || $key  == 'guardian_name' || $key  == 'guardian_relation'
				|| $key  == 'house_no' || $key == 'building_name_appratment_name_society_name' || $key  == 'district_name'
			) {
				if (is_array($value)) {
					$value = implode(",", $value);
				}
				$finalArrayAdmission[$key] = $value;
			}
		}

		tblstudentModel::insert($finalArray);
		$id = DB::getPdo()->lastInsertId();

		if ($sub_institute_id == 198) {

            $getAdmissionId = tblstudentModel::select(DB::raw('admission_id'))
                ->where(['sub_institute_id' => $sub_institute_id, 'id' => $id])->get()->toArray();
            $admission_id = $getAdmissionId[0]['admission_id'];
            DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
            $dataAdmission = admissionEnquiryModel::where(['id' => $admission_id])->update($finalArrayAdmission);
             // 2024-08-23
             $queries = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
             $sendQuery = end($queries); // 2024-08-24 required to convert query into sql for json 
             accesslog_json($sendQuery,'update','Student Edit Profile (Admission Enquiry)',$finalArrayAdmission);
             //2024-08-23
        }

		return $id;
	}

	public function updateData(Request $request)
    {
        $newRequest = $request->post();
        $student_id = $newRequest['id'];
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArrayAdmission['sub_institute_id'] = $sub_institute_id;
        $finalArray['password'] = md5('student');
        $finalArray['status'] = 1;

        unset($newRequest['student_image']);

        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'type' && $key != 'submit' && $key != 'grade' && $key != 'standard'
                && $key != 'division' && $key != 'student_quota' && $key != 'end_date' && $key != 'remarks' && $key != 'inactive_satus'
                && $key != 'id' && $key != 'optional_subject' && $key != 'optional_subject4' && $key != 'optional_subject5' && $key != 'optional_subject6' && $key != 'previous_school_gr_no' && $key != 'house'
                && $key != 'father_occupation' && $key != 'father_qualification' && $key != 'mother_occupation'
                && $key != 'mother_qualification' && $key != 'guardian_name' && $key != 'guardian_relation'
                && $key != 'house_no' && $key != 'building_name_appratment_name_society_name' && $key != 'district_name' && $key != 'roll_no'  && $key != 'editable') { //&& $key != 'place_of_birth' && $key != 'previous_school_name'
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;

                // 05-04-2022 START if city is not exist in table then insert city in table
                if ($key == 'state') {
                    $get_state_data = tblstateModel::where(['state_name' => $value])->get()->toArray();
                    if (count($get_state_data) > 0) {
                        $state_id = $get_state_data[0]['id'];
						$state_name = $get_state_data[0]['state_name'];
					}
				}
				if ($key == 'city') {
					$check_exist_city = tblcityModel::where(['city_name' => $value])->get()->toArray();
					if (count($check_exist_city) == 0) {
						$city_data['city_name'] = $finalArray[$key];
						$city_data['state_id'] = $state_id;
						$city_data['state_name'] = $state_name;
						tblcityModel::insert($city_data);
					}
				}
				// 05-04-2022 END if city is not exist in table then insert city in table

			}
			if (
				$key == 'place_of_birth' || $key == 'previous_school_name'
				|| $key  == 'father_occupation' || $key  == 'father_qualification' || $key  == 'mother_occupation'
				|| $key  == 'mother_qualification' || $key  == 'guardian_name' || $key  == 'guardian_relation'
				|| $key  == 'house_no' || $key == 'building_name_appratment_name_society_name' || $key  == 'district_name') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArrayAdmission[$key] = $value;
            }
        }

        $finalArray['updated_on'] = date('Y-m-d H:i:s');
        DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
        $data = tblstudentModel::where(['id' => $student_id])->update($finalArray);
         // 2024-08-23
         $queries = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
         $sendQuery = end($queries); // 2024-08-24 required to convert query into sql for json 
         accesslog_json($sendQuery,'update','Student Edit Profile',$finalArray);
         //2024-08-23

        $getAdmissionId = tblstudentModel::select(DB::raw('admission_id'))
            ->where(['sub_institute_id' => $sub_institute_id, 'id' => $student_id])->get()->toArray();
        $admission_id = $getAdmissionId[0]['admission_id'];
        DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
        $dataAdmission = admissionEnquiryModel::where(['id' => $admission_id])->update($finalArrayAdmission);
        // 2024-08-23
        $queries2 = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
        $sendQuery2 = end($queries2); // 2024-08-24 required to convert query into sql for json 
        accesslog_json($sendQuery2,'update','Student Edit Profile (Admission Enquiry)',$finalArrayAdmission);
        //2024-08-23
        return $data;
    }


    public function edit(Request $request, $id)
    {
        $type = $request->input('type');

        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
		}

        // $data = file_get_contents('https://erp.triz.co.in/get_adminParentCommunicationListAPI');
        // $payload = array(
		//     // 'exp' => time() + 7200,
		//     "id" => 123,
		//     "first_name" => 'keyur',
		//     "last_name" => 'modi',
		//     "roll_no" => 12,
		// );
		// $token = $jwt->createToken($payload);

		/**
		 * GET STUDENT PARENT DATA USING API
		 */
		$postData = [
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
            'student_id' => $id,
        ];

        $payload = json_encode($postData);

        // Prepare new cURL resource
        $ch = curl_init("https://" . $_SERVER['SERVER_NAME'] . "/studentParentcommunicationListAPI"); 
        $leave = curl_init("https://" . $_SERVER['SERVER_NAME'] . "/studentLeaveApplicationAPI");

        if(isset($ch))
        {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        
        if(isset($leave))
        {
            curl_setopt($leave, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($leave, CURLINFO_HEADER_OUT, true);
            curl_setopt($leave, CURLOPT_POST, true);
            curl_setopt($leave, CURLOPT_POSTFIELDS, $payload);
        }

        // Set HTTP Header for POST request
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]
        );

        curl_setopt($leave, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]
        );

        // Submit the POST request
        $getResult = curl_exec($ch);
        $getLeaveResult = curl_exec($leave);
        
        // decode json result
        $result = json_decode($getResult);
        $leaveResult = json_decode($getLeaveResult);
        
        // Close cURL session handle
        curl_close($ch);
        curl_close($leave);

        $stuParCommunication = [];
        if (!empty($result) && $result->status_code == 1) {
            $stuParCommunication = $result->data;
        }

        $leaveApplication = [];
        if (!empty($leaveResult) && $leaveResult->status == 1) {
            $leaveApplication = $leaveResult->data;
        }

        if ($sub_institute_id == 198) {
            $student_data = tblstudentModel::select('admission_enquiry.*', 'tblstudent.*', 'tblstudent_enrollment.*',
                'tblstudent.id  as id', 'admission_enquiry.building_name_appratment_name_society_name as building_name',
                'tblstudent_enrollment.house_id')
                ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
                ->leftJoin('admission_enquiry', 'tblstudent.admission_id', '=', 'admission_enquiry.id')
                ->where([
                    'tblstudent_enrollment.sub_institute_id' => $sub_institute_id,
                    'tblstudent_enrollment.syear' => $syear,
                    'tblstudent.status' => 1,
                    'tblstudent.id' => $id,
                ])->first();
        } else {

            $student_data = tblstudentModel::select('tblstudent.*', 'tblstudent_enrollment.*', 'tblstudent.id as id',
                'tblstudent_enrollment.house_id', 'admission_enquiry.enquiry_no')
                ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
                ->leftJoin('admission_enquiry', 'tblstudent.admission_id', '=', 'admission_enquiry.id')
                ->where([
                    'tblstudent_enrollment.sub_institute_id' => $sub_institute_id,
                    'tblstudent_enrollment.syear' => $syear,
                    'tblstudent.status' => 1,
                    'tblstudent.id' => $id,
                ])->first();
        }
        //echo "<pre>";print_r($student_data);exit;
		// RAJESH	->whereRaw('tblstudent_enrollment.end_date is NULL')
		$dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "tblstudent"])
        ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1) and user_type= "" ')
			->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = [];
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        $studentQuota = studentQuotaModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $std_id = $student_data->standard_id ?? "";
        $div_id = $student_data->section_id ?? "";
        $batchData = $optional_subject_data = $student_optional_subject_data = $student_optional_subject_data4 = $student_optional_subject_data5 = $student_optional_subject_data6 = [];

        if ($std_id != "" && $div_id != "") {
            $batchData = batchModel::where([
                'sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id, 'division_id' => $div_id, 'syear' => $syear
            ])
                ->get()->toArray();
        }
        $bloodgroupData = bloodgroupModel::select()->get();
        $religionData = religionModel::select()->get();
        $houseData = houseModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $casteData = casteModel::select()->get();
        $document_type_data = documentTypeModel::select('student_document_type.*')->join('tblstudent_doc_std_mapping','student_document_type.id','=','tblstudent_doc_std_mapping.document_type_id')->where(['tblstudent_doc_std_mapping.standard_id'=>$std_id,'tblstudent_doc_std_mapping.sub_institute_id'=>$sub_institute_id])
        ->where('student_document_type.user_type','student')
        ->get();

        $transport_kilometer_data = add_transport_kilometer_rate::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get();

        $hilldOptional4 = $hilldOptional5 = $hilldOptional6 =[];

		if ($std_id != "") {
            $optional_subject_data = sub_std_mapModel::select('sub_std_map.*', 'subject.subject_name',
                'subject.subject_code')
                ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
                ->where([
                    'sub_std_map.sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id,
                    'elective_subject'             => 'Yes',
                ])
                ->get()->toArray();

                if(session()->get('sub_institute_id') != 254){
                    $student_optional_subject_data = student_optional_subjectModel::selectRaw('GROUP_CONCAT(subject_id) AS subject_ids')
                    ->where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id, 'syear' => $syear])->get();
    
                    $student_optional_subject_data = explode(",", $student_optional_subject_data[0]->subject_ids);
                }else{
                    foreach ($optional_subject_data as $key => $value) {
                        if($value['optional_type'] !=null && $value['optional_type']==4){
                            $hilldOptional4[]=$value;
                        }
                        if($value['optional_type'] !=null && $value['optional_type']==5){
                            $hilldOptional5[]=$value;
                        }
                        if($value['optional_type'] !=null && $value['optional_type']==6){
                            $hilldOptional6[]=$value;
                        }
                    }
                    $student_optional_subject_data4 = student_optional_subjectModel::selectRaw('GROUP_CONCAT(subject_id) AS subject_ids')
                    ->where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id, 'syear' => $syear,"level"=>"4"])->get();

                    $student_optional_subject_data4 = explode(",", $student_optional_subject_data4[0]->subject_ids);
    
                    $student_optional_subject_data5 = student_optional_subjectModel::selectRaw('GROUP_CONCAT(subject_id) AS subject_ids')
                    ->where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id, 'syear' => $syear,"level"=>"5"])->get();
                    $student_optional_subject_data5 = explode(",", $student_optional_subject_data5[0]->subject_ids);
    
                    $student_optional_subject_data6 = student_optional_subjectModel::selectRaw('GROUP_CONCAT(subject_id) AS subject_ids')
                    ->where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id, 'syear' => $syear,"level"=>"6"])->get();
                    $student_optional_subject_data6 = explode(",", $student_optional_subject_data6[0]->subject_ids);
                }
        }
        // echo "<pre>";print_r($hilldOptional4);exit;
        $pastEducation = tblstudentPastEducationModel::where([
            'sub_institute_id' => $sub_institute_id, 'student_id' => $id,
        ])->get()->toArray();

        $familyHistory = tblstudentFamilyHistoryModel::where([
            'sub_institute_id' => $sub_institute_id, 'student_id' => $id,
        ])->get()->toArray();


        $studentSiblings_data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id");
            })->join('standard as st', function ($join) {
                $join->whereRaw("st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id");
            })->join('division as d', function ($join) {
                $join->whereRaw("d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id");
            })
            ->selectRaw("s.id,s.enrollment_no,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name,
                st.name as std_name,d.name as div_name,s.mobile,s.student_mobile,s.mother_mobile")
            ->where(function ($q) use ($student_data) {
                if (!empty($student_data)) {
                    // $q->where('s.mobile', $student_data->mobile)
                    //     ->orWhere('s.mother_mobile', $student_data->mobile)
                    //     ->orWhere('s.student_mobile', $student_data->mobile)
                    //2024-09-10 added match mobile
                    if(isset($student_data->mobile) && $student_data->mobile!='' && $student_data->mobile!=null){
                        $q->whereRaw('s.mobile="'.$student_data->mobile.'" OR s.student_mobile="'.$student_data->mobile.'" OR s.mother_mobile="'.$student_data->mobile.'"');
                    }
                    //2024-09-10 added match student mobile
                    if(isset($student_data->student_mobile) && $student_data->student_mobile!='' && $student_data->student_mobile!=null){
                        $q->whereRaw('s.mobile="'.$student_data->student_mobile.'" OR s.student_mobile="'.$student_data->student_mobile.'" OR s.mother_mobile="'.$student_data->student_mobile.'"');
                    }
                    //2024-09-10 added match monther mobile
                    if(isset($student_data->mother_mobile) && $student_data->mother_mobile!='' && $student_data->mother_mobile!=null){
                        $q->whereRaw('s.mobile="'.$student_data->mother_mobile.'" OR s.student_mobile="'.$student_data->mother_mobile.'" OR s.mother_mobile="'.$student_data->mother_mobile.'"');
                    }
                }
            })->where('s.sub_institute_id', $sub_institute_id)
            ->where('s.id', '!=', $id)
            ->where('se.syear', $syear)->get()->toArray();

        $studentSiblings_data = json_decode(json_encode($studentSiblings_data), true);

        $parentFeedback = tblstudentParentFeedbackModel::where([
            'sub_institute_id' => $sub_institute_id, 'student_id' => $id,
        ])->get()->toArray();

        $studentInfirmary = studentInfirmaryModel::where(['student_id' => $id])->get()->toArray();

        $studentVaccination = studentVaccinationModel::where([
            'sub_institute_id' => $sub_institute_id, 'student_id' => $id,
        ])->get()->toArray();

        $studentheight_weight = studentHWModel::where([
            'sub_institute_id' => $sub_institute_id, 'student_id' => $id,
        ])->get()->toArray();

		$studenthealth = studentHealthModel::where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id])->get()->toArray();

		$studentdocument = tblstudentDocumentModel::select('tblstudent_document.*', 'd.document_type')
			->join('student_document_type as d', 'd.id', 'tblstudent_document.document_type_id')
			->where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id])
			->get()
			->toArray();

		$studentfeesdetails = tblstudentFeesDetailModel::where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id])->get()->toArray();
        
        $getAnacdotals = Anacdotal::where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id])->get()->toArray();
        /* echo "<pre>";
print_r($get_anacdotals);
echo "</pre>";
die; */
		$studentTcdetails = tblstudentTcModel::where(['sub_institute_id' => $sub_institute_id, 'student_id' => $id, 'syear' => $syear])->get()->toArray();

		$stateData = tblstateModel::get()->toArray();
		$state_name = '';
        if (isset($student_data->state) && $student_data->state != '') {
            $state_name = $student_data->state;
        }
        $city_name = '';
        if (isset($student_data->city) && $student_data->city != '') {
            $city_name = $student_data->city;
        }
        // added on 19-04-24 by uma
        $cityData = tblcityModel::where(['state_name' => $state_name])->get()->toArray();

        // $cityData = tblcityModel::where(['state_name' => $state_name, 'city_name' => $city_name])->get()->toArray();

        //START if once fees is paid for current year admission year,standard,student quota,academic section can't be edited
        $studentfees_paid = DB::table('fees_collect as c')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.student_id', $id)
            ->where('syear', $syear)
            ->where('c.is_deleted', '=', 'N')->get()->toArray();

        $studentfees_paid = json_decode(json_encode($studentfees_paid), true);

        $res['edit_disable'] = "";
        if (count($studentfees_paid) > 0 && $sub_institute_id != 257) {
            $res['edit_disable'] = "disabled";
        }
        //END if once fees is paid for current year admission year,standard,student quota,academic section can't be edited


        $stuarr = [$id];
        $breakoffData = FeeBreakoffHeadWise($stuarr);
        $breakoff_MonthArr = [];
		if (count($breakoffData) > 0) {
            $breakoffData = $breakoffData[$id]['breakoff'];
            $months = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            ];
			foreach ($breakoffData as $bid => $arr) {
				$y = $bid / 10000;
				$month = (int) $y;
				$year = substr($bid, -4);
				$breakoff_MonthArr[$bid] = $months[$month] . "/" . $year;
			}
		}

        $studentPM_Mapping = tblstudentPaymentMethodMappingModel::where([
            'sub_institute_id' => $sub_institute_id, 'student_id' => $id,
        ])
            ->get()->toArray();
        $studentPM_Arr = [];
        if (count($studentPM_Mapping) > 0) {
            foreach ($studentPM_Mapping as $pmid => $pmarr) {
                $studentPM_Arr[$pmarr['month_id']] = $pmarr;
            }
        }

        //START GET NACH Account types
        $ac_type_arr = ac_typeModel::where(['sub_institute_id' => $sub_institute_id])
            ->get()->toArray();
        //END GET NACH Account types

        // GET ALL ATTENDANCE YEAR
        /* $gatYear = DB::table('attendance_student')
            ->select(DB::raw('YEAR(date(created_on)) as Year'))
            ->where(DB::raw('YEAR(date(created_on))'), DB::raw('YEAR(date(created_on))'))
            ->groupBy(DB::raw('YEAR(date(created_on))'))
            ->get(); */


		$attendanceData = DB::table('attendance_student')
		->select(
			DB::raw('DATE_FORMAT(attendance_date, "%y") AS YEAR'),
			// DB::raw('MONTHNAME(attendance_date) AS MONTH'),
			DB::raw('DATE_FORMAT(attendance_date, "%b") AS MONTH'),
			DB::raw('COUNT(DISTINCT attendance_date) as TOTAL_CLASSES'),
			DB::raw("COUNT(
				DISTINCT IF(
					attendance_code = 'P'
					AND student_id = $id,
					attendance_date,
					NULL
				)
			) as TOTAL_PRESENT"),
			DB::raw("COUNT(
				DISTINCT IF(
					attendance_code = 'A'
					AND student_id = $id,
					attendance_date,
					NULL
				)
			) as TOTAL_ABSENT")
		)->where('sub_institute_id', $sub_institute_id)
		->where('syear', $syear)
		->where('student_id', $id)
		->groupBy('YEAR', DB::raw('MONTH(attendance_date)'))
		->get();

		/*$dataStudentSiblingsNew = array();

		$studentSiblings = DB::table('tblstudent_siblings')
			->where(['sub_institute_id' => $sub_institute_id])
			->whereRaw("FIND_IN_SET(" . $id . ",siblings_id)")
			->get()
			->toArray();

		$studentSiblingsjson = json_encode($studentSiblings, true);
		$dataStudentSiblings = json_decode($studentSiblingsjson, true);*/

		$studentTransportMap = DB::table('transport_map_student')
			->where(['student_id' => $id])
			->get()
			->toArray();

		/*if (count($dataStudentSiblings) > 0) {

			$explodeSiblings = explode(',', $dataStudentSiblings[0]['siblings_id']);
			foreach ($explodeSiblings as $skey => $svalue) {
				if ($id == $svalue) {
					unset($explodeSiblings[$skey]);
				}
			}
			$siblingsId = implode(",", $explodeSiblings);

			$studentSearchController = new studentSearchController();
			$studentRequest = array();
			$studentRequest['student_id'] = $id;
			$request->request->add(['student_id' => $siblingsId]);
			$dataStudentSiblingsNew = $studentSearchController->searchStudentId($request);
		}*/

		// dd($dataStudentSiblingsNew);
        
        $total_distance = ($this->distance($student_data->address ?? ' '));

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $student_data;
		$res['total_distance'] = $total_distance;
        //$res['student_data'] = $student_data;
		$res['custom_fields'] = $dataCustomFields;

        if (count($finalfieldsData) > 0) {
			$res['data_fields'] = $finalfieldsData;
		}
		if (count($pastEducation) > 0) {
			$res['past_education'] = $pastEducation;
		}
		if (count($familyHistory) > 0) {
			$res['family_history'] = $familyHistory;
		}
		if (count($parentFeedback) > 0) {
			$res['parent_feedback'] = $parentFeedback;
		}
		if (count($studentInfirmary) > 0) {
			$res['student_infirmary'] = $studentInfirmary;
		}
		if (count($studentVaccination) > 0) {
			$res['student_vaccination'] = $studentVaccination;
		}
		if (count($studentheight_weight) > 0) {
			$res['student_height_weight'] = $studentheight_weight;
		}
		if (count($studenthealth) > 0) {
			$res['student_health'] = $studenthealth;
		}
		if (count($studentSiblings_data) > 0) {
			$res['student_siblings'] = $studentSiblings_data;
		}
		if (count($studentdocument) > 0) {
			$res['student_document'] = $studentdocument;
		}
		if (count($studentfeesdetails) > 0) {
			$res['studentfeesdetails'] = $studentfeesdetails[0];
		}
		if (count($studentTcdetails) > 0) {
			$res['studentTcdetails'] = $studentTcdetails[0];
        }
        if (count($getAnacdotals) > 0) {
			$res['get_anacdotals'] = $getAnacdotals;
        }
        $admission_year = DB::table(DB::raw("(SELECT ".$syear." AS year
        UNION ALL SELECT ".$syear - 1 ."
        UNION ALL SELECT ".$syear - 2 ."
        UNION ALL SELECT ".$syear - 3 ."
        UNION ALL SELECT ".$syear - 4 ."
        UNION ALL SELECT ".$syear - 5 ."
        ) AS subquery"))
        ->select('year')
        ->get();
        
        $controller = new fees_collect_controller;

        $OldData = $controller->getBk($request, $id);
        $FeesData = $controller->retrieveDataByUserId($request, '', $id);

        //transport details 
        $trans_controller = new map_student_controller;
        $request = new Request(['id' => $id]);
        $trans_details = $trans_controller->create($request);       
        
        // echo "<pre>";print_r($FeesData);exit;
        
        $res['paid_unpaid_fees'] = $OldData['total_fees'] ?? [];
        $res['check_fees'] = $OldData['final_fee']['Total'] ?? [];
        $res['stu_data'] = $OldData['stu_data'] ?? [];
        $res['feesData'] = $FeesData;
        
        $res['admission_year'] = $admission_year;        
        $res['student_quota'] = $studentQuota;
		$res['breakoff_MonthArr'] = $breakoff_MonthArr;
		$res['studentPM_Arr'] = $studentPM_Arr;
		$res['ac_type_arr'] = $ac_type_arr;
		$res['bloodgroup_data'] = $bloodgroupData;
		$res['religion_data'] = $religionData;
		$res['house_data'] = $houseData;
		$res['transport_kilometer_data'] = $transport_kilometer_data;
		$res['caste_data'] = $casteData;
		$res['document_type_data'] = $document_type_data;
		$res['batch_data'] = $batchData;
		$res['optional_subject_data'] = $optional_subject_data;
		if(session()->get('sub_institute_id')!=254){
		    $res['student_optional_subject_data'] = $student_optional_subject_data;
        }else{
            $res['hilldOptional4'] = $hilldOptional4;
            $res['hilldOptional5'] = $hilldOptional5;
            $res['hilldOptional6'] = $hilldOptional6;
            $res['student_optional_subject_data4'] = $student_optional_subject_data4;
		    $res['student_optional_subject_data5'] = $student_optional_subject_data5;
		    $res['student_optional_subject_data6'] = $student_optional_subject_data6;
        }
		$res['transport_map_student'] = $studentTransportMap;
		$res['state_data'] = $stateData;
		$res['city_data'] = $cityData;
		$res['attendance_data'] = $attendanceData;
		$res['stu_par_communication'] = $stuParCommunication;
        $res['leave_application'] = $leaveApplication;
        if(isset($trans_details['stu_data'])){
        $res['trans_details']=$trans_details['stu_data'];
    }else{
        $res['trans_details']=[];
    }

		return is_mobile($type, "student/edit_student", $res, "view");
	}

    public function distance($stu_add)
    {
        $studentAddress = $stu_add;

        if (!$stu_add) {
            return 0; // or any default value you prefer
        }

        $apiKey = 'AIzaSyBR3wG6BAtSxwwstbYXnbLCXDxR8WX94iE';

        // Get the school address
        $schoolData = DB::table('school_setup')->where('id', session()->get('sub_institute_id'))->first();

        if (!$schoolData || !property_exists($schoolData, 'ReceiptAddress')) {
            // Handle the case where the school address is not found
            return response()->json(['error' => 'School address not found']);
        }

        $schoolAddress = $schoolData->ReceiptAddress;

        // Make a request to the Google Maps Distance Matrix API
        $client = new Client();
        $response = $client->get("https://maps.googleapis.com/maps/api/distancematrix/json", [
            'query' => [
                'origins' => $schoolAddress,
                'destinations' => $studentAddress,
                'key' => $apiKey,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        
        // Check if the required data is present in the API response
        if (isset($data['rows'][0]['elements'][0]['distance']['text'])) {
            // Extract the distance value (in meters) from the response
            $distanceText = $data['rows'][0]['elements'][0]['distance']['text'];
            
            // $numericDistance = floatval(explode(' ', $distanceText)[0]);
            
            // Return the distance value as a float
            return $distanceText;
        } 
        else 
        {
            // Handle the case where distance data is not found in the API response
            return 0;
        }
    }

    public function update_transport(Request $request,$id){
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
        $type = $request->input('type');        
        // Access the 'from_stop' value associated with the given ID
        // return $request;exit;
        
        $area = $request['values'][$id]['from_stop'];
        $van_shift = explode('-',$request['values'][$id]['van-shift']);
        $distance = $request['distance'];
        $amount = $request['amount'];

        $update_arr = [
            "from_stop"=>$area,
            "to_stop"=>$area,          
            "from_bus_id"=>$van_shift[0],
            "to_bus_id"=>$van_shift[0],
            "from_shift_id"=>$van_shift[1],
            "to_shift_id"=>$van_shift[1],   
            "distance"=>$distance,       
            "amount"=>$amount,     
            "updated_at"=>now(),  
        ];
        DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
        $data = DB::table('transport_map_student')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'student_id'=>$id])->update($update_arr);
         // 2024-08-23
         $queries = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
         $sendQuery = end($queries); // 2024-08-24 required to convert query into sql for json 
         accesslog_json($sendQuery,'update','Student Edit Profile (Transport)',$update_arr);
         //2024-08-23
        $res['status_code'] = 1;
		$res['message'] = "Student Edit Profiled successfully.";
        $res['data'] = $data;

		// return is_mobile($type, "search_student.index", $res);
		return $res;
		// return redirect()->back();
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return RedirectResponse|Response
     */
	public function update(Request $request, $id)
	{
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$term_id = $request->session()->get('term_id');
		$syear = $request->session()->get('syear');
        $type = $request->input('type');
        
        if(isset($request->transport_details)){
        $send_data = $this->update_transport($request,$id);
        $res['status_code'] = $send_data['status_code'];
        $res['message'] = $send_data['message'];        
        }else{
		$file_name = $ext = $file_size = "";
		if ($request->hasFile('student_image')) {
			$file = $request->file('student_image');
			$originalname = $file->getClientOriginalName();
			$file_size = $file->getSize();

			if ($file_size > 500000) {

                return redirect()->back()->with("Warning", "Student image not uploaded,Please select file up to 500 KB size.");
			} else {
                $name = $id;
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
				$path = $file->storeAs('public/student/', $file_name);
			}
        }
        if ($file_name != '') {
            $request->request->add(['image' => $file_name]); //add request
            $request->request->add(['file_size' => $file_size]); //add request
            $request->request->add(['file_type' => $ext]); //add request
        }

        $request->request->add(['id' => $id]); //add request
        $student_id = $id;

        $dataCustomFields = tblcustomfieldsModel::select('field_name')
            ->where(['status' => "1", 'table_name' => "tblstudent", 'field_type' => "file"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1) and user_type= "" ')
            ->get()
            ->toArray();

        foreach ($dataCustomFields as $key => $value) {
            if ($request->hasFile($value['field_name'])) {
                $file = $request->file($value['field_name']);
                $originalname = $file->getClientOriginalName();
                $name = $value['field_name']."_".$request->input('user_name').date('YmdHis');
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
				$path = $file->storeAs('public/student/', $file_name);

				$request->files->remove($value['field_name']);
				// $request->request->set($value['field_name'], $file_name); //add request
				$request->request->add([$value['field_name'] => $file_name]); //add request
			}
		}

		$data = $this->updateData($request);

		//START Save Optional Subject
        student_optional_subjectModel::where(["sub_institute_id" => $sub_institute_id, 'student_id' => $student_id, 'syear' => $syear])->delete();
		if(session()->get('sub_institute_id') != 254){
            if ($request->input('optional_subject')) {
                $optional_subject['student_id'] = $student_id;
                $optional_subject['sub_institute_id'] = $sub_institute_id;
                $optional_subject['syear'] = $syear;
                foreach ($request->input('optional_subject') as $key => $val) {
                    $optional_subject['subject_id'] = $val;
                    student_optional_subjectModel::insert($optional_subject);
                }
            }
        }else{
            if ($request->input('optional_subject4')) {
                $optional_subject4['student_id'] = $student_id;
                $optional_subject4['sub_institute_id'] = $sub_institute_id;
                $optional_subject4['syear'] = $syear;
                foreach ($request->input('optional_subject4') as $key => $val) {
                    $optional_subject4['subject_id'] = $val;
                    $checkSubject = student_optional_subjectModel::where(["sub_institute_id" => $sub_institute_id, 'student_id' => $student_id, 'syear' => $syear])->where('subject_id',$optional_subject4['subject_id'])->first();
                    if(empty($checkSubject)){
                    $optional_subject4['level'] = 4;
                    student_optional_subjectModel::insert($optional_subject4);
                    }
                }
            }
            if ($request->input('optional_subject5')) {
                $optional_subject5['student_id'] = $student_id;
                $optional_subject5['sub_institute_id'] = $sub_institute_id;
                $optional_subject5['syear'] = $syear;
                foreach ($request->input('optional_subject5') as $key => $val) {
                    $optional_subject5['subject_id'] = $val;
                    $checkSubject5 = student_optional_subjectModel::where(["sub_institute_id" => $sub_institute_id, 'student_id' => $student_id, 'syear' => $syear])->where('subject_id',$optional_subject5['subject_id'])->first();
                    if(empty($checkSubject5)){
                    $optional_subject5['level'] = 5;
                    student_optional_subjectModel::insert($optional_subject5);
                    }
                }
            }

            if ($request->input('optional_subject6')) {
                $optional_subject6['student_id'] = $student_id;
                $optional_subject6['sub_institute_id'] = $sub_institute_id;
                $optional_subject6['syear'] = $syear;
                foreach ($request->input('optional_subject6') as $key => $val) {
                    $optional_subject6['subject_id'] = $val;
                    $checkSubject6 = student_optional_subjectModel::where(["sub_institute_id" => $sub_institute_id, 'student_id' => $student_id, 'syear' => $syear])->where('subject_id',$optional_subject6['subject_id'])->first();
                    if(empty($checkSubject6)){
                    $optional_subject6['level'] = 6;
                    student_optional_subjectModel::insert($optional_subject6);
                    }
                }
            }
        }
		//END Save Optional Subject
        if($request->input('editable') == 1){
            $studentEnrollment['standard_id'] = $request->input('standard');
            $studentEnrollment['section_id'] = $request->input('division');
            $studentEnrollment['grade_id'] = $request->input('grade');
        }

		$studentEnrollment['syear'] = $syear;
		$studentEnrollment['student_id'] = $student_id;
		$studentEnrollment['student_quota'] = $request->input('student_quota');
		$studentEnrollment['house_id'] = $request->input('house');
		// $studentEnrollment['start_date'] = date('Y-m-d');

		if ($request->input('inactive_satus') == 0) {
			$studentEnrollment['end_date'] = NULL;
			$studentEnrollment['remarks'] = NULL;
		} else {
			//$studentEnrollment['end_date'] = $request->input('end_date');
			$studentEnrollment['end_date'] = date("Y-m-d", strtotime($request->input('end_date')));
			$studentEnrollment['remarks'] = $request->input('remarks');
		}

		$studentEnrollment['term_id'] = $term_id;
		$studentEnrollment['enrollment_code'] = 1;
		$studentEnrollment['sub_institute_id'] = $sub_institute_id;
		$studentEnrollment['updated_on'] = date('Y-m-d H:i:s');
		$studentEnrollment['roll_no'] = $request->roll_no;
		// dd($studentEnrollment);
        DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
		tblstudentEnrollmentModel::where(['student_id' => $student_id, 'syear' => $syear])->update($studentEnrollment);
        // 2024-08-23
        $queries = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
        $sendQuery = end($queries); // 2024-08-24 required to convert query into sql for json 
        accesslog_json($sendQuery,'update','Student Edit Profile (Enrollment)',$studentEnrollment);
        //2024-08-23
		$res['status_code'] = 1;
		$res['message'] = "Student Edit Profiled successfully.";
        $res['data'] = $data;
    }
		// return is_mobile($type, "search_student.index", $res);
		// return redirect()->route('add_student.show', $res);
		return redirect()->back();
	}

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
	public function destroy(Request $request, $id)
	{
		$type = $request->input('type');
        $syear = $request->session()->get('syear');
        $fields = [
            'status' => "0",
        ];
        DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
        tblstudentModel::where(["id" => $id])->update($fields);
        // 2024-08-23
        $queries = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
        $sendQuery = end($queries); // 2024-08-24 required to convert query into sql for json 
        accesslog_json($sendQuery,'Delete','Student Edit Profile',$fields);
        //2024-08-23
        $fields = [
            'end_date' => date('Y-m-d'),
            'updated_on' => date('Y-m-d H:i:s'),
        ];
        DB::enableQueryLog(); // 2024-08-24 required to convert query into sql for json
        tblstudentEnrollmentModel::where(["student_id" => $id, "syear" => $syear])->update($fields);
        // 2024-08-23
        $queries2 = DB::getQueryLog(); // 2024-08-24 required to convert query into sql for json
        $sendQuery2 = end($queries2); // 2024-08-24 required to convert query into sql for json 
        accesslog_json($sendQuery2,'Delete','Student Edit Profile (Enrollment)',$fields);
        //2024-08-23
		$res['status_code'] = "1";
		$res['message'] = "Student deleted successfully";
		return is_mobile($type, "search_student.index", $res);
	}

	public function notificationHubAPI(Request $request)
	{

		try {
			if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
				return response()->json($response, 401);
			}
		} catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
			return response()->json($response, 401);
		}

		$type = $request->input("type");
		$student_id = $request->input("student_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$sms_mobile = $request->input("mobile_no");
		$syear = $request->input("syear");
		$imei = $request->input("imei");


		if ($student_id != "" && $sub_institute_id != "" && $sms_mobile != "" && $syear != "") {

			$data = DB::select("SELECT an.ID,an.NOTIFICATION_TYPE,an.STUDENT_ID,an.`Status`,an.NOTIFICATION_DESCRIPTION,DATE_FORMAT(an.NOTIFICATION_DATE,'%d-%m-%Y') AS NOTIFICATION_DATE,
CASE
    WHEN an.NOTIFICATION_TYPE = 'Homework' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/homework.png'
    WHEN an.NOTIFICATION_TYPE = 'Assignment' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/homework.png'
    WHEN an.NOTIFICATION_TYPE = 'Circular' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/circular.png'
    WHEN an.NOTIFICATION_TYPE = 'Photo Gallery' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/photogallary.png'
    WHEN an.NOTIFICATION_TYPE = 'Notification' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/notification.png'
    WHEN an.NOTIFICATION_TYPE = 'Student Remarks' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/remarks.png'
    WHEN an.NOTIFICATION_TYPE = 'Leave Application' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/leave.png'
    WHEN an.NOTIFICATION_TYPE = 'Parent Communication' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/communication.png'
    ELSE 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/noimages.png'
END AS image,
CASE
    WHEN an.NOTIFICATION_TYPE = 'Homework' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/homework_side.png'
    WHEN an.NOTIFICATION_TYPE = 'Assignment' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    WHEN an.NOTIFICATION_TYPE = 'Circular' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    WHEN an.NOTIFICATION_TYPE = 'Photo Gallery' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    WHEN an.NOTIFICATION_TYPE = 'Video Gallery' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    WHEN an.NOTIFICATION_TYPE = 'Notification' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    WHEN an.NOTIFICATION_TYPE = 'Student Remarks' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    WHEN an.NOTIFICATION_TYPE = 'Leave Application' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    WHEN an.NOTIFICATION_TYPE = 'Parent Communication' THEN 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/side.png'
    ELSE 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/noimages.png'
END AS side_image,
CASE
    WHEN an.NOTIFICATION_TYPE = 'Homework' THEN '#e3cf0c'
    WHEN an.NOTIFICATION_TYPE = 'Assignment' THEN '#ea2225'
    WHEN an.NOTIFICATION_TYPE = 'Circular' THEN '#e3cf0c'
    WHEN an.NOTIFICATION_TYPE = 'Photo Gallery' THEN '#429ad0'
    ELSE ''
END as color_code
				FROM app_notification an
				LEFT JOIN tblstudent s ON s.id=an.STUDENT_ID
				WHERE an.student_id = '" . $student_id . "' AND an.syear = '" . $syear . "'
				ORDER BY an.ID DESC"); //(an.STUDENT_ID IN (SELECT id FROM tblstudent WHERE mobile='".$sms_mobile."') OR an.STUDENT_ID=0) AND s.SUB_INSTITUTE_ID = '".$sub_institute_id."'

			$res['status'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;
		} else {
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		return json_encode($res);
	}

	public function teacherStudentListAPI(Request $request)
	{

		try {
			if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
				return response()->json($response, 401);
			}
		} catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
			return response()->json($response, 401);
		}

		$type = $request->input("type");
		$teacher_id = $request->input("teacher_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$syear = $request->input("syear");

		if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('class_teacher as ct')
                ->join('standard as s', function ($join) {
                    $join->whereRaw("ct.standard_id = s.id AND ct.sub_institute_id = s.sub_institute_id");
                })->join('division as d', function ($join) {
                    $join->whereRaw("d.id = ct.division_id AND d.sub_institute_id = ct.sub_institute_id");
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.standard_id = ct.standard_id AND se.section_id = ct.division_id
                        AND se.sub_institute_id = ct.sub_institute_id AND se.end_date IS null");
                })->join('tblstudent as ts', function ($join) {
                    $join->whereRaw("ts.id = se.student_id AND ts.sub_institute_id = ct.sub_institute_id");
                })->selectRaw("ts.id,concat_ws(' ',ts.first_name,ts.last_name) as student_name,
                    ts.enrollment_no,se.roll_no,ts.mobile,ts.email,ct.standard_id,ct.division_id,s.name AS standard_name,
                    d.name AS division_name")
                ->where('ct.sub_institute_id', $sub_institute_id)
                ->where('ct.syear', $syear)
                ->where('se.syear', $syear)
                ->where('ct.teacher_id', $teacher_id)->orderBy('se.roll_no', 'ASC')->get()->toArray();//ts.middle_name,

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}

        return json_encode($res);
	}

	public function allStudentListAPI(Request $request)
	{

		try {
			if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
				return response()->json($response, 401);
			}
		} catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
			return response()->json($response, 401);
		}

		$type = $request->input("type");
		$teacher_id = $request->input("teacher_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$syear = $request->input("syear");
		$standard_id = $request->input("standard_id");
		$division_id = $request->input("division_id");

		if ($sub_institute_id != "" && $syear != "") {
            $data = DB::table('tblstudent as ts')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.student_id = ts.id AND se.sub_institute_id = ts.sub_institute_id AND se.end_date IS null");
                })->join('standard as s', function ($join) {
                    $join->whereRaw("se.standard_id = s.id AND se.sub_institute_id = s.sub_institute_id AND s.grade_id=se.grade_id");
                })->join('division as d', function ($join) {
                    $join->whereRaw("d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id");
                })->selectRaw("ts.id,concat_ws(' ',ts.first_name,ts.last_name) as student_name,
                    ts.enrollment_no,se.roll_no,ts.dob,ts.address,ts.mobile,ts.email,if(ts.image = '','https://".$_SERVER['SERVER_NAME']."/storage/student/noimages.png',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',ts.image)) as student_image,se.standard_id,
                    se.section_id AS division_id,s.name AS standard_name,d.name AS division_name")
                ->where('ts.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear);//ts.middle_name,
            if ($standard_id) {
                $data = $data->where('se.standard_id', $standard_id);
            }
            if ($division_id) {
                $data = $data->where('se.section_id', $division_id);
            }

            $data = $data->orderBy('se.roll_no', 'ASC')->get()->toArray();

            if (count($data) > 0) {
                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $data;
            } else {
                $res['status'] = 0;
                $res['message'] = "No Record";
            }
        } else {
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		return json_encode($res);
	}

	public function teacherAnnoucementAPI(Request $request)
	{

		try {
			if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
				return response()->json($response, 401);
			}
		} catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
			return response()->json($response, 401);
		}

		$type = $request->input("type");
		$teacher_id = $request->input("teacher_id");
		$annoucement_type = $request->input("annoucement_type");
		$sub_institute_id = $request->input("sub_institute_id");
		$syear = $request->input("syear");

		if ($teacher_id != "" && $sub_institute_id != "" && $syear != "" && $annoucement_type != "") {
			if ($annoucement_type == 'SMS') {
                $data = DB::table('sms_sent_staff as ss')
                    ->selectRaw('ss.id,ss.staff_id,ss.sms_text,ss.sms_no,ss.module_name,ss.created_on')
                    ->where('ss.staff_id', $teacher_id)
                    ->where('ss.sub_institute_id', $sub_institute_id)
                    ->orderBy('ss.id', 'DESC')->get()->toArray();
            }

			if ($annoucement_type == 'Notification') {
                $data = DB::table('app_notification_teacher as an')
                    ->selectRaw("an.ID,an.NOTIFICATION_TYPE, DATE_FORMAT(an.NOTIFICATION_DATE,'%d-%m-%Y')
                        AS NOTIFICATION_DATE,an.TEACHER_ID,an.SUB_INSTITUTE_ID,an.Status as STATUS,an.NOTIFICATION_DESCRIPTION")
                    ->where('an.TEACHER_ID', $teacher_id)
                    ->where('an.SUB_INSTITUTE_ID', $sub_institute_id)
                    ->orderBy('an.ID', 'DESC')->get()->toArray();
            }
			if (count($data) > 0) {
				$res['status'] = 1;
				$res['message'] = "Success";
				$res['data'] = $data;
			} else {
				$res['status'] = 0;
				$res['message'] = "No record";
			}
		} else {
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		return json_encode($res);
	}

	public function ajax_getBatch(Request $request)
	{
		$div_id = $request->input("div_id");
		$std_id = $request->input("std_id");
		$sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");
        if($request->has('type') && $request->type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
		$batchData = batchModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id, 'division_id' => $div_id, 'syear' => $syear])
			->get()->toArray();

		return $batchData;
	}

	public function ajax_getOptionalSubject(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        if($request->has('type') && $request->type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        }
        return sub_std_mapModel::select('sub_std_map.*', 'subject.subject_name', 'subject.subject_code')
            ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
            ->where([
                'sub_std_map.sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id,
                'elective_subject'             => 'Yes',
            ])
            ->get()->toArray();
    }

	public function ajax_checkEmailExist(Request $request)
	{
		$email = $request->input("email");
		// $sql = "SELECT id,email,'student' as user_type FROM tblstudent WHERE email = '" . $email . "'
        //         UNION
        //         SELECT id,email,'user' as user_type FROM tbluser WHERE email = '" . $email . "' ";

        // $check_user_sql = DB::select($sql);
        $check_user_sql = DB::table('tblstudent')
        ->select('id', 'email', DB::raw("'student' as user_type"))
        ->where('email', $email)
        ->union(
            DB::table('tbluser')
                ->select('id', 'email', DB::raw("'user' as user_type"))
                ->where('email', $email)
        )
        ->get();

		if (count($check_user_sql) == 0) {
			return 0;
		} else {
			return 1;
		}
	}

	public function ajax_checkDivisionCapacity(Request $request)
    {
        $syear = session()->get("syear");
        $sub_institute_id = session()->get("sub_institute_id");
        $std_id = $request->input("std_id");
        $division_id = $request->input("division_id");

        $check_div_capacity = DB::table('division_capacity_master as d')
            ->leftJoin('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->whereRaw("d.standard_id = se.standard_id AND d.division_id = se.section_id AND d.sub_institute_id = se.sub_institute_id AND se.syear = '".$syear."' AND se.end_date IS NULL");
            })->leftJoin('tblstudent as s', function ($join) {
                $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
            })->selectRaw("COUNT(se.id) AS enrolled_student, d.capacity AS total_capacity,
                (d.capacity - COUNT(se.id)) AS remaining_capacity")
            ->where('d.standard_id', $std_id)
            ->where('d.division_id', $division_id)
            ->where('d.syear', $syear)
            ->where('d.sub_institute_id', $sub_institute_id)->get()->toArray();

        $total_capacity = '';
        if (isset($check_div_capacity[0]->total_capacity)) {
            $total_capacity = $check_div_capacity[0]->total_capacity;
        }

        $remaining_capacity = '';
        if (isset($check_div_capacity[0]->remaining_capacity)) {
            $remaining_capacity = $check_div_capacity[0]->remaining_capacity;
        }

        return $total_capacity."/".$remaining_capacity;
	}

	public function ajax_StatewiseCity(Request $request)
    {
        $state_name = $request->input("state_name");

        return tblcityModel::where(['state_name' => $state_name])->get()->toArray();
    }   

    public function checkExists(Request $request){
       
        $sub_institute_id = session()->get('sub_institute_id');
        if($request->has('type') && $request->type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        }

        $response = DB::table('tblstudent as ts')
        ->join('tblstudent_enrollment as se', function ($join) {
            $join->whereRaw("se.student_id = ts.id AND se.sub_institute_id = ts.sub_institute_id AND se.end_date IS null");
        })
        ->join('academic_section as g','g.id','=','se.grade_id')
        ->join('standard as s', function ($join) {
            $join->whereRaw("se.standard_id = s.id AND se.sub_institute_id = s.sub_institute_id AND s.grade_id=se.grade_id");
        })->join('division as d', function ($join) {
            $join->whereRaw("d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id");
        })
        ->leftJoin('student_quota as q','q.id','=','se.student_quota')
        ->leftJoin('batch as b','b.id','=','ts.studentbatch')
        ->selectRaw("ts.id,concat_ws(' ',COALESCE(ts.first_name,'-'),COALESCE(ts.last_name,'-')) as student_name,ts.mobile,ts.dob,ts.enrollment_no,IFNULL(ts.house,'-') as house,IFNULL(b.title,'-') as batch,g.title as grade,s.name as standard,d.name as division,IFNULL(q.title,'-') as quota,IFNULL(ts.uniqueid,'-') as uniqueid,IFNULL(ts.nationality,'-') as nationality,(CASE WHEN se.end_date IS NULL THEN 'active' ELSE 'inactive' END) AS status")
        ->where('ts.sub_institute_id', $sub_institute_id)
        ->when($request->quota,function($query) use($sub_institute_id,$request){
            $query->where(['ts.first_name'=>$request->first_name,'ts.enrollment_no'=>$request->enrollment,'se.student_quota'=>$request->quota]);
        }, function($query) use($sub_institute_id,$request){
            $query->where(['ts.first_name'=>$request->first_name,'ts.last_name'=>$request->last_name,'ts.mobile'=>$request->mobile]);
        })
       ->first();

        return  response()->json($response);
    }
}
