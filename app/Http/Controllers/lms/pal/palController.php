<?php
 
namespace App\Http\Controllers\lms\pal;
 
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
use App\Http\Controllers\AJAXController;
use App\Http\Controllers\lms\onlineExamController;
use App\Models\lms\lmsQuestionMasterModel;
// use App\Models\lms\lmsOnlineExamAnswerStudent;
// // use App\Models\lms\lmsOnlineExamStudent;
use App\Models\lms\lmsOnlineExamAnswerModel;
use App\Models\lms\lmsOnlineExamModel;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\questionpaperModel;
use App\Models\lms\lmsmappingtypeModel;
use DB;
use App\Models\lms\chapterModel;
use App\Models\lms\contentModel;
use App\Models\lms\topicModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Support\Facades\Log;
use function App\Helpers\neo4jCreateNode;
use function App\Helpers\neo4jCreateRelationship;

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
        // $res['attemptExams'] = questionpaperModel::join('lms_online_exam_student as loes','loes.question_paper_id','=','question_paper.id')
         $res['attemptExams'] = questionpaperModel::join('lms_online_exam as loes','loes.question_paper_id','=','question_paper.id')
        ->where('question_paper.created_by',$student_id)->where(['question_paper.sub_institute_id'=>$sub_institute_id])->where('question_paper.exam_type','PAL')->get()->toArray();
        // echo "<pre>";print_r($newData);exit;
        return is_mobile($type, 'lms/pal/show', $res, "view");        
    }
    
    /**
     * Get Student Result API - Returns student level based on their exam performance
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentResult(Request $request)
    {
        $type = $request->input('type');
        
        if ($type == 'API') {
            $student_id = $request->user_id;
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        } else {
            $student_id = session()->get('user_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        // Get the latest PAL exam result for the student
        $latestExam = DB::table('lms_online_exam_student as loes')
            ->join('question_paper as qp', 'qp.id', '=', 'loes.question_paper_id')
            ->where('loes.student_id', $student_id)
            ->where('qp.exam_type', 'PAL')
            ->where('qp.sub_institute_id', $sub_institute_id)
            ->orderBy('loes.created_at', 'DESC')
            ->first();

        $studentLevel = null;
        $studentLevelId = null;

        if ($latestExam) {
            // Calculate performance percentage
            $totalQuestions = $latestExam->total_right + $latestExam->total_wrong;
            $percentage = $totalQuestions > 0 ? ($latestExam->total_right / $totalQuestions) * 100 : 0;

            // Determine student level based on performance
            // Easy: < 40%, Medium: 40-70%, Hard: > 70%
            if ($percentage < 40) {
                $studentLevel = 'easy';
            } elseif ($percentage < 70) {
                $studentLevel = 'medium';
            } else {
                $studentLevel = 'hard';
            }

            // Get the level id from lms_mapping_type table
            $levelMapping = lmsmappingtypeModel::where('name', $studentLevel)
                // ->where('sub_institute_id', $sub_institute_id)
                ->first();

            if ($levelMapping && $levelMapping->id) {
                $studentLevelId = $levelMapping->id;
            } else {
                // If level not found, get default "easy" level id
                $easyLevel = lmsmappingtypeModel::where('name', 'easy')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->first();
                $studentLevelId = $easyLevel ? $easyLevel->id : null;
            }
        } else {
            // No previous exam, default to easy level
            $studentLevel = 'easy';
            $easyLevel = lmsmappingtypeModel::where('name', 'easy')
                // ->where('sub_institute_id', $sub_institute_id)
                ->first();
            $studentLevelId = $easyLevel ? $easyLevel->id : null;
        }

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'student_level' => $studentLevel,
            'student_level_id' => $studentLevelId,
            'latest_exam' => $latestExam
        ]);
    }

    /**
     * Get the next level based on current student level
     * 
     * @param string $currentLevel
     * @return string
     */
    private function getNextLevel($currentLevel)
    {
        $levelMap = [
            'easy' => 'medium',
            'medium' => 'hard',
            'hard' => 'hard'
        ];

        return $levelMap[$currentLevel] ?? 'medium';
    }

    /**
     * Get level ID from lms_mapping_type by level name
     * 
     * @param string $levelName
     * @param int $subInstituteId
     * @return int|null
     */
  private function getLevelId($levelName, $subInstituteId)
    {
        $level = lmsmappingtypeModel::where('name', $levelName)
            // ->where('sub_institute_id', $subInstituteId)
 ->where('parent_id', 9)
            ->first();

        return $level ? $level->id : null;
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
        // Get student level from Student Result API
        $studentLevelRequest = new Request();
        $studentLevelRequest->merge([
            'type' => $type,
            'user_id' => $student_id,
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear
        ]);
        $studentLevelResponse = $this->getStudentResult($studentLevelRequest);
        $studentLevelData = $studentLevelResponse->getData(true);
        
        // Determine the level to fetch questions for
        $currentLevel = $studentLevelData['student_level'] ?? 'easy';
        $nextLevel = $this->getNextLevel($currentLevel);
        $selectedLevelId = $this->getLevelId($nextLevel, $sub_institute_id);


        $command = "python3 /home/pal/pal.py $sub_institute_id $standard_id $subject_id $chapter_id $enrollment_no";
        $getLists = shell_exec($command);
        $questionList=json_decode($getLists,true);

        // $questionList = lmsQuestionMasterModel::where(['sub_institute_id'=>$sub_institute_id,'standard_id'=>$standard_id,'subject_id'=>$subject_id,'chapter_id'=>$chapter_id])->take(10)->orderBy('id','DESC')->get()->toArray();
        $answer=[];
        $existQusetion = [];
        if(empty($questionList)){
             // Use level-based question fetching with the provided query
            $randomQuestions = DB::table('lms_question_master as lqm')
                ->join('lms_question_mapping as lm', 'lqm.id', '=', 'lm.questionmaster_id')
                ->join('answer_master as am', 'lqm.id', '=', 'am.question_id')
                ->select('lqm.*')
                ->where('lqm.sub_institute_id', $sub_institute_id)
                ->where('lqm.standard_id', $request->standard_id)
                ->where('lqm.subject_id', $request->subject_id)
                ->where('lqm.chapter_id', $request->chapter_id)
                ->where('lqm.question_type_id', 1)
                ->when($selectedLevelId, function ($query) use ($selectedLevelId) {
                    $query->where('lm.mapping_value_id', $selectedLevelId);
                })
                ->inRandomOrder()
                ->take(10)
                ->groupBy('lqm.id')
               ->groupBy('lqm.question_title')
                ->get()
                ->toArray();

            // If no questions found with the selected level, fallback to any questions
            if (empty($randomQuestions)) {
                $randomQuestions = DB::table('lms_question_master as lqm')
                    ->join('answer_master as am', 'lqm.id', '=', 'am.question_id')
                    ->select('lqm.*')
                    ->where('lqm.sub_institute_id', $sub_institute_id)
                    ->where('lqm.standard_id', $request->standard_id)
                    ->where('lqm.subject_id', $request->subject_id)
                    ->where('lqm.chapter_id', $request->chapter_id)
                    ->where('lqm.question_type_id', 1)
                    ->inRandomOrder()
                    ->take(10)
                    ->groupBy('lqm.question_title')
                    ->get()
                    ->toArray();
            }
                
            foreach($randomQuestions as $k => $v){
                $questionList[$k]['question_id'] = $v->id;
                $questionList[$k]['question_text'] = $v->question_title;
            }
        }
        // echo "<pre>";print_r($questionList);exit;

        if(!empty($questionList)){
            $filteredQuestions = [];
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
                    $filteredQuestions[] = $val;

                }
                $existQusetion[]=$val['question_id'];
            }
        }
         $questionList = $filteredQuestions;
        
        // Check if we have any questions with answers after filtering
        if(empty($questionList)){
            $res['status_code'] = 0;
            $res['message'] = 'Questions Not Found';
            return is_mobile($type, 'pal.index', $res, "redirect");exit;
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
        $chapter_name = $request->chapter_name;
        $date = date('Y-m-d H:i:s');     
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

        lmsOnlineExamModel::insert($online_exam);
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
                lmsOnlineExamModel::insert($single);
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
                        lmsOnlineExamAnswerModel::insert($multiple);
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
                lmsOnlineExamModel::insert($narrative);
            }
        }

        // Neo4j Assessment Node
         neo4jCreateNode(
            'Assessment',
            [
                'assId' => (int)$questionPaperId,
                'sub_institute_id' => (int)$sub_institute_id
            ],
            [
                'displayLabel' => 'Assessment:' . $paper_name,
                'exam_type' => 'pal',
                'paper_name' => $paper_name,
                'total_marks' => (float)$total_marks,
                'standard_id' => (int)$standard_id,
                'subject_id' => (int)$subject_id,
                'grade_id' => (int)$grade_id,
                'question_ids' => $question_ids,
                'total_ques' => (int)$total_question
            ]
        );

    // ================= RESULT NODE =================
    // Result Node (using original online_exam_id from line 384)
    neo4jCreateNode(
        'Result',
        ['resultId' => (int)$online_exam_id, 'student_id' => (int)$user_id],
        [
            'question_paper_id' => (int)$questionPaperId,
            'total_right' => (int)$result['total_right_ans'],
            'total_wrong' => (int)$result['total_wrong_ans'],
            'obtain_marks' => (int)$result['obtain_marks'],
            'displayLabel' => 'Result:' . $result['obtain_marks']
        ]
    );

    // ================= RELATIONSHIPS =================
    neo4jCreateRelationship(
        'Result',
        ['resultId' => (int)$online_exam_id],
        'FOR_ASSESSMENT',
        'Assessment',
        ['assId' => (int)$questionPaperId]
    );

    neo4jCreateRelationship(
        'Student',
        ['student_id' => (int)$user_id],
        'HAS_RESULT',
        'Result',
        ['resultId' => (int)$online_exam_id]
    );

    neo4jCreateRelationship(
        'Assessment',
        ['assId' => (int)$questionPaperId],
        'ASSESSES_CHAPTER',
        'Chapter',
        ['chId' => (int)$request->chapter_id]
    );

    // ================= ✅ MASTERS RELATION =================
    try {

        $records = DB::select("
            SELECT 
                r.obtain_marks,
                a.total_marks,
                a.paper_desc as chapter_id
            FROM lms_online_exam r
            JOIN question_paper a ON a.id = r.question_paper_id
            WHERE r.student_id = ? AND a.total_marks > 0
        ", [$user_id]);

        $chapterWise = [];

        foreach ($records as $row) {

            $chId = $row->chapter_id;
            if (!$chId) continue;

            $ratio = $row->obtain_marks / $row->total_marks;

            if (!isset($chapterWise[$chId])) {
                $chapterWise[$chId] = ['total' => 0, 'count' => 0];
            }

            $chapterWise[$chId]['total'] += $ratio;
            $chapterWise[$chId]['count']++;
        }

        foreach ($chapterWise as $chId => $data) {

            $avg = $data['total'] / $data['count'];
            $score = round($avg * 100);

            neo4jCreateRelationship(
                'Student',
                ['student_id' => (int)$user_id],
                'MASTERS',
                'Chapter',
                ['chId' => (int)$chId],
                ['proficiency_score' => (int)$score]
            );
        }

        \Log::info('✅ MASTERS created', ['student_id' => $user_id]);

    } catch (\Exception $e) {
        \Log::error('❌ MASTERS ERROR', ['message' => $e->getMessage()]);
    }

        $res['message'] = "Exam submitted";
        return redirect()->route('pal.show',[$questionPaperId,"online_exam_id"=> $online_exam_id,"rightInterest"=>$rightInterest]);
    
    }

    public function suggestedContent(Request $request)
{
    $requestedLevel = $request->input('student_level');

     // ================= GET SESSION DATA =================
     $type = $request->input('type');
     $isAjax = $request->ajax() || $request->input('type') === 'AJAX';
 
     if ($type == 'API' || $isAjax) {
         $student_id = $request->input('user_id', session()->get('user_id'));
         $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));
         $syear = $request->input('syear', session()->get('syear'));
     } else {
         $student_id = session()->get('user_id');
         $sub_institute_id = session()->get('sub_institute_id');
         $syear = session()->get('syear');
     }
 
     // Fallback to session if values not found
     if(empty($sub_institute_id)) $sub_institute_id = session()->get('sub_institute_id');
     if(empty($student_id)) $student_id = session()->get('user_id');
     if(empty($syear)) $syear = session()->get('syear');
    if (!$requestedLevel) {

        $latestExam = DB::table('lms_online_exam as loes')
            ->join('question_paper as qp', 'qp.id', '=', 'loes.question_paper_id')
            ->where('loes.student_id', $student_id)
            ->where('qp.exam_type', 'PAL')
            ->where('qp.sub_institute_id', $sub_institute_id)
            ->orderBy('loes.created_at', 'DESC')
            ->first();

        if ($latestExam) {
            $totalQuestions = $latestExam->total_right + $latestExam->total_wrong;
            $percentage = $totalQuestions > 0 ? ($latestExam->total_right / $totalQuestions) * 100 : 0;

            if ($percentage < 40) {
                $studentLevel = 'easy';
            } elseif ($percentage < 70) {
                $studentLevel = 'medium';
            } else {
                $studentLevel = 'hard';
            }
        } else {
            $studentLevel = 'easy';
        }

    } else {
        $studentLevel = $requestedLevel;
    }

    // ================= SPECIAL RULE =================
    // easy → medium
    if ($studentLevel == 'easy') {
        $studentLevel = 'medium';
    }

    // ================= GET LEVEL ID =================
    $levelMapping = lmsmappingtypeModel::where('name', $studentLevel)->first();
    $studentLevelId = $levelMapping ? $levelMapping->id : null;

    // ================= MERGE REQUEST =================
    $request->merge([
        'student_level' => $studentLevel,
        'student_level_id' => $studentLevelId
    ]);

    // ================= GET DATA =================
    $data = $this->getData($request);

    $res['sub_institute_id'] = $sub_institute_id;

    // ================= MAPPING =================
    $lms_mapping_type = DB::table('lms_mapping_type')
        ->where('status', '=', 1)
        ->where('parent_id', '=', 0)
        ->where(function ($q) use ($request) {
            $q->where('globally', '=', 1)
              ->orWhere('chapter_id', $request->get('chapter_id'));
        })
        ->where(function ($q) use ($request) {
            $q->where('topic_id', '=', 0)
              ->orWhere('topic_id', $request->get('topic_id'));
        })
        ->where('element_id','content_library')
        ->get()->toArray();

    $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);

    $lms_mapping_Values = [];
    foreach ($lms_mapping_type as $key => $value) {
        $lms_mapping_Values[$value['name']] = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', $value['id'])
            ->get()->toArray();
    }

    // ================= FINAL RESPONSE =================
    $res['status_code'] = 1;
    $res['message'] = "SUCCESS";
    $res['data'] = $data['chapter_data'];
    $res['content_data'] = $data['content_data'];
    $res['grade'] = $data['basic_ids']['grade_id'];
    $res['standard'] = $data['basic_ids']['standard_id'];
    $res['subject'] = $data['basic_ids']['subject_id'];
    $res['subject_name'] = $data['basic_ids']['subject_name'];
    $res['show_content'] = $data['basic_ids']['add_content'];
    $res['lms_mapping_type'] = $lms_mapping_type;
    $res['lms_mapping_Values'] = $lms_mapping_Values;
    $res['mapped_type'] = $request->mapping_type;
    $res['mapped_value'] = $request->mapped_value;

    // ✅ IMPORTANT
    $res['student_level'] = $studentLevel;
    $res['student_level_id'] = $studentLevelId;

    if ($isAjax) {
        return response()->json($res);
    }

     return is_mobile($type, 'lms/pal/suggested_content', $res, "view");
 }    

     /**
      * Get suggested content from the suggested_content table for a chapter
      * 
      * @param Request $request
      * @return \Illuminate\Http\JsonResponse
      */
     public function getSuggestedContent(Request $request)
     {
         $type = $request->input('type');
         $isAjax = $request->ajax() || $request->input('type') === 'AJAX';

         if ($type == 'API' || $isAjax) {
             $student_id = $request->input('user_id', session()->get('user_id'));
             $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));
             $syear = $request->input('syear', session()->get('syear'));
         } else {
             $student_id = session()->get('user_id');
             $sub_institute_id = session()->get('sub_institute_id');
             $syear = session()->get('syear');
         }

         // Fallback to session if values not found
         if(empty($sub_institute_id)) $sub_institute_id = session()->get('sub_institute_id');
         if(empty($student_id)) $student_id = session()->get('user_id');
         if(empty($syear)) $syear = session()->get('syear');

         $standard_id = $request->input('standard_id');
         $subject_id = $request->input('subject_id');
         $chapter_id = $request->input('chapter_id');
         $grade_id = $request->input('grade_id');

         // Get suggested content from the suggested_content table
         $suggestedContent = DB::table('suggested_content as sc')
             ->join('content_master as cm', 'cm.id', '=', 'sc.type_id')
             ->where('sc.type', 'pal_content')
             ->where('sc.student_id', $student_id)
             ->where('sc.standard_id', $standard_id)
             ->where('sc.subject_id', $subject_id)
             ->where('sc.chapter_id', $chapter_id)
             ->where('sc.sub_institute_id', $sub_institute_id)
             ->where('sc.syear', $syear)
             ->select('cm.*')
             ->get()
             ->toArray();

         // Format the data similar to the suggestedContent method's content_data
         $content_data = [];
         if (!empty($suggestedContent)) {
             foreach ($suggestedContent as $content) {
                 $content_data[$content->chapter_id][$content->content_category][] = $content;
             }
         }

         // Get chapter data for the chapter (to get chapter name, etc.)
         $chapterData = chapterModel::where('id', $chapter_id)
             ->where('sub_institute_id', $sub_institute_id)
             ->first();

         $res['status_code'] = 1;
         $res['message'] = "SUCCESS";
         $res['content_data'] = $content_data;
         $res['chapter_data'] = $chapterData ? [$chapterData->toArray()] : [];
         $res['grade'] = $grade_id;
         $res['standard'] = $standard_id;
         $res['subject'] = $subject_id;
         $res['subject_name'] = DB::table('sub_std_map')
             ->where('standard_id', $standard_id)
             ->where('subject_id', $subject_id)
             ->where('sub_institute_id', $sub_institute_id)
             ->value('display_name');

         if ($isAjax) {
             return response()->json($res);
         }

         return is_mobile($type, 'lms/pal/suggested_content', $res, "view");
     }

     public function storeSuggestedContent(Request $request)
     {
         $student_id = session()->get('user_id');

         $contentData = $request->content_data;

         if(empty($contentData)){
             return response()->json(['status' => 0, 'message' => 'No content found']);
         }

         // ✅ Get all required values
         $studentLevel = $request->student_level ?? 'medium';
         $sub_institute_id = $request->sub_institute_id ?? session()->get('sub_institute_id');
         $standard_id = $request->standard_id;
         $subject_id = $request->subject_id;
         $chapter_id = $request->chapter_id;
         $syear = $request->syear;

         foreach($contentData as $chapterId => $categories){
             foreach($categories as $category => $contents){
                 foreach($contents as $content){

                     if(empty($content)) continue;

                     DB::table('suggested_content')->insert([
                         'type' => 'pal_content',
                         'type_id' => $content['id'] ?? null,
                         'student_id' => $student_id,
                         'student_level' => $studentLevel,
                         'standard_id' => $standard_id,
                         'subject_id' => $subject_id,
                         'chapter_id' => $chapter_id,
                         'sub_institute_id' => $sub_institute_id,
                         'syear' => $syear,
                         'created_by' => $student_id,
                         'created_at' => now()
                     ]);
                 }
             }
         }

         return response()->json([
             'status' => 1,
             'message' => 'Content Stored Successfully'
         ]);
     }
