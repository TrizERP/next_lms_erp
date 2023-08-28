<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\subjectModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setupModel;
use App\Models\student\studentQuotaModel;
use App\Models\tblclientModel;
use App\Models\temp_signupModel;
use App\Models\user\tbluserModel;
use App\Models\user\tbluserprofilemasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;
use function App\Helpers\sendSMS;
use Illuminate\Support\Facades\Storage;


class NewLMS_ApiController extends Controller
{

    public function NewLMS_temp_signup(Request $request)
    {
        // return $request;exit;
        // return back()->with("data","signup");exit;
        $user_type = $request->input("user_type");
        $first_name = $request->input("first_name");
        $last_name = $request->input("last_name");
        $gender = $request->input("gender");
        $birthdate = $request->input("birthdate");
        $email = $request->input("email");
        $mobile = $request->input("mobile");
        $institute_type = $request->input("institute_type");        
        $institute_name = $request->input("institute_name");

        $validator = Validator::make($request->all(), [
            'user_type'      => 'required|in:LMS Teacher,Student,Admin',
            'first_name'     => 'required|regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/',
            'last_name'      => 'required|regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/',
            'email'          => 'required|email',
            'mobile'         => 'required|numeric|digits:10',
            'institute_name' => 'required',
            'institute_type' => 'required',            
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $check_user_exist = $this->check_user_exist($mobile, $email);

            if ($check_user_exist != 0) {
                if ($user_type != "" && $first_name != "" && $last_name != "" 
                    && $email != "" && $mobile != "" && $institute_name != "" && $institute_type != "") {

                    $otp = rand(100000, 999999);
                    $sub_institute_id = 1; // Triz Innovation
                    $text = "Dear Parent, Your OTP is ".$otp.".";
                    $res = sendSMS($mobile, $text, $sub_institute_id);
                    $res = ["error" => "0"];

                    if ($res["error"] == 1) {
                        $response['status'] = 0;
                        $response['message'] = $res['message'].' - Please add api details first.';

                        return json_encode($response);
                    }

                    $check_temp_user = DB::table('temp_signup')->where('mobile', $mobile)->get()->toArray();

                    if (count($check_temp_user) == 0) {
                        $data = [
                            'user_type'      => $user_type,
                            'first_name'     => $first_name,
                            'last_name'      => $last_name,
                            'gender'         => $gender,
                            'birthdate'      => $birthdate,
                            'email'          => $email,
                            'mobile'         => $mobile,
                            'otp'            => $otp,
                            'institute_name' => $institute_name,
                            'syear'          => date('Y'),
                            'ip_address'     => $_SERVER['REMOTE_ADDR'],
                        ];
                        temp_signupModel::insert($data);

                    } else {
                        $data = [
                            'user_type'      => $user_type,
                            'first_name'     => $first_name,
                            'last_name'      => $last_name,
                            'gender'         => $gender,
                            'birthdate'      => $birthdate,
                            'email'          => $email,
                            'otp'            => $otp,
                            'institute_name' => $institute_name,
                            'syear'          => date('Y'),
                            'ip_address'     => $_SERVER['REMOTE_ADDR'],
                        ];

                        temp_signupModel::where(["mobile" => $mobile])->update($data);
                    }

                    $response['status'] = 1;
                    $response['message'] = "Successfully Added";
                } else {
                    $response['status'] = 0;
                    $response['message'] = "Parameter Missing";
                }
            } else {
                $response['status'] = 0;
                $response['message'] = "User is already exists";
            }
        }

        if ($request->has('type')) {
            if ($request->input("type") == "web") {
                $type = $request->input('type');
                if ($response['status'] == 1) {
                    $res = [
                        'user_type'      => $user_type,
                        'first_name'     => $first_name,
                        'last_name'      => $last_name,
                        'gender'         => $gender,
                        'birthdate'      => $birthdate,
                        'email'          => $email,
                        'otp'            => $otp,
                        'institute_name' => $institute_name,
                        'institute_type' => $institute_type,
                        'syear'          => date('Y'),
                        'mobile'         => $mobile,
                        'ip_address'     => $_SERVER['REMOTE_ADDR'],
                    ];

                    return is_mobile($type, 'signup', $res, "view");
                } else {

                    return is_mobile($type, 'signup', $response, "view");
                }
            }
        } else {

            return json_encode($response);
        }
    }

        public function NewLMS_signup(Request $request)
    {
         $user_type = $request->input("user_type");
        $first_name = $request->input("first_name");
        $last_name = $request->input("last_name");
        $gender = $request->input("gender");
        $birthdate = $request->input("birthdate");
        $email = $request->input("email");
        $mobile = $request->input("mobile");
        $institute_name = $request->input("institute_name_confirm");
        $institute_type = $request->input("institute_type");        
         $type = $request->input('type');
        // return $request;
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|numeric',
            'otp'    => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        } else {
            $data = temp_signupModel::select('*')
                ->where(["mobile" => $_REQUEST['mobile'], "otp" => $_REQUEST['otp']])
                ->get();

                // return $data;exit;
            if (count($data) != 0) {
                $response = ['status' => '1', 'message' => 'Entered OTP is wrong one.'];

            if ($request->has('type')) {
            if ($request->input("type") == "web") {
               
                if ($response['status'] == 1) {
                    $response = [
                        'user_type'      => $user_type,
                        'first_name'     => $first_name,
                        'last_name'      => $last_name,
                        'gender'         => $gender,
                        'birthdate'      => $birthdate,
                        'email'          => $email,
                        'institute_name' => $institute_name,
                        'institute_type' => $institute_type,
                        'syear'          => date('Y'),
                        'mobile' =>$_REQUEST['mobile'],
                        'confirm'=>"confirm",
                    ];
                    $response['status'] = 1;
                    $response['message'] = "Successfully Added";
                    return is_mobile($type, 'signup', $response, "view");
                } else {
                    return is_mobile($type, 'signup', $response, "view");
                }

            } else {
                $response['status'] = '0';
                $response['message'] = 'Entered OTP is wrong one.';
                $response['otp'] = $_REQUEST['otp'];
                $response['mobile'] = $_REQUEST['mobile'];
                return is_mobile('', 'signup', $response, "view");
            }
        }
        }
    }
    if($type=='web'){
          $response['status'] = '0';
        $response['failed'] = 'Entered OTP is wrong one.';
        
                return is_mobile('', 'signup', $response, "view");
    }else{
          $response['status'] = '0';
        $response['message'] = 'Entered OTP is wrong one.';
        
        return json_encode($response);

    }

    }
// pre load institute data
    public function Preload_institute(Request $request){
         if ($request->has('preload_btn')) {
               return $this->preload_data($request);
        } elseif ($request->has('institute_btn')) {
            return $this->show_add_institute($request);
        }
    }

