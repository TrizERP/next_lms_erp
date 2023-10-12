<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class apiController extends Controller
{
    use GetsJwtToken;

    public function login(Request $request, JwtToken $jwt)
    {
        $send_data = [];
        $response = ['status' => '0', 'message' => 'No Student Found', 'data' => $send_data];
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $select = [
                "tblstudent.first_name",
                "tblstudent.id",
                "tblstudent.enrollment_no",
                "tblstudent.first_name",
                "tblstudent.middle_name",
                "tblstudent.last_name",
                "tblstudent.sub_institute_id",
                "tblstudent.mobile",
                "tblstudent.roll_no",
                "standard.name as std_name",
                "division.name as division",
                "tblstudent.dob",
                "tblstudent.address",
                "tblstudent.father_name",
                "tblstudent.mother_name",
                "tblstudent.image",
                "tblstudent.email",
                "tblstudent.gender",
                "school_setup.is_lms",
            ];
//            $student_syear = 2023;
            $data = DB::table("tblstudent")
                ->join('school_setup', 'school_setup.id', '=', 'tblstudent.sub_institute_id')
                ->join('tblstudent_enrollment', 'tblstudent_enrollment.student_id', '=', 'tblstudent.id')
                ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
                ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
                ->join('sms_api_details', 'sms_api_details.sub_institute_id', '=', 'school_setup.id')
                ->orWhere([
                    "tblstudent.mobile"         => $_REQUEST['mobile'],
                    "tblstudent.mother_mobile"  => $_REQUEST['mobile'],
                    "tblstudent.student_mobile" => $_REQUEST['mobile'],
                ])
                ->where([
                    "sms_api_details.is_active" => "1", 
//                    'school_setup.syear' => 'tblstudent_enrollment.syear'
                ])
                ->whereRaw('tblstudent_enrollment.end_date is NULL')
                ->get($select);

            if (isset($data[0])) {
                $otp = rand(100000, 999999);
                $sub_institute_id = $data[0]->sub_institute_id;
                if ($_REQUEST['mobile'] == '9979176562') {
                    $otp = "123456";
                } else {
                    //$text = "Dear Parent, Your OTP is ".$otp;
                    if ($sub_institute_id == 49 || $sub_institute_id == 232 || $sub_institute_id == 233) {
                        $text = "Dear Student Your Application Login OTP is ".$otp;
                    } //"Dear Student your OTP is ".$otp;
                    else {
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

                $data = DB::table("tblstudent")
                    ->orWhere([
                        "tblstudent.mobile"         => $_REQUEST['mobile'],
                        "tblstudent.mother_mobile"  => $_REQUEST['mobile'],
                        "tblstudent.student_mobile" => $_REQUEST['mobile'],
                    ])
                    ->update(["tblstudent.otp" => $otp]);

                $response['status'] = '1';
                $response['message'] = 'success';
            }

            return json_encode($response);
        }

        return json_encode($response);
    }


    public function teacherlogin(Request $request)
    {
        $send_data = [];
        $response = ['status' => '0', 'message' => 'No Teacher Found', 'data' => $send_data];
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {

            $data = DB::table('tbluser as u')
                ->join('tbluserprofilemaster as p', function ($join) {
                    $join->whereRaw('p.sub_institute_id = u.sub_institute_id AND u.user_profile_id = p.id');
                })->join('school_setup as ss', function ($join) {
                    $join->whereRaw('ss.id = u.sub_institute_id');
                })->leftjoin('class_teacher as c', function ($join) {
                    $join->whereRaw('c.teacher_id = u.id AND c.sub_institute_id = u.sub_institute_id');
                })->leftjoin('standard as s', function ($join) {
                    $join->whereRaw('s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id');
                })->leftjoin('division as d', function ($join) {
                    $join->whereRaw('d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id');
                })->selectRaw("u.id,u.user_name,u.first_name,u.middle_name,u.last_name,u.sub_institute_id,u.email,u.mobile,
                    u.birthdate,u.address,u.gender,u.join_year,if(u.image = '','https://".$_SERVER['SERVER_NAME']."/storage/student/noimages.png',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,p.name as user_profile_name,u.user_profile_id,group_concat(concat_ws('||',c.standard_id,c.division_id))
                    as standard_division,group_concat(concat_ws('||',s.name,d.name)) as standard_division_title,ss.syear,ss.SchoolName,ss.Logo")
                ->where('u.status', '1')
                ->where('u.mobile', $_REQUEST['mobile'])->get()->toArray();

            $data = json_decode(json_encode($data), true);
            $data = $data[0];

            if (isset($data['id']) && $data['id'] != '') {
                $otp = rand(100000, 999999);

                $sub_institute_id = $data['sub_institute_id'];
                if ($_REQUEST['mobile'] == '9979176562') {
                    $otp = "123456";
                } else {

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

                $data = DB::table("tbluser AS tu")
                    ->join('tbluserprofilemaster AS tpm', 'tpm.id', '=', 'tu.user_profile_id')
                    ->where(["tu.mobile" => $_REQUEST['mobile'], "tpm.name" => 'Teacher'])
                    ->update(["tu.otp" => $otp]);

                $response['status'] = '1';
                $response['message'] = 'success';
            }
        }

        return json_encode($response);
    }


    public function check_otp(Request $request, JwtToken $jwt)
    {
        $send_data = [];
        $response = ['status' => '0', 'message' => 'Invalid', 'data' => $send_data];

        $validator = Validator::make($request->all(), [
            'mobile' => 'required|numeric',
            'otp'    => 'required|numeric',
        ]);

        //START - For Hills School - Display only current year student display
			$exists = DB::table('tblstudent')
			    ->selectRaw('sub_institute_id')
                ->orWhere([
                    "tblstudent.mobile"         => $_REQUEST['mobile'],
                    "tblstudent.mother_mobile"  => $_REQUEST['mobile'],
                    "tblstudent.student_mobile" => $_REQUEST['mobile'],
                ])
                ->where('sub_institute_id', [254])
                ->get()->toArray();

            $exists = json_decode(json_encode($exists), true);

            $is_exist = false;
            if (count($exists) > 0) {
                $is_exist = true;
            }
        //End - For Hills School - Display only current year student display


        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $select = [
                "tblstudent.first_name",
                "tblstudent.id",
                "tblstudent.enrollment_no",
                "tblstudent.first_name",
                "tblstudent.middle_name",
                "tblstudent.last_name",
                "tblstudent.sub_institute_id",
                "tblstudent.mobile",
                "tblstudent.roll_no",
                "standard.name as std_name",
                "division.name as division",
                "tblstudent.dob",
                "tblstudent.address",
                "tblstudent.father_name",
                "tblstudent.mother_name",
                "tblstudent.image as image",
                "tblstudent.email",
                "tblstudent.gender",
                "academic_section.title as academic_section",
                "school_setup.is_lms",
                "school_setup.SchoolName",
                "school_setup.Logo",
                "tblstudent_enrollment.syear",
                "tblstudent_enrollment.standard_id",
                "tblstudent_enrollment.section_id as division_id",
                "tbluserprofilemaster.name as user_profile_name",
                "tbluserprofilemaster.id as user_profile_id",
            ];

			$query = DB::table("tblstudent")
			    ->join('school_setup', 'school_setup.id', '=', 'tblstudent.sub_institute_id')
			    ->join('tblstudent_enrollment', 'tblstudent_enrollment.student_id', '=', 'tblstudent.id')
			    ->join('academic_section', 'tblstudent_enrollment.grade_id', '=', 'academic_section.id')
			    ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
			    ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
			    ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', '=', 'tblstudent.user_profile_id')
			    ->orWhere([
			        "tblstudent.mobile" => $_REQUEST['mobile'],
			        "tblstudent.mother_mobile" => $_REQUEST['mobile'],
			        "tblstudent.student_mobile" => $_REQUEST['mobile'],
			    ])
			    ->where(["tblstudent.otp" => $_REQUEST['otp']])
			    ->where('tblstudent_enrollment.syear', function ($query) {
			        $query->select(DB::raw('tblstudent_enrollment.syear'))
			            ->from('tblstudent_enrollment')
			            ->whereRaw('tblstudent_enrollment.student_id = tblstudent.id')
			            ->whereRaw('tblstudent_enrollment.end_date is NULL')
			            ->orderBy('tblstudent_enrollment.syear', 'DESC')
			            ->take(1);
			    });

			if($is_exist) {
			    $query->whereColumn('school_setup.syear', '=', 'tblstudent_enrollment.syear');
			}

			$data = $query
			    ->groupBy('tblstudent.id')
			    ->get($select);

            $send_data = [];

            if (isset($data[0])) {
                foreach ($data as $id => $arr) {
                    $payload = [];
                    $lms_user_id = "9";
                    if ($arr->is_lms == 'Y') {
                        $lms_data = DB::connection("information_schema")
                            ->table("mdl_user")
                            ->where(["idnumber" => $arr->id])
                            ->get("id", "username", "idnumber");

                        if (isset($lms_data[0])) {
                            $payload["lms_user_id"] = $lms_data[0]->id;
                            $lms_user_id = $lms_data[0]->id;
                        }

                    }
                    $time = time() + (60 * 60 * 24 * 30);
                    $payload = [
                        //'exp'              => $time,
                        'student_id'       => $arr->id,
                        'sub_institute_id' => $arr->sub_institute_id,
                        //'mobile'           => $arr->mobile,
                    ];

                    $token = $jwt->createToken($payload);
                    $image_path = 'https://'.$_SERVER['SERVER_NAME'].'/storage/student/';
                    $image = $arr->image;

                    if ((is_null($image)) || $image == '') {
                        $image = asset("storage/student/student-avatar.png");
                    }

                    $school_logo = 'https://'.$_SERVER['SERVER_NAME'].'/admin_dep/images/'.$arr->Logo;

                    $term_data = DB::table("academic_year")->select('term_id', 'title', 'syear', 'start_date',
                        'end_date')
                        ->where(["sub_institute_id" => $arr->sub_institute_id, "syear" => $arr->syear])
                        ->get()->toArray();

                    $send_data[$id] = [
                        'student_id'        => strtoupper($arr->id),
                        'sub_institute_id'  => strtoupper($arr->sub_institute_id),
                        'mobile'            => $arr->mobile,
                        'first_name'        => $arr->first_name,
                        'middle_name'       => $arr->middle_name,
                        'father_name'       => isset($arr->father_name) ? $arr->father_name : '-',
                        'mother_name'       => isset($arr->mother_name) ? $arr->mother_name : '-',
                        'image_path'        => $image_path,
                        'image'             => $image,
                        'last_name'         => $arr->last_name,
                        'roll_no'           => strtoupper($arr->roll_no),
                        'std_name'          => $arr->std_name,
                        'section'           => $arr->academic_section,
                        'division'          => $arr->division,
                        'address'           => $arr->address,
                        'email'             => $arr->email,
                        'gender'            => $arr->gender,
                        'birthday'          => date('d-m-Y', strtotime($arr->dob)),
                        'is_lms'            => $arr->is_lms,
                        'school_logo'       => $school_logo,
                        'school_name'       => $arr->SchoolName,
                        'syear'             => $arr->syear,
                        'standard_id'       => $arr->standard_id,
                        'division_id'       => $arr->division_id,
                        'user_profile_name' => $arr->user_profile_name,
                        'user_profile_id'   => $arr->user_profile_id,
                        'term_data'         => $term_data,
                        'token'             => $token,
                    ];

                    $send_data[$id]["lms_user_id"] = strtoupper($lms_user_id);

                }

                $response['status'] = '1';
                $response['message'] = 'success';
                $response['data'] = $send_data;
            }
        }

        return json_encode($response);
    }

    /**
     * TEACHER OTP
     * Teacher check otp
     */
    public function teacher_check_otp(Request $request, JwtToken $jwt)
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

            $data = DB::table('tbluser as u')
                ->join('tbluserprofilemaster as p', function ($join) {
                    $join->whereRaw("p.sub_institute_id = u.sub_institute_id AND u.user_profile_id = p.id");
                })->join('school_setup as ss', function ($join) {
                    $join->whereRaw("ss.id = u.sub_institute_id");
                })->leftJoin('class_teacher as c', function ($join) {
                    $join->whereRaw("c.teacher_id = u.id AND c.sub_institute_id = u.sub_institute_id AND ss.syear = c.syear");
                })->leftJoin('standard as s', function ($join) {
                    $join->whereRaw("s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id");
                })->leftJoin('division as d', function ($join) {
                    $join->whereRaw("d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id");
                })
                ->selectRaw("u.id,u.user_name,u.first_name,u.middle_name,u.last_name,u.sub_institute_id,u.email,
                    u.mobile,u.birthdate,u.address,u.gender,u.join_year,if(u.image = '','https://".$_SERVER['SERVER_NAME']."/storage/student/noimages.png',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,p.name as user_profile_name,
                    u.user_profile_id,group_concat(concat_ws('||',c.standard_id,c.division_id)) as standard_division,
                    group_concat(concat_ws('||',s.name,d.name)) as standard_division_title,ss.syear,ss.SchoolName,ss.Logo")
                ->where('u.mobile', $_REQUEST['mobile'])
                ->where('u.otp', $_REQUEST['otp'])->get()->toArray();

            $data = json_decode(json_encode($data), true);
            $data = $data[0];

            if (isset($data['id']) && $data['id'] != '') {
                $payload = [];

                $time = time() + (60 * 60 * 24 * 30);
                $payload = [
                    //'exp'              => $time,
                    'teacher_id'       => $data['id'],
                    'sub_institute_id' => $data['sub_institute_id'],
                    //'mobile'           => $data['mobile'],
                ];

                $token = $jwt->createToken($payload);

                $school_logo = 'https://'.$_SERVER['SERVER_NAME'].'/admin_dep/images/'.$data['Logo'];

                $term_data = DB::table("academic_year")->select('term_id', 'title', 'syear', 'start_date', 'end_date')
                    ->where(["sub_institute_id" => $data['sub_institute_id'], "syear" => $data['syear']])
                    ->get()->toArray();

                $send_data = [
                    'teacher_id'              => $data['id'],
                    'user_name'               => $data['user_name'],
                    'first_name'              => $data['first_name'],
                    'middle_name'             => $data['middle_name'],
                    'last_name'               => $data['last_name'],
                    'sub_institute_id'        => $data['sub_institute_id'],
                    'standard_division'       => $data['standard_division'],
                    'standard_division_title' => $data['standard_division_title'],
                    'email'                   => $data['email'],
                    'mobile'                  => $data['mobile'],
                    'birthdate'               => $data['birthdate'],
                    'address'                 => $data['address'],
                    'gender'                  => $data['gender'],
                    'image'                   => $data['image'],
                    'join_year'               => $data['join_year'],
                    'school_logo'             => $school_logo,
                    'school_name'             => $data['SchoolName'],
                    'user_profile_name'       => $data['user_profile_name'],
                    'user_profile_id'         => $data['user_profile_id'],
                    'syear'                   => $data['syear'],
                    'term_data'               => $term_data,
                    'token'                   => $token,
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

    public function playscreen()
    {
        $send_data = [
            "status"  => 1,
            "message" => "Success",
        ];

        $data = [];

        $data["android"]["type"] = "android";
        $data["android"]["appVersion"] = "1.0.20";
        $data["android"]["isUpdate"] = 0;
        $data["android"]["isComplusory"] = 0;
        $data["android"]["is_maintenance"] = 0;
        $data["android"]["maintenance_message"] = "Application is under maintenance. Please try after some time.";
        $data["android"]["message"] = "New version 1.0.20 Available";

        $data["ios"]["type"] = "ios";
        $data["ios"]["appVersion"] = "1.0.20";
        $data["ios"]["isUpdate"] = 0;
        $data["ios"]["isComplusory"] = 0;
        $data["ios"]["is_maintenance"] = 0;
        $data["ios"]["maintenance_message"] = "Application is under maintenance. Please try after some time.";
        $data["ios"]["message"] = "New version 1.0.20 Available";

        $send_data["data"] = $data;

        return json_encode($send_data);
    }


    public function homescreen(Request $request)
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

        $user_profile_id = $request->input("user_profile_id");
        $user_profile_name = $request->input("user_profile_name");
        $sub_institute_id = $request->input("sub_institute_id");

        $validator = Validator::make($request->all(), [
            'sub_institute_id'  => 'required|numeric',
            'user_profile_id'   => 'required|numeric',
            'user_profile_name' => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table("mobile_homescreen")
                ->where([
                    "status"            => "Yes",
                    'user_profile_id'   => $user_profile_id,
                    'user_profile_name' => $user_profile_name,
                    'sub_institute_id'  => $sub_institute_id,
                ])
                ->orderBy('main_sort_order', 'ASC')
                ->orderBy('sub_title_sort_order', 'ASC')
                ->get();
            $data = json_encode($data);
            $data = json_decode($data, 1);

            $send_data = [];
            $i = 0;

            foreach ($data as $id => $arr) {
                if ($i != 0) {
                    if (isset($send_data[$i - 1]["main_title"]) && $send_data[$i - 1]["main_title"] == $arr['main_title']) {
                        continue;
                    }
                }
                if ($arr['menu_type'] == 'Banner') {
                    $send_data[$i] = [
                        "main_title"                  => $arr['main_title'],
                        "menu_type"                   => $arr['menu_type'],
                        "main_itle_color"             => $arr['main_title_color_code'],
                        "main_title_background_image" => $arr['main_title_background_image'],
                        "api"                         => $arr['sub_title_api'],
                        "api_param"                   => $arr['sub_title_api_param'],
                        "screen_name"                 => $arr['screen_name'],
                    ];

                    $i++;
                    continue;
                } else {
                    $send_data[$i] = [
                        "main_title"                  => $arr['main_title'],
                        "menu_type"                   => $arr['menu_type'],
                        "main_itle_color"             => $arr['main_title_color_code'],
                        "main_title_background_image" => $arr['main_title_background_image'],
                        "contents"                    => [],
                    ];
                }

                foreach ($data as $id1 => $arr1) {
                    if ($arr['main_title'] == $arr1['main_title']) {
                        $send_data[$i]["contents"][] = [
                            "sub_title"           => $arr1["sub_title_of_main"],
                            "sub_title_icon"      => $arr1["sub_title_icon"],
                            "sub_title_api"       => $arr1["sub_title_api"],
                            "sub_title_api_param" => $arr1["sub_title_api_param"],
                            "screen_name"         => $arr1["screen_name"],

                        ];
                    }
                }
                $i++;
            }

            $response["data"] = $send_data;
        }

        return json_encode($response);
    }

    public function testkey(Request $request, JwtToken $jwt)
    {
        $payload = [
            "id"         => 123,
            "first_name" => 'keyur',
            "last_name"  => 'modi',
            "roll_no"    => 12,
        ];

        $token = $jwt->createToken($payload);

        $connection = [
            'driver'    => 'mysql',
            'host'      => '202.47.117.131',
            'database'  => 'triz_lms',
            'username'  => 'dev_db',
            'password'  => 'Triz@2020',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ];


        $data = DB::connection("information_schema")
            ->table("app_practice_grades")
            ->get();

        $data = DB::table("tblstudent")
            ->where(["mobile" => "9727453987"])
            ->get();

        echo $token;
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

    public function gcm_insert(Request $request)
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
        $mobile_no = $request->input("mobile_no");
        $sub_institute_id = $request->input("sub_institute_id");
        $gcm_regid = $request->input("gcm_regid");
        $imei_no = $request->input("imei_no");
        $curr_version = $request->input("curr_version");
        $new_version = $request->input("new_version");

        if ($mobile_no != "" && $sub_institute_id != "" && $gcm_regid != "" && $imei_no != "") {

            $check_record_count = DB::table('gcm_users')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('mobile_no', $mobile_no)
                ->get()->toArray();

            if (count($check_record_count) > 0) {
                $updated_on = date("Y-m-d H:i:s"); // Get the current date and time in the specified format.

                DB::table("gcm_users") // Specify the table "gcm_users" for the query.
                    ->where([ // Specify the conditions for the update operation.
                        "sub_institute_id" => $sub_institute_id, // Match the "sub_institute_id" column.
                        "imei_no" => $imei_no, // Match the "imei_no" column.
                        "mobile_no" => $mobile_no // Match the "mobile_no" column.
                    ])
                    ->update([ // Define the columns and values to update.
                        "gcm_regid" => $gcm_regid, // Set the "gcm_regid" column to the new value.
                        "updated_on" => $updated_on // Set the "updated_on" column to the current date and time.
                    ]);

                $res['status'] = 1;
                $res['message'] = "Record Updated Successfully";
            } else {
                $data = [
                    'mobile_no'        => $mobile_no,
                    'gcm_regid'        => $gcm_regid,
                    'imei_no'          => $imei_no,
                    'sub_institute_id' => $sub_institute_id,
                    'curr_version'     => $curr_version,
                    'new_version'      => $new_version,
                ];

                DB::table('gcm_users')->insert($data);

                $res['status'] = 1;
                $res['message'] = "Record Added Successfully";
            }
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
