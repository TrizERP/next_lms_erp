<?php

namespace App\Http\Controllers\result\result_activity_marks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use DB;

class resultActivityMarksV1Controller extends Controller
{
    //
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "result/result_activity_marks/show", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->sub_institute_id;
        }
        // get student Lists
        $studentsList = SearchStudent($request->grade,$request->standard,$request->division);
        // get activity Lists
        $activityData=$groupActivity=$subActivity=[];
        $activitySkillSet = DB::table('result_skillset')
            ->where('standard',$request->standard)
            ->where('sub_institute_id',$sub_institute_id)
            ->orderBy('sort_order')
            ->get()->toArray();
        // get grouped activity and other
        foreach ($activitySkillSet as $key => $value) {
           $activityData[$value->main_title][]=$value;
           // get grouped Data 
           $activityGroupData = DB::table('result_activity_master')
            ->where('standard',$request->standard)
            ->where('sub_institute_id',$sub_institute_id)
            ->where('skill_id',$value->id)
            ->orderBy('sort_order')
            ->get()->toArray();

            foreach ($activityGroupData as $key2 => $value2) {
                $groupActivity[$value2->skill_id][] = $value2;
                // get subactivity
                $subActivityData = DB::table('result_sub_activity')
                ->where('sub_institute_id',$sub_institute_id)
                ->where('skill_id',$value->id)
                ->where('sub_skill_id',$value2->id)
                ->orderBy('sort_order')
                ->get()->toArray();

                foreach ($subActivityData as $key3 => $value3) {
                    $subActivity[$value3->sub_skill_id][] = $value3;
                }
            }
        }

        // get students marks 
        $marksArr =[];
        foreach ($studentsList as $key => $value) {
            $getActivityMarks = DB::table('result_activity_marks')
            ->where('student_id',$value['id'])
            ->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])
            ->whereNull('sub_activity_id')
            ->get()->toArray();

            foreach($getActivityMarks as $k=>$v){
                $marksArr['activity'][$value['id']][$v->activity_id] = $v->group_id;
            }

            $getSubActivityMarks = DB::table('result_activity_marks')
            ->where('student_id',$value['id'])
            ->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])
            ->get()->toArray();

            foreach($getSubActivityMarks as $k=>$v){
                $marksArr['sub_activity_id'][$value['id']][$v->sub_activity_id] = $v->group_id;
            }
        }
        // echo "<pre>";print_r($marksArr);exit;

        // groups of activities
        $activityGroup = DB::table('result_activity_group')->where('sub_institute_id',$sub_institute_id)->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['grade_id'] = $request->grade;
        $res['standard_id'] = $request->standard;
        $res['division_id'] = $request->division;
        $res['studentsList'] = $studentsList;
        $res['skillData'] = $activityData;
        $res['activityGroup'] = $groupActivity;
        $res['subActivityGroup'] = $subActivity;
        $res['marksType'] = $activityGroup;
        $res['studentMarks'] = $marksArr;
        // echo "<pre>";print_r($res);exit;
      
        return is_mobile($type, "result/result_activity_marks/show", $res, "view");
    }

    public function store(Request $request){
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->sub_institute_id;
            $user_id = $request->user_id;
        }

        foreach ($request->marksArr as $student_id => $value) {
            // activity insert or update 
            if(isset($value['activity_id']) && !empty($value['activity_id'])){
                foreach($value['activity_id'] as $activityId => $groupId){
                    if($groupId!=''){
                    $finalArray = [
                        'student_id' => $student_id,
                        'activity_id' => $activityId,
                        'group_id' => $groupId,
                        'created_by' => $user_id,
                        'sub_activity_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $activity = DB::table('result_activity_marks')
                        ->updateOrInsert(
                            ['activity_id' => $activityId,'sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'student_id'=>$student_id],
                            $finalArray
                        );
                    }
                }
            }
            // sub activity insert or update 
            if(isset($value['sub_activity_id']) && !empty($value['sub_activity_id'])){
                foreach($value['sub_activity_id'] as $activityId => $subActivities){
                    foreach ($subActivities as $subActivityId => $groupId) {
                        if($groupId!=''){
                            $finalArray2 = [
                                'student_id' => $student_id,
                                'activity_id' => $activityId,
                                'group_id' => $groupId,
                                'created_by' => $user_id,
                                'sub_activity_id' => $subActivityId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        $subActivity  = DB::table('result_activity_marks')
                            ->updateOrInsert(
                                ['activity_id' => $activityId,'sub_activity_id'=>$subActivityId,'sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'student_id'=>$student_id],
                                $finalArray2
                            );
                        }
                    }
                }
            }
            // exit;
        }

        $res['status_code'] = "1";
        $res['message'] = "Result activity master deleted successfully";

        return is_mobile($type, "result_activity_marks_V1.index", $res, "redirect"); 
    }
}
