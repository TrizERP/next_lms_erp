<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\aut_token;
use DB;
use App\Models\temp_signupModel;
use App\Models\student\tblstudentModel;
use function App\Helpers\sendSMS;
use App\Http\Controllers\loginController;
use Mail;
use function App\Helpers\is_mobile;

class NewLMS_StudentApiController extends Controller
{

    public function NewLMS_temp_signup_student(Request $request)
    {

        $user_type = $request->input("user_type");
        $first_name = $request->input("first_name");
        $last_name = $request->input("last_name");
        $gender = $request->input("gender");        
        $birthdate = $request->input("birthdate");        
        $email = $request->input("email");        
        $mobile = $request->input("mobile");            
        $standard_id = $request->input("standard");       

        $validator =  Validator::make($request->all(), [
            'user_type' => 'required|in:Student',
            'first_name' => 'required|regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/',
            'last_name' => 'required|regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/',
            'gender' => 'required|in:M,F', 
            'birthdate' => 'required|date_format:Y-m-d',
            'email' => 'required|email',
            'mobile' => 'required|numeric|digits:10',
            'standard' => 'required|numeric',
        ]);

        if ($validator->fails()) 
        {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $check_student_exist = $this->check_student_exist($mobile);

            if($check_student_exist != 0)
            {
                if($user_type != "" && $first_name != "" && $last_name != "" && $gender != "" && $birthdate != "" && $email != "" && $mobile != "" && $standard_id != "")
                {       

                    $otp = rand(100000,999999);
                    $sub_institute_id = 1; // Triz Innovation
                    $text = "OTP for login is ".$otp." and is valid for 5 minutes";
                    $res = sendSMS($mobile, $text, $sub_institute_id);

                    if($res["error"] == 1)
                    {
                        $response['status'] = 0;
                        $response['message'] = $res['message'].' - Please add api details first.'; 
                        return json_encode($response);
                        exit;                 
                    }

                    $check_temp_user = DB::SELECT("SELECT * FROM temp_signup WHERE mobile = '".$mobile."' AND user_type = 'Student' ");

                    if(count($check_temp_user) == 0)
                    {
                        $data = array(
                            'user_type' => $user_type,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'gender' => $gender,
                            'birthdate' => $birthdate,
                            'email' => $email,
                            'mobile' => $mobile,
                            'otp' => $otp,
                            'standard_id' => $standard_id,
                            'syear' => date('Y'),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        );
                        temp_signupModel::insert($data);
                    }else{
                        $data = array(
                            'user_type' => $user_type,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'gender' => $gender,
                            'birthdate' => $birthdate,
                            'email' => $email,
                            'otp' => $otp,
                            'standard_id' => $standard_id,                            
                            'syear' => date('Y'),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        );
                        temp_signupModel::where(["mobile" => $mobile,"user_type" => 'Student'])->update($data);
                    }

                    $response['status'] = 1;
                    $response['message'] = "Student Added Successfully";  
                }else{
                    $response['status'] = 0;
                    $response['message'] = "Parameter Missing";
                }
            }else{
                $response['status'] = 0;
                $response['message'] = "Student is already exists";
            }
        }

        if($request->has('type'))
        {
            if($request->input("type") == "web")
            {
                $type = $request->input('type');
                if($response['status'] == 1)
                {
                    $res = array(
                            'user_type' => $user_type,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'gender' => $gender,
                            'birthdate' => $birthdate,
                            'email' => $email,
                            'otp' => $otp,
                            'standard_id' => $standard_id,
                            'syear' => date('Y'),
                            'mobile' => $mobile,
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        );
                    
                    return is_mobile($type,'signup_otp',$res,"view");  
                }
                else
                {                   
                    return is_mobile($type,'signup',$response,"view");  
                }
            }
        }
        else
        {
            return json_encode($response);
        }
    }

