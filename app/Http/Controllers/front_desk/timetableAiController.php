<?php

namespace App\Http\Controllers\front_desk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\user\tbluserModel;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\batchModel;
use App\Models\school_setup\periodModel;
use App\Models\school_setup\subjectModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setup\timetableModel;
use App\Models\front_desk\create_timetable;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use DB;
use File;

class timetableAiController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id'); 
        $syear = session()->get('syear');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
        // message 
        if (session()->has('data')) { 
            $res = session('data'); 
        }
        $res['type']=$type;
        return is_mobile($type, "front_desk/timetable/indexV1", $res, "view");       
    }

    public function create(Request $request){
        $type = $request->type;   
        $sub_institute_id = session()->get('sub_institute_id');     
        $syear = session()->get('syear');             
        $res['days'] = $getDay = $this->getDays();
        $getPeriod = $this->getPeriods();
        // requested values
        $res['grade_id'] = $grade_id = $request->grade;
        $res['standard_id'] = $standard_id = $request->standard;
        $res['division_id'] = $division_id = $request->division; 
        $general_data = DB::table('general_data')->where('fieldname','timetable_ai')->where('sub_institute_id',$sub_institute_id)->first();
        create_timetable::where('sub_institute_id',$sub_institute_id)->delete();
        $main_data = [
            "minimum_periods_per_day" => count($getPeriod),
            "subjects_per_period" => 1,
            "teacher_availability" => []
        ];
        $teacherData=[];
        
            if (isset($general_data->fieldvalue) && $general_data->fieldvalue == 1) {
            // for teacher wise
            $getMapData = DB::table('mapped_teachers')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->whereIn('standard_id', [implode(',',$standard_id)])
            ->get();

            foreach ($getMapData as $key => $mapData) {
                if (!isset($teacherData[$mapData->teacher_id])) {
                    $teacherData[$mapData->teacher_id] = [
                        "teacher_id" => $mapData->teacher_id,
                        "teacher_name" => DB::table('tbluser')
                            ->selectRaw('concat_ws(" ", COALESCE(first_name, "-"), COALESCE(last_name, "-")) as teacher_name')
                            ->where('id', $mapData->teacher_id)
                            ->value('teacher_name'),
                        "days" => array_keys($getDay), // Assuming 'M' as the day for now
                        "work_load" => "{$mapData->load}" ?? '',
                        "periods" => $getPeriod,
                        "subjects" => [],
                        // "standards" => [],
                        // "division" => [],
                    ];
                }
                // get subject
                $subject_ids = DB::table('sub_std_map')
                ->selectRaw('IFNULL(display_name, "-") as display_name, IFNULL(`load`, "0") as `load`')
                ->where('subject_id', $mapData->subject_id)
                ->where('standard_id', $mapData->standard_id)
                ->first();     

                if(isset($subject_ids->display_name)){
                    $teacherData[$mapData->teacher_id]["subjects"][] = [
                        'subject_id' => $mapData->subject_id,
                        'subject_name' => $subject_ids->display_name,
                        // 'subject_load'=> $subject_ids->load,
                    ];
                }

                // $teacherData[$mapData->teacher_id]["standards"][] = [
                //     "standard_id" => $mapData->standard_id,
                //     "standard_name" => DB::table('standard')->where('id', $mapData->standard_id)->value('name')
                // ];
                // foreach ($request->division as $key => $division) {
                //     $teacherData[$mapData->teacher_id]["division"][] = [
                //         "division_id" => $division,
                //         "division_name" => DB::table('division')->where('id', $division)->value('name')
                //     ];
                // }

            }
            // fill data for post.php 
            $main_data['teacher_availability'] = array_values($teacherData);

            } else {
                // standard wise
                $getallusers = DB::table('tbluser as u')
                ->join('tbluserprofilemaster as upm','upm.id','=','user_profile_id')
                ->selectRaw('u.id,concat_ws(" ", COALESCE(first_name, "-"), COALESCE(last_name, "-")) as teacher_name,upm.name as profile_name,subject_ids,IFNULL(`u`.`load`, "0") as `load`')->where('name','Teacher')->whereNotNull('subject_ids')->where('subject_ids','!=',0)->where('u.sub_institute_id',$sub_institute_id)->where('u.status',1)->get()->toArray();

                foreach ($getallusers as $key => $teacherDetail) {
                    $teacherData[$teacherDetail->id] = [
                        "teacher_id" => $teacherDetail->id,
                        "teacher_name" => $teacherDetail->teacher_name,
                        "days" => array_keys($getDay), // Assuming 'M' as the day for now
                        "work_load" => $teacherDetail->load,
                        "periods" => $getPeriod,
                        "subjects" => [],
                        // "standards" => [],
                        // "division" => [],
                    ];

                  $subject_ids = explode(',',$teacherDetail->subject_ids);
                    // get mapped subject in add user 
                    foreach($subject_ids as $subjectKey=>$subject_id){

                        $subjectData = DB::table('sub_std_map')
                        ->selectRaw('display_name, IFNULL(`load`, "0") as `load`')
                        ->where('subject_id', $subject_id)
                        ->groupBy('subject_id')
                        ->first();   

                        $teacherData[$teacherDetail->id]["subjects"][] = [
                            'subject_id'=>$subject_id,
                            'subject_name' => isset($subjectData->display_name) ? $subjectData->display_name : '-' ,
                            // 'subject_load'=> isset($subjectData->load) ? $subjectData->load : '0',
                        ];
                    }

                    // /foreach ($request->standard as $key => $standard) {
                    //     $teacherData[$teacherDetail->id]["standards"][] = [
                    //         "standard_id" => $standard,
                    //         "standard_name" => DB::table('standard')->where('id', $standard)->value('name')
                    //     ];
                    // }

                    // foreach ($request->division as $key => $division) {
                    //     $teacherData[$teacherDetail->id]["division"][] = [
                    //         "division_id" => $division,
                    //         "division_name" => DB::table('division')->select('name')->where('id', $division)->value('name')
                    //     ];
                    // }

                // echo "<pre>";print_r($standard);
                $main_data['teacher_availability'] = array_values($teacherData);
            }
        }
        // echo "<pre>";print_r($main_data);exit;

        $request_to_sent = json_encode($main_data,JSON_PRETTY_PRINT);
        // echo "<pre>";print_r($request_to_sent);exit; 
        $file_path = public_path('lms/ai/timetable/post.php');
        // $content =  file_get_contents($file_path);
       
        if (File::exists($file_path)) {
            File::delete($file_path);
        }
        $inputData = '$inputData';
        $file_data = "<?php 
        echo $inputData = '".$request_to_sent."'; 
        ?>";
        // Write PHP code to file
        file_put_contents($file_path, $file_data);
        $content =  file_get_contents($file_path);
        // dd($content);exit;
        $generated_timetable = shell_exec('python3 /home/timetable.py');
        $res['response'] = json_decode($generated_timetable, true); 
        // echo "<pre>";print_r($res['response']);exit;

        if ($res['response'] !== null) {
            foreach ($res['response']['timetable'] as $day) {
                foreach ($day['periods'] as $period) {
                    foreach ($grade_id as $grd => $gradeId) {   

                    foreach ($standard_id as $std => $stdId) {   
                     foreach ($division_id as $div => $divId) {
                            
                        # code...
                    $insert_data=[
                        "sub_institute_id"=>$sub_institute_id,
                        "syear"=>$syear,
                        "academic_section_id"=>$gradeId,
                        "standard_id"=>$stdId,
                        "division_id"=>$divId,
                        "period_id"=>$period['period_id'],
                        "subject_id"=>$period['subject_id'],
                        "teacher_id"=>$period['teacher_id teacher_name']['teacher_id'],
                        "week_day"=>$day['day'],
                    ];
                    $check_exists = create_timetable::where($insert_data)->first();
                    $insert_data["created_at"]=now();
                    // echo "<pre>";print_r($insert_data);
                    if(empty($check_exists)){
                        $insertCreateTimetable = create_timetable::insert($insert_data);
                    }
                }
            }
        }
        

                }
            }
        }else{
          $res['status_code'] = "0";
          $res['message']="Failed to get Data";
        }
        // exit;
        $res['timetableData'] = $this->timetableData($request);
        // echo "<pre>";print_r($res['timetableData']);exit;
        return is_mobile($type, "front_desk/timetable/indexV1", $res, "view");               
    }

    public function store(Request $request){
       
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
       
        $teacher_id = $request->teacher_id;
        $periods = $request->periods;        
        $exist_data = [];
        foreach ($periods as $period_id => $days) {
            foreach ($days as $day => $datas) {
                if($datas['subject'][0]!="-" && $datas['teachers'][0]!="-"){
                    $insert_data=[
                        "sub_institute_id"=>$sub_institute_id,
                        "syear"=>$syear,
                        "academic_section_id"=>$grade_id,
                        "standard_id"=>($datas['standards'][0] !='-') ? $datas['standards'] : $standard_id,
                        "division_id"=>($datas['divisions'][0] !='-') ? $datas['divisions'] : $division_id,
                        "period_id"=>$period_id,
                        "week_day"=>$day,
                    ];

                    $check_exists = timetableModel::where($insert_data)->first();
                    $insert_data["teacher_id"]=$datas['teachers'][0];
                    $insert_data["subject_id"]=$datas['subject'][0];
                    $insert_data["created_at"]=now();                        
                    if(empty($check_exists)){
                        $insertCreateTimetable = timetableModel::insert($insert_data);
                    }else{
                        $exist_data[]=$check_exists;
                    }
                    $delete_clone = create_timetable::where('sub_institute_id',$sub_institute_id)->delete();
                }
            }
        }
        $details_exist = [];
        if(!empty($exist_data)){
            foreach ($exist_data as $key => $value) {
                $details_exist[]=timetableModel::join('standard as std','std.id','=','timetable.standard_id')
                ->join('division as d','d.id','=','timetable.division_id')
                ->join('tbluser as u','u.id','=','timetable.teacher_id')
                ->join('sub_std_map as ssm','ssm.subject_id','=','timetable.subject_id')
                ->join('period as p','p.id','=','timetable.period_id')    
                ->selectRaw('std.name as standard,d.name as division,u.user_name as teacher,p.title as period,timetable.week_day,ssm.display_name as subject_name')
                ->where('timetable.id',$value->id)
                ->orderBy('p.sort_order')         
                ->groupBy('timetable.id')   
                ->first();
            }
        }
     
        $res['status_code']=1;
        $res['message']="Timetable Created !!";
        if(!empty($details_exist)){
            $res['existed_data']=$details_exist;
        }

        return is_mobile($type, "timetableAI.index", $res, "redirect");                                                    
    }


    public function timetableData(Request $request){
        $html = "";
        $sub_institute_id=session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $type = $request->type;
        
        $academic_section_id=$request->grade;
        $standard_id=$request->standard;
        $division_id = $request->division;
        $teacher_id = $request->select_teacher;            

        $timetable_data= create_timetable::where([
            'sub_institute_id'    => $sub_institute_id,
            'syear'               => $syear,
        ])->get()->toArray();
        foreach ($timetable_data as $k => $p) {
            $res['timetable_data'][$p['week_day']][$p['period_id']]['ID'][] = $p['id'];
            $res['timetable_data'][$p['week_day']][$p['period_id']]['SUBJECT_ID'][] = $p['subject_id'];
            $res['timetable_data'][$p['week_day']][$p['period_id']]['TEACHER_ID'][] = $p['teacher_id'];
            $res['timetable_data'][$p['week_day']][$p['period_id']]['standard_id'][] = $p['standard_id'];
            $res['timetable_data'][$p['week_day']][$p['period_id']]['division_id'][] = $p['division_id']; 
            if (isset($p['batch_id']) && $p['batch_id'] != "") {
                $res['timetable_data'][$p['week_day']][$p['period_id']]['BATCH_ID'][] = $p['batch_id'];
            }
        }

        $res['batch_data'] = batchModel::where([
            'sub_institute_id' => $sub_institute_id,
            'standard_id' => $standard_id,
            'division_id' => $division_id,
            'syear' => $syear,
        ])->get()->toArray();
        $res['total_batches'] = count($res['batch_data']);

        $res['period_data'] = periodModel::where(['sub_institute_id' => $sub_institute_id])
            ->orderby('sort_order')->get();

        $res['subject_data'] = sub_std_mapModel::where([
            'sub_institute_id' => $sub_institute_id, "standard_id" => $standard_id,
        ])->get(["subject_id", "display_name"])->toArray();

        $res['teacher_data'] = tbluserModel::select('tbluser.*',
            DB::raw('CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name) AS teacher_name,
                (CASE WHEN total_lecture IS NULL THEN "Unlimited" ELSE tbluser.total_lecture - count(t.id) END) AS remaining_lecture'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', '=', 'tbluser.user_profile_id')
            ->leftjoin("timetable AS t", function ($join) {
                $join->on("t.teacher_id", "=", "tbluser.id")
                    ->on("t.sub_institute_id", "=", "tbluser.sub_institute_id");
            })
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher'])
            ->where('tbluser.status', 1)
            // ->where('tbluser.id', $teacher_id)                
            ->groupby("tbluser.id")
            ->orderby("tbluser.first_name")
            ->get();

        $res['stdData'] = standardModel::where(['sub_institute_id' => $sub_institute_id, 'grade_id' => $academic_section_id])
            ->get();

        $res['divData'] = std_div_mappingModel::select('division.*')
            ->join('division', 'division.id', "=", 'std_div_map.division_id')
            ->where(['std_div_map.sub_institute_id' => $sub_institute_id, 'std_div_map.standard_id' => $standard_id])
            ->get();

        $res['subject_data'] = sub_std_mapModel::where([
            'sub_institute_id' => $sub_institute_id, "standard_id" => $standard_id,
        ])->get(["subject_id", "display_name"])->toArray();

        return $res;
    }

    public function getTeachersList(){
        $sub_institute_id = session()->get('sub_institute_id');

        return DB::table('tbluser as u')
        ->join('tbluserprofilemaster as up','up.id','=','u.user_profile_id')
        ->selectRaw('u.id,u.user_name,concat_ws(" ",COALESCE(u.first_name,"-"),COALESCE(u.last_name,"-")) as teacher_name,u.user_profile_id,up.name as profile_name')
        ->where(['u.sub_institute_id'=>$sub_institute_id,'up.name'=>'Teacher'])
        ->where('u.status',1)
        ->orderBy('u.user_name')
        ->get()->toArray();
    }
    
     //DELETE Timetable data -- Ajax Call
     public function deleteTimetable(Request $request)
     {
         $division_id = $request->input("division_id");
         $id = $request->input("id");
       
         $arr = explode("-", $id);
         $week_day = $arr[0];
         $period_id = $arr[1];
 
         $check_timetable_data = create_timetable::where('id',$id)->get()->toArray();
 
         if (count($check_timetable_data) > 0) {
             $deleted_record = create_timetable::where(
                 [
                     "sub_institute_id" => $sub_institute_id,
                     "standard_id"      => $standard_id,
                     "division_id"      => $division_id,
                     "syear"            => $syear,
                     "week_day"         => $week_day,
                     "period_id"        => $period_id,
                 ])->delete();
         }

         $res['redirect'] = '/front_desk/timetableAI';        
         $type =$request->input('type');
         return response()->json($res);
 
     }
    public function getDays(){
        return [
            'M'=>'Monday',
            'T'=>'Tuesday',
            'W'=>'Wednesday',
            'H'=>'Thursday',
            'F'=>'Friday',
            'S'=>'Saturday',            
        ];
    }

    public function getPeriods(){
        $sub_institute_id = session()->get('sub_institute_id');
        return DB::table('period')
        ->select('id as period_id','title as period_name')
        ->where(['sub_institute_id'=>$sub_institute_id,'status'=>1])
        ->orderBy('sort_order')
        ->get()->toArray();
    }
}