public function getData($request)
    {
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
            $year = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
            $syear =$year[0]->syear;
            $user_profile_name = 1;
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
            $user_profile_name = $request->session()->get('user_profile_name');
        }

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $extra_where = array();
        if ($user_profile_name == "Student") {
            $extra_where['chapter_master.show_hide'] = "1";
            $content_where['content_master.show_hide'] = '1';
        }

        $subject_id = $request->input('subject_id');
        $standard_id = $request->input('standard_id');
        $data['chapter_data'] = array();

        $data['chapter_data'] = chapterModel::select('chapter_master.*',
            DB::raw('COUNT(content_master.id) as total_content,sum(if(content_category = "Triz", 1, 0)) AS total_triz_content,
        sum(if(content_category = "OER", 1, 0)) AS total_OER_content'))
            ->leftjoin('content_master', 'content_master.chapter_id', '=', 'chapter_master.id')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('chapter_master.sub_institute_id', '1')
                        ->orWhere('chapter_master.sub_institute_id', $sub_institute_id);
                } else {
                    $query->Where('chapter_master.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('chapter_master.subject_id', $subject_id)
            ->where('chapter_master.standard_id', $standard_id)
            ->where($extra_where)
            ->groupBy('chapter_master.id')
            ->orderBy('chapter_master.sort_order')
            ->get();

        $data['basic_ids'] = sub_std_mapModel::select('standard.grade_id', 'sub_std_map.subject_id',
            'sub_std_map.standard_id',
            'sub_std_map.display_name as subject_name', 'sub_std_map.add_content')
            ->join('standard', 'standard.id', '=', 'sub_std_map.standard_id')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('sub_std_map.sub_institute_id', '1')
                        ->orWhere('sub_std_map.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('sub_std_map.subject_id', $subject_id)
            ->where('sub_std_map.standard_id', $standard_id)
            ->get()->toArray();

        $content_data = contentModel::select('content_master.*')
    ->where(function ($query) use ($getIsLms, $sub_institute_id) {
        if ($getIsLms == 'Y') {
            $query->where('content_master.sub_institute_id', '1')
                ->orWhere('content_master.sub_institute_id', $sub_institute_id);
        } else {
            $query->where('content_master.sub_institute_id', $sub_institute_id);
        }
    })
    ->where('content_master.subject_id', $subject_id)
    ->where('content_master.standard_id', $standard_id)
    ->where('content_master.chapter_id', $request->chapter_id) // ✅ ADD THIS
    ->where(function ($query) {
        $query->whereNull('content_master.topic_id')
            ->orWhere('content_master.topic_id', '0');
    })
     ->when($request->student_level_id, function ($query) use ($request) {
         $query->join('content_mapping_type as cmt', 'cmt.content_id', '=', 'content_master.id')
               ->where('cmt.mapping_value_id', $request->student_level_id);
     }) // ✅ ADD THIS (LEVEL FILTER)
     // Note: Using JOIN for level filter means only content mapped to this level is returned.
     // If no content is found, the level mapping in content_mapping_type table may need to be set up.
    ->get()
    ->toArray();

        $content_data_array =[];
        $mappedVals = explode(',',$request->mapped_value);

        if (!empty($content_data)) {
            foreach ($content_data as $content) {
               if(isset($mappedVals[0]) && $mappedVals[0] != ''){

    $exists = DB::table('content_mapping_type')
        ->where('content_id', $content['id'])
        ->whereIn('mapping_value_id', $mappedVals)
        ->exists();

    if($exists){
        $content_data_array[$content['chapter_id']][$content['content_category']][] = $content;
    }

} else {
    $content_data_array[$content['chapter_id']][$content['content_category']][] = $content;
}
            }
            foreach ($content_data_array as $chapter_id => &$chapter_content) {
                
                if (!isset($chapter_content['Flash Cards'])) {
                    $chapter_content['Flash Cards'] =$flash =DB::table('lms_flashcard')
                    ->where(['chapter_id' => $chapter_id, 'sub_institute_id' => $sub_institute_id, 'status' => 1])
                    ->get()
                    ->toArray();
                }
                if (!isset($chapter_content['Mindmap'])) {
                    $chapter_content['Mindmap'] = array();
                }
                if (!isset($chapter_content['Virtual Lab'])) {
                    $chapter_content['Virtual Lab'] = array();
                }
            }
        }
        $data['content_data'] = $content_data_array;

        $data['basic_ids'] = $data['basic_ids'][0];

        return $data;
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

        $onlineExamData = lmsOnlineExamModel::where([
            'id'=>$online_exam_id,'student_id'=>$user_id
        ])->get()->toArray();
        $data['online_exam_data'] = $onlineExamData[0] ?? $onlineExamData;
        
        // Calculate student level based on performance
        $totalQuestions = $data['online_exam_data']['total_right'] + $data['online_exam_data']['total_wrong'];
        $percentage = $totalQuestions > 0 ? ($data['online_exam_data']['total_right'] / $totalQuestions) * 100 : 0;
        if ($percentage < 40) {
            $data['online_exam_data']['student_level'] = 'easy';
        } elseif ($percentage < 70) {
            $data['online_exam_data']['student_level'] = 'medium';
        } else {
            $data['online_exam_data']['student_level'] = 'hard';
        }

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
                FROM lms_online_exam_answer
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
    public function palreport(Request $request){
        $type= $request->type;
        if($type == 'API'){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }else{
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

            $data = DB::table('question_paper as q')
            // ->join('lms_online_exam_student as l', 'l.question_paper_id', '=', 'q.id')
            ->join('lms_online_exam as l', 'l.question_paper_id', '=', 'q.id')
            ->join('tblstudent as t', 't.id', '=', 'l.student_id')
            ->join('tblstudent_enrollment as te', function ($join) {
                $join->on('te.student_id', '=', 't.id')
                     ->on('te.syear', '=', 'q.syear');
            })
            ->join('standard as s', 's.id', '=', 'te.standard_id')
            ->join('division as d', 'd.id', '=', 'te.section_id')
            ->where('q.sub_institute_id', $sub_institute_id)
            ->where('q.syear', $syear)
            ->where('q.exam_type', 'PAL')
            ->select(
                't.first_name',
                't.middle_name',
                't.last_name',
                's.name as standard',
                'd.name as division',
                DB::raw("DATE_FORMAT(l.start_time, '%d-%m-%Y %h:%i:%s') as start_time"),
                DB::raw("CONCAT(l.obtain_marks,'/',(l.total_right+l.total_wrong)) as grade")
            )
            ->orderBy('l.start_time', 'desc')
            ->get()->toArray();
            // echo "<pre>";print_r($res);exit;
        //}

        if (empty($data)) {
            $res['status'] = 0;
            $res['message'] = "No Data Found";
        } else {
            $res['data'] = $data;
            $res['status'] = 1;
            $res['message'] = "Success";
        }
        return is_mobile($type, "lms/pal/palreport", $res, "view");
    }
}