    public function NewLMS_signup_student(Request $request)
    {
        $response = array('status' => '0', 'message' => 'OTP not matched.');
        $validator =  Validator::make($request->all(), [
            'mobile' => 'required|numeric',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $data = temp_signupModel::select('*')
                ->where(["mobile" => $_REQUEST['mobile'],"user_type" => 'Student',"otp" => $_REQUEST['otp']])
                ->get();

            if(count($data) != 0)
            {
                $data = $data[0];
                $send_data = array();  
                if(isset($data)) 
                {
                    $check_student_exist = $this->check_student_exist($_REQUEST['mobile']);
                    if($check_student_exist != 0)
                    {
                        // START step-1 INSERT INTO tblstudent table
                        $student_id = $this->insert_tblstudent($data);
                        // END step-1 INSERT INTO tblstudent table                
                        
                        // START step-2 INSERT INTO tblstudent_enrollment table
                        $enrollment_id = $this->insert_tblstudent_enrollment($data,$student_id);
                        // END step-2 INSERT INTO tblstudent_enrollment table  

                        // START step-3 INSERT INTO tblgroupwise_rights table
                        $this->insert_tblgroupwise_rights();
                        // END step-3 INSERT INTO tblgroupwise_rights table  

                        $select = array(
                            "tblstudent.first_name",
                            "tblstudent.id",
                            "tblstudent.password",
                            "tblstudent.enrollment_no",
                            "tblstudent.last_name",
                            "tblstudent.sub_institute_id",
                            "tblstudent.mobile",
                            "tblstudent.roll_no",
                            "standard.name as std_name",
                            "division.name as division",
                            "tblstudent.dob",
                            "tblstudent.email",
                            "tblstudent.gender",
                            "tblstudent.image as image",
                            "academic_section.title as academic_section",
                            "school_setup.SchoolName",
                            "school_setup.Logo",
                            "school_setup.syear",
                        );
                        $student_data = DB::table("tblstudent")
                            ->join('school_setup', 'school_setup.id', '=', 'tblstudent.sub_institute_id')
                            ->join('tblstudent_enrollment', 'tblstudent_enrollment.student_id', '=', 'tblstudent.id')
                            ->join('academic_section', 'tblstudent_enrollment.grade_id', '=', 'academic_section.id')
                            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
                            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')            
                            ->where(["tblstudent.id" => $student_id])
                            ->whereRaw('tblstudent_enrollment.end_date is NULL')
                            ->groupBy('tblstudent.id')
                            ->get($select);
                        $student_data = $student_data[0];

                        $image_path = 'https://'.$_SERVER['SERVER_NAME'].'/storage/student/';
                        $image =  $student_data->image;
                        if((is_null($image)) || $image == ''){
                            $image = "student-avatar.png";
                        }
                        $school_logo = 'https://'.$_SERVER['SERVER_NAME'].'/admin_dep/images/'.$student_data->Logo;

                        $send_data = [
                            'student_id' => $student_data->id,
                            'sub_institute_id' => $student_data->sub_institute_id,
                            'mobile' => $student_data->mobile,
                            'first_name' => $student_data->first_name,
                            'middle_name' => '-',
                            'last_name' => $student_data->last_name,
                            'father_name' => '-',
                            'mother_name' => '-',
                            'image_path' => '-',
                            'image' => $image_path.$image,
                            'roll_no' => $student_data->roll_no,
                            'std_name' => $student_data->std_name,
                            'section' => $student_data->academic_section,
                            'division' => $student_data->division,
                            'address' => '-',
                            'email' => $student_data->email,
                            'gender' => $student_data->gender,
                            'birthday' => $student_data->dob,
                            'is_lms' => '-',
                            'school_logo' => $school_logo,
                            'school_name' => $student_data->SchoolName,
                            'syear' => $student_data->syear
                        ];
                        $response['status'] = '1';
                        $response['message'] = 'success';
                        $response['data'] = $send_data;
                    }else{
                        $response['status'] = 0;
                        $response['message'] = "Student is already exists";
                    }
                } 
            }else{
                $response['status'] = '0';
                $response['message'] = 'OTP is not matched.';
            }  
        }
        if($request->has('type'))
        {
            if($request->input("type") == "web")
            {   
                if($response['status'] == 1)
                {      
                    $password = 'student';
                    $user_name = ucfirst($student_data->first_name ." ". $student_data->last_name);
                    $request->request->add(['email' => $student_data->email]); //add request
                    $request->request->add(['password' => $password]); //add request
                    $request->request->add(['captchaText' => '123']); //add request
                    $request->request->add(['hid_captcha' => '123']); //add request

                    Mail::send('email.signupmail', ['user_name' => $user_name,'email' => $student_data->email,'password' => 'student'], 
                        function($message) use($student_data){
                          $message->to($student_data->email);
                          $message->subject('Welcome To Triz Family!');
                    });
                    $login_object = new loginController();

                    return $login_object->index($request);
                }    
            }
        }
        else
        {
            $user_name = ucfirst($student_data->first_name ." ". $student_data->last_name);
            Mail::send('email.signupmail', ['user_name' => $user_name,'email' => $student_data->email,'password' => 'student'], 
                function($message) use($student_data){
                  $message->to($student_data->email);
                  $message->subject('Welcome To Triz Family!');
            });
            return json_encode($response);
        }
    }

    public function check_student_exist($mobile_no)
    {
        $check_student_sql = DB::select("SELECT * FROM tblstudent WHERE mobile = '".$mobile_no."' ");

        if(count($check_student_sql) == 0)
        {
            return 1;
        }else{
            return 0;
        }
    }

    public function insert_tblstudent($data)
    {        

        $maxEnrollment = DB::select("SELECT (MAX(CAST(enrollment_no AS INT)) + 1) AS new_enrollment_no,(MAX(CAST(roll_no AS INT)) + 1) AS new_roll_no FROM tblstudent WHERE sub_institute_id = '1' ORDER BY id DESC LIMIT 1");

        $new_enrollment_no = $maxEnrollment[0]->new_enrollment_no;
        $new_roll_no = $maxEnrollment[0]->new_roll_no;
        $user_name = $data->first_name.'_'.$data->last_name;
        $password = md5('student');

        $insert_stu = DB::select("INSERT INTO `tblstudent` (`enrollment_no`,`roll_no`, `first_name`,`last_name`,`gender`, `dob`, `mobile`, `email`, `username`, `password`, `user_profile_id`, `admission_year`, `admission_date`,`otp`,`sub_institute_id`, `status`,`created_on`) VALUES ('".$new_enrollment_no."','".$new_roll_no."','".$data->first_name."','".$data->last_name."','".$data->gender."','".$data->birthdate."','".$data->mobile."','".$data->email."','".$user_name."','".$password."','8','".date('Y')."','".date('Y-m-d')."','".$data->otp."','1','1',Now() )");
        $student_id = DB::getPdo()->lastInsertId();

        return $student_id;        
    }

    public function insert_tblstudent_enrollment($data,$student_id)
    {     
        $get_grade_id = DB::select("SELECT * FROM standard WHERE id = '".$data->standard_id."' ");  
        $grade_id = $get_grade_id[0]->grade_id;
        $standard_id = $data->standard_id;

        $get_division_id = DB::select("SELECT * FROM division WHERE sub_institute_id = '1' ");  
        $division_id = $get_division_id[0]->id;

        $get_quota = DB::select("SELECT * FROM student_quota WHERE sub_institute_id = 1 AND title = 'General' ");
        $student_quota = $get_quota[0]->id;

        $insert_student_enrollment = DB::select("INSERT INTO `tblstudent_enrollment` (`syear`, `student_id`, `grade_id`, `standard_id`, `section_id`, `student_quota`, `start_date`, `enrollment_code`, `term_id`,`sub_institute_id`) VALUES ('".date('Y')."', '".$student_id."', '".$grade_id."', '".$standard_id."', '".$division_id."', '".$student_quota."', '".date('Y-m-d')."','1','1','1') ");
        $enrollment_id = DB::getPdo()->lastInsertId();

        return $enrollment_id;        
    }

    public function insert_tblgroupwise_rights()
    {        
        $student_rights = array(            
            "Homework Submission" => 218,
            "LMS" => 230,
            "Exam" => 242,
            "Teach/Learn" => 269,
            "All Courses" => 270,
            "LMS Global Mapping " => 275,
            "Test" => 276,
            "Activity Stream" => 277,
            "Message" => 278,
            "Social & Collabrotive" => 279,
            "Virtual Classroom" => 280,
            "Portfolio" => 281,
            "Counselling" => 282,
            "Leader Board" => 290,
            "Assignment" => 312,
            "Assignment Submission" => 313
        );

        $profile_array = DB::SELECT("SELECT * FROM tbluserprofilemaster WHERE sub_institute_id = '1' AND name = 'Student' ");
        $profile_array = json_decode(json_encode($profile_array),true);
        
        foreach($profile_array as $profilekey => $profileval)
        {
            $profileval['name'] = str_replace(' ','',$profileval['name']);       
            $arr_name = strtolower($profileval['name'])."_rights";
            $profile_id = $profileval['id'];
            if(isset($$arr_name))
            {             
                foreach($$arr_name as $key => $val)
                {
                    $check_sql = DB::SELECT("SELECT * FROM tblgroupwise_rights WHERE menu_id = '".$val."'
                      AND profile_id = '".$profile_id."' AND sub_institute_id = '1'");
                    $check_sql = json_decode(json_encode($check_sql),true);
                    if(count($check_sql) == 0)
                    {   
                        $insertQuery = DB::SELECT("INSERT INTO tblgroupwise_rights(menu_id,profile_id,can_view,can_add,can_edit,can_delete,sub_institute_id) 
                        values('".$val."',$profile_id,'1','1','1','1','1')");                                                              
                    }
                }
            }
        }
        
    }
    
}