    public function preload_data(Request $request){

        $type=$request->type;
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $user_name = $first_name."_".$last_name;
        $password = "admin";
        $email = $request->email;
        $mobile = $request->mobile;
        $gender = $request->gender;
        if($gender=="M"){
            $name_suffix = "Mr.";
        }elseif($gender == "F"){
            $name_suffix = "Mrs.";
        }
        $birthdate = $request->birthdate;
        $user_profile_id = "1";
        $join_year = date('Y');
        $sub_institute_id = "1";
        $client_id = "11";
        $status = "1";

        $data = [
            "user_name"=>$user_name,
            "password"=>$password,
            "name_suffix"=>$name_suffix ?? '',
            "first_name"=>$first_name,
            "last_name"=>$last_name,
            "email"=>$email,
            "mobile"=>$mobile,
            "gender"=>$gender,
            "birthdate"=>$birthdate,
            "user_profile_id"=>$user_profile_id,
            "join_year"=>$join_year,
            "sub_institute_id" => $sub_institute_id,
            "client_id"=>$client_id,
            "status"=>$status,
            "created_on"=>date('d-m-y'),
        ];

         if ($request->has('type')) {
            if ($request->input("type") == "web") {
                         Mail::send('email.signupmail', [
                            'user_name' => $user_name, 'email' => $email,
                            'password'  => $password,
                        ], function ($message) use ($email) {
                            $message->to($email);
                            $message->subject('Welcome To Triz Family!');
                        });

                        $type = $request->input("type");
                        $res['status_code'] = 1;
                        $res['message'] = "Please check your email for Username & Password";

                }
            }

        $insert = DB::table('tbluser')->insert($data);
            // $insert =1;
        $login_data=array(
        'email' => $email,
        'password'=>$password,
        'type'=>$type,
        );
        // return $login_data;exit;
        if($insert==1){
            return redirect()->route('login')->with('login_data', $login_data);

        }else{
            return is_mobile($type, 'signup', $res, "view");
        }
        // return $data;
    }

