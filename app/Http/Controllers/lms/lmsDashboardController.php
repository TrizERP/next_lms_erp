<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\result\result_api\resultAPIController;
use DB;

class lmsDashboardController extends Controller
{
    //
    public function index(Request $request){
        $res = session()->get('data');
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id') ;
        $syear = session()->get('syear') ;
        $user_id = session()->get('user_id') ;

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id= $request->user_id;
        }
        // [user_id] => 97382
        // [DUSER_ID] => evaan_rafaliya
        // [DUSER_PWD] => cd73502828457d15655bbd7a63fb0bc8
        // [hrms_rights] => 0
        // [client_id] => 
        // [is_admin] => 
        // [user_profile_name] => Student
        // [profile_parent_id] => 0
        // [user_name] => evaan_rafaliya
        // [name] => EVAAN RAFALIYA
        // [email] => student1@gmail.com
        // [image] => 97382_1.jpg
        // [erpcode] => TIS
        // [school_name] => Triz International School
        // [school_logo] => scholar_clone.png
        // [user_token] => 1276216921_97382
        // [current_menu_id] =>
        // get student standards
        $res['standardData'] = DB::table('tblstudent_enrollment as se')
                                ->join('tblstudent as s','s.id','=','se.student_id')    
                                ->join('standard as std','std.id','=','se.standard_id')
                                 ->selectRaw('se.student_id as id,s.enrollment_no,std.id as standardId,std.name as standardName,se.syear')
                                ->where('se.student_id',$user_id)
                                ->orderBy('se.syear')
                                ->get()->toArray();
            set_time_limit(300);

        $resultAPIController = new resultAPIController;

        if(isset($request->stdname)){
            // create a request 
            $res['standard_name'] = $request->stdname;
            $request2 = new Request(['type' => "API",'sub_institute_id'=>$sub_institute_id,'enrollment_no'=>$request->enrollment_no,'syear'=>$request->syear]);
            $res['selectedData'] = $resultAPIController->resultPersonalize($request2);
        }
        // get current standard 
            $currentData =  DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as s','s.id','=','se.student_id')    
            ->join('standard as std','std.id','=','se.standard_id')
            ->selectRaw('se.student_id as id,s.enrollment_no,std.id as standardId,std.name as standardName,se.syear,se.standard_id')
            ->where('se.student_id',$user_id)
            ->where('syear',$syear)
            ->first(); 

            $request3 = new Request(['type' => "API",'sub_institute_id'=>$sub_institute_id,'enrollment_no'=>$request->enrollment_no,'student_id'=>$currentData->id,'standard'=>$currentData->standard_id,'syear'=>$syear]);
        $res['selectedCurrentData'] = $resultAPIController->currentResult($request3);
        $res['standardCount'] = count($res['standardData']);
        $res['user_id'] = $user_id;
        $res['sub_institute_id'] = $sub_institute_id;
        // echo "<pre>";print_r($res['selectedCurrentData']);exit;
        return is_mobile($type, "lms/lmsDashboard", $res, "view");
    }
}
