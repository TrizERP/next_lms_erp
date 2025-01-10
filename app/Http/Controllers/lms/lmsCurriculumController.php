<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class lmsCurriculumController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }

        $getData = DB::table('lms_curriculum as lc')
        ->join('standard as s','s.id','=','lc.standard_id')
        ->join('sub_std_map as ssm','ssm.subject_id','=','lc.subject_id')
        ->selectRaw('lc.*,s.name as standard_name,ssm.display_name as subject_name,ssm.display_image')
        ->where('lc.sub_institute_id',$sub_institute_id)
        ->groupBy('lc.id')
        ->get()->toArray();
       
        $newData = [];
        foreach ($getData as $key => $value) {
            // $newData[$key] = $value;
            // $newData[$key]->subject_curricula_name = DB::table('sub_std_map')->where('standard_id',$value->standard_id)->whereRaw('subject_id IN ('.$value->subject_curricula.')')->select('display_name')->get()->toArray();
            $getTotalLessons = DB::table('lessonplan')->where(['standard_id'=>$value->standard_id,'subject_id'=>$value->subject_id,'sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->count();

            $getCompletedLessons = DB::table('lessonplan')->where(['standard_id'=>$value->standard_id,'subject_id'=>$value->subject_id,'sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'completion_status'=>'Yes'])->count();
            // $newData[$key][$value->standard_id][$value->subject_id]['lesson'] = $getTotalLessons;
            // $newData[$key][$value->standard_id][$value->subject_id]['complete'] = $getCompletedLessons;
            $value->total_lesson = $getTotalLessons;
            $value->completed_status = $getCompletedLessons;
            $newData[$key] = $value;
        }
        // echo "<pre>";print_r($newData);exit;
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['boards'] = ['CBSE', 'ICSE', 'IB', 'GSEB'];
        $res['model_integration'] = ['CBE (Competency-Based Education)','PBL (Project-Based Learning)','STEAM (Science, Technology, Engineering, Arts, and Mathematics)','SEL (Social-Emotional Learning)'];
        $res['allData'] = $getData;
        return is_mobile($type, 'lms/lms_curriculum/index', $res, "view");
    }

     /**
     * Get Resources to Display on Add Page.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');

        $res['boards'] = ['CBSE', 'ICSE', 'IB', 'GSEB'];
        $res['model_integration'] = ['CBE (Competency-Based Education)','PBL (Project-Based Learning)','STEAM (Science, Technology, Engineering, Arts, and Mathematics)','SEL (Social-Emotional Learning)'];
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'lms/lms_curriculum/add', $res, "view");
    }

    /**
     * Get Resources to Display on Add Page.
     *
     * @return Response
     */
    public function Store(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
        }
        // get array for inser or update from commonValue
        $insertArr = $this->commonValue($request,$sub_institute_id,'insert');
        $insert = DB::table('lms_curriculum')->insert($insertArr);
        
        if($insert==1){
            $res['status_code'] = 1;
            $res['message'] = "Data Stored Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Store Data";
        }
        
        return is_mobile($type, 'lms_curriculum.index', $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
        }

        $getData = DB::table('lms_curriculum as lc')
        ->selectRaw('lc.*')
        ->where('lc.sub_institute_id',$sub_institute_id)
        ->where('lc.id',$id)
        ->first();
      
        // echo "<pre>";print_r($newData);exit;
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['boards'] = ['CBSE', 'ICSE', 'IB', 'GSEB'];
        $res['model_integrations'] = ['CBE (Competency-Based Education)','PBL (Project-Based Learning)','STEAM (Science, Technology, Engineering, Arts, and Mathematics)','SEL (Social-Emotional Learning)'];
        $res['data'] = $getData;
        return is_mobile($type, 'lms/lms_curriculum/edit', $res, "view");
    }
    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
        }
        // get array for inser or update from commonValue
        $updateArr = $this->commonValue($request,$sub_institute_id,'update');
        $update = DB::table('lms_curriculum')->where('id',$id)->update($updateArr);
        
        if($update==1){
            $res['status_code'] = 1;
            $res['message'] = "Data Stored Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Store Data";
        }
        
        return is_mobile($type, 'lms_curriculum.index', $res);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('lms_curriculum')->where(["id" => $id])->delete();

        $res['status_code'] = 1;
        $res['message'] = "Data Deleted Successfully";

        return is_mobile($type, "lms_curriculum.index", $res, "redirect");
    }

    public function commonValue($request,$sub_institute_id,$types){
        $grade_id = $request->grade;
        $standard_id = $request->standard;
        $subject_id = $request->subject;
        $board_id = $request->board_id;
        $curriculum_name = $request->curriculum_name;
        $curriculum_alignment = $request->curriculum_alignment;
        $holistic_curriculum = $request->holistic_curriculum;
        // added on 18-10-2024
        $objective = $request->objective;
        $chapter = $request->chapter;
        $outcome = $request->outcome;
        $assessment_tool = $request->assessment_tool;
        // endded on 18-10-2024

        $model_integration = $subject_curricula = '';

        // foreach($request->subject_curricula as $key => $value){
        //     $subject_curricula .= $value.',';            
        // }

        foreach($request->model_integration as $key => $value){
            $model_integration .= $value.',';            
        }

        // if($subject_curricula!=''){
        //     $subject_curricula = rtrim($subject_curricula,',');            
        // }
        if($model_integration!=''){
            $model_integration = rtrim($model_integration,',');            
        }
        
        $commonArr = [
            'sub_institute_id'=>$sub_institute_id,
            'grade_id'=>$grade_id,
            'standard_id'=>$standard_id,
            'subject_id'=>$subject_id,
            'board_id'=>$board_id,
            'curriculum_name'=>$curriculum_name,
            'curriculum_alignment'=>$curriculum_alignment,
            'holistic_curriculum'=>$holistic_curriculum,
            // 'subject_curricula'=>$subject_curricula,
            'model_integration'=>$model_integration,
            'objective'=>$objective,
            'chapter'=>$chapter,
            'outcome'=>$outcome,
            'assessment_tool'=>$assessment_tool,
        ];

        if($types=='update'){
            $commonArr['updated_at'] = now();
        }else{
            $commonArr['created_at'] = now();
        }
        return $commonArr;
    }
}
