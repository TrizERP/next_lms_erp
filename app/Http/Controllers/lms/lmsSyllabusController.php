<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class lmsSyllabusController extends Controller
{
    //
     /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
        }
        $getData = DB::table('lms_syllabus as ls')
        ->join('lms_curriculum as lc','ls.curriculum_id','=','lc.id')
        ->join('standard as s','s.id','=','ls.standard_id')
        ->join('sub_std_map as ssm','ssm.subject_id','=','ls.subject_id')
        ->selectRaw('ls.*,s.name as standard_name,ssm.display_name as subject_name,lc.curriculum_name')
        ->where('ls.sub_institute_id',$sub_institute_id)
        ->groupBy('ls.id')
        ->get()->toArray();
       
        // $newData = [];
        // foreach ($getData as $key => $value) {
        //     $newData[$key] = $value;
        //     $newData[$key]->subject_curricula_name = DB::table('sub_std_map')->where('standard_id',$value->standard_id)->whereRaw('subject_id IN ('.$value->subject_id.')')->select('display_name')->get()->toArray();
        // }
        // echo "<pre>";print_r($getData);exit;
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['allData'] = $getData;
    
        return is_mobile($type, 'lms/lms_syllabus/index', $res, "view");
    }
    /**
     * Get Resources to Display on Add Page.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'lms/lms_syllabus/add', $res, "view");
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
        $insert = DB::table('lms_syllabus')->insert($insertArr);
        
        if($insert==1){
            $res['status_code'] = 1;
            $res['message'] = "Data Stored Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Store Data";
        }
        
        return is_mobile($type, 'lms_syllabus.index', $res);
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

        $getData = DB::table('lms_syllabus as ls')
        ->join('lms_curriculum as lc','ls.curriculum_id','=','lc.id')
        ->selectRaw('ls.*,lc.curriculum_name')
        ->where('ls.sub_institute_id',$sub_institute_id)
        ->where('ls.id',$id)
        ->first();
      
        // echo "<pre>";print_r($newData);exit;
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $getData;
        return is_mobile($type, 'lms/lms_syllabus/edit', $res, "view");
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
        $update = DB::table('lms_syllabus')->where('id',$id)->update($updateArr);
        
        if($update==1){
            $res['status_code'] = 1;
            $res['message'] = "Data Stored Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Store Data";
        }
        
        return is_mobile($type, 'lms_syllabus.index', $res);
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
        DB::table('lms_syllabus')->where(["id" => $id])->delete();

        $res['status_code'] = 1;
        $res['message'] = "Data Deleted Successfully";

        return is_mobile($type, "lms_syllabus.index", $res, "redirect");
    }
    public function commonValue($request,$sub_institute_id,$types){
        $grade_id = $request->grade;
        $standard_id = $request->standard;
        $subject_id = $request->subject;
        $curriculum_id = $request->curriculum_id;
        $syllabus_title = $request->syllabus_title;
        $syllabus_objectives = $request->syllabus_objectives;
        $learning_outcomes = $request->learning_outcomes;
        $suggested_materials = $request->suggested_materials;
        $assesment_plans = $request->assesment_plans;
        $progress_tracking = $request->progress_tracking;
        
        $commonArr = [
            'sub_institute_id'=>$sub_institute_id,
            'grade_id'=>$grade_id,
            'standard_id'=>$standard_id,
            'subject_id'=>$subject_id,
            'curriculum_id'=>$curriculum_id,
            'title'=>$syllabus_title,
            'objectives'=>$syllabus_objectives,
            'learning_outcomes'=>$learning_outcomes,
            'suggested_materials'=>$suggested_materials,
            'assessment_plan'=>$assesment_plans,
            'progress_tracking'=>$progress_tracking,
        ];

        if($types=='update'){
            $commonArr['updated_at'] = now();
        }else{
            $commonArr['created_at'] = now();
        }
        return $commonArr;
    }

    public function getCurriculums(Request $request){
        $standard = $request->standard;
        $subject = $request->subject;
        $sub_institute_id = session()->get('sub_institute_id');

        $data = DB::table('lms_curriculum')
                ->where(['standard_id'=>$standard,'subject_id'=>$subject,'sub_institute_id'=>$sub_institute_id])
                ->get()->toArray();
                
        return $data;
    }
}
