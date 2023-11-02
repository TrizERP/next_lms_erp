<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\front_desk\circular\circularController;
use App\Http\Controllers\front_desk\photo_video_gallary\photo_video_gallaryController;
use App\Http\Controllers\inventory\requisitionController;
use App\Http\Controllers\result\cbse_result\cbse_1t5_result_controller;
use App\Http\Controllers\result\cbse_result\WRT_progress_report_controller;
use App\Models\attendanceJsonResultModel;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use App\Models\frontdesk\complaintModel;
use App\Models\frontdesk\taskModel;
use App\Models\inventory\requisitionModel;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\proxyModel;
use App\Models\school_setup\SchoolModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\studentCaptureAttendanceModel;
use App\Models\studentCapturePhotosModel;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use PHPMailer\PHPMailer;
use function App\Helpers\htmlToPDF;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;
use function App\Helpers\sendSMS;

class adminapiController extends Controller
{
    use GetsJwtToken;

    public function admin_login(Request $request, JwtToken $jwt)
    {
        $send_data = [];
        $response = ['status' => '0', 'message' => 'User Not Found', 'data' => $send_data];
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {

            $data = DB::table('tbluser as u')
                ->join('tbluserprofilemaster as p', function ($join) {
                    $join->whereRaw("p.sub_institute_id = u.sub_institute_id and u.user_profile_id = p.id and p.parent_id = 1");
                })->join('school_setup as ss', function ($join) {
                    $join->whereRaw("ss.id = u.sub_institute_id");
                })->selectRaw("u.id,u.user_name,u.first_name,u.middle_name,u.last_name,u.sub_institute_id,
                    u.email,u.mobile,u.birthdate,u.address,u.gender,u.join_year,
                    if(u.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,
                    p.name as user_profile_name,u.user_profile_id,ss.syear,ss.SchoolName,ss.Logo")
                ->where('u.status', '1')
                ->where('u.mobile', $_REQUEST['mobile'])->get()->toArray();

            $data = json_decode(json_encode($data), true);
            $data = $data[0];

            if (isset($data['id']) && $data['id'] != '') {

                // send otp
                $otp = rand(100000, 999999);

                $sub_institute_id = $data['sub_institute_id'];
                if ($_REQUEST['mobile'] == '9979176562') {
                    $otp = "123456";
                } else {
                    //$text = "Dear Parent, Your OTP is ".$otp;
                    if ($sub_institute_id == 49 || $sub_institute_id == 232 || $sub_institute_id == 233) {
                        $text = "Dear Teacher your 0TP is ".$otp;
                    } else {
                        $text = "OTP for login is ".$otp." and is valid for 5 minutes";
                    }

                    $res = $this->sendSMS($_REQUEST['mobile'], $text, $sub_institute_id);
                    if ($res["error"] == 1) {
                        $errorMessage = "Please add api details first.";
                        if ($res["error"] == $errorMessage) {
                            $otp = "123456";
                        }
                    }
                }
//DB::enableQueryLog();
                $data = DB::table("tbluser AS tu")
                    ->join('tbluserprofilemaster AS tpm', 'tpm.id', '=', 'tu.user_profile_id')
                    ->where(["tu.mobile" => $_REQUEST['mobile'], "tpm.parent_id" => '1'])
                    ->update(["tu.otp" => $otp]);
//dd(DB::getQueryLog($data));                    
//echo "<pre>";
//print_r($data);
//exit();
                $response['status'] = '1';
                $response['message'] = 'success';
            }
        }

        return json_encode($response);
    }

    public function admin_check_otp(Request $request, JwtToken $jwt)
    {
        $send_data = [];
        $response = ['status' => '0', 'message' => 'Invalid', 'data' => $send_data];
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|numeric',
            'otp'    => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $data =DB::table('tbluser as u')
                ->join('tbluserprofilemaster as p', function ($join) {
                    $join->whereRaw("p.sub_institute_id = u.sub_institute_id and u.user_profile_id = p.id and p.parent_id = 1");
                })
                ->join('school_setup as ss', function ($join) {
                    $join->whereRaw("ss.id = u.sub_institute_id");
                })
                ->selectRaw("u.id,u.user_name,u.first_name,u.middle_name,u.last_name,u.sub_institute_id,
                    u.email,u.mobile,u.birthdate,u.address,u.gender,u.join_year,
                    if(u.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,
                    p.name as user_profile_name,u.user_profile_id,ss.syear,ss.SchoolName,ss.Logo")
                ->where('u.status', '1')
                ->where('u.otp', $_REQUEST['otp'])
                ->where('u.mobile', $_REQUEST['mobile'])->get()->toArray();

            $data = json_decode(json_encode($data), true);
            if(!empty($data)) {
                    $data = $data[0];
                    $payload = array();

                $time = time() + (60 * 60 * 24 * 30);
                $payload = [
                    'exp'              => $time,
                    'user_id'          => $data['id'],
                    'sub_institute_id' => $data['sub_institute_id'],
                    'mobile'           => $data['mobile'],
                ];

                $token = $jwt->createToken($payload);

                $school_logo = 'https://'.$_SERVER['SERVER_NAME'].'/admin_dep/images/'.$data['Logo'];

                $term_data = DB::table("academic_year")->select('term_id', 'title', 'syear', 'start_date', 'end_date')
                    ->where(["sub_institute_id" => $data['sub_institute_id'], "syear" => $data['syear']])
                    ->get()->toArray();

                $send_data = [
                    'user_id'           => $data['id'],
                    'user_name'         => $data['user_name'],
                    'first_name'        => $data['first_name'],
                    'middle_name'       => $data['middle_name'],
                    'last_name'         => $data['last_name'],
                    'sub_institute_id'  => $data['sub_institute_id'],
                    'email'             => $data['email'],
                    'mobile'            => $data['mobile'],
                    'birthdate'         => $data['birthdate'],
                    'address'           => $data['address'],
                    'gender'            => $data['gender'],
                    'image'             => $data['image'],
                    'join_year'         => $data['join_year'],
                    'school_logo'       => $school_logo,
                    'school_name'       => $data['SchoolName'],
                    'user_profile_name' => $data['user_profile_name'],
                    'user_profile_id'   => $data['user_profile_id'],
                    'syear'             => $data['syear'],
                    'term_data'         => $term_data,
                    'token'             => $token,
                ];

                $response['status'] = '1';
                $response['message'] = 'success';
                $response['data'] = $send_data;
            } else {
                $response['status'] = '0';
                $response['message'] = 'Failed';
            }

        }

        return json_encode($response);
    }

    public function get_Syear(Request $request)
    {

        $sub_institute_id = $request->input("sub_institute_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('academic_year')
                ->selectRaw("CONCAT_WS('-',syear,RIGHT(syear+1,2)) AS title,syear,CASE WHEN does_grades = 'Y' 
                    THEN 'selected' ELSE '' END AS selected")
                ->where('sub_institute_id', $sub_institute_id)
                ->groupBy('syear')->get()->toArray();
            $data = json_decode(json_encode($data), true);

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminAcademicSection(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }

        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminStandard(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $grade_id = $request->input("grade_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'grade_id'         => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = standardModel::where([
                'grade_id' => $grade_id, 'sub_institute_id' => $sub_institute_id,
            ])->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminDivision(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $standard_id = $request->input("standard_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'standard_id'      => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = std_div_mappingModel::select('division.id', 'division.name')
                ->join('division', 'division.id', '=', 'std_div_map.division_id')
                ->where(['standard_id' => $standard_id, 'std_div_map.sub_institute_id' => $sub_institute_id])
                ->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminSubject(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $standard_id = $request->input("standard_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'standard_id'      => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('sub_std_map')
                ->join('standard', function ($join) {
                    $join->whereRaw('standard.id = sub_std_map.standard_id');
                })
                ->selectRaw("sub_std_map.subject_id,sub_std_map.display_name as subject_name,standard.id as standard_id,
                    standard.name as standard_name,if(sub_std_map.display_image = '','',
                    concat('https://".$_SERVER['SERVER_NAME']."/storage',sub_std_map.display_image)) as subject_image,
                    sub_std_map.subject_category")
                ->where('sub_std_map.status', 1)
                ->where('sub_std_map.standard_id', $standard_id)
                ->where('sub_std_map.sub_institute_id', $sub_institute_id)
                ->orderBy('sub_std_map.sort_order')->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminStudentList(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $grade_id = $request->input("grade_id");
        $standard_id = $request->input("standard_id");
        $division_id = $request->input("division_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $extra = '';
            if ($grade_id != '') {
                $extra .= "AND se.grade_id = '".$grade_id."' ";
            }
            if ($standard_id != '') {
                $extra .= "AND se.standard_id = '".$standard_id."' ";
            }
            if ($division_id != '') {
                $extra .= "AND se.section_id = '".$division_id."' ";
            }

            $data = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('s.id = se.student_id AND se.end_date is NULL');
                })->join('academic_section as ac', function ($join) {
                    $join->whereRaw('ac.id = se.grade_id AND ac.sub_institute_id = s.sub_institute_id');
                })->join('standard as st', function ($join) {
                    $join->whereRaw('st.id = se.standard_id');
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->selectRaw("s.id, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
                    se.syear,s.enrollment_no,s.roll_no,s.dob,s.address,s.mobile,s.email,
                    if(s.image = '','https://".$_SERVER['SERVER_NAME']."/storage/student/noimages.png',
                    concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',s.image)) as student_image,se.standard_id,
                    se.section_id AS division_id,ac.title AS academic_section,st.name AS standard_name,d.name AS division_name,
                    s.gender,s.admission_year,s.mother_name,s.father_name")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                ->where(function ($q) use ($grade_id, $standard_id, $division_id) {
                    if ($grade_id != '') {
                        $q->where('se.grade_id', $grade_id);
                    }
                    if ($standard_id != '') {
                        $q->where('se.standard_id', $standard_id);
                    }
                    if ($division_id != '') {
                        $q->where('se.section_id', $division_id);
                    }
                })->groupBy('s.roll_no')->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminTeacherList(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('tbluser as u')
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = u.user_profile_id AND up.sub_institute_id = u.sub_institute_id");
                })
                ->selectRaw("u.id,u.user_name,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS user_full_name,
                    u.first_name,u.middle_name,u.last_name,u.sub_institute_id,u.email,u.mobile,u.birthdate,u.address,u.gender,
                    u.join_year,if(u.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,
                    up.name as user_profile_name,u.user_profile_id")
                ->where('u.sub_institute_id', $sub_institute_id)
                ->where('up.name', 'like', '%Teacher%')->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminLeaveApplicationListAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('leave_applications as la')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.student_id = la.student_id AND la.sub_institute_id = se.sub_institute_id AND se.syear = la.syear AND se.end_date is NULL");
                })->join('tblstudent as s', function ($join) {
                    $join->whereRaw("s.id = se.student_id and s.sub_institute_id = se.sub_institute_id");
                })->join('standard as st', function ($join) {
                    $join->whereRaw("st.id = se.standard_id");
                })->join('division as di', function ($join) {
                    $join->whereRaw("di.id = se.section_id");
                })->leftJoin('tbluser as u', function ($join) {
                    $join->whereRaw("u.id = la.reply_by");
                })
                ->selectRaw("la.id as leave_app_id,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name,
                    if(s.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',s.image)) as student_image,
                    st.name as std_name,la.title,la.message,if(files = '','',
                    concat('https://".$_SERVER['SERVER_NAME']."/storage/leave_application/',files)) as file_name,
                    la.apply_date,la.reply,la.reply_on,concat_ws(' ',u.first_name,u.middle_name,u.last_name) as reply_by,la.`status`")
                ->where('la.syear', $syear)
                ->where('la.sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_adminLeaveApplicationSaveAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $admin_id = $request->input("admin_id");
        $leave_app_id = $request->input("leave_app_id");
        $reply = $request->input("reply");
        $status = $request->input("status");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'admin_id'         => 'required|numeric',
            'reply'            => 'required',
            'status'           => 'required',
            'leave_app_id'     => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            DB::table('leave_applications')
                ->where(['id' => $leave_app_id, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear])
                ->update([
                    'reply'    => $reply,
                    'status'   => $status,
                    'reply_on' => date('Y-m-d H:i:s'),
                    'reply_by' => $admin_id,
                ]);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_adminParentCommunicationListAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('parent_communication as pc')
                ->join('tblstudent as ts', function ($join) {
                    $join->whereRaw("ts.id = pc.student_id AND pc.sub_institute_id = ts.sub_institute_id");
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.student_id = ts.id AND se.sub_institute_id = ts.sub_institute_id AND se.end_date is NULL");
                })->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id");
                })->join('division as d', function ($join) {
                    $join->whereRaw("d.id = se.section_id");
                })->leftJoin('tbluser as tu', function ($join) {
                    $join->whereRaw("tu.id = pc.reply_by");
                })
                ->selectRaw("pc.id as parent_comm_id,concat_ws(' ',ts.first_name,ts.middle_name,ts.last_name) as student_name,
                    if(ts.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',ts.image)) as student_image,
                    ts.enrollment_no,ts.mobile,se.standard_id,se.section_id as division_id,ts.email,s.name AS standard_name,
                    d.name AS division_name,pc.message,date_format(pc.date_,'%d-%m-%Y') as parent_comm_date,pc.reply,
                    concat_ws(' ',tu.first_name,tu.middle_name,tu.last_name) as reply_by,pc.reply_on")
                ->where('pc.syear', $syear)
                ->where('se.syear', $syear)
                ->where('pc.sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_adminParentCommunicationSaveAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $admin_id = $request->input("admin_id");
        $parent_communication_id = $request->input("parent_communication_id");
        $reply = $request->input("reply");
        $syear = $request->input("syear");
        $sub_institute_id = $request->input("sub_institute_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id'        => 'required|numeric',
            'syear'                   => 'required|numeric',
            'admin_id'                => 'required|numeric',
            'reply'                   => 'required',
            'parent_communication_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $updated = DB::table('parent_communication')
                ->where(['id' => $parent_communication_id, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear])
                ->update([
                    'reply'    => $reply,
                    'reply_on' => date('Y-m-d H:i:s'),
                    'reply_by' => $admin_id,
                ]);

            if ($updated) {
                $response['status'] = 1;
                $response['message'] = "Record Added";
            } else {
                $response['status'] = 0;
                $response['message'] = "Not Added";
            }


        }

        return json_encode($response);
    }

    public function get_adminPhotoVideoAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $type = $request->input("type");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'type'             => 'required|in:Photo,Video',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $server = "http://".$_SERVER['HTTP_HOST'];

            $data = DB::table('photo_video_gallary as p')
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = p.standard_id");
                })->join('division as d', function ($join) {
                    $join->whereRaw("d.id = p.division_id");
                })
                ->selectRaw("p.*,s.name as standard_name,d.name as division_name,if(p.file_name IS NULL OR p.file_name='',
                    '-',if(p.`type`='Video',p.file_name, CONCAT('$server/storage/photo_video_gallary/',p.file_name))) as file_name ")
                ->where('p.syear', $syear)
                ->where('p.sub_institute_id', $sub_institute_id)
                ->where('p.type', $type)
                ->groupBy('album_title', 'file_name')
                ->orderBy('p.standard_id')->get()->toArray();

            foreach ($data as $key => $val) {
                $new_data[$val->album_title][] = $val;
            }

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['data'] = $new_data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_adminPhotoVideoAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $response = [];
        $types_req = "";
        if($request->type=="Photo"){
            $types_req="attachment";
        }else if($request->type=="Video"){
            $types_req="youtube_link";
        }
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'user_id'          => 'required|numeric',
            'standard_id'      => 'required',
            'division_id'      => 'required',
            'date_'            => 'required|date',
            'title'            => 'required',
            'album_title'      => 'required',
            'type'             => 'required|in:Photo,Video',
            $types_req       => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $standard = explode(",", $_REQUEST['standard_id']);
            $division = explode(",", $_REQUEST['division_id']);
            unset($_REQUEST['standard_id'], $_REQUEST['division_id']);

            $_REQUEST['standard'] = $standard;
            $_REQUEST['division'] = $division;
            $_REQUEST['action'] = "API";

            $ph_object = new photo_video_gallaryController;
            $result = $ph_object->store($request);  
            
            if ($result == 1) {
                $response['status'] = 1;
                $response['message'] = "Record Added";
            } else {
                $response['status'] = 0;
                $response['message'] = "Record Not Added";
            }
        }

        return json_encode($response);
    }

    public function get_adminPTMBookingTodaysListAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $current_date = date('Y-m-d');

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table('ptm_booking_master as pb')
                ->join('ptm_time_slots_master as ps', function ($join) {
                    $join->whereRaw('ps.id= pb.TIME_SLOT_ID');
                })->join('standard as cs', function ($join) {
                    $join->whereRaw('cs.id = ps.standard_id');
                })->join('tblstudent as s', function ($join) {
                    $join->whereRaw('s.id = pb.STUDENT_ID and s.sub_institute_id = pb.SUB_INSTITUTE_ID');
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id and se.sub_institute_id = s.sub_institute_id AND se.end_date is NULL');
                })->leftJoin('tbluser as u', function ($join) {
                    $join->whereRaw('u.id = pb.PTM_ATTENDED_BY and u.sub_institute_id = pb.SUB_INSTITUTE_ID');
                })
                ->selectRaw("pb.ID,pb.DATE,pb.TEACHER_ID,pb.TIME_SLOT_ID,pb.CONFIRM_STATUS,pb.STUDENT_ID,pb.CREATED_ON,
                    pb.SUB_INSTITUTE_ID,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as PTM_ATTENDED_BY_NAME,
                    pb.PTM_ATTENDED_STATUS,pb.PTM_ATTENDED_REMARKS,pb.PTM_ATTENDED_ENTRY_DATE,
                    ps.from_time AS FROM_TIME,ps.to_time AS TO_TIME,ps.ptm_date AS PTM_DATE")
                ->where('pb.SUB_INSTITUTE_ID', $sub_institute_id)
                ->where('pb.DATE', $current_date)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminVisitorListAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('visitor_master as v')
                ->join('visitor_type as vt', function ($join) {
                    $join->whereRaw('vt.id = v.visitor_type AND vt.sub_institute_id = v.sub_institute_id AND vt.status = 1');
                })->join('tbluser as u', function ($join) {
                    $join->whereRaw('u.id = v.to_meet AND u.sub_institute_id = v.sub_institute_id');
                })
                ->selectRaw("v.*,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as staff_name,vt.title as visitor_type_name,
                    if(v.photo = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/visitor_photo/',v.photo)) as visitor_photo")
                ->where('v.sub_institute_id', $sub_institute_id)
                ->whereBetween('v.meet_date', [$from_date, $to_date])->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminTodaysProxyListAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $from_date = $request->input('from_date');//date('Y-m-d');
            $to_date = $request->input('to_date');//date('Y-m-d');
            $sub_institute_id = $request->input('sub_institute_id');

            $proxydata = [];

            $proxydata = proxyModel::select('proxy_master.*', 's.name as standard_name', 'd.name as division_name',
                DB::raw('concat_ws(" ",u.first_name,u.middle_name,u.last_name) as teacher_name'),
                DB::raw('concat_ws(" ",u1.first_name,u1.middle_name,u1.last_name) as proxy_teacher_name'),
                'p.title as period_name', DB::raw('concat(sub.subject_name,"(",sub.subject_code,")") as sub_name'))
                ->join('standard as s', 's.id', '=', 'proxy_master.standard_id')
                ->join('division as d', 'd.id', '=', 'proxy_master.division_id')
                ->join('tbluser as u', 'u.id', '=', 'proxy_master.teacher_id')
                ->join('tbluser as u1', 'u1.id', '=', 'proxy_master.proxy_teacher_id')
                ->join('period as p', 'p.id', '=', 'proxy_master.period_id')
                ->join('subject as sub', 'sub.id', '=', 'proxy_master.subject_id')
                ->where(['proxy_master.sub_institute_id' => $sub_institute_id])
                ->whereBetween('proxy_date', [$from_date, $to_date])
                ->get();

            if (count($proxydata) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $proxydata;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_adminSendSmsAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $admin_id = $request->input("admin_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $mobile_number = $request->input("mobile_number");
        $sms_text = $request->input("sms_text");
        $syear = $request->input("syear");


        $response = [];
        $validator = Validator::make($request->all(), [
            'admin_id'         => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'mobile_number'    => 'required',
            'sms_text'         => 'required',
            'syear'            => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $error = 1;
            foreach ($mobile_number as $student_id => $number) {
                $response1 = sendSMS($number, $sms_text, $sub_institute_id);
                if ($response1['error'] == 1) {
                    $error = 0;
                    break;
                } else {
                    DB::table('sms_sent_parents')->insert([
                        'SYEAR'            => $syear,
                        'STUDENT_ID'       => $student_id,
                        'SMS_TEXT'         => $sms_text,
                        'SMS_NO'           => $number,
                        'MODULE_NAME'      => 'SENT SMS PARENT',
                        'sub_institute_id' => $sub_institute_id,
                    ]);
                }
            }

            if ($error == 0) {
                $response['status_code'] = 0;
                $response['message'] = $response1['message'];
            } else {
                $response['status_code'] = 1;
                $response['message'] = "Successfully Sent SMS";
            }
        }

        return json_encode($response);
    }

    public function add_adminSendEmailAPI(Request $request)
    {
        $path = "";
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $admin_id = $request->input('admin_id');
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        if ($request->hasFile('fileToUpload')) {
            $file = $request->file('fileToUpload');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('fileToUpload').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = "email_".$name.'.'.$ext;
            $path = $file->storeAs('public/email', $file_name);
        }
        if ($path != "") {
            $filePath = storage_path()."/app/".$path;
            $path = $filePath;
        }
        $where_arr = [
            "sub_institute_id" => $sub_institute_id,
        ];

        $smtp_details = DB::table('smtp_details')
            ->where($where_arr)
            ->get();

        if (count($smtp_details) > 0) {
            $emails = $request->input('all_email');
            $to_arr = explode(',', $emails);

            $subject = $request->input('example-subject');
            $message = $request->input('content');
            $attechment = $path;
            $ip = \Request::ip();

            DB::table('email_sent_parents')->insert([
                'SYEAR'            => $syear,
                'EMAIL'            => $emails,
                'SUBJECT'          => $subject,
                'EMAIL_TEXT'       => $message,
                'ATTECHMENT'       => $attechment,
                'USER_ID'          => $admin_id,
                'IP'               => $ip,
                'sub_institute_id' => $sub_institute_id,
            ]);

            $from = $smtp_details[0]->gmail;
            $from_pass = $smtp_details[0]->password;

            $mail = new PHPMailer\PHPMailer();
            $mail->IsSMTP();
            $mail->isHTML(true);
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "ssl";
            $mail->Host = $smtp_details[0]->server_address;
            $mail->Port = $smtp_details[0]->port;
            foreach ($to_arr as $id => $val) {
                $mail->AddAddress($val);
            }
            $mail->Username = $from;
            $mail->Password = $from_pass;
            $mail->SetFrom($from, $from);
            $mail->AddReplyTo($from, $from);
            $mail->addAttachment($attechment);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = $message;
            $mail->Send();

            $res = [
                "status_code" => 1,
                "message"     => "Email Sent",
            ];
        } else {
            $res = [
                "status_code" => 1,
                "message"     => "You did not setup mail client.",
            ];
        }

        return json_encode($res);
    }

    public function get_attendanceGraphAPI(Request $request)
    {
        $path = "";
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $admin_id = $request->input('admin_id');
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $date = $request->input("date");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'date'             => 'required|date',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $date = date("Y-m-d");

            $data = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("s.id = se.student_id AND se.syear = '".$syear."' AND se.end_date is NULL");
                })->join('academic_section as acs', function ($join) {
                    $join->whereRaw("acs.id = se.grade_id");
                })->join('standard as sm', function ($join) {
                    $join->whereRaw("se.standard_id = sm.id");
                })->join('division as dm', function ($join) {
                    $join->whereRaw("se.section_id = dm.id");
                })->leftJoin('attendance_student as a', function ($join) use ($date) {
                    $join->whereRaw("a.student_id = s.id and a.attendance_date = '".$date."'");
                })
                ->selectRaw("acs.title,sm.name AS standard_name,dm.name AS division_name,se.standard_id,se.section_id,
                    count(se.student_id) total_student,SUM(CASE WHEN a.attendance_code = 'A' THEN 1 ELSE 0 END) TA,
                    SUM(CASE WHEN a.attendance_code = 'P' THEN 1 ELSE 0 END) TP")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->groupByRaw("se.grade_id,se.standard_id,se.section_id")->get()->toArray();

            $chart_data = "[{
                id: '0.0',
                parent: '',
                name: 'Attendance Chart'
            },";

            $grades = [];
            foreach ($data as $id => $arr) {
                if (! in_array($arr->title, $grades)) {
                    $grades[] = $arr->title;
                    $chart_data .= "{";
                    $chart_data .= "id: "."'1.".count($grades)."',";
                    $chart_data .= "parent: '0.0',";
                    $chart_data .= "name: "."'".$arr->title."'";
                    $chart_data .= "},";
                }
            }

            $i = 1;
            $standards = [];
            foreach ($grades as $id => $val) {
                foreach ($data as $key => $arr) {
                    if ($arr->title == $val) {
                        if (! in_array($arr->standard_name, $standards)) {
                            $standards[] = $arr->standard_name;
                            $chart_data .= "{";
                            $chart_data .= "id: "."'2.".count($standards)."',";
                            $chart_data .= "parent: '1.".$i."',";
                            $chart_data .= "name: "."'".$arr->standard_name."'";
                            $chart_data .= "},";
                        }
                    }
                }
                $i++;
            }

            $divisioin = [];
            $temp = 0;
            foreach ($standards as $id => $val) {
                foreach ($data as $key => $arr) {
                    if ($arr->standard_name == $val) {
                        $divisioin[] = $arr->division_name;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'3.".count($divisioin)."',";
                        $chart_data .= "parent: '2.".($id + 1)."',";
                        $chart_data .= "name: "."'".$arr->division_name."',";
                        $chart_data .= "value: ".$arr->total_student;
                        $chart_data .= "},";

                        if ($arr->TA != 0 || $arr->TP != 0) {
                            $temp++;
                            $chart_data .= "{";
                            $chart_data .= "id: "."'4.".$temp."',";
                            $chart_data .= "parent: '3.".count($divisioin)."',";
                            $chart_data .= "name: 'Present',";
                            $chart_data .= "value: ".$arr->TP;
                            $chart_data .= "},";
                            $temp++;
                            $chart_data .= "{";
                            $chart_data .= "id: "."'4.".$temp."',";
                            $chart_data .= "parent: '3.".count($divisioin)."',";
                            $chart_data .= "name: 'Absent',";
                            $chart_data .= "value: ".$arr->TA;
                            $chart_data .= "},";
                        }
                    }
                }
            }

            $chart_data = rtrim($chart_data, ",");
            $chart_data .= "];";

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $chart_data;
        }

        return json_encode($response);
    }

    public function get_feesGraphAPI(Request $request)
    {
        $path = "";
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $admin_id = $request->input('admin_id');
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $date = $request->input("date");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'date'             => 'required|date',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $date = "2019-08-22";

            $data = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("se ON s.id = se.student_id AND se.syear = '".$syear."' AND se.end_date is NULL");
                })->join('academic_section as acs', function ($join) {
                    $join->whereRaw("acs.id = se.grade_id");
                })->join('standard as sm', function ($join) {
                    $join->whereRaw("se.standard_id = sm.id");
                })->join('division as dm', function ($join) {
                    $join->whereRaw("se.section_id = dm.id");
                })->join('fees_breackoff as fb', function ($join) {
                    $join->whereRaw("(fb.syear = se.syear AND fb.admission_year = s.admission_year AND fb.quota = se.student_quota 
                        AND fb.grade_id = se.grade_id AND fb.standard_id = se.standard_id)");
                })->whereRaw("LEFT JOIN (
                        select if(ifnull(sum(fp.amount),0)='',0,ifnull(sum(fp.amount),0)) tot_paid,se.grade_id,se.standard_id,se.section_id
                        from fees_collect fp
                        INNER JOIN tblstudent_enrollment se ON fp.student_id = se.student_id AND se.syear = '".$syear."' AND se.end_date is NULL
                        group by se.grade_id,se.standard_id,se.section_id
                    ) fp ON (fp.grade_id = se.grade_id AND fp.standard_id = se.standard_id AND fp.section_id = se.section_id)")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->groupByRaw("se.grade_id,se.standard_id,se.section_id")->get()->toArray();

            foreach ($data as $id => $arr) {
                if (
                    $arr->tot_paid == '' ||
                    $arr->tot_paid == ' ' ||
                    $arr->tot_paid == null
                ) {
                    $data[$id]->tot_paid = 0;
                }
            }

            $chart_data = "[{
                id: '0.0',
                parent: '',
                name: 'Fees Chart'
            },";

            $grades = [];
            foreach ($data as $id => $arr) {
                if (! in_array($arr->title, $grades)) {
                    $grades[] = $arr->title;
                    $chart_data .= "{";
                    $chart_data .= "id: "."'1.".count($grades)."',";
                    $chart_data .= "parent: '0.0',";
                    $chart_data .= "name: "."'".$arr->title."'";
                    $chart_data .= "},";
                }
            }


            $i = 1;
            $standards = [];
            foreach ($grades as $id => $val) {
                foreach ($data as $key => $arr) {
                    if ($arr->title == $val) {
                        if (! in_array($arr->standard_name, $standards)) {
                            $standards[] = $arr->standard_name;
                            $chart_data .= "{";
                            $chart_data .= "id: "."'2.".count($standards)."',";
                            $chart_data .= "parent: '1.".$i."',";
                            $chart_data .= "name: "."'".$arr->standard_name."'";
                            $chart_data .= "},";
                        }
                    }
                }
                $i++;
            }

            $divisioin = [];
            $temp = 0;
            foreach ($standards as $id => $val) {
                foreach ($data as $key => $arr) {
                    if ($arr->standard_name == $val) {
                        $divisioin[] = $arr->division_name;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'3.".count($divisioin)."',";
                        $chart_data .= "parent: '2.".($id + 1)."',";
                        $chart_data .= "name: "."'".$arr->division_name."',";
                        $chart_data .= "value: ".$arr->tot_amount;
                        $chart_data .= "},";

                        $temp++;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'4.".$temp."',";
                        $chart_data .= "parent: '3.".count($divisioin)."',";
                        $chart_data .= "name: 'Paid',";
                        $chart_data .= "value: ".$arr->tot_paid;
                        $chart_data .= "},";

                        $temp++;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'4.".$temp."',";
                        $chart_data .= "parent: '3.".count($divisioin)."',";
                        $chart_data .= "name: 'UnPaid',";
                        $chart_data .= "value: ".($arr->tot_amount - $arr->tot_paid);
                        $chart_data .= "},";
                    }
                }
            }

            $chart_data = rtrim($chart_data, ",");
            $chart_data .= "];";

            $res['chartData'] = $chart_data;

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $chart_data;
        }


        return json_encode($response);
    }

    public function get_taskAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('task as t')
                ->join('tbluser as u', function ($join) {
                    $join->whereRaw('t.TASK_ALLOCATED = u.id AND u.sub_institute_id = t.sub_institute_id');
                })->join('tbluser as u2', function ($join) {
                    $join->whereRaw('t.TASK_ALLOCATED_TO = u2.id AND u2.sub_institute_id = t.sub_institute_id');
                })->leftJoin('tbluser as u3', function ($join) {
                    $join->whereRaw('t.approved_by = u3.id AND u3.sub_institute_id = t.sub_institute_id');
                })
                ->selectRaw("t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS ALLOCATOR, 
                    CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS ALLOCATED_TO,
                    CONCAT_WS(' ',u3.first_name,u3.middle_name,u3.last_name) AS approved_by,
                    if(t.TASK_ATTACHMENT = '','',CONCAT('https://".$_SERVER['SERVER_NAME']."/storage/frontdesk/',t.TASK_ATTACHMENT)) 
                    as TASK_ATTACHMENT")
                ->where('t.syear', $syear)
                ->where('t.sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_taskAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $admin_id = $request->get('admin_id');
        $title = $request->get('title');
        $date = $request->get('date');
        $allocated_to = $request->get('allocated_to');
        $description = $request->get('description');

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'admin_id'         => 'required|numeric',
            'title'            => 'required',
            'date'             => 'required|date',
            'allocated_to'     => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $file_folder = $ext = $size = $newfilename = "";
            if ($request->hasFile('attachment')) {
                $img = $request->file('attachment');
                $filename = $img->getClientOriginalName();
                $ext = $img->getClientOriginalExtension();
                $size = $img->getSize();
                $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
                $file_folder = '/frontdesk';
                $img->storeAs('public/frontdesk/', $newfilename);
            }

            $data['SYEAR'] = $syear;
            $data['CREATED_BY'] = $admin_id;
            $data['TASK_ALLOCATED'] = $admin_id;
            $data['CREATED_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
            $data['CREATED_ON'] = date('Y-m-d H:i:s');
            $data['sub_institute_id'] = $sub_institute_id;
            $data['TASK_ALLOCATED_TO'] = $allocated_to;
            $data['TASK_TITLE'] = $title;
            $data['TASK_DESCRIPTION'] = $description;
            $data['TASK_DATE'] = $date;
            $data['TASK_ATTACHMENT'] = $newfilename;

            taskModel::insert($data);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_complaintAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('complaint as t')
                ->join('tbluser as u', function ($join) {
                    $join->whereRaw('t.COMPLAINT_BY = u.id AND u.sub_institute_id = t.sub_institute_id');
                })->join('tbluser as u3', function ($join) {
                    $join->whereRaw('t.COMPLAINT_SOLUTION_BY = u3.id AND u3.sub_institute_id = t.sub_institute_id');
                })
                ->selectRaw("t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS COMPLAINT_BY,
                    CONCAT_WS(' ',u3.first_name,u3.middle_name,u3.last_name) AS COMPLAINT_SOLUTION_BY,
                    if(t.ATTACHEMENT = '','',CONCAT('https://".$_SERVER['SERVER_NAME']."/storage/frontdesk/',t.ATTACHEMENT)) 
                    as COMPLAINT_ATTACHMENT")
                ->where('t.syear', $syear)
                ->where('t.sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
        exit;
    }

    public function add_complaintAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $admin_id = $request->get('admin_id');
        $title = $request->get('title');
        $date = $request->get('date');
        $allocated_to = $request->get('allocated_to');
        $description = $request->get('description');

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'admin_id'         => 'required|numeric',
            'title'            => 'required',
            'date'             => 'required|date',
            'allocated_to'     => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $file_folder = $ext = $size = $newfilename = "";
            if ($request->hasFile('attachment')) {
                $img = $request->file('attachment');
                $filename = $img->getClientOriginalName();
                $ext = $img->getClientOriginalExtension();
                $size = $img->getSize();
                $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
                $file_folder = '/frontdesk';
                $img->storeAs('public/frontdesk/', $newfilename);
            }

            $data['SYEAR'] = $syear;
            $data['COMPLAINT_BY'] = $admin_id;
            $data['SUB_INSTITUTE_ID'] = $sub_institute_id;
            $data['COMPLAINT_SOLUTION'] = "PENDING";
            $data['CREATED_IP'] = $_SERVER['REMOTE_ADDR'];
            $data['CREATED_DATE'] = date('Y-m-d H:i:s');
            $data['TITLE'] = $title;
            $data['DESCRIPTION'] = $description;
            $data['ATTACHEMENT'] = $newfilename;
            $data['DATE'] = $date;

            complaintModel::insert($data);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_adminAllRequisitionAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('inventory_requisition_details as ir')
                ->join('tbluser as tu', function ($join) {
                    $join->whereRaw("tu.id = ir.requisition_by");
                })->leftJoin('tbluser as ira', function ($join) {
                    $join->whereRaw("ira.id = ir.requisition_approved_by");
                })->join('inventory_item_master as i', function ($join) {
                    $join->whereRaw("i.id = ir.item_id");
                })->join('inventory_requisition_status_master as irs', function ($join) {
                    $join->whereRaw("irs.id = ir.requisition_status");
                })
                ->selectRaw("ir.id,CONCAT_WS(' ',tu.first_name,tu.middle_name,tu.last_name) AS requisition_by,
                ir.requisition_no,ir.requisition_date,i.title AS item_name,ir.item_qty,ir.item_unit,ir.expected_delivery_time,
                ir.remarks,irs.title AS requisition_status,CONCAT_WS(' ',ira.first_name,ira.middle_name,ira.last_name) 
                AS requisition_approved_by,ir.approved_qty,ir.requisition_approved_remarks,ir.requisition_approved_date")
                ->where('ir.sub_institute_id', $sub_institute_id)
                ->where('ir.syear', $syear)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminRequisitionByListAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table('tbluser')
                ->selectRaw("id as user_id,user_name,CONCAT_WS(' ',first_name,middle_name,last_name) AS requisition_name,mobile,email")
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function get_adminItemListAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('inventory_item_master')
                ->where('sub_institute_id', $sub_institute_id)->where('syear', $syear)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_adminRequisitionAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $requisition_by = $request->get('requisition_by');
        $requisition_date = date('Y-m-d H:i:s');
        $item_id = $request->get('item_id');
        $item_unit = $request->get('item_unit');
        $item_qty = $request->get('item_qty');
        $expected_delivery_time = $request->get('expected_delivery_time');
        $remarks = $request->get('remarks');
        $created_by = $request->get('created_by');
        $created_ip_address = $request->get('created_ip_address');

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'                  => 'required|numeric',
            'sub_institute_id'       => 'required|numeric',
            'requisition_by'         => 'required|numeric',
            'item_id'                => 'required|numeric',
            'item_qty'               => 'required|numeric',
            'expected_delivery_time' => 'required|date',
            'remarks'                => 'required',
            'created_ip_address'     => 'required',
            'created_by'             => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $requisition_controller = new requisitionController;

            $FORM_NO = $requisition_controller->generate_requisition_no($sub_institute_id, $syear);

            $requisition = new requisitionModel([
                'syear'                  => $syear,
                'sub_institute_id'       => $sub_institute_id,
                'requisition_no'         => $FORM_NO,
                'requisition_by'         => $requisition_by,
                'requisition_date'       => $requisition_date,
                'item_id'                => $item_id,
                'item_qty'               => $item_qty,
                'item_unit'              => $item_unit,
                'expected_delivery_time' => $expected_delivery_time,
                'requisition_status'     => 1,
                'remarks'                => $remarks,
                'created_by'             => $created_by,
                'created_ip_address'     => $created_ip_address,
            ]);

            $result = $requisition->save();

            if ($result == 1) {
                $response['status'] = 1;
                $response['message'] = "Record Added";
            } else {
                $response['status'] = 0;
                $response['message'] = "Record Not Added";
            }
        }

        return json_encode($response);
    }

    public function get_RequisitionStatusAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('inventory_requisition_status_master')->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function adminApprovedRequisitionAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $requisition_id = $request->get('requisition_id');
        $requisition_approved_by = $request->get('requisition_approved_by');
        $requisition_approved_date = date('Y-m-d H:i:s');
        $requisition_approved_remarks = $request->get('requisition_approved_remarks');
        $approved_qty = $request->get('approved_qty');
        $requisition_status = $request->get('requisition_status');

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'                   => 'required|numeric',
            'sub_institute_id'        => 'required|numeric',
            'requisition_id'          => 'required|numeric',
            'requisition_approved_by' => 'required|numeric',
            'approved_qty'            => 'required|numeric',
            'requisition_status'      => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $requisitionsArray = [];
            $requisitionsArray['requisition_approved_remarks'] = $requisition_approved_remarks;
            $requisitionsArray['approved_qty'] = $approved_qty;
            $requisitionsArray['requisition_status'] = $requisition_status;
            $requisitionsArray['requisition_approved_by'] = $requisition_approved_by;
            $requisitionsArray['requisition_approved_date'] = $requisition_approved_date;

            $updated = requisitionModel::where([
                "id"               => $requisition_id, 'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
            ])->update($requisitionsArray);

            if ($updated) {
                $response['status'] = 1;
                $response['message'] = "Requisition Status Update Successfully.";
            } else {
                $response['status'] = 0;
                $response['message'] = "Not Update";
            }
        }

        return json_encode($response);
    }

    public function get_adminCircularAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('circular as c')
                ->join('standard as s', function ($join) {
                    $join->whereRaw('s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id');
                })->selectRaw("*,if(c.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',c.file_name)) 
                    as file_name,s.name as std_name")
                ->where('c.syear', $syear)
                ->where('c.sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_adminCircularAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'            => 'required|numeric',
            'user_id'          => 'required|numeric',
            'standard_id'      => 'required',
            'division_id'      => 'required',
            'title'            => 'required',
            'type'             => 'required|in:1,2',
            'message'          => 'required',
            'date_'            => 'required|date',
            'sub_institute_id' => 'required|numeric',
        ]);//'attachment'       => 'required',

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $standard = explode(",", $_REQUEST['standard_id']);
            $division = explode(",", $_REQUEST['division_id']);
            unset($_REQUEST['standard_id']);
            unset($_REQUEST['division_id']);
            $_REQUEST['standard'] = $standard;
            $_REQUEST['division'] = $division;
            $_REQUEST['action'] = "API";

            $ph_object = new circularController;
            $result = $ph_object->store($request);

            if ($result == 1) {
                $response['status'] = 1;
                $response['message'] = "Record Added";
            } else {
                $response['status'] = 0;
                $response['message'] = "Record Not Added";
            }
        }

        return json_encode($response);
    }

    public function add_SendNotificationAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'user_id'          => 'required|numeric',
            'student_id'       => 'required',
            'description'      => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $student_id = explode(",", $_REQUEST['student_id']);
            $syear = $request->get('syear');
            $sub_institute_id = $request->get('sub_institute_id');
            $description = $request->get('description');
            $user_id = $request->get('user_id');

            $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
            $schoolName = $schoolData[0]['SchoolName'];
            $schoolLogo = '';

            $i = 0;
            foreach ($student_id as $key => $val) {
                $student_data = DB::table('tblstudent_enrollment as se')
                    ->join('tblstudent as s', function ($join) {
                        $join->whereRaw('s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id AND se.end_date is NULL');
                    })
                    ->selectRaw("*,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name")
                    ->where('s.id', $val)
                    ->where('se.syear', $syear)
                    ->whereNull('se.end_date')
                    ->where('se.sub_institute_id', $sub_institute_id)->get()->toArray();

                $student_mobile = $student_data[0]->mobile;

                $app_notification_content = [
                    'NOTIFICATION_TYPE'        => 'Notification',
                    'NOTIFICATION_DATE'        => now(),
                    'STUDENT_ID'               => $val,
                    'NOTIFICATION_DESCRIPTION' => $description,
                    'STATUS'                   => 0,
                    'SUB_INSTITUTE_ID'         => $sub_institute_id,
                    'SYEAR'                    => $syear,
                    'SCREEN_NAME'              => 'general',
                    'CREATED_BY'               => $user_id,
                    'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                ];

                $gcm_data = DB::table('gcm_users')
                    ->where('mobile_no', $student_mobile)
                    ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

                $gcmRegIds = [];
                if (count($gcm_data) > 0) {
                    foreach ($gcm_data as $key1 => $val1) {
                        $gcmRegIds[] = $val1->gcm_regid;
                    }
                }

                $pushMessage = $description;

                $bunch_arr = array_chunk($gcmRegIds, 1000);
                if (! empty($bunch_arr)) {
                    foreach ($bunch_arr as $bval) {
                        if (isset($bval, $pushMessage)) {
                            $type = 'Notification';
                            $message = [
                                'body'    => $pushMessage,
                                'TYPE'    => $type,
                                'USER_ID' => $student_id,
                                'title'   => $schoolName,
                                'image'   => $schoolLogo,
                            ];

                            $pushStatus = send_FCM_Notification($bval, $message, $sub_institute_id);
                            sendNotification($app_notification_content);
                        }
                    }
                    $i++;
                }
            }
            if (! empty($i)) {
                $response = [
                    "status"  => 1,
                    "message" => "Notification Sent successfully.",
                ];
            } else {
                $response = [
                    "status"  => 0,
                    "message" => "Notification Not Sent.",
                ];
            }
        }

        return json_encode($response);
    }

    public function get_wrtreportAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 200);
        }
        $payload = $this->jwtPayload();

        $response = ['status' => '1', 'message' => 'Success', 'data' => []];

        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $exam_type = $request->input("exam_type");

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'student_id'       => 'required|numeric',
            'syear'            => 'required',
            'from_date'        => 'required|date',
            'to_date'          => 'required|date',
            'exam_type'        => 'nullable',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $get_student_data = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('s.id = se.student_id AND se.end_date is NULL');
                })->join('academic_section as ac', function ($join) {
                    $join->whereRaw('ac.id = se.grade_id AND ac.sub_institute_id = s.sub_institute_id');
                })->join('standard as st', function ($join) {
                    $join->whereRaw('st.id = se.standard_id');
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })
                ->selectRaw("s.id,s.id AS student_id,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
                    se.syear,s.enrollment_no,s.roll_no,s.dob,s.address,s.mobile,s.email,if(s.image = '','',
                    concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',s.image)) as student_image,se.standard_id,
                    se.section_id AS division_id,ac.title AS academic_section,st.name AS standard_name,d.name AS division_name,
                    s.gender,s.admission_year,s.mother_name,s.father_name")
                ->where('s.id', $student_id)
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)->get()->toArray();

            $get_student_data = json_decode(json_encode($get_student_data), true);

            if (count($get_student_data) > 0) {
                $student_data = $get_student_data[0];

                $total_student_count = DB::table('tblstudent_enrollment as tt')
                    ->selectRaw('COUNT(id) AS total_student')
                    ->where('tt.standard_id', $student_data['standard_id'])
                    ->where('tt.section_id', $student_data['division_id'])
                    ->where('tt.sub_institute_id', $sub_institute_id)
                    ->where('tt.syear', $syear)
                    ->whereNull('tt.end_date')->get()->toArray();

                $total_student = $total_student_count[0]->total_student;

                $WRT_object = new WRT_progress_report_controller;
                $cbse_1t5_result_controller = new cbse_1t5_result_controller;
                $grade_arr = cbse_1t5_result_controller::getGradeScale($student_data['standard_id'], $type);

                //getting all exam master heading
                $all_exam_master = $WRT_object->getAllExamMaster($student_data['standard_id'], $from_date, $to_date, $type, $exam_type);

                //getting all exam marks        
                $all_WRT_data = $WRT_object->getWRTData($get_student_data, $student_data['standard_id'], $type);

                //getting result header        
                $header_data = $WRT_object->getHeader($student_data['standard_id'], $type);

                $next_year = $syear + 1;
                $result_year = $syear."-".$next_year;
                $left_logo = 'https://'.$_SERVER['SERVER_NAME'].'/storage/result/left_logo/'.$header_data['left_logo'];
                $right_logo = 'https://'.$_SERVER['SERVER_NAME'].'/storage/result/right_logo/'.$header_data['right_logo'];

                $html_data = '';

                $html_data .= '<table class="main-table ml-5 mr-5 mb-5" width="100%" style="border:1px solid #f37a0d;">
                            <tbody>
                                <tr>
                                    <td>
                                        <table class="report-card" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                            <tbody>
                                                <tr>
                                                     <td style="width: 165px;text-align: center;" align="left">
                                                        <img style="width: 100px;height: 90px;margin: 0;" src='.$left_logo.' alt="SCHOOL LOGO">
                                                     </td>
                                                     <td style="text-align:center !important;" align="center"> 
                                                        <span class="sc-hd">'.$header_data['line1'].'</span><br>   
                                                        <span class="ma-hd">'.$header_data['line2'].'</span><br>  
                                                        <span class="rg-hd">'.$header_data['line3'].'</span><br> 
                                                        <span class="rg-hd">'.$header_data['line4'].'</span><br>                                                            
                                                     </td>
                                                     <td style="width: 165px;text-align: center;" align="left">
                                                        <img style="width: 100px;height: 90px;margin: 0;" src='.$left_logo.' alt="SCHOOL LOGO">                                                            
                                                     </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4">
                                                        <hr></hr>
                                                    </td>
                                                </tr>                                                      
                                            </tbody>
                                        </table>
                                        <table class="report-card ml-4" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                            <tbody>
                                                <tr>
                                                    <td colspan="3" align="center">
                                                        <h3 style="font-size:14">WRT REPORT</h3>
                                                        <h3 style="font-size:14">SESSION '.$result_year.'</h3>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td width="60%">Student Name : <label>'.$student_data['student_name'].'</label></td>
                                                    <td width="20%"></td>
                                                    <td width="20%">Roll No. : <label>'.$student_data['roll_no'].'</label></td>
                                                </tr>
                                                <tr>
                                                    <td>Mother Name : <label>'.$student_data['mother_name'].'</label></td>
                                                    <td></td>
                                                    <td>Class : <label>'.$student_data['standard_name'].'</label></td>
                                                </tr>
                                                <tr>
                                                    <td>Father Name : <label>'.$student_data['father_name'].'</label></td>
                                                    <td></td>
                                                    <td>Division : <label>'.$student_data['division_name'].'</label></td>
                                                </tr>
                                                <tr>
                                                    <td>Date Of Birth : <label>'.$student_data['dob'].'</label></td>
                                                    <td></td>
                                                    <td>G.R. No. : <label>'.$student_data['enrollment_no'].'</label></td>
                                                </tr>
                                            </tbody>
                                        </table>';


                $sr = 1;
                $grand_total_points = 0;
                $grand_obtained_points = 0;

                if (isset($all_WRT_data[$student_id]) && count($all_WRT_data[$student_id]) > 0) {
                    $html_data .= '<table class="report-card ml-4 mr-4 mb-4" style="border-collapse:collapse;" width="100%" cellspacing="0" cellpadding="0">
                                            <tbody>                                                
                                                <tr>
                                                    <td colspan="2">
                                                        <table class="aca-year" style="border-collapse:collapse; border:1px solid #e68023;" width="100%" cellspacing="0" cellpadding="0" border="1">
                                                            <tr>
                                                                <th>Sr. No.</th>
                                                                <th>Test Name</th>
                                                                <th>Date</th>
                                                                <th>Day</th>
                                                                <th>Subject</th>
                                                                <th>Total Marks</th>
                                                                <th>Obt. Marks</th>
                                                                <th>Percentage (%)</th>
                                                                <th>Grade</th>
                                                            </tr>';
                    foreach ($all_WRT_data[$student_id] as $wkey => $wdata) {
                        $html_data .= '<tr>
                                                                    <td>'.$sr++.'</td>
                                                                    <td>'.$wdata['ExamTitle'].'</td>
                                                                    <td>'.$wdata['exam_date'].'</td>
                                                                    <td>'.$wdata['exam_day'].'</td>
                                                                    <td>'.$wdata['subject_name'].'</td>
                                                                    <td>'.$wdata['total_points'].'</td>
                                                                    <td>'.$wdata['obtained_points'].'</td>
                                                                    <td>'.$wdata['percentage'].'%</td>
                                                                    <td>'.$wdata['grade'].'</td>';

                        $grand_total_points += $wdata['total_points'];
                        $grand_obtained_points += $wdata['obtained_points'];
                        $grand_per = (($grand_obtained_points * 100) / $grand_total_points);
                        $grand_per = number_format($grand_per, 2);

                        $grand_grade = cbse_1t5_result_controller::getGrade($grade_arr, $grand_total_points,
                            $grand_obtained_points);

                        $passing_ratio = 35;
                        if ($grand_per >= $passing_ratio) {
                            $result = 'Pass';
                        } else {
                            $result = 'Fail';
                        }

                        if ($grand_per >= 80) {
                            $ribbenClass = 'ribben-base-red';
                            $ribbenLabel = 'Red';
                        } else {
                            if ($grand_per >= 60 && $grand_per < 80) {
                                $ribbenClass = 'ribben-base-blue';
                                $ribbenLabel = 'Blue';
                            } else {
                                if ($grand_per >= 40 && $grand_per < 60) {
                                    $ribbenClass = 'ribben-base-yellow';
                                    $ribbenLabel = 'Yellow';
                                } else {
                                    $ribbenClass = 'ribben-base-white';
                                    $ribbenLabel = 'White';
                                }
                            }
                        }
                        $rank = $wdata['rank'];

                        $html_data .= '</tr>';
                    }
                    $html_data .= '<tr>
                                                                <td><b>Total : </b>'.$grand_obtained_points.'/'.$grand_total_points.'</td>
                                                                <td><b>Per.(%) : </b>'.$grand_per.'</td>
                                                                <td><b>Difference Per.(%) : </b>'.$grand_per.'</td>
                                                                <td><b>Grade : </b>'.$grand_grade.'</td>
                                                                <td><b>Result : </b>'.$result.'</td>
                                                                <td><b>Rank : </b>'.$rank.'/'.$total_student.'</td>
                                                                <td width="25%" align="center" colspan="3">
                                                                    <b>Ribben Base : </b>
                                                                    <span class='.$ribbenClass.'>'.$ribbenLabel.'</span>
                                                                </td>
                                                            </tr>                                                            
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            </table>';
                }
                $html_data .= '</td>
                                </tr>
                            </tbody>
                        </table>';

                $html = $html_data;
                $css_name = "https://".$_SERVER['SERVER_NAME'];
                $result_css = '<link rel="stylesheet" href="'.$css_name.'/css/result.css" />';
                $dom = '<!DOCTYPE html>
                        <html>
                            <head>
                               <title></title>
                               <meta charset="UTF-8">
                               <meta name="viewport" content="width=device-width, initial-scale=1.0">';
                $dom .= "<style>
                                .exam_title_class
                                {    
                                    font-size: 18px;
                                    font-weight: bold;
                                    color: #FFF;
                                    background: #000;
                                    padding: 2px 10px;
                                    border-radius: 5px 5px 0px 0px;
                                }
                                .exam{
                                    width: 30%;
                                    float: left;
                                    font-size:17px;
                                    font-weight: bold
                                }
                                .co_ordinator{
                                    width: 40%;
                                    float: right;
                                    font-size:17px;
                                    font-weight: bold
                                }
                                .ribben-base-blue {
                                  width: 50px;
                                  padding: 2px 15px;
                                  background-color: #2012ff;
                                  margin-left: 5px;
                                  border: 1px solid #adadad;
                                  color: #fff;
                                  font-size: 12px;
                                }
                                .ribben-base-yellow {
                                  width: 50px;
                                  padding: 2px 15px;
                                  background-color: #fdeb10;
                                  margin-left: 5px;
                                  border: 1px solid #adadad;
                                  color: #000;
                                  font-size: 12px;
                                }
                                .ribben-base-white {
                                  width: 50px;
                                  padding: 2px 15px;
                                  background-color: #ffffff;
                                  margin-left: 5px;
                                  border: 1px solid #adadad;
                                  color: #000;
                                  font-size: 12px;
                                }
                                .ribben-base-red {
                                  width: 80px;
                                  padding: 2px 15px;
                                  background-color: #ff0707;
                                  margin-left: 5px;
                                  border: 1px solid #adadad;
                                  color: #fff;
                                  font-size: 12px;
                                }    
                            </style>";
                $dom .= $result_css;
                $dom .= '</head>
                            <body>
                                <div>
                                    ##HTML_SEC##
                                </div>
                            </body>
                        </html>';

                $path = 'src="https://'.$_SERVER['HTTP_HOST'];

                $html = str_replace('##HTML_SEC##', $html, $dom);

                //Start For Empty folder before creating new PDF
                $folder_path = $_SERVER['DOCUMENT_ROOT'].'/storage/WRT_result_pdf/*';
                $files = glob($folder_path); // get all file names
                foreach ($files as $file) { // iterate files
                    if (is_file($file)) {
                        unlink($file); // delete file
                    }
                }
                //END For Empty folder before creating new PDF

                $save_path = $_SERVER['DOCUMENT_ROOT'].'/storage/WRT_result_pdf';

                $CUR_TIME = date('YmdHis');
                $html_filename = $student_id.'_'.$CUR_TIME.".html";
                $pdf_filename = $student_id.'_'.$CUR_TIME.".pdf";

                $html_file_path = $save_path.'/'.$html_filename;
                $pdf_file_path = $save_path.'/'.$pdf_filename;
                file_put_contents($html_file_path, $html);
                htmlToPDF($html_file_path, $pdf_file_path);
                unlink($html_file_path);

                $data['student_id'] = $student_id;
                $data['sub_institute_id'] = $sub_institute_id;
                $data['syear'] = $syear;
                $data['exam_type'] = $exam_type;
                $data['title'] = 'WRT Progress Report';
                $data['file_name'] = "https://".$_SERVER['SERVER_NAME']."/storage/WRT_result_pdf/".$pdf_filename;

                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $data;

            } else {
                $res['status'] = 0;
                $res['message'] = "No Record";
            }


        }

        return json_encode($res);
    }

    public function add_studentCapturePhotosAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $student_id = $request->get('student_id');


        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'student_id'       => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $check_data = DB::table('student_capture_photos')
                ->selectRaw('COUNT(*) AS total_record,group_concat(stu_image) as stu_images')
                ->where('student_id', $student_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)->get()->toArray();

            if ($check_data[0]->total_record > 0) {
                $files_array = explode(',', $check_data[0]->stu_images);

                // START UNLINK files 
                $folder_path = $_SERVER['DOCUMENT_ROOT'].'/storage/capture_photos/'.$student_id;
                foreach ($files_array as $file) { // iterate files
                    if (is_file($file)) {
                        unlink($folder_path.'/'.$file); // delete file
                    }
                }
                // END UNLINK files
                $delete_record = DB::table('student_capture_photos')
                    ->where('student_id', $student_id)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('syear', $syear)->delete();

                if ($request->hasFile('stu_image')) {
                    foreach ($request->file('stu_image') as $key => $file_data) {
                        $file_name = $file_size = $ext = "";
                        $random_no = rand(10000, 99999);
                        $originalname = $file_data->getClientOriginalName();
                        $file_size = $file_data->getSize();
                        $name = $student_id.'_'.$random_no;
                        $ext = File::extension($originalname);
                        $file_name = $name.'.'.$ext;
                        $file_folder = '/capture_photos/'.$student_id;
                        File::makeDirectory($file_folder, $mode = 0777, true, true);
                        $path = $file_data->storeAs('public/capture_photos/'.$student_id, $file_name);

                        $data['syear'] = $syear;
                        $data['sub_institute_id'] = $sub_institute_id;
                        $data['student_id'] = $student_id;
                        $data['stu_image'] = $file_name;
                        $data['created_on'] = date('Y-m-d_h-i-s');

                        studentCapturePhotosModel::insert($data);

                    }
                }

            } else {
                if ($request->hasFile('stu_image')) {
                    foreach ($request->file('stu_image') as $key => $file_data) {
                        $file_name = $file_size = $ext = "";
                        $random_no = rand(10000, 99999);
                        $originalname = $file_data->getClientOriginalName();
                        $file_size = $file_data->getSize();
                        $name = $student_id.'_'.$random_no;
                        $ext = File::extension($originalname);
                        $file_name = $name.'.'.$ext;
                        $file_folder = '/capture_photos/'.$student_id;
                        File::makeDirectory($file_folder, $mode = 0777, true, true);
                        $path = $file_data->storeAs('public/capture_photos/'.$student_id, $file_name);

                        $data['syear'] = $syear;
                        $data['sub_institute_id'] = $sub_institute_id;
                        $data['student_id'] = $student_id;
                        $data['stu_image'] = $file_name;
                        $data['created_on'] = date('Y-m-d_h-i-s');

                        studentCapturePhotosModel::insert($data);

                    }
                }
            }


            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function add_studentCaptureAttendanceAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $date = $request->get('date');
        $standard_id = $request->get('standard_id');
        $division_id = $request->get('division_id');
        $image = $request->get('image');

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'date'             => 'required|date',
            'standard_id'      => 'required|numeric',
            'division_id'      => 'required|numeric',
            'image'            => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            if ($request->hasFile('image')) {
                foreach ($request->file('image') as $key => $file_data) {
                    $file_name = $file_size = $ext = "";
                    $random_no = rand(10000, 99999);
                    $originalname = $file_data->getClientOriginalName();
                    $file_size = $file_data->getSize();
                    $name = $standard_id.'-'.$division_id.'_'.$random_no;
                    $ext = File::extension($originalname);
                    $file_name = $name.'.'.$ext;
                    $file_folder = '/capture_attendance/'.$date.'/'.$standard_id.'-'.$division_id;
                    File::makeDirectory($file_folder, $mode = 0777, true, true);
                    $path = $file_data->storeAs('public/capture_attendance/'.$date.'/'.$standard_id.'-'.$division_id,
                        $file_name);

                    $data['syear'] = $syear;
                    $data['sub_institute_id'] = $sub_institute_id;
                    $data['standard_id'] = $standard_id;
                    $data['division_id'] = $division_id;
                    $data['date'] = $date;
                    $data['image'] = $file_name;
                    $data['created_on'] = date('Y-m-d_h-i-s');

                    studentCaptureAttendanceModel::insert($data);

                }
            }

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_attendanceDataAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $json_data = $request->input("json_data");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'json_data'        => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $all_data = json_decode($json_data, 1); //json_decode($json_data, 1);

            $data['syear'] = $syear;
            $data['sub_institute_id'] = $sub_institute_id;
            $data['json_data'] = $json_data;
            $data['created_on'] = date('Y-m-d_h-i-s');

            attendanceJsonResultModel::insert($data);

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $all_data;
        }

        return json_encode($response);
    }

