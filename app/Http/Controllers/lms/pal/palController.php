<?php

namespace App\Http\Controllers\lms\pal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
use App\Http\Controllers\AJAXController;
use App\Http\Controllers\lms\onlineExamController;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\lmsOnlineExamAnswerStudent;
use App\Models\lms\lmsOnlineExamStudent;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\questionpaperModel;
use DB;

class palController extends Controller
{
    //
    public function index(Request $request){
        $type=$request->type;
        $res['message'] = "no data";
        if($type=='API'){
            $student_id =$request->user_id;
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
               
        }else{
            $student_id =session()->get('user_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $syear=session()->get('syear');        
        }
        
        $studentData = getStudents([$student_id],$sub_institute_id, $syear);
        $ajaxController = new AJAXController;
        $newData=$getSubjectList=$getchapterList=[];

        if(!empty($studentData)){
            $newData = $studentData[$student_id];
            $currentStandard = $studentData[$student_id]['standard_id'];
			$request->merge(['standard_id' => $currentStandard]);
            $getSubjectList=$ajaxController->getSubjectList($request)->original;
            // get chapters list 
            if(!empty($getSubjectList)){
                foreach ($getSubjectList as $subject_id => $subject_name) {
                    # code...
                    $request->merge(['standard_id' => $currentStandard,'subject_id'=>$subject_id]);
                    $getchapterList[$subject_id]=$ajaxController->getChapterList($request)->original;   
                }
            }
        }
        $res['studentDetails'] = $newData;
        $res['subjectList'] =$getSubjectList;
        $res['chapterList'] =$getchapterList;  
        $res['attemptExams'] = questionpaperModel::join('lms_online_exam_student as loes','loes.question_paper_id','=','question_paper.id')
        ->where('question_paper.created_by',$student_id)->where(['question_paper.sub_institute_id'=>$sub_institute_id,'question_paper.syear'=>$syear])->where('question_paper.exam_type','PAL')->get()->toArray();
        // echo "<pre>";print_r($newData);exit;
        return is_mobile($type, 'lms/pal/show', $res, "view");        
    }

    public function create(Request $request){
        $type=$request->type;
        $grade_id = $res['grade_id'] = $request->grade_id;        
        $standard_id = $res['standard_id'] = $request->standard_id;
        $subject_id = $res['subject_id']= $request->subject_id;
        $chapter_id = $res['chapter_id']= $request->chapter_id;
        $enrollment_no = $res['enrollment_no'] = $request->enrollment_no;
        $res['message'] = "no data";

        if($type=='API'){
            $student_id =$request->user_id;   
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;         
        }else{
            $student_id =session()->get('user_id');
            $sub_institute_id =session()->get('sub_institute_id');
            $syear = session()->get('syear');   
        }

        $command = "python3 /home/pal/pal.py $sub_institute_id $syear $standard_id $subject_id $chapter_id $enrollment_no";
        $getLists = shell_exec($command);
        $questionList=json_decode($getLists,true);
        // echo "<pre>";print_r($getLists);exit;

        // $questionList = lmsQuestionMasterModel::where(['sub_institute_id'=>$sub_institute_id,'standard_id'=>$standard_id,'subject_id'=>$subject_id,'chapter_id'=>$chapter_id])->take(10)->orderBy('id','DESC')->get()->toArray();
        $answer=[];
        $existQusetion = [];
        if(empty($questionList)){
            // $res['status_code'] = 0;
            // $res['message'] = 'Questions Not Found';
            // return is_mobile($type, 'pal.index', $res, "redirect");exit;       
            $randomQuestions = DB::table('lms_question_master')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('standard_id',$request->standard_id)
                ->where('subject_id',$request->subject_id)
                ->where('chapter_id',$request->chapter_id)
                ->inRandomOrder()
                ->take(10)
                ->get()->toArray();
                foreach($randomQuestions as $k => $v){
                    $questionList[$k]['question_id'] = $v->id;
                    $questionList[$k]['question_text'] = $v->question_title;
                }
        }
        // echo "<pre>";print_r($questionList);exit;

        if(!empty($questionList)){
        foreach ($questionList as $key => $val) {
            if(!in_array($val['question_id'],$existQusetion)){
                $answer_arr = answermasterModel::where([
                    "question_id"      => $val['question_id'],
                    "sub_institute_id" => $sub_institute_id,
                ])->get()->toArray();
                if (count($answer_arr) > 0) {
                    foreach ($answer_arr as $anskey => $ansval) {
                        $answer[$val['question_id']][] = $ansval;
                    }
                }
                $existQusetion[]=$val['question_id'];
            }
        }
        // echo "<pre>";print_r($answer);exit;
    }else{
        $res['status_code'] = 0;
        $res['message'] = 'Questions Not Found';
        return is_mobile($type, 'pal.index', $res, "redirect");exit;      
    }
    
        // echo "<pre>";print_r($questionList);exit;
        
        $res['question_arr'] = $questionList;
        $res['answer_arr'] = $answer;        
        $res['questionpaper_data']['total_marks'] = 10;
        $res['questionpaper_data']['time_allowed'] = 20;
        $res['questionpaper_data']['paper_name'] = "PAL Test";        
        // send request to python file 
        // echo "<pre>";print_r($res['question_arr']);exit;
        return is_mobile($type, 'lms/pal/exam', $res, "view");                
    }


    public function store(Request $request){
        $type = $request->type;
        if($type != 'API'){
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');            
            $user_id = $request->session()->get('user_id');
        }else{
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id = $request->user_id;
        }
        $grade_id = $request->grade_id;
        $standard_id = $request->standard_id;
        $subject_id= $request->subject_id;
        $paper_name= $request->paper_name;      
        $allowed_time = $request->questionpaper_time;  
        $total_marks = $request->total_marks;
        $question_ids = implode(',',$request->question_ids);        
        $total_question = $request->total_question;
        // echo "<pre>";print_r($request->all());exit;
        $res['message']='failed to submit';
        // first add question paper
        $getChaptername = DB::table('chapter_master')->where('id',$request->chapter_id)->where('sub_institute_id',$sub_institute_id)->first();
        
        $questionPaperDetails = [
            'grade_id'=>$grade_id,
            'standard_id'=>$standard_id,
            'subject_id'=>$subject_id,
            'paper_name'=>$paper_name,
            'paper_desc'=>$request->chapter_id,
            'timelimit_enable'=>1,
            'time_allowed' =>$allowed_time,
            'total_marks' =>$total_marks,
            'total_ques'=>$total_question,
            'question_ids' =>$question_ids,
            'shuffle_question' =>1,
            'attempt_allowed' =>0,
            'show_feedback'=>1,
            'show_hide' =>1,
            'result_show_ans' =>1,
            'created_by'=>$user_id,
            'sub_institute_id'=>$sub_institute_id,
            'syear'=>$syear,
            'exam_type'=>'PAL',
        ];
        // $check_exists = DB::table('question_paper')->where($questionPaperDetails)->first();
        // if(empty($check_exists)){
            $questionPaperDetails['open_date'] = now();
            $questionPaperDetails['created_on'] = now();
            $questionPaperDetails['close_date'] = now();
            $questionPaperId = DB::table('question_paper')->insertGetId($questionPaperDetails);
        // echo "<pre>";print_r($questionPaperId);exit;
        
        $controller = new onlineExamController;
        $result = $controller->get_calculate_marks($request);
        // echo "<pre>";print_r($result);exit;
        //START Insert into lms_online_exam table
        $online_exam = [
            'student_id'        => $user_id,
            'question_paper_id' => $questionPaperId,
            'total_right'       => $result['total_right_ans'],
            'total_wrong'       => $result['total_wrong_ans'],
            'obtain_marks'      => $result['obtain_marks'],
            'start_time'        => $request->get('hid_session_quiz') ?? now(),
            'created_at'        => now(),
        ];

        lmsOnlineExamStudent::insert($online_exam);
        $online_exam_id = DB::getPDO()->lastInsertId();
        //END Insert into lms_online_exam table

        //START Insert into lms_online_exam_answer table
        $answer_single = $request->get('answer_single');
        $answer_multiple = $request->get('answer_multiple');
        $answer_narrative = $request->get('answer_narrative');
        $rightInterest=[];
        // echo "<pre>";print_r($answer_single);exit;
        if (is_array($answer_single)) {
            foreach ($answer_single as $single_question_id => $single_answer_ids) {
                $ans_status = "wrong";
                $single_ans_arr = explode("##", $single_answer_ids);
                $interset = $request->interestValue[$single_question_id];
                if(!isset($rightInterest[$interset])){
                    $rightInterest[$interset]=0;
                }
                if ($single_ans_arr[1] == 1) {
                    $ans_status = "right";
                    // interset mapped type
                    $rightInterest[$interset] += 1;
                }
                $single = [
                    'question_paper_id' => $questionPaperId,
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $single_question_id,
                    'answer_id'         => $single_ans_arr[0],
                    'ans_status'        => $ans_status,
                    'created_at'        => now(),                    
                ];
                lmsOnlineExamAnswerStudent::insert($single);
            }
        }

        if (is_array($answer_multiple)) {
            foreach ($answer_multiple as $multiple_question_id => $multiple_answer_ids) {
                if (is_array($multiple_answer_ids))//Insert MCQ Answers
                {
                    foreach ($multiple_answer_ids as $key => $val) {
                        $ans_status = "wrong";
                        $multiple_ans_arr = explode("##", $val);
                        $interset = $request->interestValue[$multiple_question_id];
                     
                        if(!isset($rightInterest[$interset])){
                            $rightInterest[$interset]=0;
                        }
                        if ($multiple_ans_arr[1] == 1) {
                            $ans_status = "right";
                            // interset mapped type
                            $rightInterest[$interset] += 1;
                        }
                        $multiple = [
                            'question_paper_id' => $questionPaperId,
                            'online_exam_id'    => $online_exam_id,
                            'student_id'        => $user_id,
                            'question_id'       => $multiple_question_id,
                            'answer_id'         => $multiple_ans_arr[0],
                            'ans_status'        => $ans_status,
                            'created_at'        => now(),                                                
                        ];
                        lmsOnlineExamAnswerStudent::insert($multiple);
                    }
                }
            }
        }

        if (is_array($answer_narrative)) {
            foreach ($answer_narrative as $narrative_question_id => $narrative_answer_ids) {
                $ans_status = "right";
                if(!isset($rightInterest[$interset])){
                    $rightInterest[$interset]=0;
                }
                $rightInterest[$interset] += 1;
                $narrative = [
                    'question_paper_id' => $questionPaperId,
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $narrative_question_id,
                    'narrative_answer'  => $narrative_answer_ids,
                    'ans_status'        => $ans_status,
                    'created_at'        => now(),                                                                    
                ];
                lmsOnlineExamAnswerStudent::insert($narrative);
            }
        }
        $res['message'] = "Exam submitted";
    // }else{

    // }
    return redirect()->route('pal.show',[$questionPaperId,"online_exam_id"=> $online_exam_id,"rightInterest"=>$rightInterest]);
    
    }

    public function show(Request $request, $id)
    {
        $questionpaper_id = $id;

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $online_exam_id = $request->get('online_exam_id');
        $data['user_id'] = $online_exam_id;

        $data['questionpaper_data'] = questionpaperModel::find($questionpaper_id)->toArray();

        //Get all questions subject wise        
        $question_ids = explode(",", $data['questionpaper_data']['question_ids']);
        $data['question_arr'] = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();

        $lmsmapping = array();
        foreach ($data['question_arr'] as $key => $val) {
            $answer_arr = answermasterModel::where([
                "question_id"      => $val['id'],
                "sub_institute_id" => $sub_institute_id,
            ])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }

            $lmsquestionmapping_arr = lmsQuestionMappingModel::select('lms_question_mapping.questionmaster_id',
                't.name as type_name', 't.id as type_id'
                , 't1.name as value_name', 't1.id as value_id')
                ->join('lms_mapping_type as t', 't.id', 'lms_question_mapping.mapping_type_id')
                ->join('lms_mapping_type as t1', 't1.id', 'lms_question_mapping.mapping_value_id')
                ->where(["questionmaster_id" => $val['id']])
                ->get()->toArray();
            if (count($lmsquestionmapping_arr) > 0) {
                foreach ($lmsquestionmapping_arr as $lkey => $lval) {
                    $lmsmapping[$val['id']][$lval['type_name']] = $lval['value_name'];
                }
            }
        }

        $data['mapping_arr'] = $lmsmapping;
        $data['answer_arr'] = $answer;
        
        // $data['online_exam_data'] =DB::SELECT("SELECT * FROM lms_online_exam  where id ='$online_exam_id' and student_id=95634 AND question_paper_id = '$user_id'");

        $data['online_exam_data'] = lmsOnlineExamStudent::where([
            'id'=>$online_exam_id,'student_id'=>$user_id
        ])->get()->toArray();
        // print_r($data['online_exam_data']);exit;
        $data['online_exam_data'] = $data['online_exam_data'][0] ?? $data['online_exam_data'];

        // $online_answer_data = lmsOnlineExamAnswerModel::where(['online_exam_id'=>$online_exam_id,'student_id'=>$user_id])->get()->toArray();
        // foreach($online_answer_data as $key => $val)
        // {
        //     $data['online_answer_data'][$val['question_id']][] = $val; 
        // }

        $online_answer_data = DB::select("SELECT a.*, GROUP_CONCAT(am.id) AS actual_answer,q.question_type_id,q.multiple_answer,
                (
                CASE 
                WHEN question_type_id = 2 THEN IF(given_answer is null,'wrong','right') 
                WHEN question_type_id = 1 AND multiple_answer = 0 THEN IF(given_answer=GROUP_CONCAT(am.id),'right','wrong') 
                WHEN question_type_id = 1 AND multiple_answer = 1 THEN IF(given_answer=GROUP_CONCAT(am.id),'right','wrong') 
                END
                ) AS right_wrong 
                FROM (
                SELECT question_id,ans_status, IFNULL(narrative_answer, GROUP_CONCAT(answer_id)) AS given_answer
                FROM lms_online_exam_answer_student
                WHERE online_exam_id = '".$online_exam_id."' AND student_id = '".$user_id."'
                GROUP BY question_id) AS a
                INNER JOIN lms_question_master q ON q.id = a.question_id and q.status = 1
                LEFT JOIN answer_master am ON a.question_id = am.question_id AND correct_answer = 1
                GROUP BY am.question_id,a.question_id
            ");
        //dd($online_answer_data);
        foreach ($online_answer_data as $key => $val) {
            $data['online_answer_data'][$val->question_id]['RIGHT_WRONG'] = $val->right_wrong;
            $data['online_answer_data'][$val->question_id]['ACTUAL_ANSWER'] = $val->actual_answer;
            $data['online_answer_data'][$val->question_id]['GIVEN_ANSWER'] = $val->given_answer;
        }
        //dd($online_answer_data);
       
        $type = $request->input('type');
        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";
        $data['rightInterest'] = $request->rightInterest;
        $data['exam_type'] = 'PAL';
        // echo "<pre>";print_r($data);exit;
        return is_mobile($type, 'lms/online_exam_result', $data, "view");
    }

}
