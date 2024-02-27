<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class subjectElectiveController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        
        $res['subject_elective'] = DB::table('subject_elective as se')
        ->join('standard as s','s.id','=','se.standard_id')
        ->join('division as d','d.id','=','se.division_id')
        ->leftjoin('sub_std_map as ssm','ssm.subject_id','=','se.subject_id')
        ->selectRaw('se.*, s.name as std_name, d.name as div_name, (CASE WHEN se.subject_id = 0 THEN "N/A" ELSE ssm.display_name END) as subject_name')
        ->where(['se.sub_institute_id'=>$sub_institute_id,'se.syear'=>$syear])
        ->orderByRaw('s.sort_order,d.name')
        ->groupBy('se.id')
        ->get()->toArray();

        if (session()->has('data')) { 
            $data_arr = session('data'); 
            if (isset($data_arr['message'])) {
                $res['status_code'] = $data_arr['status_code'];                
                $res['message'] = $data_arr['message'];
            }
        }

        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, 'school_setup/subject_elective/show', $res, "view");
    }

    public function create(Request $request){
        $type = $request->type;
        $res = '';
        return is_mobile($type, 'school_setup/subject_elective/add', $res, "view");
    }
    
    public function store(Request $request){
        // echo "<pre>";print_r($request->all());exit;
        $type= $request->type;
        
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $standard_id=$request->standard;
        $division_id=$request->division;
        $subject_id=$request->opt_sub;

        $check_data=DB::table('subject_elective')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->where('standard_id',$standard_id)->where('division_id',$division_id)->get()->toArray();

        if(empty($check_data)){
            $status = DB::table('subject_elective')->insert([
                'syear'=>$syear,
                'sub_institute_id'=>$sub_institute_id,
                'elective_sub_no'=>' ELE1',
                'subject_id'=>$subject_id,
                'standard_id'=>$standard_id,
                'division_id'=>$division_id,
                'created_at'=>now(),
            ]);
            $res['status_code'] = 1;
            $res['message'] = "Added Successfully !!";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Already Exists";
        }

        return is_mobile($type, 'subject_elective.index', $res, "redirect");
    }

    public function edit($id,Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $standard_id = $request->standard_id;

        $res['subject_elective'] = DB::table('subject_elective as se')
        ->join('standard as s','s.id','=','se.standard_id')
        ->join('division as d','d.id','=','se.division_id')
        ->leftjoin('sub_std_map as ssm','ssm.subject_id','=','se.subject_id')
        ->selectRaw('se.*, s.name as std_name, d.name as div_name, (CASE WHEN se.subject_id = 0 THEN "N/A" ELSE ssm.display_name END) as subject_name')
        ->where('se.id',$id)
        ->orderByRaw('s.sort_order,d.name')
        ->first();

        $res['optional_subject'] = DB::table('sub_std_map')
        ->where('sub_institute_id',$sub_institute_id)
        ->where('standard_id',$standard_id)
        ->where('elective_subject','Yes')
        ->get()->toArray();

        return is_mobile($type, 'school_setup/subject_elective/edit', $res, "view");
        
    }

    public function update($id,Request $request){
        $type= $request->type;
        $subject_id = $request->opt_sub;
        $status = DB::table('subject_elective')->where('id',$id)->update([
            'subject_id'=>$subject_id,                
            'updated_at'=>now(),
        ]);
        $res['status_code'] = 1;
        $res['message'] = "Updated Successfully !!";
        return is_mobile($type, 'subject_elective.index', $res, "redirect");
        
    }
    public function getOptionalSubject(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $standard_id = $request->standard_id;

       return DB::table('sub_std_map')
       ->where('sub_institute_id',$sub_institute_id)
       ->where('standard_id',$standard_id)
       ->where('elective_subject','Yes')
       ->get()->toArray();
    }
}