    public function get_studentCapturePhotosAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('student_capture_photos as s')
                ->selectRaw("s.syear,s.sub_institute_id,s.student_id,if(s.stu_image = '','',
                    CONCAT('http://".$_SERVER['SERVER_NAME']."/storage/capture_photos/".$student_id."/',s.stu_image)) as stu_image")
                ->where('s.student_id', $student_id)
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('s.syear', $syear)->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function sendSMS($mobile, $text, $sub_institute_id)
    {
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();
        $isError = 0;

        if ($data) {
            $data = $data->toArray();
            $isError = 0;
            $errorMessage = true;

            $text = urlencode($text);
            $data['last_var'] = urlencode($data['last_var']);

//Start added by rajesh OTP for CN all institute
$cn_templateid = '';
$cn = array(244,245,246,247,248,253,257,264,265);
if(in_array($sub_institute_id, $cn))
    $cn_templateid = '&template_id=1507166607307092495';
//END added by rajesh OTP for CN all institute

            $url = $data['url'].$data['pram'].$cn_templateid.$data['mobile_var'].$mobile.$data['text_var'].$text.$data['last_var'];

            $ch = curl_init();

            // Ignore SSL certificate verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $output = curl_exec($ch);


            //Print error if any
            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch);
            }
            curl_close($ch);
        } else {
            $isError = 1;
            $errorMessage = "Please add api details first.";
        }
        $responce = [];
        if ($isError) {
            $responce = ['error' => 1, 'message' => $errorMessage];
        } else {
            $responce = ['error' => 0];
        }

        return $responce;
    }
}
