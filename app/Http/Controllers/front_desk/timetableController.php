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
use DB;

class timetableController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->type;
        if (session()->has('data')) { // check if it exists
            $res = session('data'); // to retrieve value
        }
        $res['teachers'] = $this->getTeachersList();
        $res['days'] = $this->getDays();
        $res['periods'] = $this->getPeriods();
        // echo "<pre>";print_r($res['periods']);exit;
        return is_mobile($type, "front_desk/timetable/index", $res, "view");       
    }

    public function create(Request $request){
        $type = $request->type;   
        $sub_institute_id = session()->get('sub_institute_id');     
        $syear = session()->get('syear');             
        $res['teachers'] = $this->getTeachersList();
        $res['days'] = $this->getDays();
        $res['periods'] = $this->getPeriods();
        // requested values
        $res['grade_id'] = $grade_id = $request->grade;
        $res['standard_id'] = $standard_id = $request->standard;
        $res['division_id'] = $division_id = $request->division;
        $res['number_period'] = $number_period = $request->number_period;
        $res['subject_per_period'] = $subject_per_period = $request->subject_per_period;
        $res['select_teacher'] = $teachers = $request->select_teacher;
        $res['week_load'] = $week_load = $request->week_load;
        $res['select_day'] = $days = $request->select_day;
        $res['select_period'] = $periods = $request->select_period;
        $res['select_subject'] = $subjects = $request->select_subject;     

        $main_data=[];
        foreach ($teachers as $key => $teacher_id) {
            # code...
        $teacher_details = tbluserModel::where(['id'=>$teacher_id])
        ->selectRaw('id as teacher_id,concat_ws(" ",COALESCE(first_name,"-"),COALESCE(last_name,"-")) as teacher_name')
        ->first();

        $period_details = periodModel::whereIn('id',$periods[$key])->where('sub_institute_id',$sub_institute_id)
        ->selectRaw('id as period_id,title as period_name')->get()->toArray();

        $subject_details = sub_std_mapModel::whereIn('subject_id',$subjects[$key])
        ->where(['sub_institute_id'=>$sub_institute_id,'standard_id'=>$standard_id])
        ->selectRaw('subject_id,display_name as subject_name')->get()->toArray();
     
        $data[] = array(
                    "teacher_id" => $teacher_details->teacher_id,
                    "teacher_name" => $teacher_details->teacher_name,
                    "days" => $days,
                    "work_load" => $week_load,
                    "periods" => $period_details,
                    "subjects" => $subject_details,
                );
        $main_data=array(
            "minimum_periods_per_day" => $number_period,
            "subjects_per_period" => $subject_per_period,
            "teacher_availability" => array($data)
        );
    }

        $request_to_sent = json_encode($main_data,JSON_PRETTY_PRINT);
// echo "<pre>";print_r($request_to_sent);exit;
        $file_path = public_path('lms/ai/timetable/post.php');
        // $file_path= 'http://dev.triz.co.in/lms/ai/timetable/post_old.php';
        if (file_exists($file_path)) {
            unlink($file_path);
        } 

        $file_data = "<?php 
        echo '".$request_to_sent."'; 
        ?>";
        // Write PHP code to file
        file_put_contents($file_path, $file_data);
        $content =  file_get_contents($file_path);
        // dd($content);exit;
        
        $generated_timetable = shell_exec('python3 /home/timetable.py');
        $res['response'] = json_decode($generated_timetable, true); 
        // python code not working in local sso to test use dummy data and comment $generated_timetable and $res['response']
        // $dummy_data = '{
        //     "timetable": [
        //         {
        //             "day": "M",
        //             "periods": [
        //                 {
        //                     "period_id": 16425,
        //                     "period_name": "Period-1",
        //                     "subject_id": 3976,
        //                     "subject_name": "Mathematics",
        //                     "teacher_id": 7011,
        //                     "teacher_name": "Haresh Rafaliya"
        //                 },{
        //                     "period_id": 16454,
        //                     "period_name": "Period-5",
        //                     "subject_id": 3978,
        //                     "subject_name": "English-1 ( Honeysuckle)",
        //                     "teacher_id": 7011,
        //                     "teacher_name": "Haresh Rafaliya"
        //                 }
        //             ]
        //         },
        //         {
        //             "day": "T",
        //             "periods": [
        //                 {
        //                     "period_id": 16454,
        //                     "period_name": "Period-5",
        //                     "subject_id": 3978,
        //                     "subject_name": "English-1 ( Honeysuckle)",
        //                     "teacher_id": 7011,
        //                     "teacher_name": "Haresh Rafaliya"
        //                 }
        //             ]
        //         },
        //         {
        //             "day": "W",
        //             "periods": [
        //                 {
        //                     "period_id": 16425,
        //                     "period_name": "Period-1",
        //                     "subject_id": 3980,
        //                     "subject_name": "Hindi Grammar",
        //                     "teacher_id": 7011,
        //                     "teacher_name": "Haresh Rafaliya"
        //                 }
        //             ]
        //         }
        //     ]
        // }';
        
        // $res['response'] = json_decode($dummy_data, true); // Decode JSON string into an associative array
        
        if ($res['response'] !== null) {
            foreach ($res['response']['timetable'] as $day) {
                foreach ($day['periods'] as $period) {
                    $insert_data=[
                        "sub_institute_id"=>$sub_institute_id,
                        "syear"=>$syear,
                        "academic_section_id"=>$grade_id,
                        "standard_id"=>$standard_id,
                        "division_id"=>$division_id,
                        "period_id"=>$period['period_id'],
                        "subject_id"=>$period['subject_id'],
                        "teacher_id"=>$period['teacher_id'],
                        "week_day"=>$day['day'],
                    ];
                    $check_exists = create_timetable::where($insert_data)->first();
                    $insert_data["created_at"]=now();
                    if(empty($check_exists)){
                        $insertCreateTimetable = create_timetable::insert($insert_data);
                    }
                }
            }
        }
        $res['timetableData'] = $this->timetableData($request);

        return is_mobile($type, "front_desk/timetable/index", $res, "view");               
    }

    public function store(Request $request){
       
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $grade_id = $request->grade_id;
        $standard_id = $request->standard_id;
        $division_id = $request->division_id;
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
            'academic_section_id' => $academic_section_id,
            'standard_id'         => $standard_id,
            'division_id'         => $division_id,
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
        ->where(['sub_institute_id'=>$sub_institute_id,'status'=>1])
        ->orderBy('sort_order')
        ->get()->toArray();
    }

     //DELETE Timetable data -- Ajax Call
     public function deleteTimetable(Request $request)
     {
         $division_id = $request->input("division_id");
         $id = $request->input("id");
         $standard_id = $request->input("standard_id");
         $grade_id = $request->input("grade_id");
         $sub_institute_id = session()->get('sub_institute_id');
         $syear = session()->get('syear');
         $marking_period_id=session()->get('term_id');
         $arr = explode("-", $id);
         $week_day = $arr[0];
         $period_id = $arr[1];
 
         $check_timetable_data = create_timetable::where([
             'sub_institute_id' => $sub_institute_id,
             'syear'            => $syear,
             'standard_id'      => $standard_id,
             'division_id'      => $division_id,
             'week_day'         => $week_day,
             'period_id'        => $period_id,
         ])->get()->toArray();
 
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

}
