<?php

namespace App\Http\Controllers;

use App\Models\loginModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\SchoolModel;
use App\Models\student\tblstudentModel;
use App\Models\tourModel;
use App\Models\user\tbluserprofilemasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

class loginController extends Controller
{

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required',
            'password' => 'required',
        ]);

        $type = $request->input('type');

        if ($validator->fails()) {
            if (! empty(session()->get('loginpage_link'))) {
                $res = array();
            } else {
                $res['status_code'] = 0;
                $res['message'] = "Parameter Missing";
            }
            $data = is_mobile($type, "login", $res, "view");

            return $data;
        }


        $email = $request->input("email");
        $password = $request->input("password");
        $captchaText = $request->input("captchaText");
//        $hid_captcha = $request->input("hid_captcha");

        if ($captchaText != env('CAPTCHA')) {
            $validator = Validator::make($request->all(), [
                'captchaText' => 'required|captcha',
            ]);
            if ($validator->fails()) {
                $res['status_code'] = 0;
                $res['message'] = "Invalid Captcha";

                return is_mobile($type, "login", $res, "view");
            }
//            $captchaText = $hid_captcha;
        }

        // $data = loginModel::where(['email' => $email, 'password' => $password])->first();

        // $silver = DB::table("tbluser")->select("tbluser.id","tbluser.user_name","tbluser.email","tbluser.password","tbluser.user_profile_id")->where(['tbluser.email' => $email, 'tbluser.password' => $password]);

        // $gold = DB::table("tblstudent")->select("tblstudent.id","tblstudent.username","tblstudent.email","tblstudent.password","tblstudent.user_profile_id")
        // 	->where(['tblstudent.email' => $email, 'tblstudent.password' => $password])
        //     ->union($silver)
        //     ->get();

        //  $first = DB::table('tbluser')
        //         ->select('id', 'user_name','email','password','user_profile_id')
        //         ->where('email', '=', $email)
        //         ->where('password', '=', $password);

        // $sec_data = DB::table('tblstudent')
        //                 ->select('id', 'username','email','password','user_profile_id')
        //                 ->where('email', '=', $email)
        //                 ->where('password', '=', $password);

        // $divya = $first->unionall($sec_data)->get();

        //    $notifications = DB::table('tbluser')
        //           ->select("tbluser.id","tbluser.user_name","tbluser.email","tbluser.password","tbluser.user_profile_id");

        // $posts = DB::table('tblstudent')
        //     ->select("tblstudent.id","tblstudent.username","tblstudent.email","tblstudent.password","tblstudent.user_profile_id")
        //     ->unionAll($notifications);

        // $result = DB::table(DB::raw("({$posts->toSql()}) as posts"))
        //           ->mergeBindings($posts)
        //           ->where(['email' => $email, 'password' => $password])
        //           ->get();

        $a = loginModel::select(DB::raw('id,user_name,password,name_suffix,first_name,middle_name,last_name,email,mobile,gender,
		birthdate,address,city,state,pincode,user_profile_id,join_year,image,plain_password,sub_institute_id,client_id,is_admin,status,last_login'))
            ->where(['email' => $email, 'password' => $password, 'status' => "1"]);

        $data = tblstudentModel::select(DB::raw('id,username as user_name,password,"" as name_suffix,first_name,middle_name,last_name,email,
            mobile, gender,dob as birthdate,address,city,state,pincode,user_profile_id,admission_year as join_year,image,"student" as plain_password,
            sub_institute_id,"" as client_id,"" as is_admin,status,created_on as last_login'))
            ->where(['email' => $email, 'password' => md5($password), 'status' => "1"])
            ->union($a)
            ->get();

        // $result = DB::select("SELECT id,user_name,email,password,user_profile_id
        // 					FROM tbluser
        // 					WHERE email='".$email."' AND password = '".$password."'
        // 					UNION
        // 					SELECT id,username as user_name,email,password,user_profile_id
        // 					FROM tblstudent
        // 					WHERE email = '".$email."' AND password = md5('".$password."')
        // 					");

        // $clasa = json_decode(json_encode($result),FALSE);

        //START Check user Rights
        $rightsMenusIds = 0;
        if (count($data) != 0) {
            $udata = $data[0];

            if ($udata['plain_password'] == 'student' || $udata['plain_password'] == 'Student' || $udata['plain_password'] == 'STUDENT') {

                $rightsQuery = DB::table('tblstudent as u')
                    ->leftJoin('tblindividual_rights as i', function ($join) {
                        $join->whereRaw('u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id');
                    })->leftJoin('tblgroupwise_rights as g', function ($join) {
                        $join->whereRaw('u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id');
                    })->join('tblmenumaster as m', function ($join) use ($udata) {
                        $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $udata['sub_institute_id'] . ",
                        m.sub_institute_id)");
                    })->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
                    ->whereIn('u.sub_institute_id', explode(',', $udata['sub_institute_id']))
                    ->where('u.id', $udata['id'])->get()->toArray();

            } else {
                //START FOR MULTI-INSTITUTE

                if ($udata['sub_institute_id'] == 0 && $udata['client_id'] != '' && $udata['is_admin'] == 1) {
                    $rightsQuery = DB::table('tbluser as u')
                        ->leftJoin('tblindividual_rights as i', function ($join) {
                            $join->whereRaw('u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id');
                        })->leftJoin('tblgroupwise_rights as g', function ($join) {
                            $join->whereRaw('u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id');
                        })->join('tblmenumaster as m', function ($join) use ($udata) {
                            $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $udata['client_id'] . ",
                        m.client_id)");
                        })->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
                        ->whereIn('u.sub_institute_id', explode(',', $udata['sub_institute_id']))
                        ->where('u.id', $udata['id'])->get()->toArray();
                    //END FOR MULTI-INSTITUTE
                } else {
                    $rightsQuery = DB::table('tbluser as u')
                        ->leftJoin('tblindividual_rights as i', function ($join) {
                            $join->whereRaw('u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id');
                        })->leftJoin('tblgroupwise_rights as g', function ($join) {
                            $join->whereRaw('u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id');
                        })->join('tblmenumaster as m', function ($join) use ($udata) {
                            $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $udata['sub_institute_id'] . ",
                        m.sub_institute_id)");
                        })->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
                        ->whereIn('u.sub_institute_id', explode(',', $udata['sub_institute_id']))
                        ->where('u.id', $udata['id'])->get()->toArray();
                }
            }

            $rightsQuery = array_map(function ($value) {
                return (array)$value;
            }, $rightsQuery);
            if (isset($rightsQuery['0']['MID'])) {
                $rightsMenusIds = $rightsQuery['0']['MID'];
            }
        }
        //END Check user Rights

//        if ($captchaText != $hid_captcha) {
//        if (false) {
//            if ($type != 'API' || $type != 'direct') {
//                $res['status_code'] = 0;
//                $res['message'] = "Invalid Captcha";
//
//                return is_mobile($type, "login", $res, "view");
//            }
//
//        } else {
        if (count($data) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "Invalid User Id And Password";

            return is_mobile($type, "login", $res, "view");
        } else {
            if ($rightsMenusIds == 0) { //Check user Rights
                $res['status_code'] = 0;
                $res['message'] = "Please Contact Administrator For ERP Rights";

                    return is_mobile($type, "login", $res, "view");
                } else {
                    $user = $data->toArray();
                    $user = $user[0];

                    $userprofiledetails = tbluserprofilemasterModel::where(['id' => $user['user_profile_id']])->get()->toArray();

                //START FOR MULTI-INSTITUTE
                    if ($user['is_admin'] == 1) {
                        $schoolData = DB::table('tblclient')->where(['id' => $user['client_id']])->get()->toArray();
                        $schoolData = json_decode(json_encode($schoolData), true);
                        $ShortCode = $schoolData[0]['short_code'];
                        $SchoolName = $schoolData[0]['client_name'];
                        $Logo = $schoolData[0]['logo'];

                        $getMultiInst = DB::table('tblclient')->where(['id' => $user['client_id']])->get()->toArray();
                        if (isset($getMultiInst['0']->multischool)) {
                            $request->session()->put('multiSchool', $getMultiInst['0']->multischool);
                        }

                        $schools = SchoolModel::where(['client_id' => $user['client_id']])->get()->toArray();

                        $client_sub_institute_id = '';
                        if (count($schools) > 0) {
                            $client_sub_institute_id = $schools[0]['Id'];
                        }
echo "<pre>";print_r($client_sub_institute_id);exit;

                        $getTermId = academic_yearModel::where(['sub_institute_id' => $client_sub_institute_id])
                            ->whereRaw('"'.date('Y-m-d').'" '.'between start_date and end_date')
                            ->get()->toArray();

                        $given_hrms_rights = '';
                        $getAcademicTerms = $getAcademicYear = array();

                        $getInstitutes = DB::table('school_setup')->where('client_id',
                            $user['client_id'])->get()->toArray();
                        if($client_sub_institute_id == 63){
echo "<pre>";print_r($getTermId);exit;
}
                        $request->session()->put('sub_institute_id', '');
                        $request->session()->put('syear', $getTermId[0]['syear']);
                        $request->session()->put('term_id', $getTermId[0]['term_id']);
                        $request->session()->put('academicTerms', $getAcademicTerms);
                        $request->session()->put('academicYears', $getAcademicYear);
                        $request->session()->put('getInstitutes', $getInstitutes);
                        $request->session()->put('erpTour', '');

                        /*$checkUserTour = tourModel::where(['user_id' => $user['id'], 'sub_institute_id' => $user['sub_institute_id']])->get()->toArray();

                        if (count($checkUserTour) > 0)
                        {
                            $inTour = $checkUserTour[0];
                        } else {
                            $inTour['dashboard'] = 0;
                            $inTour['school_sidebar'] = 0;
                            $inTour['student_quota'] = 0;
                            $inTour['user_id'] = $user['id'];
                            $inTour['sub_institute_id'] = $user['sub_institute_id'];
                            tourModel::insert($inTour);
                        }*/


                    }//END FOR MULTI-INSTITUTE
                    else {
                        $schoolData = SchoolModel::where(['id' => $user['sub_institute_id']])->get()->toArray();
                        $ShortCode = $schoolData[0]['ShortCode'];
                        $SchoolName = $schoolData[0]['SchoolName'];
                        $Logo = $schoolData[0]['Logo'];

                        if (isset($schoolData[0]['client_id'])) {
                            $getMultiInst = DB::table('tblclient')->where(['id' => $schoolData[0]['client_id']])->get()->toArray();
                            if (isset($getMultiInst['0']->multischool)) {
                                $request->session()->put('multiSchool', $getMultiInst['0']->multischool);
                            }
                        }

                        $getTermId = academic_yearModel::where(['sub_institute_id' => $user['sub_institute_id']])
                            ->whereRaw('"'.date('Y-m-d').'" '.'between start_date and end_date')
                            ->get()->toArray();

                        //START set class teacher standard , grade , division
                        DB::table('tbluserprofilemaster')->where('NAME', 'Teacher')
                            ->where('sub_institute_id', $user['sub_institute_id'])->get()->toArray();
                        $user_group_id = DB::table('tbluserprofilemaster')->where('NAME', 'Teacher')
                            ->where('sub_institute_id', $user['sub_institute_id'])->get()->toArray();
                        $user_group_id = $user_group_id[0]->id;
                        if ($user_group_id == session()->get('user_profile_id')) {

                            $class_teacher = DB::table('class_teacher')->where('teacher_id', $user['id'])
                                ->where('sub_institute_id', $user['sub_institute_id'])
                                ->where('syear', $getTermId[0]['syear'])->get()->toArray();
                            $classTeacherGrdArr = $classTeacherStdArr = $classTeacherDivArr = array();
                            if (count($class_teacher) > 0) {
                                foreach ($class_teacher as $k => $v) {
                                    $classTeacherGrdArr[] = $v->grade_id;
                                    $classTeacherStdArr[] = $v->standard_id;
                                    $classTeacherDivArr[] = $v->division_id;
                                }
                            }
                            $request->session()->put('classTeacherGrdArr', $classTeacherGrdArr);
                            $request->session()->put('classTeacherStdArr', $classTeacherStdArr);
                            $request->session()->put('classTeacherDivArr', $classTeacherDivArr);
                        }
                        //END set class teacher standard , grade , division


                        $hrms_rights = DB::table('school_setup as s')->join('tblclient as c', function ($join) {
                            $join->whereRaw('c.id = s.client_id');
                        })->selectRaw('if(db_hrms is null,0,1) as rights')
                            ->where('s.Id', $user['sub_institute_id'])->get()->toArray();
                        $given_hrms_rights = $hrms_rights[0]->rights;

                        $getAcademicTerms = DB::table('academic_year')
                            ->where('sub_institute_id', $user['sub_institute_id'])
                            ->where('syear', $getTermId[0]['syear'])->get()->toArray();

                        $getAcademicYear = DB::table('academic_year')
                            ->where('sub_institute_id', $user['sub_institute_id'])
                            ->groupBy('syear')->get()->toArray();

                        $request->session()->put('sub_institute_id', $user['sub_institute_id']);
                        $request->session()->put('syear', $getTermId[0]['syear']);
                        $request->session()->put('term_id', $getTermId[0]['term_id']);
                        $request->session()->put('academicTerms', $getAcademicTerms);
                        $request->session()->put('academicYears', $getAcademicYear);
                        $request->session()->put('getInstitutes', '');

                        $checkUserTour = tourModel::where([
                            'user_id'          => $user['id'],
                            'sub_institute_id' => $user['sub_institute_id'],
                        ])->get()->toArray();

                        if (count($checkUserTour) > 0) {
                            $inTour = $checkUserTour[0];
                        } else {
                            $inTour['dashboard'] = 0;
                            $inTour['school_sidebar'] = 0;
                            $inTour['student_quota'] = 0;
                            $inTour['user_id'] = $user['id'];
                            $inTour['sub_institute_id'] = $user['sub_institute_id'];
                            tourModel::insert($inTour);
                        }

                        $request->session()->put('erpTour', $inTour);
                    }

                    $request->session()->put('user_id', $user['id']);
                    $request->session()->put('user_profile_id', $user['user_profile_id']);
                    $request->session()->put('DUSER_ID', $user['user_name']);
                    $request->session()->put('DUSER_PWD', $user['password']);
                    $request->session()->put('hrms_rights', $given_hrms_rights);
                    $request->session()->put('client_id', $user['client_id']);
                    $request->session()->put('is_admin', $user['is_admin']);
                    $request->session()->put('user_profile_name', $userprofiledetails[0]['name']);
                    $request->session()->put('user_name', $user['user_name']);
                    $request->session()->put('name', $user['first_name'].' '.$user['last_name']);
                    $request->session()->put('email', $user['email']);
                    $request->session()->put('image', $user['image']);
                    $request->session()->put('erpcode', $ShortCode);
                    $request->session()->put('school_name', $SchoolName);
                    $request->session()->put('school_logo', $Logo);

                    $res['status_code'] = 1;
                    $res['message'] = "User Successfully Login";
                    $user['user_profile'] = $userprofiledetails[0]['name'];
                    $res['data'] = $user;
                    $res['academicTerms'] = $getAcademicTerms;
                    $res['academicYears'] = $getAcademicYear;

                    return is_mobile($type, "dashboard", $res);
                }
            }
//        }
    }

    public function logout(Request $request)
    {
        //logout user
        $request->session()->flush();

        // redirect to homepage
        return redirect('/');
    }

    public function profileAPI(Request $request)
    {
        $type = $request->input("type");
        $mobile_number = $request->input("mobile_number");

        if ($mobile_number != '') {
            $data = DB::table('tbluser as u')
                ->join('tbluserprofilemaster as um', function ($join) {
                    $join->whereRaw('u.user_profile_id = um.id');
                })->join('school_setup as s', function ($join) {
                    $join->whereRaw('.sub_institute_id = s.Id');
                })->selectRaw('u.*,um.name AS profile,s.SchoolName AS school_name')
                ->where('u.mobile', $mobile_number)->get()->toArray();

            $data = array_map(function ($value) {
                return (array) $value;
            }, $data);

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;


        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return is_mobile($type, "implementation", $res);
    }

    public function ajaxMenuSession(Request $request)
    {
        $type = $request->input("type");
        $menu_id = $request->input("menu_id");

        if ($menu_id != '') {
            $request->session()->put('right_menu_id', $menu_id);

            $res['status_code'] = 1;
            $res['message'] = "Success";

        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return is_mobile($type, "implementation", $res);
    }

}
