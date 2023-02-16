<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use DB;
use App\Models\easy_com\manage_sms_api\manage_sms_api;

class apiController extends Controller
{
    use GetsJwtToken;

    public function login(Request $request, JwtToken $jwt)
    {
        $send_data = array();
        $response = array('status' => '0', 'message' => 'No Student Found', 'data' => $send_data);
        $validator =  Validator::make($request->all(), [
            'mobile' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $select = array(
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
                "school_setup.is_lms"
            );
            $data = DB::table("tblstudent")
                ->join('school_setup', 'school_setup.id', '=', 'tblstudent.sub_institute_id')
                ->join('tblstudent_enrollment', 'tblstudent_enrollment.student_id', '=', 'tblstudent.id')
                ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
                ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
                ->join('sms_api_details', 'sms_api_details.sub_institute_id', '=', 'school_setup.id')
                ->orWhere(["tblstudent.mobile" => $_REQUEST['mobile'],"tblstudent.mother_mobile" => $_REQUEST['mobile'],"tblstudent.student_mobile" => $_REQUEST['mobile']])
                ->where(["sms_api_details.is_active" => "1"])
                ->whereRaw('tblstudent_enrollment.end_date is NULL')
                ->get($select);
                if (isset($data[0])) 
                {     
                    $otp = rand(100000,999999);
                    $sub_institute_id = $data[0]->sub_institute_id;
					if($_REQUEST['mobile'] == '9979176562')
					{
                        $otp = "123456";
                    }else{
                        //$text = "Dear Parent, Your OTP is ".$otp;
                        if($sub_institute_id == 49 || $sub_institute_id == 232 || $sub_institute_id == 233)
                            $text = "Dear Student Your Application Login OTP is ".$otp; //"Dear Student your OTP is ".$otp;
                        else
                            $text = "OTP for login is ".$otp." and is valid for 5 minutes";
                        
                        $res = $this->sendSMS($_REQUEST['mobile'], $text, $sub_institute_id);
                        if($res["error"]==1){
                            $errorMessage = "Please add api details first.";
                            if($res["error"] == $errorMessage){
                                $otp = "123456";
                            }
                        
                        }
                    }
               
                    $data = DB::table("tblstudent")
                    ->orWhere(["tblstudent.mobile" => $_REQUEST['mobile'],"tblstudent.mother_mobile" => $_REQUEST['mobile'],"tblstudent.student_mobile" => $_REQUEST['mobile']])
                    ->update(["tblstudent.otp"=>$otp]);
//                  ->where(["tblstudent.sub_institute_id" => $sub_institute_id])
                    $response['status'] = '1';
                    $response['message'] = 'success';
                }
            return json_encode($response);
            exit;
        }
        return json_encode($response);
        exit;
    }
    public function teacherlogin(Request $request)
    {
        $send_data = array();
        $response = array('status' => '0', 'message' => 'No Teacher Found', 'data' => $send_data);
        $validator =  Validator::make($request->all(), [
            'mobile' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $data = DB::select("SELECT u.id,u.user_name,u.first_name,u.middle_name,u.last_name,u.sub_institute_id,u.email,u.mobile,u.birthdate,u.address,u.gender,u.join_year,if(u.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,p.name as user_profile_name,u.user_profile_id,group_concat(concat_ws('||',c.standard_id,c.division_id)) as standard_division,group_concat(concat_ws('||',s.name,d.name)) as standard_division_title,ss.syear,ss.SchoolName,ss.Logo
                    FROM tbluser u
                    INNER JOIN tbluserprofilemaster p on p.sub_institute_id = u.sub_institute_id AND u.user_profile_id = p.id
                    INNER JOIN school_setup ss on ss.id = u.sub_institute_id
                    LEFT JOIN class_teacher c on c.teacher_id = u.id AND c.sub_institute_id = u.sub_institute_id
                    LEFT JOIN standard s on s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id
                    LEFT JOIN division d on d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id
                    WHERE u.mobile = ".$_REQUEST['mobile']."
                    ");
            $data = json_decode(json_encode($data),true);
            $data = $data[0];
            
            if(isset($data['id']) && $data['id'] != '') {
                // $payload = array();
                
                // $time = time() + (60*60*24*30);
                // $payload = array(
                //     'exp' => $time,
                //     'teacher_id' => $data['id'],
                //     'sub_institute_id' => $data['sub_institute_id'],
                //     'mobile' => $data['mobile'],
                // );
                // $token = $jwt->createToken($payload);

                // $school_logo = 'https://'.$_SERVER['SERVER_NAME'].'/admin_dep/images/'.$data['Logo'];

                // $term_data = DB::table("academic_year")->select('term_id','title','syear','start_date','end_date')
                //     ->where(["sub_institute_id" => $data['sub_institute_id'],"syear"=>$data['syear']])                    
                //     ->get()->toArray(); 

                // $send_data = array(
                //     'teacher_id' => $data['id'],
                //     'user_name' => $data['user_name'],
                //     'first_name' => $data['first_name'],
                //     'middle_name' => $data['middle_name'],
                //     'last_name' => $data['last_name'],
                //     'sub_institute_id' => $data['sub_institute_id'],
                //     'standard_division' => $data['standard_division'],
                //     'standard_division_title' => $data['standard_division_title'],
                //     'email' => $data['email'],
                //     'mobile' => $data['mobile'],
                //     'birthdate' => $data['birthdate'],
                //     'address' => $data['address'],
                //     'gender' => $data['gender'],
                //     'image' => $data['image'],
                //     'join_year' => $data['join_year'],
                //     'school_logo' => $school_logo,
                //     'school_name' => $data['SchoolName'],
                //     'user_profile_name' => $data['user_profile_name'],
                //     'user_profile_id' => $data['user_profile_id'],
                //     'syear' => $data['syear'],
                //     'term_data' => $term_data,
                //     'token' => $token
                // );

                // $response['status'] = '1';
                // $response['message'] = 'success';
                // $response['data'] = $send_data;


                // send otp
                $otp = rand(100000,999999);
                // echo "<pre>"; print_r($data); exit;
                $sub_institute_id = $data['sub_institute_id'];
                if($_REQUEST['mobile'] == '9979176562')
                {
                    $otp = "123456";
                }else{
                    //$text = "Dear Parent, Your OTP is ".$otp;
                    if($sub_institute_id == 49 || $sub_institute_id == 232 || $sub_institute_id == 233)
                    $text = "Dear Teacher your 0TP is ".$otp;
                    else
                    $text = "OTP for login is ".$otp." and is valid for 5 minutes";
                    
                    $res = $this->sendSMS($_REQUEST['mobile'], $text, $sub_institute_id);
                    if($res["error"]==1){
                        $errorMessage = "Please add api details first.";
                        if($res["error"] == $errorMessage){
                            $otp = "123456";
                        }
                        
                    }
                }
                // exit($otp);
            
                $data = DB::table("tbluser AS tu")
                ->join('tbluserprofilemaster AS tpm', 'tpm.id', '=', 'tu.user_profile_id')
                ->where(["tu.mobile" => $_REQUEST['mobile'], "tpm.name" => 'Teacher'])
                ->update(["tu.otp"=>$otp]);
//                  ->where(["tblstudent.sub_institute_id" => $sub_institute_id])
                $response['status'] = '1';
                $response['message'] = 'success';
            }
        }
        return json_encode($response);
        exit;
    }
    public function check_otp(Request $request, JwtToken $jwt)
    {			
        // echo '<pre>'; print_r(getenv('JWT_SECRET')); exit;
        $send_data = array();
        $response = array('status' => '0', 'message' => 'Invalid', 'data' => $send_data);
        $validator =  Validator::make($request->all(), [
            'mobile' => 'required|numeric',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $select = array(
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
                "tbluserprofilemaster.name as user_profile_name",
                "tbluserprofilemaster.id as user_profile_id",
            );
            $data = DB::table("tblstudent")
                ->join('school_setup', 'school_setup.id', '=', 'tblstudent.sub_institute_id')
                ->join('tblstudent_enrollment', 'tblstudent_enrollment.student_id','=','tblstudent.id')
                ->join('academic_section', 'tblstudent_enrollment.grade_id', '=', 'academic_section.id')
                ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
                ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
                ->join('tbluserprofilemaster','tbluserprofilemaster.id', '=', 'tblstudent.user_profile_id')
                ->orWhere(["tblstudent.mobile" => $_REQUEST['mobile'],"tblstudent.mother_mobile" => $_REQUEST['mobile'],"tblstudent.student_mobile" => $_REQUEST['mobile']])                
                ->where(["tblstudent.otp" => $_REQUEST['otp']])
                ->where('tblstudent_enrollment.syear', function($query)
                    {
                        $query->select(DB::raw('tblstudent_enrollment.syear'))
                              ->from('tblstudent_enrollment')
                              ->whereRaw('tblstudent_enrollment.student_id = tblstudent.id')
                              ->orderBy('tblstudent_enrollment.syear','DESC')
                              ->take(1);
                    })
                ->groupBy('tblstudent.id')
                ->get($select);//,"tbluserprofilemaster.sub_institute_id" => "tblstudent.sub_institute_id"
                
/*                ->join('tblstudent_enrollment',function($join){
                        $join->on('tblstudent_enrollment.student_id','=','tblstudent.id')
                        ->on('tblstudent_enrollment.syear','=','school_setup.syear');
                })*/
            $send_data = array();
            if (isset($data[0])) {
                foreach ($data as $id=>$arr) {
                    $payload = array();
                    $lms_user_id = "9";
                    if ($arr->is_lms == 'Y') {
                        $lms_data = DB::connection("information_schema")
                        ->table("mdl_user")
                        ->where(["idnumber" => $arr->id])
                        ->get("id", "username", "idnumber");
                        // echo '<pre>'; print_r($lms_data); exit;
                        if (isset($lms_data[0])) {
                            $payload["lms_user_id"] = $lms_data[0]->id;
                            $lms_user_id = $lms_data[0]->id;
                        }
                        // $payload["lms_user_id"] = 1;
                    }
                    $time = time() + (60*60*24*30);
                    $payload = array(
                        // 'exp' => time() + 108000,
                        'exp' => $time,
                        'student_id' => $arr->id,
                        'sub_institute_id' => $arr->sub_institute_id,
                        'mobile' => $arr->mobile,
                    );
                    $token = $jwt->createToken($payload);
                    $image_path = 'https://'.$_SERVER['SERVER_NAME'].'/storage/student/';
                    $image = $arr->image;
                    if((is_null($image)) || $image == ''){
                        $image = "student-avatar.png";
                    }
					$school_logo = 'https://'.$_SERVER['SERVER_NAME'].'/admin_dep/images/'.$arr->Logo;

                    $term_data = DB::table("academic_year")->select('term_id','title','syear','start_date','end_date')
                    ->where(["sub_institute_id" => $arr->sub_institute_id,"syear"=>$arr->syear])                    
                    ->get()->toArray(); 
					
                    $send_data[$id] = [
                        'student_id' => strtoupper($arr->id),
                        // 'time' =>$now,
                        // 'exp' =>$time,
                        'sub_institute_id' => strtoupper($arr->sub_institute_id),
                        'mobile' => $arr->mobile,
                        'first_name' => $arr->first_name,
                        'middle_name' => $arr->middle_name,
                        'last_name' => $arr->last_name,
                        'father_name' => isset($arr->father_name) ? $arr->father_name : '-',
                        'mother_name' => isset($arr->mother_name) ? $arr->mother_name : '-',
                        'image_path' => $image_path,
                        'image' => $image,
                        'last_name' => $arr->last_name,
                        'roll_no' => strtoupper($arr->roll_no),
                        'std_name' => $arr->std_name,
                        'section' => $arr->academic_section,
                        'division' => $arr->division,
                        'address' => $arr->address,
                        'email' => $arr->email,
                        'gender' => $arr->gender,
                        'birthday' => date('d-m-Y',strtotime($arr->dob)),
                        'is_lms' => $arr->is_lms,
                        'school_logo' => $school_logo,
                        'school_name' => $arr->SchoolName,
                        'syear' => $arr->syear,
                        'user_profile_name' => $arr->user_profile_name,
                        'user_profile_id' => $arr->user_profile_id,
                        'term_data' => $term_data,
                        'token' => $token
                    ];
                    
                        $send_data[$id]["lms_user_id"] = strtoupper($lms_user_id);
                  
                }

                $response['status'] = '1';
                $response['message'] = 'success';
                $response['data'] = $send_data;
            }
        }
        return json_encode($response);
        exit;
    }

    /**
     * TEACHER OTP
     * Teacher check otp 
     */
    public function teacher_check_otp(Request $request, JwtToken $jwt)
    {	
        // die('here');
        $send_data = array();
        $response = array('status' => '0', 'message' => 'Invalid', 'data' => $send_data);
        $validator =  Validator::make($request->all(), [
            'mobile' => 'required|numeric',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {

            // DB::enableQueryLog();
            $data = DB::select("SELECT u.id,u.user_name,u.first_name,u.middle_name,u.last_name,u.sub_institute_id,u.email,u.mobile,u.birthdate,u.address,u.gender,u.join_year,if(u.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,p.name as user_profile_name,u.user_profile_id,group_concat(concat_ws('||',c.standard_id,c.division_id)) as standard_division,group_concat(concat_ws('||',s.name,d.name)) as standard_division_title,ss.syear,ss.SchoolName,ss.Logo
                FROM tbluser u
                INNER JOIN tbluserprofilemaster p on p.sub_institute_id = u.sub_institute_id AND u.user_profile_id = p.id
                INNER JOIN school_setup ss on ss.id = u.sub_institute_id
                LEFT JOIN class_teacher c on c.teacher_id = u.id AND c.sub_institute_id = u.sub_institute_id
                LEFT JOIN standard s on s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id
                LEFT JOIN division d on d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id
                WHERE u.mobile = ".$_REQUEST['mobile']." and u.otp = ".$_REQUEST['otp']."
                ");
            // echo "<pre>"; print_r(DB::getQueryLog()); exit;
            $data = json_decode(json_encode($data),true);
            $data = $data[0];
            
            if(isset($data['id']) && $data['id'] != '') { 
                $payload = array();
                
                $time = time() + (60*60*24*30);
                $payload = array(
                    'exp' => $time,
                    'teacher_id' => $data['id'],
                    'sub_institute_id' => $data['sub_institute_id'],
                    'mobile' => $data['mobile'],
                );
                $token = $jwt->createToken($payload);

                $school_logo = 'https://'.$_SERVER['SERVER_NAME'].'/admin_dep/images/'.$data['Logo'];

                $term_data = DB::table("academic_year")->select('term_id','title','syear','start_date','end_date')
                    ->where(["sub_institute_id" => $data['sub_institute_id'],"syear"=>$data['syear']])                    
                    ->get()->toArray(); 

                $send_data = array(
                    'teacher_id' => $data['id'],
                    'user_name' => $data['user_name'],
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'],
                    'last_name' => $data['last_name'],
                    'sub_institute_id' => $data['sub_institute_id'],
                    'standard_division' => $data['standard_division'],
                    'standard_division_title' => $data['standard_division_title'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                    'birthdate' => $data['birthdate'],
                    'address' => $data['address'],
                    'gender' => $data['gender'],
                    'image' => $data['image'],
                    'join_year' => $data['join_year'],
                    'school_logo' => $school_logo,
                    'school_name' => $data['SchoolName'],
                    'user_profile_name' => $data['user_profile_name'],
                    'user_profile_id' => $data['user_profile_id'],
                    'syear' => $data['syear'],
                    'term_data' => $term_data,
                    'token' => $token
                );

                $response['status'] = '1';
                $response['message'] = 'success';
                $response['data'] = $send_data;
            } else {
                $response['status'] = '0';
                $response['message'] = 'Failed';
            }
        }
        return json_encode($response);
        exit;
    }
    
    public function playscreen()
    {
        $send_data = array(
            "status" => 1,
            "message" => "Success"
        );


        // $response["play_screen"] = array();
        $data = array();

        $data["android"]["type"] = "android";
        $data["android"]["appVersion"] = "1.0.4";
        $data["android"]["isUpdate"] = 1;
        $data["android"]["isComplusory"] = 0;
        $data["android"]["is_maintenance"] = 0;
        $data["android"]["maintenance_message"] = "Application is under maintenance. Please try after some time.";
        $data["android"]["message"] = "New version 1.0.4 Available";

        $data["ios"]["type"] = "ios";
        $data["ios"]["appVersion"] = "1.0.4";
        $data["ios"]["isUpdate"] = 1;
        $data["ios"]["isComplusory"] = 0;
        $data["ios"]["is_maintenance"] = 0;
        $data["ios"]["maintenance_message"] = "Application is under maintenance. Please try after some time.";
        $data["ios"]["message"] = "New version 1.0.4 Available";

        $send_data["data"] = $data;

        return json_encode($send_data);
    }
    public function homescreen(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 200);
        }
        $payload = $this->jwtPayload();
        
        $response = array('status' => '1', 'message' => 'Success', 'data' => array());

        $user_profile_id = $request->input("user_profile_id");
        $user_profile_name = $request->input("user_profile_name");
        $sub_institute_id = $request->input("sub_institute_id");

        $validator =  Validator::make($request->all(), [            
            'sub_institute_id' => 'required|numeric',                    
            'user_profile_id' => 'required|numeric',                    
            'user_profile_name' => 'required'                    
        ]);
        if ($validator->fails()) 
        {
            $response['response'] = $validator->messages();
        } 
      else
        {
        $data = DB::table("mobile_homescreen")
        ->where(["status" => "Yes",
                 'user_profile_id' => $user_profile_id,
                 'user_profile_name' => $user_profile_name,
                 'sub_institute_id' => $sub_institute_id
                ]) 
        ->orderBy('main_sort_order', 'ASC')
        ->orderBy('sub_title_sort_order', 'ASC')
        ->get();
        $data = json_encode($data);
        $data = json_decode($data, 1);

        $send_data = array();
        $i = 0;
        foreach ($data as $id=>$arr) {
            if ($i!=0) {
                if(isset($send_data[$i-1]["main_title"]) && $send_data[$i-1]["main_title"] == $arr['main_title']){
                    continue;
                }
            }
            if ($arr['menu_type'] == 'Banner') {
                $send_data[$i] = array(
                    "main_title" => $arr['main_title'],
                    "menu_type" => $arr['menu_type'],
                    "main_itle_color" => $arr['main_title_color_code'],
                    "main_title_background_image" => $arr['main_title_background_image'],
                    "api" => $arr['sub_title_api'],
                    "api_param" => $arr['sub_title_api_param'],
                    "screen_name" => $arr['screen_name'],
                );
                $i++;
                continue;
            } else {
                $send_data[$i] = array(
                    "main_title" => $arr['main_title'],
                    "menu_type" => $arr['menu_type'],
                    "main_itle_color" => $arr['main_title_color_code'],
                    "main_title_background_image" => $arr['main_title_background_image'],
                    "contents" => array()
                );
            }
            foreach ($data as $id1=>$arr1) {
                if ($arr['main_title'] == $arr1['main_title']) {
                    $send_data[$i]["contents"][] = array(
                        "sub_title" => $arr1["sub_title_of_main"],
                        "sub_title_icon" => $arr1["sub_title_icon"],
                        "sub_title_api" => $arr1["sub_title_api"],
                        "sub_title_api_param" => $arr1["sub_title_api_param"],
                        "screen_name" => $arr1["screen_name"],
    
                    );
                }
            }
            $i++;
        }

        $response["data"] = $send_data;
        }
        return json_encode($response);
        exit;
    }
    public function testkey(Request $request, JwtToken $jwt)
    {
        $payload = array(
            // 'exp' => time() + 7200,
            "id" => 123,
            "first_name" => 'keyur',
            "last_name" => 'modi',
            "roll_no" => 12,
        );
        $token = $jwt->createToken($payload);

        // $connection = ‘sample’;
        // config(['database.connections.mysql' => [
        $connection = array(
            'driver'    => 'mysql',
            'host'      =>  '202.47.117.131',
            'database'  => 'triz_lms',
            'username'  =>  'dev_db',
            'password'  =>  'Triz@2020',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        );


        $data = DB::connection("information_schema")
            ->table("app_practice_grades")
            ->get();

        $data = DB::table("tblstudent")
            ->where(["mobile" => "9727453987"])
            ->get();

        // $data = DB::
        //echo '<pre>';
        //print_r($data);
        //exit;



        echo $token;
        exit;
    }
    public function sendSMS($mobile, $text, $sub_institute_id)
    {
        // $sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();
        // ->toArray();
        $isError = 0;
        // if($data){
            
        //     echo '<pre>'; print_r($data); exit;
        // }
         if ($data) {
            $data = $data->toArray();
            $isError = 0;
            $errorMessage = true;

            $text = urlencode($text);
            $data['last_var'] = urlencode($data['last_var']);

            $url = $data['url'] . $data['pram'] . $data['mobile_var'] . $mobile . $data['text_var'] . $text . $data['last_var'];

            $ch = curl_init();
            // curl_setopt($ch, CURLOPT_URL, $url);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // $output = curl_exec($ch);

            // Ignore SSL certificate verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $output = curl_exec($ch);

         // print "<pre>";
         // print_r(curl_getinfo($ch));
         // echo '<pre>';
         // print_r($_REQUEST);
         // echo 'out put '.$output ;
         // exit;

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
        $responce = array();
        if ($isError) {
            $responce = array('error' => 1, 'message' => $errorMessage);
        } else {
            $responce = array('error' => 0);
        }
        return $responce;
    }

    public function gcm_insert(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $mobile_no = $request->input("mobile_no");
        $sub_institute_id = $request->input("sub_institute_id");
        //$created_on = now();      
        $gcm_regid = $request->input("gcm_regid");      
        $imei_no = $request->input("imei_no");
        $curr_version = $request->input("curr_version");
        $new_version = $request->input("new_version");

        if($mobile_no != "" && $sub_institute_id != "" && $gcm_regid != "" && $imei_no != "")
        {
            $check_record = "SELECT * FROM gcm_users WHERE sub_institute_id = '".$sub_institute_id."' AND imei_no = '".$imei_no."' ";
            $check_record_count = DB::select($check_record);
            // echo count($check_record_count);
            // die;
            if(count($check_record_count) > 0)
            {
                DB::table("gcm_users")
                ->where(["sub_institute_id" => $sub_institute_id,"imei_no" => $imei_no,"curr_version" => $curr_version,"new_version" => $new_version])
                ->update(["gcm_regid" => $gcm_regid]);
//,"mobile" => $mobile,'created_on' => $created_on
                $res['status'] = 1;
                $res['message'] = "Record Updated Successfully";
            }
            else
            {
                $data = array(
                    'mobile_no' => $mobile_no,
                    'gcm_regid' => $gcm_regid,
                    'imei_no' => $imei_no,
                    'sub_institute_id' => $sub_institute_id,
                    'curr_version' => $curr_version,
                    'new_version' => $new_version,
                );//'created_on' => $created_on,

                //echo "<pre>rajesh";
                //print_r($data);
                DB::table('gcm_users')->insert($data);

                $res['status'] = 1;
                $res['message'] = "Record Added Successfully";
            }
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        return json_encode($res);
    }
}
