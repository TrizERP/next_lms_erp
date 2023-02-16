<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\aut_token;
use DB;
use App\Models\temp_signupModel;
use App\Models\tblclientModel;
use App\Models\school_setupModel;
use App\Models\user\tbluserprofilemasterModel;
use App\Models\user\tbluserModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\subjectModel;
use App\Models\student\studentQuotaModel;
use function App\Helpers\sendSMS;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\View;
use App\Models\loginModel;
use App\Models\student\tblstudentModel;
use App\Models\school_setup\SchoolModel;
use App\Http\Controllers\loginController;
use Mail;


class NewLMS_ApiController extends Controller
{

    public function NewLMS_temp_signup(Request $request)
    {
        // dd($request);
        $user_type = $request->input("user_type");
        $first_name = $request->input("first_name");
        $last_name = $request->input("last_name");
        $gender = $request->input("gender");        
        $birthdate = $request->input("birthdate");        
        $email = $request->input("email");        
        $mobile = $request->input("mobile");        
        $institute_name = $request->input("institute_name");       

        $validator =  Validator::make($request->all(), [
            'user_type' => 'required|in:LMS Teacher,Student,Admin',
            'first_name' => 'required|regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/',
            'last_name' => 'required|regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/',
            'gender' => 'required|in:M,F',
            'birthdate' => 'required|date_format:Y-m-d',
            'email' => 'required|email',
            'mobile' => 'required|numeric|digits:10',
            'institute_name' => 'required',
        ]);

        if ($validator->fails()) 
        {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } 
        else 
        {
            $check_user_exist = $this->check_user_exist($mobile,$email);

            if($check_user_exist != 0)
            {
if($user_type != "" && $first_name != "" && $last_name != "" && $gender != "" && $birthdate != "" && $email != "" && $mobile != "" && $institute_name != "")
{       

    $otp = rand(100000,999999);
    $sub_institute_id = 1; // Triz Innovation
    $text = "OTP for login is ".$otp." and is valid for 5 minutes";
    $res = sendSMS($mobile, $text, $sub_institute_id);
    $res = array("error"=>"0");

    if($res["error"] == 1)
    {
        $response['status'] = 0;
        $response['message'] = $res['message'].' - Please add api details first.'; 
        return json_encode($response);
        exit; 
    }

    $check_temp_user = DB::SELECT("SELECT * FROM temp_signup WHERE mobile = '".$mobile."' ");

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
            'institute_name' => $institute_name,
            'syear' => date('Y'),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        );
        temp_signupModel::insert($data);
    }
    else
    {
        $data = array(
            'user_type' => $user_type,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'birthdate' => $birthdate,
            'email' => $email,
            'otp' => $otp,
            'institute_name' => $institute_name,
            'syear' => date('Y'),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        );
        temp_signupModel::where(["mobile" => $mobile])->update($data);
    }

    $response['status'] = 1;
    $response['message'] = "Successfully Added";  
}
else
{
    $response['status'] = 0;
    $response['message'] = "Parameter Missing";
}
            }
            else
            {
$response['status'] = 0;
$response['message'] = "User is already exists";
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
            'institute_name' => $institute_name,
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

    public function NewLMS_signup(Request $request)
    {   
        $response = array('status' => '0', 'message' => 'Entered OTP is wrong one.');
        $validator =  Validator::make($request->all(), [
            'mobile' => 'required|numeric',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $data = temp_signupModel::select('*')
->where(["mobile" => $_REQUEST['mobile'],"otp" => $_REQUEST['otp']])
->get();
 
            if(count($data) != 0)
            {
$send_data = array();  
$data = $data[0];
// START STEP 1 -> INSERT INTO tblclient table
$client_id = $this->INSERT_CLIENT($data);
// END STEP 1 -> INSERT INTO tblclient table

// START STEP 2 -> INSERT INTO school_setup table
$sub_institute_id = $this->INSERT_SCHOOLSETUP($data,$client_id);
// END STEP 2 -> INSERT INTO school_setup table

// START STEP 3 -> INSERT INTO tbluserprofilemaster table
$this->INSERT_USERPROFILEMASTER($sub_institute_id);
// END STEP 3 -> INSERT INTO tbluserprofilemaster table 

// START STEP 4 -> INSERT INTO tbluser table
$new_user_id = $this->INSERT_USER($data,$sub_institute_id);
// END STEP 4 -> INSERT INTO tbluser table  

// START STEP 5 -> INSERT INTO academic_year table
$this->INSERT_ACADEMIC_YEAR($sub_institute_id);
// END STEP 5 -> INSERT INTO academic_year table  

// START STEP 6 -> INSERT INTO academic_section table
$this->INSERT_ACADEMIC_SECTION($sub_institute_id);
// END STEP 6 -> INSERT INTO academic_section table 

// START STEP 7 -> INSERT INTO standard table
$this->INSERT_STANDARD($sub_institute_id);
// END STEP 7 -> INSERT INTO standard table 

// START STEP 8 -> INSERT INTO division table
$this->INSERT_DIVISION($sub_institute_id);
// END STEP 8 -> INSERT INTO division table 

// START STEP 9 -> INSERT INTO subject table
$this->INSERT_SUBJECT($sub_institute_id);
// END STEP 9 -> INSERT INTO subject table 

// START STEP 10 -> INSERT INTO subject table
$this->INSERT_STUDENTQUOTA($sub_institute_id);
// END STEP 10 -> INSERT INTO subject table 

// START STEP 11 -> INSERT INTO tblmenumaster & rightside_menumaster           
$this->INSERT_MENUMASTER($sub_institute_id);
// END STEP 11 -> INSERT INTO tblmenumaster & rightside_menumaster            

// START STEP 12 -> INSERT INTO tblgroupwiseright
$this->INSERT_RIGHTS($data,$sub_institute_id);
// END STEP 12 -> INSERT INTO tblgroupwiseright          

$user_data = DB::select("SELECT id,user_name,mobile,email,password,first_name,last_name,'https://erp.triz.co.in' as URL,
        gender,birthdate,sub_institute_id   
        FROM tbluser 
        WHERE id = '".$new_user_id."' AND sub_institute_id = '".$sub_institute_id."'");

// $send_data = [
//     'mobile' => $data->mobile,
//     'first_name' => $data->first_name,
// ];

$response['status'] = '1';
$response['message'] = 'success';
$response['data'] = $user_data;

if($request->has('type'))
{
    if($request->input("type") == "web")
    {             
        $user_name = ucfirst($user_data[0]->first_name ." ". $user_data[0]->last_name);
        $request->request->add(['email' => $user_data[0]->email]); //add request
        $request->request->add(['password' => $user_data[0]->password]); //add request
        $request->request->add(['captchaText' => '123']); //add request
        $request->request->add(['hid_captcha' => '123']); //add request

        Mail::send('email.signupmail', ['user_name' => $user_name,'email' => $user_data[0]->email,'password' => $user_data[0]->password], 
            function($message) use($user_data){
              $message->to($user_data[0]->email);
              $message->subject('Welcome To Triz Family!');
        });
        $login_object = new loginController();
        // return $login_object->index($request);
        
        $type = $request->input("type");
        $login_data['status_code'] = 1;
        $login_data['message'] = "Please check your email for Username & Password";
        return $data = is_mobile($type, "login", $login_data, "view");

        
        //$this->Login_user($data);
    }
}
else
{
    $user_name = ucfirst($user_data[0]->first_name ." ". $user_data[0]->last_name);
    Mail::send('email.signupmail', ['user_name' => $user_name,'email' => $user_data[0]->email,'password' => $user_data[0]->password], 
        function($message) use($user_data){
          $message->to($user_data[0]->email);
          $message->subject('Welcome To Triz Family!');
    });
    return json_encode($response);
}

            }else{
$response['status'] = '0';
$response['message'] = 'Entered OTP is wrong one.';
$response['otp'] = $_REQUEST['otp'];
$response['mobile'] = $_REQUEST['mobile'];
return is_mobile('','signup_otp',$response,"view");  
            }     
        }
        return json_encode($response);
        //exit;
    }

    public function check_user_exist($mobile_no,$email='')
    {
        $sql = "SELECT id,email,'student' as user_type FROM tblstudent WHERE mobile = '".$mobile_no."' OR email = '".$email."'
UNION
SELECT id,email,'user' as user_type FROM tbluser WHERE mobile = '".$mobile_no."' OR email = '".$email."' ";

        $check_user_sql = DB::select($sql);

        if(count($check_user_sql) == 0)
        {
            return 1;
        }else{
            return 0;
        }
    }

    public function INSERT_CLIENT($data)
    {        
        $contact_person = $data->first_name.' '.$data->last_name;
        $data = array(
'client_name' => $data->institute_name,
'email' => $data->email,
'contact_person' => $contact_person,
'contact_person_mobile' => $data->mobile,
'contact_persoon_email' => $data->email,
'number_of_schools' => '1'
            );
        tblclientModel::insert($data);
        $client_id = DB::getPdo()->lastInsertId();        

        return $client_id;        
    }

    public function INSERT_SCHOOLSETUP($data,$client_id)
    {        
        $contact_person = $data->first_name.' '.$data->last_name;
        $data = array(
'SchoolName' => $data->institute_name,
'ShortCode' => $data->email,
'ContactPerson' => $contact_person,
'Mobile' => $data->mobile,
'Email' => $data->email,
'created_at' => now(),
'updated_at' => now(),
'client_id' => $client_id,
'is_lms' => 'N',
'syear' => date('Y'),
'expire_date' => date('Y-m-d', strtotime(date('Y-m-d'). ' + 1 months'))
            );
        school_setupModel::insert($data);
        $sub_institute_id = DB::getPdo()->lastInsertId();        

        return $sub_institute_id;        
    }

    public function INSERT_USERPROFILEMASTER($sub_institute_id)
    {
        $profile_array = array("Admin","Teacher","LMS Teacher","Student");
        foreach($profile_array as $key => $val)
        {
            $sort_order = ($key + 1);
            $data = array(
    'parent_id' => '0',
    'name' => $val,
    'description' => $val,
    'sort_order' => $sort_order,
    'status' => '1',
    'sub_institute_id' => $sub_institute_id
);
            tbluserprofilemasterModel::insert($data);
        }
    }

    public function INSERT_USER($data,$sub_institute_id)
    {       
        $userprofile_data = tbluserprofilemasterModel::select('*')->where(['sub_institute_id' => $sub_institute_id,'name' => $data->user_type])
            ->get()->toArray(); 
        $userprofile_data = $userprofile_data[0];        
       
        $user_name = strtolower($data->first_name.'.'.$data->last_name);
        $data = array(
'user_name' => $user_name,
'password' => 'staff',
'name_suffix' => '',
'first_name' => $data->first_name,
'middle_name' => '',
'last_name' => $data->last_name,
'email' => $data->email,
'mobile' => $data->mobile,
'gender' => $data->gender,
'birthdate' => $data->birthdate,
'address' => '',
'city' => '',
'state' => '',
'pincode' => '',
'user_profile_id' => $userprofile_data['id'],
'join_year' => date('Y'),
'image' => '',
'plain_password' => 'staff',
'sub_institute_id' => $sub_institute_id,
'status' => '1'
            );
        tbluserModel::insert($data);
        $user_id = DB::getPdo()->lastInsertId();       

        return $user_id; 
    }

    public function INSERT_ACADEMIC_YEAR($sub_institute_id)
    {       
        $current_date = date('Y-m-d');
        $future_date=date('Y-m-d', strtotime('+1 year'));       

        $data = array(
'term_id' => '1', // by default and its not use anywhere
'syear' => date('Y'),
'sub_institute_id' => $sub_institute_id,
'title' => 'TERM-1',
'short_name' => 'T-1',
'sort_order' => '1',
'start_date' => $current_date,
'end_date' => $future_date,
'post_start_date' => $current_date,
'post_end_date' => $future_date,
'does_grades' => 'Y',
'does_exams' => 'Y',
'created_at' => now(),
'updated_at' => now()            
);
        academic_yearModel::insert($data);  
    }

    public function INSERT_ACADEMIC_SECTION($sub_institute_id)
    {
        $academic_section_array = array('PRI'=>'PRIMARY','SEC'=>'SECONDARY','HSEC'=>'HIGH-SECONDARY');
        $j = 1;
        foreach($academic_section_array as $key => $val)
        {            
            $data = array(
    'sub_institute_id' => $sub_institute_id,
    'title' => $val,
    'short_name' => $key,
    'sort_order' => $j++,
    'shift' => '1',
    'medium' => 'CBSE',
    'created_at' => now(),
    'updated_at' => now()
);
            academic_sectionModel::insert($data);
        }
    }

    public function INSERT_STANDARD($sub_institute_id)
    {
        for($i=1;$i<=12;$i++)
        {
            $name = 'CBSE-'.$i;
            $short_name = 'C-'.$i;

            if($i >= 1 && $i <= 5)
            {
$grade_title = 'PRIMARY'; 
            }
            else if($i >= 6 && $i <= 10)
            {
$grade_title = 'SECONDARY'; 
            }
            else if($i >= 11 && $i <= 12)
            {
$grade_title = 'HIGH-SECONDARY'; 
            }

            $adata = academic_sectionModel::select('*')->where(['title'=>$grade_title,'sub_institute_id'=>$sub_institute_id])->get()->toArray();
            $adata = $adata[0];

            $data = array(
    'grade_id' => $adata['id'],
    'name' => $name,
    'short_name' => $short_name,
    'sort_order' => $i,    
    'medium' => 'ENGLISH',
    'sub_institute_id' => $sub_institute_id,
    'course_duration' => '1 Year',
    'created_at' => now(),
    'updated_at' => now()
);
            standardModel::insert($data);
        }
    }

    public function INSERT_DIVISION($sub_institute_id)
    {
        $div_array = range('A', 'D');
         
        foreach($div_array as $key => $val)
        {        
            $data = array(    
    'name' => $val,    
    'sub_institute_id' => $sub_institute_id,   
    'created_at' => now(),
    'updated_at' => now()
);
            divisionModel::insert($data);
        }
    }

    public function INSERT_SUBJECT($sub_institute_id)
    {
        $sub_array = array('Eng'=>'English' ,'Math'=>'Math' , 'Hindi'=>'Hindi' , 'Sci'=>'Science');
         
        $j = 1; 
        foreach($sub_array as $key => $val)
        {      
            $subject_code = "000".$j++;            
            $data = array(    
    'subject_name' => $val,    
    'subject_code' => $subject_code,   
    'subject_type' => 'Major',
    'short_name' => $key,
    'sub_institute_id' => $sub_institute_id,
    'status' => '1',
    'created_at' => now(),
    'updated_at' => now()
);
            subjectModel::insert($data);
        }
    }

    public function INSERT_STUDENTQUOTA($sub_institute_id)
    {
        $data = array(    
    'title' => 'General',    
    'sort_order' => '1',   
    'sub_institute_id' => $sub_institute_id,    
    'created_on' => now()
);
        studentQuotaModel::insert($data);
    }

    public function INSERT_MENUMASTER($sub_institute_id)
    {
        DB::select("UPDATE tblmenumaster SET sub_institute_id = CONCAT_WS(',',sub_institute_id,'".$sub_institute_id."')");
        DB::select("UPDATE rightside_menumaster SET sub_institute_id = CONCAT_WS(',',sub_institute_id,'".$sub_institute_id."')");
    }

    public function INSERT_RIGHTS($data,$sub_institute_id)
    {        
        $user_type = $data->user_type;
        $profileval['name'] = str_replace(' ','',$user_type);       
        $arr_name = strtolower($profileval['name'])."_rights";

        $userprofile_data = tbluserprofilemasterModel::select('*')->where(['sub_institute_id' => $sub_institute_id,'name' => $data->user_type])
            ->get()->toArray(); 
        $userprofile_data = $userprofile_data[0]; 

        //START Give Admin Full rights        
        $adminresult = DB::SELECT("SELECT * FROM tblmenumaster WHERE find_in_set(".$sub_institute_id.",sub_institute_id) and status = 1");
        $adminresult = json_decode(json_encode($adminresult),true);
        if(count($adminresult) > 0)
        {
            foreach($adminresult as $akey => $aval)
            {
$admin_rights[$aval['name'].'_'.$aval['id']] = $aval['id'];
            }
        }
        //END Give Admin Full rights

        $lmsteacher_rights = array(       
            "Student Academics" => 3,
            "Student" => 259,
            "Search/Edit Student" => 80,
            "Bulk Student Update" => 82,
            "Add Student" => 81,
            "Subject Standard Mapping" => 40,
            "Teachers/Users" => 2,
            "Teacher Diary" => 97,
            "Reports" => 4,
            "Student Report 1" => 91,
            "Student Report" => 92,
            "Student Homework Report" => 156,
            "Student Homework Submission Report" => 220,
            "In-active Student Report" => 295,
            "LMS" => 230,
            "Teach/Learn" => 269,
            "All Courses" => 270,
            "Test" => 276,
            "Student Homework" => 90,
            "Homework Submission" => 218,  
            "Assignment" => 312,
            "Annotate Assignment" => 314,
            "Exam" => 242,
            "Engagement" => 301,
            "Social & Collabrotive" => 279,
            "Virtual Classroom" => 280,
            "Portfolio" => 281,
            "Counselling" => 282,
            "Leader Board" => 290,
            "LMS Communication" => 302,
            "Activity Stream" => 277, 
            "Message" => 278, 
            "Report" => 309,
            "Student Analysis Report" => 310,
            "Curriculum Planning" => 327,    
            "Lesson Planning" => 96,
            "Book List" => 153,
            "Syllabus" => 154, 
            "LMS Global Mapping" => 275,  
            "Leader Board Master" => 311, 
            "Create Subject" => 138,
            "Create Timetable" => 22,
            "Users" => 105,
            "Student Height Weight" => 88,
            "Student Health" => 89,
            "Exam Schedule" => 141,
            "Teacher Transfer Utility" => 221
        );       
        // $profile_array = DB::SELECT("SELECT * FROM tbluserprofilemaster WHERE sub_institute_id = '".$sub_institute_id."'");
        // $profile_array = json_decode(json_encode($profile_array),true);
        
        // foreach($profile_array as $profilekey => $profileval)
        // {
        //     $profileval['name'] = str_replace(' ','',$profileval['name']);       
        //     $arr_name = strtolower($profileval['name'])."_rights";
        //     $profile_id = $profileval['id'];
        //     if(isset($$arr_name))
        //     {
foreach($$arr_name as $key => $val)
{
    $check_sql = DB::SELECT("SELECT * FROM tblgroupwise_rights WHERE menu_id = '".$val."'
      AND profile_id = '".$userprofile_data['id']."' AND sub_institute_id = '".$sub_institute_id."'");
    $check_sql = json_decode(json_encode($check_sql),true);
    if(count($check_sql) == 0)
    {   
        $insertQuery = DB::SELECT("INSERT INTO tblgroupwise_rights(menu_id,profile_id,can_view,can_add,can_edit,can_delete,sub_institute_id) 
        values('".$val."','".$userprofile_data['id']."','1','1','1','1','".$sub_institute_id."')");              
    }
}
            //}
        //}
        
    }
    public function Resend_otp(Request $request)
    {
        $mobile_no = $request->get('mobile');
        $otp = rand(100000,999999);
        $sub_institute_id = 1; // Triz Innovation
        $text = "OTP for login is ".$otp." and is valid for 5 minutes";
        $res = sendSMS($mobile_no, $text, $sub_institute_id);
        $res = array("error"=>"0");

        if($res["error"] == 1)
        {
            $message = $res['message'].' - Please add api details first.'; 
            return $message;               
        }else{
            $message = 'Enter receive OTP'; 
            return $message;
        }
    }

    
}