    public function show_add_institute(Request $request){
      $type=$request->type ?? '';
        $response =[ 
            "status"=>1,
            "message"=>"Welcome ".$request->first_name." ".$request->last_name." ",
            "institute"=>$request->institute_name_confirm,
            "mobile"=>$request->mobile,
            "type"=>$request->type,
            "institute_type"=>$request->institute_type,
        ]?? [];
        // return $request;exit;
        if($request!=null){
        return is_mobile($type, 'add-institute-details', $response, "view");
        }else{
        return is_mobile($type, 'signup', $response, "view");
        }
    }
    public function add_institute(Request $request){
        $type = " ";
        if ($request->has('cropped_image')) {
            $croppedImage = $request->input('cropped_image');
        
            // Generate a unique file name
            $fileName = time() . '.webp';
        
            // Decode the base64 image data
            $imageData = file_get_contents($croppedImage);
        
            // Specify the image storage path
            $imagePath = public_path('admin_dep/images/' . $fileName);
        
            // Store the image file
            file_put_contents($imagePath, $imageData);
        
            Storage::disk('public')->put('user/' . $fileName, $imageData);

        } else {
            $fileName = ' ';
        }
        
            $new_index = ['PRE_PRI', 'PRI', 'SEC', 'HSEC'];
            $selectedRadios = $request->input('exampleRadios');
            $mobile = $request->input('mobile');
            $indexes = [];
            $values = [];

            $allRadios = ['PRE-PRIMARY', 'PRIMARY', 'SECONDARY', 'HIGH-SECONDARY'];

            foreach ($selectedRadios as $selectedRadio) {
                $index = array_search($selectedRadio, $allRadios);
                if ($index !== false) {
                   $indexes[$new_index[$index]] = $selectedRadio;
                }
            }

            $section = $indexes;
               $board = $request->input('exampleRadiosboard');
             
            $institute_type = $request->institute_type;
            $data = temp_signupModel::select('*')
                ->where(["mobile" => $mobile])
                ->get();
                // echo $data;exit;

            if (count($data) != 0) {
                $send_data = [];
                $data = $data[0];
                // START STEP 1 -> INSERT INTO tblclient table
                $client_id = $this->INSERT_CLIENT($data);
                // END STEP 1 -> INSERT INTO tblclient table

                // START STEP 2 -> INSERT INTO school_setup table
                $sub_institute_id = $this->INSERT_SCHOOLSETUP($data, $client_id,$fileName,$institute_type);
                // END STEP 2 -> INSERT INTO school_setup table

                // START STEP 3 -> INSERT INTO tbluserprofilemaster table
                $this->INSERT_USERPROFILEMASTER($sub_institute_id);
                // END STEP 3 -> INSERT INTO tbluserprofilemaster table 

                // START STEP 4 -> INSERT INTO tbluser table
                $new_user_id = $this->INSERT_USER($data, $sub_institute_id,$fileName);
                // END STEP 4 -> INSERT INTO tbluser table  

                // START STEP 5 -> INSERT INTO academic_year table
                $this->INSERT_ACADEMIC_YEAR($sub_institute_id);
                // END STEP 5 -> INSERT INTO academic_year table  

                // START STEP 6 -> INSERT INTO academic_section table
                $this->INSERT_ACADEMIC_SECTION($sub_institute_id,$section,$board);
                // END STEP 6 -> INSERT INTO academic_section table 

                // START STEP 7 -> INSERT INTO standard table
                $this->INSERT_STANDARD($sub_institute_id,$section,$board);
                // END STEP 7 -> INSERT INTO standard table 

                // START STEP 8 -> INSERT INTO division table
                $this->INSERT_DIVISION($sub_institute_id,$board);
                // END STEP 8 -> INSERT INTO division table 

                // START STEP 9 -> INSERT INTO subject table
                $this->INSERT_SUBJECT($sub_institute_id,$board);
                // END STEP 9 -> INSERT INTO subject table 

                // START STEP 10 -> INSERT INTO subject table
                $this->INSERT_STUDENTQUOTA($sub_institute_id,$board);
                // END STEP 10 -> INSERT INTO subject table 

                // START STEP 11 -> INSERT INTO tblmenumaster & rightside_menumaster           
                $this->INSERT_MENUMASTER($sub_institute_id,$board);
                // END STEP 11 -> INSERT INTO tblmenumaster & rightside_menumaster            

                // START STEP 12 -> INSERT INTO tblgroupwiseright
                $this->INSERT_RIGHTS($data, $sub_institute_id,$board);
                // END STEP 12 -> INSERT INTO tblgroupwiseright          


                $user_data = DB::table('tbluser')
                    ->selectRaw("id,user_name,mobile,email,password,first_name,last_name,
                    'https://erp.triz.co.in' as URL, gender,birthdate,sub_institute_id")
                    ->where('id', $new_user_id)
                    ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

                $response['status'] = '1';
                $response['message'] = 'success';
                $response['data'] = $user_data;

                if ($request->has('type')) {
                    if ($request->input("type") == "web") {
                        $user_name = ucfirst($user_data[0]->first_name." ".$user_data[0]->last_name);
                        $request->request->add(['email' => $user_data[0]->email]); //add request
                        $request->request->add(['password' => "admin"]); //add request
                        $request->request->add(['captchaText' =>  env('CAPTCHA')]); //add request
                        $request->request->add(['hid_captcha' =>  env('CAPTCHA')]); //add request

                        Mail::send('email.signupmail', [
                            'user_name' => $user_name, 'email' => $user_data[0]->email,
                            'password'  => "admin",
                        ], function ($message) use ($user_data) {
                            $message->to($user_data[0]->email);
                            $message->subject('Welcome To Triz Family!');
                        });

                        $type = $request->input("type");
                        $login_data=array(
                            'email' => $user_data[0]->email,
                            'password'=>"admin",
                            'type'=>$type,

                            );

                        return redirect()->route('login')->with('login_data', $login_data);
                    }
                }
                        // return $data = is_mobile($type, "signup", $login_data, "view");

            }else{
                $response =[ 
                    "status"=>0,
                    "message"=>"Something Wrong !!",
                ];
                return is_mobile($type, 'add-institute-details', $response, "view");
            }

                        // return $data = is_mobile($type, "login", $login_data, "view");

    }

    public function check_user_exist($mobile_no, $email = '')
    {
        $query = DB::table('tbluser')->selectRaw("id,email,'user' as user_type")
            ->where('mobile', $mobile_no)
            ->where('email', $email);

        $check_user_sql = DB::table('tblstudent')
            ->selectRaw("id,email,'student' as user_type")
            ->where('mobile', $mobile_no)
            ->where('email', $email)
            ->union($query)
            ->get()->toArray();

        if (count($check_user_sql) == 0) {
            return 1;
        } else {
            return 0;
        }
    }

    public function INSERT_CLIENT($data)
    {
        $contact_person = $data->first_name.' '.$data->last_name;
        $data = [
            'client_name'           => $data->institute_name,
            'email'                 => $data->email,
            'contact_person'        => $contact_person,
            'contact_person_mobile' => $data->mobile,
            'contact_persoon_email' => $data->email,
            'number_of_schools'     => '1',
        ];
        tblclientModel::insert($data);

        return DB::getPdo()->lastInsertId();
    }

    public function INSERT_SCHOOLSETUP($data, $client_id,$filename,$institute_type)
    {
        $contact_person = $data->first_name.' '.$data->last_name;
        $data = [
            'SchoolName'    => $data->institute_name,
            'ShortCode'     => $data->email,
            'ContactPerson' => $contact_person,
            'Mobile'        => $data->mobile,
            'Email'         => $data->email,
            'created_at'    => now(),
            'updated_at'    => now(),
            'client_id'     => $client_id,
            'logo'          => $filename,
            'is_lms'        => 'N',
            'syear'         => date('Y'),
            'expire_date'   => date('Y-m-d', strtotime(date('Y-m-d').' + 1 months')),
            'institute_type' => $institute_type,
        ];
        school_setupModel::insert($data);

        return DB::getPdo()->lastInsertId();
    }

    public function INSERT_USERPROFILEMASTER($sub_institute_id)
    {
        $profile_array = ["Admin", "Teacher", "LMS Teacher", "Student"];

        foreach ($profile_array as $key => $val) {
            $sort_order = ($key + 1);
            $data = [
                'parent_id'        => '0',
                'name'             => $val,
                'description'      => $val,
                'sort_order'       => $sort_order,
                'status'           => '1',
                'sub_institute_id' => $sub_institute_id,
            ];

            tbluserprofilemasterModel::insert($data);
        }
    }

    public function INSERT_USER($data, $sub_institute_id,$filename)
    {
        $userprofile_data = tbluserprofilemasterModel::select('*')->where([
            'sub_institute_id' => $sub_institute_id, 'name' => $data->user_type,
        ])->get()->toArray();

        $userprofile_data = $userprofile_data[0];

        $user_name = strtolower($data->first_name.'.'.$data->last_name);
        $surfix = isset($data->name_suffix) ? strtolower($data->name_suffix) : '';

        $data = [
            'user_name'        => $user_name,
            'password'         => 'admin',
            'name_suffix'      => $surfix ?? '',
            'first_name'       => $data->first_name,
            'middle_name'      => '',
            'last_name'        => $data->last_name,
            'email'            => $data->email,
            'mobile'           => $data->mobile,
            'gender'           => $data->gender,
            'birthdate'        => $data->birthdate,
            'address'          => '',
            'city'             => '',
            'state'            => '',
            'pincode'          => '',
            'user_profile_id'  => $userprofile_data['id'],
            'join_year'        => date('Y'),
            'image'            => $filename ?? '',
            'plain_password'   => 'admin',
            'sub_institute_id' => $sub_institute_id,
            'status'           => '1',
        ];

        tbluserModel::insert($data);

        return DB::getPdo()->lastInsertId();
    }

    public function INSERT_ACADEMIC_YEAR($sub_institute_id)
    {
        $current_date = date('Y-m-d');
        $future_date = date('Y-m-d', strtotime('+1 year'));

        $data = [
            'term_id'          => '1', // by default and its not use anywhere
            'syear'            => date('Y'),
            'sub_institute_id' => $sub_institute_id,
            'title'            => 'TERM-1',
            'short_name'       => 'T-1',
            'sort_order'       => '1',
            'start_date'       => $current_date,
            'end_date'         => $future_date,
            'post_start_date'  => $current_date,
            'post_end_date'    => $future_date,
            'does_grades'      => 'Y',
            'does_exams'       => 'Y',
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        academic_yearModel::insert($data);
    }

    public function INSERT_ACADEMIC_SECTION($sub_institute_id,$board,$section)
    {
        if($board !== '' && $section !== ''){
            $academic_section_array = ['PRI' => 'PRIMARY', 'SEC' => 'SECONDARY', 'HSEC' => 'HIGH-SECONDARY'];

            $j = 1;
             foreach($board as $index => $bod){
            foreach ($section as $key => $val) {
                $data = [
                    'sub_institute_id' => $sub_institute_id,
                    'title'            => $bod.'-'.$val,
                    'short_name'       => $index,
                    'sort_order'       => $j++,
                    'shift'            => '1',
                    'medium'           => $val,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
                academic_sectionModel::insert($data);
                     // echo "<pre>";print_r($data);
            }
        }
    }else{
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
      
    }

    public function INSERT_STANDARD($sub_institute_id,$section,$board)
    {
        if ($board !== '' && $section !== '') {
            $grades = [];
            $j = [];
            foreach ($board as $key => $medium) {

                if ($medium == 'GSEB') {
                    $name = 'GSEB-';
                    $title = "GSEB";
                    $short_name = 'G-';
                }
                if ($medium == 'CBSE') {
                    $name = 'CBSE-';
                    $title = "CBSE";
                    $short_name = 'C-';
                }
                if ($medium == 'BSEB') {
                    $name = 'BSEB-';
                    $title = "BSEB";
                    $short_name = 'B-';

                }
                if ($medium == 'BSEAP') {
                    $name = 'BSEAP-';
                    $title = "BSEAP";
                    $short_name = 'BP-';

                }

                if (isset($section['PRE_PRI'])) {

                    $grade_title = 'PRE-PRIMARY';

                    $adatas = academic_sectionModel::select('*')->where([
                        'title' => $grade_title . '-' . $title, 'sub_institute_id' => $sub_institute_id,
                    ])->get()->toArray();
                    $adata = $adatas[$key] ?? $adatas[0];
            // echo "<pre>";print_r($adatas[0]);exit;
                    $data = [
                        'grade_id' => isset($adata['id']) ? $adata['id'] : null,
                        'name' => $name . "NUR",
                        'short_name' => $name . "NUR",
                        'sort_order' => 1,
                        'medium' => 'ENGLISH',
                        'sub_institute_id' => $sub_institute_id,
                        'course_duration' => '1 Year',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    standardModel::insert($data);

                    $data2 = [
                        'grade_id' => isset($adata['id']) ? $adata['id'] : null,
                        'name' => $name . "JR",
                        'short_name' => $name . "JR",
                        'sort_order' => 2,
                        'medium' => 'ENGLISH',
                        'sub_institute_id' => $sub_institute_id,
                        'course_duration' => '1 Year',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    standardModel::insert($data2);

                    $data3 = [
                        'grade_id' => isset($adata['id']) ? $adata['id'] : null,
                        'name' => $name . "SR",
                        'short_name' => $name . "SR",
                        'sort_order' => 3,
                        'medium' => 'ENGLISH',
                        'sub_institute_id' => $sub_institute_id,
                        'course_duration' => '1 Year',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    standardModel::insert($data3);
                }

                if (isset($section['PRI'])) {
                    for ($i = 1; $i <= 5; $i++) {

                        $grade_title = 'PRIMARY';
                        $adatas = academic_sectionModel::select('*')->where([
                            'title' => $grade_title . '-' . $title, 'sub_institute_id' => $sub_institute_id,
                        ])->get()->toArray();
                        $adata = $adatas[$key] ?? $adatas[0];

                        $data = [
                            'grade_id' => isset($adata['id']) ? $adata['id'] : null,
                            'name' => $name . $i,
                            'short_name' => $short_name . $i,
                            'sort_order' => $i,
                            'medium' => 'ENGLISH',
                            'sub_institute_id' => $sub_institute_id,
                            'course_duration' => '1 Year',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        standardModel::insert($data);

                    }
                }
                if (isset($section['SEC'])) {

                    for ($i = 6; $i <= 10; $i++) {

                        $grade_title = 'SECONDARY';
                        $adatas = academic_sectionModel::select('*')->where([
                            'title' => $grade_title . '-' . $title, 'sub_institute_id' => $sub_institute_id,
                        ])->get()->toArray();
                        $adata = $adatas[$key] ?? $adatas[0];

                        $data = [
                            'grade_id' => isset($adata['id']) ? $adata['id'] : null,
                            'name' => $name . $i,
                            'short_name' => $short_name . $i,
                            'sort_order' => $i,
                            'medium' => 'ENGLISH',
                            'sub_institute_id' => $sub_institute_id,
                            'course_duration' => '1 Year',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        standardModel::insert($data);
                    }
                }
                if (isset($section['HSEC'])) {

                    for ($i = 11; $i <= 12; $i++) {
                        $grade_title = 'HIGH-SECONDARY';
                        $adatas = academic_sectionModel::select('*')->where([
                            'title' => $grade_title . '-' . $title, 'sub_institute_id' => $sub_institute_id,
                        ])->get()->toArray();
                        $adata = $adatas[$key] ?? $adatas[0];

                        $data = [
                            'grade_id' => $adata['id'],
                            'name' => $name . $i,
                            'short_name' => $short_name . $i,
                            'sort_order' => $i,
                            'medium' => 'ENGLISH',
                            'sub_institute_id' => $sub_institute_id,
                            'course_duration' => '1 Year',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        standardModel::insert($data);

                    }
                }
            }
        } else {
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
}

    public function INSERT_DIVISION($sub_institute_id,$board)
    {
        $div_array = range('A', 'D');
           
        foreach ($div_array as $key => $val) {
            $data = [
                'name'             => $val,
                'sub_institute_id' => $sub_institute_id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            divisionModel::insert($data);
        }

        $get_standards = DB::table('standard')->where('sub_institute_id', $sub_institute_id)->get();

        foreach($get_standards as $get_standard)
        {
            $get_division = DB::table('division')->where(['sub_institute_id' => $sub_institute_id, 'name' => 'A'])->first('id');

            $data = [
                'standard_id'      => $get_standard->id,
                'division_id'      => $get_division->id,
                'sub_institute_id' => $sub_institute_id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            std_div_mappingModel::insert($data);
        }
    }

    public function INSERT_SUBJECT($sub_institute_id,$board)
    {
        if ($board !== '') {
            $sub_array = ['Eng' => 'English', 'Math' => 'Math', 'Hindi' => 'Hindi', 'Sci' => 'Science', 'Guj' => 'Gujarati'];

            $j = 1;
            foreach ($board as $medium) {

                foreach ($sub_array as $key => $val) {
                    $subject_code = "000" . $j++;
                    $data = [
                        'subject_name' => $val,
                        'subject_code' => $subject_code,
                        'subject_type' => 'Major',
                        'short_name' => $key.'-'.$medium,
                        'sub_institute_id' => $sub_institute_id,
                        'status' => '1',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    subjectModel::insert($data);
                }
            }
        } else {
            $sub_array = array('Eng' => 'English', 'Math' => 'Math', 'Hindi' => 'Hindi', 'Sci' => 'Science');

            $j = 1;
            foreach ($sub_array as $key => $val) {
                $subject_code = "000" . $j++;
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
    }

    public function INSERT_STUDENTQUOTA($sub_institute_id,$board)
    {
        $data = [
            'title'            => 'General',
            'sort_order'       => '1',
            'sub_institute_id' => $sub_institute_id,
            'created_on'       => now(),
        ];

        studentQuotaModel::insert($data);
    }

    public function INSERT_MENUMASTER($sub_institute_id,$board)
    {
        // TODO: This query will be change
        DB::select("UPDATE tblmenumaster SET sub_institute_id = CONCAT_WS(',',sub_institute_id,'".$sub_institute_id."')");
        DB::select("UPDATE rightside_menumaster SET sub_institute_id = CONCAT_WS(',',sub_institute_id,'".$sub_institute_id."')");
    }

    public function INSERT_RIGHTS($data, $sub_institute_id,$board)
    {
        $user_type = $data->user_type;
        $profileval['name'] = str_replace(' ', '', $user_type);
        $arr_name = strtolower(str_replace(' ', '', $profileval['name']))."_rights";
        $arr_name = [$arr_name, "teacher_rights", "student_rights"];
        
        $user = ["Admin", "Teacher", "Student"];

        $userprofile_data = tbluserprofilemasterModel::select('*')->where([
            'sub_institute_id' => $sub_institute_id
        ])->whereIn('name', $user)->get()->toArray();
        
        //START Give Admin Full rights        

        $adminresult = DB::table('tblmenumaster')
            ->whereRaw("find_in_set(".$sub_institute_id.",sub_institute_id)")
            ->where('status', 1)->get()->toArray();
        $adminresult = json_decode(json_encode($adminresult), true);
        if (count($adminresult) > 0) {
            foreach ($adminresult as $akey => $aval) {
                $admin_rights[$aval['name'].'_'.$aval['id']] = $aval['id'];
            }
        }
        //END Give Admin Full rights

        $lmsteacher_rights = [
            "Student Academics"                  => 3,
            "Student"                            => 259,
            "Search/Edit Student"                => 80,
            "Bulk Student Update"                => 82,
            "Add Student"                        => 81,
            "Subject Standard Mapping"           => 40,
            "Teachers/Users"                     => 2,
            "Teacher Diary"                      => 97,
            "Reports"                            => 4,
            "Student Report 1"                   => 91,
            "Student Report"                     => 92,
            "Student Homework Report"            => 156,
            "Student Homework Submission Report" => 220,
            "In-active Student Report"           => 295,
            "LMS"                                => 230,
            "Teach/Learn"                        => 269,
            "All Courses"                        => 270,
            "Test"                               => 276,
            "Student Homework"                   => 90,
            "Homework Submission"                => 218,
            "Assignment"                         => 312,
            "Annotate Assignment"                => 314,
            "Exam"                               => 242,
            "Engagement"                         => 301,
            "Social & Collabrotive"              => 279,
            "Virtual Classroom"                  => 280,
            "Portfolio"                          => 281,
            "Counselling"                        => 282,
            "Leader Board"                       => 290,
            "LMS Communication"                  => 302,
            "Activity Stream"                    => 277,
            "Message"                            => 278,
            "Report"                             => 309,
            "Student Analysis Report"            => 310,
            "Curriculum Planning"                => 327,
            "Lesson Planning"                    => 96,
            "Book List"                          => 153,
            "Syllabus"                           => 154,
            "LMS Global Mapping"                 => 275,
            "Leader Board Master"                => 311,
            "Create Subject"                     => 138,
            "Create Timetable"                   => 22,
            "Users"                              => 105,
            "Student Height Weight"              => 88,
            "Student Health"                     => 89,
            "Exam Schedule"                      => 141,
            "Teacher Transfer Utility"           => 221,
        ];

        $lmsstudent_rights = [
            "Student Academics"                  => 3,
            "LMS"                                => 230,
            "Teach/Learn"                        => 269,
            "All Courses"                        => 270,
            "LMS Global Mapping"                 => 275,
            "Test"                               => 276,
            "Student Homework"                   => 90,
            "Homework Submission"                => 218,
            "Assignment"                         => 312,
            "Assignment Submission"                => 313,
            "Exam"                               => 242,
            "Engagement"                         => 301,
            "Social & Collabrotive"              => 279,
            "Virtual Classroom"                  => 280,
            "Portfolio"                          => 281,
            "Counselling"                        => 282,
            "Leader Board"                       => 290,
            "LMS Communication"                  => 302,
            "Activity Stream"                    => 277,
            "Message"                            => 278,
            "Student"                            => 259,
            "Search/Edit Student"                => 80,
            "Leader Board Master"                => 311,
            "Report"                             => 309,
            "Student Analysis Report"            => 310,
            "Curriculum Planning"                => 327,
            "Lesson Planning"                    => 96,
            "Book List"                          => 153,
            "Syllabus"                           => 154,
            "Payroll Type"                       => 352,
        ];

        $menuIdMapping = [
            "admin_rights"   => $admin_rights,
            "teacher_rights" =>  $lmsteacher_rights ,
            "student_rights" => $lmsstudent_rights,
        ];

        foreach ($userprofile_data as $profile) 
        {    
            $profileType = strtolower(str_replace(' ', '', $profile['name']))."_rights";

            foreach ($menuIdMapping[$profileType] as $menuName => $val) 
            {
                $check_sql = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $val)
                    ->where('profile_id', $profile['id'])
                    ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

                $check_sql = json_decode(json_encode($check_sql), true); 
          
                if (count($check_sql) == 0) 
                {
                    $insertQuery = DB::table('tblgroupwise_rights')
                        ->insert([
                            'menu_id'          => $val,
                            'profile_id'       => $profile['id'],
                            'can_view'         => '1',
                            'can_add'          => '1',
                            'can_edit'         => '1',
                            'can_delete'       => '1',
                            'sub_institute_id' => $sub_institute_id,
                        ]);

                    $profileinsertQuery = DB::table('tblprofilewise_menu')
                    ->insert([
                        'menu_id'          => $val,
                        'user_profile_id'  => $profile['id'],
                        'sub_institute_id' => $sub_institute_id,
                    ]);
                }
            }
        }
    }

    public function Resend_otp(Request $request)
    {
        $mobile_no = $request->get('mobile');
        $otp = rand(100000, 999999);
        $sub_institute_id = 1; // Triz Innovation
        $text = "Dear Parent, Your OTP is ".$otp.".";
        $res = sendSMS($mobile_no, $text, $sub_institute_id);

        if ($res["error"] == 1) {
            $res['message']=' - Please add api details first.';
        }else{
            $res['message']='OTP Resend Successfully .';
        }
        return is_mobile('', 'signup', $res, "view");

    }
}
