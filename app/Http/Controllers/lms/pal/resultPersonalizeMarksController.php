<?php

namespace App\Http\Controllers\lms\pal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\MappedStdDiv;
use function App\Helpers\SearchStudent;
use DB;

class resultPersonalizeMarksController extends Controller
{
    public function index(Request $request){
        $type= $request->type;
        if($type!='API' && $type!='noLog'){
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }else{
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }
        if($type=='noLog'){
            session()->put('sub_institute_id',$sub_institute_id);
            session()->put('syear',$syear);
        }
        
        $res['getStdDiv'] = MappedStdDiv($syear,$sub_institute_id); 
        $res['sub_institute_id'] =$sub_institute_id; 
        $res['syear'] = $syear; 
        
        // echo "<pre>";print_r($getstdDiv);exit; 
        return is_mobile($type, 'lms/pal/personalize_marks', $res, "view");                        
    }
    //
    public function create(Request $request){
        $grade_id = $request->grade;
        $standard_id = $request->standard;
        $division_id =$request->division;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;

        $getStudentLists = SearchStudent($grade_id,$standard_id,$division_id,$sub_institute_id,$syear);
        return $getStudentLists;
    }

    public function store(Request $request){
        // echo "<pre>";print_r($request->all());exit;
        $type= $request->type;
        $sub_institute_id = $request->get('sub_institute_id');
        $syear = $request->get('syear');
        $std_div = $request->std_div;
        $student_name = $request->student_name;
        $enrollment_no =$request->enrollment_no;
        $subject = $request->subject;
        $exam = $request->exam;
        $total= $request->total;
        $obtain = $request->obtain;
        $res['status_code'] = 0;
        $res['message'] = 'Failed to Add Data';
        $addedData =[];
        foreach ($std_div as $key => $standard) {
            # code...
            if($standard!='-- Select Standard Division --'){
               $insertData= [
                   'syear'=>$syear,
                   'sub_institute_id'=>$sub_institute_id,
                    'standard'=>$standard,
                    'student_name'=>$student_name[$key],
                    'enrollment_no'=>$enrollment_no[$key],
                    'subject'=>$subject[$key],
                    'exam'=>$exam[$key],
                    'total'=>$total[$key],
                    'obtain'=>$obtain[$key],                    
               ];

                $addedData[] = $insertData;
                $insert=DB::table('result_personalize_marks')->insert($insertData);   
            }
            $res['status_code'] = 1;
            $res['message'] = 'Data Added Successfully';
        }
        // echo "<pre>";print_r($addedData);exit;
        $res['StudentData']=$addedData;
        // $check_data = DB::table()->get()->toArray();
        return is_mobile($type, 'result_personalize_marks.index', $res, "redirect");                        
    }

    public function resultPersonalMarksApi(Request $request){
        $type= $request->type;
        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }else{
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }
        $enrollment_no = $request->enrollment_no;
        $standard = $request->standard;

        $res['student_data'] = DB::table('result_personalize_marks')->where(['sub_institute_id'=>$sub_institute_id,'enrollment_no'=>$enrollment_no])->get();

        $res['sub_institute_id'] = $sub_institute_id;
        return is_mobile($type, 'lms/pal/show', $res, "view");                
    }

    public function questionListsAPI(Request $request){
        $type= $request->type;
        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }else{
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $grade_id = $res['grade_id'] = $request->grade_id;        
        $standard_id = $res['standard_id'] = $request->standard_id;
        $subject_id = $res['subject_id']= $request->subject_id;
        $chapter_id = $res['chapter_id']= $request->chapter_id;
        
        $result = DB::table('lms_question_master AS lqm')
        ->select('lqm.sub_institute_id', 'ss.SchoolName AS institute_name', 'lqm.grade_id AS section_id', 'acs.title AS academic_section', 'lqm.standard_id', 's.name AS standard', 'lqm.subject_id', 'ssm.display_name AS subject', 'lqm.chapter_id', 'cm.chapter_name AS chapter', 'qtm.question_type', 'lqm.id AS id', 'lqm.question_title AS title', DB::raw('group_concat(Distinct lqmt.mapping_type_id) as type_id'), DB::raw('group_concat(Distinct lqmt.mapping_value_id) as value_id'), DB::raw('group_concat(Distinct lmt.name) as types_name'), DB::raw('group_concat(Distinct lmt1.name) as values_name'))
        ->join('question_type_master AS qtm', 'qtm.id', '=', 'lqm.question_type_id')
        ->join('school_setup AS ss', 'ss.Id', '=', 'lqm.sub_institute_id')
        ->join('academic_section AS acs', 'acs.id', '=', 'lqm.grade_id')
        ->join('standard AS s', 's.id', '=', 'lqm.standard_id')
        ->join('sub_std_map AS ssm', 'ssm.subject_id', '=', 'lqm.subject_id')
        ->join('chapter_master AS cm', function ($join) use ($syear) {
            $join->on('cm.id', '=', 'lqm.chapter_id')
                ->where('cm.syear', '=', $syear);
        })
        ->join('lms_question_mapping AS lqmt', 'lqmt.questionmaster_id', '=', 'lqm.id')
        ->join('lms_mapping_type AS lmt', 'lmt.id', '=', 'lqmt.mapping_type_id')
        ->join('lms_mapping_type AS lmt1',function($join){
            $join->on( 'lmt1.id', '=', 'lqmt.mapping_value_id')->on('lmt1.parent_id','=','lmt.id');
        })
        ->where('lqm.sub_institute_id', $sub_institute_id)
        ->where('lqm.standard_id', $standard_id)
        ->where('lqm.subject_id', $subject_id)
        ->where('lqm.chapter_id', $chapter_id)
        ->where('lqm.question_type_id','1')
        ->groupBy(['lqm.id'])
        ->get();

        $all_questions = [];
        foreach ($result as $key => $value) {
            $map_types = explode(',',$value->type_id);
            $map_valueId = explode(',',$value->value_id);
            $map_values = explode(',',$value->values_name);
            $bloom_val = $depth_val =$skill_val = null;
            if(in_array(9,$map_types)){
                $keys = array_keys($map_types, 9);
                $depth_val=$map_values[$keys[0]];
            }

            if(in_array(82,$map_types)){
                $keys = array_keys($map_types, 82);
                $bloom_val=$map_values[$keys[0]];
            }

            if(in_array(73548,$map_types)){
                $keys = array_keys($map_types, 73548);
                $skill_val=$map_values[$keys[0]];
            }
            $value->bloom_taxonomy = $bloom_val;
            $value->cognitive_difficulty = $depth_val;
            $value->competency_skill = $skill_val;

            $all_questions[] = $value;
        }
        // exit;
        $res['questionList'] =$all_questions;
        // echo "<pre>";print_r($res['questionList']);exit;
        return is_mobile($type, 'lms/pal/show', $res, "view");                        
    }
}
