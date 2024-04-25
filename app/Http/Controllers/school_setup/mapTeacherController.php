<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class mapTeacherController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id=session()->get('sub_institute_id');
        $syear=session()->get('syear');

        $res = session()->get('data');
        $res['details'] = DB::table('mapped_teachers as mt')
        ->join('tbluser as u',function($join){
            $join->on('u.id','=','mt.teacher_id')->where('u.status',1);  // 23-04-24 by uma
        })
        ->join('standard as s','s.id','=','mt.standard_id')
        ->join('sub_std_map as ssm','ssm.subject_id','=','mt.subject_id')
        ->selectRaw('mt.*,ssm.display_name as subject_name,s.name as standard_name,concat_ws(" ",COALESCE(u.first_name,"-"),COALESCE(u.last_name,"-")) as teacher_name')
        ->where('mt.sub_institute_id',$sub_institute_id)
        ->where('mt.syear',$syear)
        ->orderBy('mt.id','DESC')
        ->groupBy('mt.id')
        ->get()->toArray();

        return is_mobile($type,'school_setup/map_teacher/index',$res,'view');
    }

    public function create(Request $request){
        $type = $request->type;
      
        $res = $this->allData($request);
        return is_mobile($type,'school_setup/map_teacher/create',$res,'view');
    }

    public function store(Request $request){
        // echo "<pre>";print_r(session()->all());exit;
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear= session()->get('syear');
        $user_name = session()->get('user_profile_name');
        if($type=='API'){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_name = $request->user_profile_name;
        }
        $teachers = $request->teacher_id;
        $standards = $request->standard_id;
        $subjects = $request->subject_id;
        $loads = $request->load;
        $insert=[];
        foreach ($teachers as $key => $teacher_id) {
            $std = $standards[$key];
            $sub = $subjects[$key];
            $load = $loads[$key];
            $checkMap = DB::table('mapped_teachers')
            ->where('standard_id',$std)
            ->where('subject_id',$sub)
            ->where('sub_institute_id',$sub_institute_id)->where('syear',$syear)->first();
            if(empty($checkMap)){
                $insert[] = DB::table('mapped_teachers')->insert([
                    'teacher_id'=>$teacher_id,
                    'standard_id'=>$std,
                    'subject_id'=>$sub,
                    'load'=>$load,
                    'created_by'=>$user_name,
                    'sub_institute_id'=>$sub_institute_id,
                    'syear'=>$syear,
                    'created_at'=>now(),
                ]);
            }
        }

        $res['status_code']=0;
        $res['message']="Failed To Add Data";

        if(!empty($insert)){
            $res['status_code']=1;
            $res['message']="Data Added Successfully";
        }
        return is_mobile($type,'map_teacher.index',$res,'redirect');
    }

    public function edit(Request $request,$id){
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->type;

        $sub_institute_id = session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->get('sub_institute_id');
        }

        $res = $this->allData($request);
        $res['mapData'] = DB::table('mapped_teachers as mt')
        ->join('standard as s','s.id','=','mt.standard_id')
        ->join('sub_std_map as ssm','ssm.subject_id','=','mt.subject_id')
        ->selectRaw('mt.*,ssm.display_name as subject_name,s.name as standard_name')->where('mt.id',$id)->first();

        return is_mobile($type,'school_setup/map_teacher/edit',$res,'view');
    }

    public function update(Request $request,$id){
        // echo "<pre>";print_r($request->all());exit;
        $type=$request->type;
        $update = DB::table('mapped_teachers')->where('id',$id)->update([
            'teacher_id'=>$request->teacher_id,
            'load'=>$request->load,
            'updated_at'=>now(),
        ]);
        $res['status_code']=1;
        $res['message']="Data Updated Successfully";

        return is_mobile($type,'map_teacher.index',$res,'redirect');
    }

    public function destroy(Request $request,$id){
        $type=$request->type;

        $delete = DB::table('mapped_teachers')->where('id',$id)->delete();
        $res['status_code']=1;
        $res['message']="Data Deleted Successfully";

        return is_mobile($type,'map_teacher.index',$res,'redirect');
    }

    public function allData(Request $request){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->get('sub_institute_id');
        }

        $res['standard'] = DB::table('standard')->where('sub_institute_id',$sub_institute_id)->get()->toArray();

        $res['users'] = DB::table('tbluser as u')->join('tbluserprofilemaster as up','up.id','=','u.user_profile_id')
        ->selectRaw('u.id,concat_ws(" ",COALESCE(u.first_name,"-"),COALESCE(u.middle_name,"-"),COALESCE(u.last_name,"-")) as name,u.user_profile_id,up.name as profile_name')
        ->where('up.name','Teacher')
        ->where('u.sub_institute_id',$sub_institute_id)
        ->where('u.status',1)
        ->orderBy('u.first_name')
        ->get()->toArray();

        return $res;
    }
}
