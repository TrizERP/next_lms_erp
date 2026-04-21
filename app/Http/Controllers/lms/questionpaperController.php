<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questionpaperModel;
use App\Models\lms\questiontypeModel;
use App\Models\lms\chapterModel;
use App\Models\lms\topicModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\school_setup\subjectModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Validator;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\sendNotification;
use function App\Helpers\send_FCM_Notification;
use App\Models\school_setup\SchoolModel;

class questionpaperController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['questionpaper_data'];

        return is_mobile($type, 'lms/show_questionpaper', $res, "view");
    }

 public function getData($request)
{
    $sub_institute_id = $request->session()->get('sub_institute_id');
    $syear = $request->session()->get('syear');
    $data['questionpaper_data'] = array();
    $marking_period_id = session()->get('term_id');
    $teacher = session()->get('user_profile_name');
    $user_id = session()->get('user_id');

    // Add LMS conditional logic
    $getIsLms = DB::table('school_setup')
        ->where('Id', $sub_institute_id)
        ->value('is_Lms');

    $sub_institute_id_by_lms = ($getIsLms == 'Y') ? 
        "(question_paper.sub_institute_id = 1 or question_paper.sub_institute_id = $sub_institute_id)" : 
        "question_paper.sub_institute_id = $sub_institute_id";

    if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
        $student_id = session()->get('user_id');
        $stu_data = tblstudentEnrollmentModel::select('standard_id')->where([
            'student_id' => $student_id, 'syear' => $syear,
        ])->get()->toArray();

        if (count($stu_data) > 0) {
            $data['questionpaper_data'] = questionpaperModel::select(
                'question_paper.*',
                'standard.name as standard_name',
                'academic_section.title as grade_name',
                'ssm.display_name as subject_name',
                DB::raw('count(lms_online_exam.id) as total_attempt'),
                DB::raw('date_format(open_date, "%Y-%m-%d") as open_date'),
                DB::raw('date_format(close_date, "%Y-%m-%d") as close_date'),
                DB::raw('if(now() between open_date and close_date, "yes", "no") as active_exam')
            )
            ->join('standard', 'standard.id', '=', 'question_paper.standard_id')
            ->join('tblstudent_enrollment as se', function ($join) use ($student_id, $syear, $sub_institute_id) {
                $join->on('se.student_id', '=', DB::raw($student_id))
                    ->on('se.syear', '=', DB::raw($syear))
                    ->on('se.sub_institute_id', '=', DB::raw($sub_institute_id));
            })                
            ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->join('sub_std_map as ssm', function ($join) use ($sub_institute_id) {
                $join->on('ssm.subject_id', '=', 'question_paper.subject_id')
                    ->on('ssm.standard_id', '=', 'se.standard_id')
                    ->where('ssm.sub_institute_id', $sub_institute_id); // Specify table for sub_institute_id
            })
            ->leftJoin('lms_online_exam', function ($join) use ($student_id) {
                $join->on('lms_online_exam.question_paper_id', '=', 'question_paper.id')
                    ->on('lms_online_exam.student_id', '=', DB::raw($student_id));
            })
            ->whereRaw($sub_institute_id_by_lms)
            ->where('question_paper.syear', $syear)
            ->where('standard.id', $stu_data[0]['standard_id'])
            ->where('question_paper.exam_type', 'online')
            // Remove subject_id filter since it doesn't exist in the table
            ->where(function ($query) use ($sub_institute_id, $syear, $student_id) {
                $query->where('ssm.elective_subject', '!=', 'Yes')
                    ->orWhere(function ($subquery) use ($sub_institute_id, $syear, $student_id) {
                        $subquery->whereIn('ssm.subject_id', function ($inQuery) use ($sub_institute_id, $syear, $student_id) {
                            $inQuery->select('sos.subject_id')
                                ->from('student_optional_subject as sos')
                                ->where('sos.sub_institute_id', $sub_institute_id)
                                ->where('sos.syear', $syear)
                                ->where('sos.student_id', $student_id);
                        });
                    });
            })                
            ->groupBy('question_paper.id')
            ->get();
        }
    } 
    else if ($teacher == "Teacher") 
    {
        $data['questionpaper_data'] = questionpaperModel::select('question_paper.*',
            'standard.name as standard_name',
            'academic_section.title as grade_name', 
            'subject.subject_name', 
            DB::raw('date_format(open_date,"%Y-%m-%d") as open_date,
            date_format(close_date,"%Y-%m-%d") as close_date,
            if(now() between open_date and close_date,"yes","no") as active_exam'))
            ->join('standard', function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'question_paper.standard_id');
            })
            ->whereRaw($sub_institute_id_by_lms)
            ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->leftJoin('subject', 'subject.id', '=', 'question_paper.subject_id')
            ->where('question_paper.syear', $syear)
            ->where('question_paper.created_by', $user_id)
            ->orderBy('question_paper.id', 'desc')
            ->get();
    }
    else
    {
        $data['questionpaper_data'] = questionpaperModel::select('question_paper.*',
            'standard.name as standard_name',
            'academic_section.title as grade_name', 
            'subject.subject_name', 
            DB::raw('date_format(open_date,"%Y-%m-%d") as open_date,
            date_format(close_date,"%Y-%m-%d") as close_date,
            if(now() between open_date and close_date,"yes","no") as active_exam'))
            ->join('standard', function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'question_paper.standard_id');
            })
            ->whereRaw($sub_institute_id_by_lms)
            ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->leftJoin('subject', 'subject.id', '=', 'question_paper.subject_id')
            ->where('question_paper.syear', $syear)
            ->orderBy('question_paper.id', 'desc')
            ->get();
    }

    return $data;
}

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['questiontype_data'] = questiontypeModel::select('*')->get();

        // $data['question_level_data'] = questionlevelModel::select('*')->get();

        // $data['question_category_data'] = questioncategoryModel::select('*')->get();

        // $category_text = questioncategoryModel::select('*')->get()->toArray();
        // $question_category_text = "";
        // foreach($category_text as $key => $val)
        // {
        //     $question_category_text .= $val['question_category'].'  =>  '.$val['description'].'<br/><br/>';
        // }
        // $data['question_category_text'] = $question_category_text;

        $data['lms_mapping_type'] = lmsmappingtypeModel::select('*')
            ->where(['globally' => '1', 'parent_id' => '0'])
            ->get()->toArray();

        return is_mobile($type, 'lms/add_questionpaper', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store($request)
    {
        $open_date = $close_date = null;
        if ($request['open_date'] != "") {
            $open_date = date('Y-m-d H:i:s', strtotime($_REQUEST['open_date']));
        }
        if ($request['close_date'] != "") {
            $close_date = date('Y-m-d 23:59:59', strtotime($_REQUEST['close_date']));
        }

        $sub_institute_id = $request['sub_institute_id'];
        $syear = $request['syear'];
        $user_id = $request['created_by'];

        $show_hide = $request['show_hide'];
        $show_hide_val = isset($show_hide) ? $show_hide : '';

        $result_show_ans = $request['result_show_ans'];
        $result_show_ans_val = isset($result_show_ans) ? $result_show_ans : '';

        $shuffle_question = $request['shuffle_question'];
        $shuffle_question_val = isset($shuffle_question) ? $shuffle_question : '';

        $show_feedback = $request['show_feedback'];
        $show_feedback_val = $show_feedback ?? '';

        $timelimit_enable = $request['timelimit_enable'];
        $timelimit_enable_val = isset($timelimit_enable) ? $timelimit_enable : '';

        $question_ids = "";
        if ($request['question_ids']) {
            $question_ids = implode(",", $request['question_ids']);
        }

        $questionpaper = array(
            'grade_id'         => $request['grade'],
            'standard_id'      => $request['standard'],
            'subject_id'       => $request['subject'],
            'paper_name'       => $request['paper_name'],
            'paper_desc'       => $request['paper_desc'],
            'open_date'        => $open_date,
            'close_date'       => $close_date,
            'timelimit_enable' => $timelimit_enable_val,
            'time_allowed'     => $request['time_allowed'],
            'total_ques'       => $request['total_ques'],
            'total_marks'      => $request['total_marks'],
            'question_ids'     => $question_ids,
            'shuffle_question' => $shuffle_question_val,
            'attempt_allowed'  => $request['attempt_allowed'],
            'show_feedback'    => $show_feedback_val,
            'show_hide'        => $show_hide_val,
            'result_show_ans'  => $result_show_ans_val,
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
            'exam_type'        => $request['exam_type'],
        );
        // echo ('<pre>');print_r($questionpaper);die;
        $query = questionpaperModel::insertGetId($questionpaper);
        $questionpaper_id = DB::getPDO()->lastInsertId();
        // send notification
        if(isset($questionpaper_id) && $questionpaper_id!=0){
            $student_data = SearchStudent($request['grade'], $request['standard']);

            $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();

            $schoolName = $schoolData[0]['SchoolName'];
            $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

            foreach ($student_data as $id => $value) {
                $text = "Reminder: ".$request['paper_name']." exam added on ".$open_date." and closing date of exam is ".$close_date." )";
                $app_notification_content = [
                    'NOTIFICATION_TYPE'        => 'Notification',
                    'NOTIFICATION_DATE'        => now(),
                    'STUDENT_ID'               => $value['id'],
                    'NOTIFICATION_DESCRIPTION' => $text,
                    'STATUS'                   => 0,
                    'SUB_INSTITUTE_ID'         => $sub_institute_id,
                    'SYEAR'                    => $syear,
                    'SCREEN_NAME'              => 'general',
                    'CREATED_BY'               => $user_id,
                    'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                ];

                $gcm_data = DB::table('gcm_users')->where('mobile_no', $value['mobile'])
                        ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

                    $gcmRegIds = [];
                    if (count($gcm_data) > 0) {
                        foreach ($gcm_data as $key1 => $val1) {
                            $gcmRegIds[] = $val1->gcm_regid;
                        }
                    }

                    $pushMessage = $text;

                    $bunch_arr = array_chunk($gcmRegIds, 1000);
                    sendNotification($app_notification_content);
                    
                    if (! empty($bunch_arr)) {
                        foreach ($bunch_arr as $val) {
                            if (isset($val, $pushMessage)) {
                                $type1 = 'Notification';
                                $message = [
                                    'body'  => $pushMessage, 'TYPE' => $type1, 'USER_ID' => $value['id'],
                                    'title' => $schoolName, 'image' => $schoolLogo,
                                ];
                                $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                               
                            }
                        }
                      
                    }
            }
          
        }
        // notification ended 

        $res = array(
            "status_code" => 1,
            "message"     => "Question-Paper Added Successfully",
        );
        $type = $request['type'];
        $this->generatePDF($questionpaper, $questionpaper_id);

        return is_mobile($type, "question_paper.index", $res, "redirect");
    }

    public function generatePDF($request, $questionpaper_id)
    {
        $sub_institute_id = $request['sub_institute_id'];
        $syear = $request['syear'];

        $dom = '<!DOCTYPE html>
        <html>
            <head>
                <title></title>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <div>
                ##HTML_SEC##
            </div>
        </body>
        </html>';

        //Get Question Paper Data
        $data['questionpaper_data'] = questionpaperModel::find($questionpaper_id)->toArray();

        //Get all questions subject wise
        $question_ids = explode(",", $data['questionpaper_data']['question_ids']);
        $data['question_arr'] = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();

        $answer = array();
        foreach ($data['question_arr'] as $key => $val) {
            $answer_arr = answermasterModel::where("question_id", $val['id'])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }
        }
        $data['answer_arr'] = $answer;

        $html = view('lms/questionpaper_html', compact('data'))->render();

        $pdf_folder = $_SERVER['DOCUMENT_ROOT'].'/storage/QuestionPaper';

        $html_filename = $questionpaper_id.'_'.$sub_institute_id.'_'.$syear.".html";
        $pdf_filename = $questionpaper_id.'_'.$sub_institute_id.'_'.$syear.".pdf";

        //$path = "src=http://" . $_SERVER['HTTP_HOST'] . "/storage/QuestionPaper";
        //$html = str_replace('src="', $path, $html);
        //$html = str_replace('" alt=', ' alt=', $html);

        $html = str_replace('##HTML_SEC##', $html, $dom);

        $html_file_path = $pdf_folder.'/'.$html_filename;
        $pdf_file_path = $pdf_folder.'/'.$pdf_filename;
        if(file_exists($html_file_path)){
        file_put_contents($html_file_path, $html);
        $this->htmlToPDF($html_file_path, $pdf_file_path);
        unlink($html_file_path);
        }
    }

    public function htmlToPDF($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
public function edit(Request $request, $id)
{
    $type = $request->input('type');
    $sub_institute_id = $request->session()->get('sub_institute_id');

    // Add LMS conditional logic
    $getIsLms = DB::table('school_setup')
        ->where('Id', $sub_institute_id)
        ->value('is_Lms');

    $sub_institute_id_by_lms = ($getIsLms == 'Y') ? "(qm.sub_institute_id = 1 or qm.sub_institute_id = $sub_institute_id)" : "qm.sub_institute_id = $sub_institute_id";

    $sub_institute_id_alias = ($getIsLms == 'Y') ? "(qm.sub_institute_id = 1 or qm.sub_institute_id = $sub_institute_id)" : "qm.sub_institute_id = $sub_institute_id";

    $data['questionpaper_data'] = questionpaperModel::find($id)->toArray();

    if ($data['questionpaper_data']['open_date'] != "0000-00-00 00:00:00" && $data['questionpaper_data']['open_date'] != null) {
        $data['questionpaper_data']['open_date'] = date('m/d/Y h:i A',
            strtotime($data['questionpaper_data']['open_date']));

    } else {
        $data['questionpaper_data']['open_date'] = "";

    }

    if ($data['questionpaper_data']['close_date'] != "0000-00-00 00:00:00" && $data['questionpaper_data']['close_date'] != null) {
        $data['questionpaper_data']['close_date'] = date('m/d/Y h:i A',
            strtotime($data['questionpaper_data']['close_date']));
    } else {
        $data['questionpaper_data']['close_date'] = "";

    }

    $std_id = $data['questionpaper_data']['standard_id'];
    $grade_id = $data['questionpaper_data']['grade_id'];

    // Apply LMS condition to subjects query
    $stdData = sub_std_mapModel::from('sub_std_map as qm')
    ->where('qm.standard_id', $std_id)
    ->whereRaw($sub_institute_id_by_lms)
    ->orderBy('qm.display_name')
    ->get()
    ->toArray();

    $data['subjects'] = $stdData;

    $sub_id = $data['questionpaper_data']['subject_id'];

    $questionIds = explode(',', $data['questionpaper_data']['question_ids']);

    $chapters = DB::table('lms_question_master')
        ->whereIn('id', $questionIds)
        ->distinct()
        ->pluck('chapter_id')
        ->toArray();
        
    $chapterIds = DB::table('lms_question_master')
        ->whereIn('id', $questionIds)
        ->pluck('chapter_id', 'id');

    // Apply LMS condition to questions query
    $questionData = DB::table('lms_question_master as qm')
        ->select('qm.id', 'question_title', 'points', 'question_type_master.question_type',
            DB::raw('IFNULL(answer_master.answer, "-") as correct_answer'), 'chapter_master.chapter_name', 'chapter_master.sort_order',
            'qm.standard_id', 'qm.chapter_id')
        ->join('question_type_master', 'question_type_master.id', '=', 'qm.question_type_id')
        ->join('chapter_master', 'chapter_master.id', '=', 'qm.chapter_id')
        ->leftJoin('answer_master', function ($join) {
            $join->on('answer_master.question_id', '=', 'qm.id')->where('answer_master.correct_answer', '=', 1);
        })
        ->whereIn('qm.chapter_id', $chapters)
        ->where('qm.standard_id', $std_id)
        ->where('qm.subject_id', $sub_id)
        ->whereRaw($sub_institute_id_alias) // Apply LMS condition here for questions
        ->where('qm.status', 1)
        ->groupBy('qm.id')
        ->orderBy('chapter_master.sort_order')
        ->get();

    $questionData = json_decode(json_encode($questionData), true);
    
    foreach ($questionData as $key => $val) {
        $lmsquestionmapping_arr = lmsQuestionMappingModel::select('lms_question_mapping.questionmaster_id',
            't.name as type_name', 't.id as type_id'
            , 't1.name as value_name', 't1.id as value_id')
            ->join('lms_mapping_type as t', 't.id', 'lms_question_mapping.mapping_type_id')
            ->join('lms_mapping_type as t1', 't1.id', 'lms_question_mapping.mapping_value_id')
            ->where(["questionmaster_id" => $val['id']])
            ->get()->toArray();
        if (count($lmsquestionmapping_arr) > 0) {
            $mapping_html = "";
            $i = 1;
            foreach ($lmsquestionmapping_arr as $lkey => $lval) {
                $mapping_html .= $i++.") ".$lval['type_name']." - ".$lval['value_name']."<br><br>";
                $questionData[$key]['LMS_MAPPING_DATA'] = $mapping_html;
            }
        }
    }

    $data['questionData'] = $questionData;
    $data['grade_id'] = $grade_id;
    $data['standard_id'] = $std_id;
    $data['edit_id'] = $id;

    return is_mobile($type, "lms/add_questionpaper", $data, "view");
}
    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request)
    {


        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $question_ids = $request->hidden_question_ids;
        $id =  $request->edit_id;

        $show_hide = $request->get('show_hide');
        $show_hide_val = $show_hide ?? '';

        $result_show_ans = $request->get('result_show_ans');
        $result_show_ans_val = $result_show_ans ?? '';

        $shuffle_question = $request->get('shuffle_question');
        $shuffle_question_val = $shuffle_question ?? '';

        $show_feedback = $request->get('show_feedback');
        $show_feedback_val = $show_feedback ?? '';

        $timelimit_enable = $request->get('timelimit_enable');
        $timelimit_enable_val = $timelimit_enable ?? '';

        $question_ids = "";
        if ($request->has('questions')) {
            $question_ids = implode(",", $request->get('questions'));
        }

        $questionpaper = array(
            'grade_id'         => $request->get('grade'),
            'standard_id'      => $request->get('standard'),
            'subject_id'       => $request->get('subject'),
            'paper_name'       => $request->get('paper_name'),
            'paper_desc'       => $request->get('paper_desc'),
            'timelimit_enable' => $timelimit_enable_val,
            'time_allowed'     => $request->get('time_allowed'),
            'total_ques'       => $request->get('total_ques'),
            'total_marks'      => $request->get('total_marks'),
            'question_ids'     => $question_ids,
            'shuffle_question' => $shuffle_question_val,
            'attempt_allowed'  => $request->get('attempt_allowed'),
            'show_feedback'    => $show_feedback_val,
            'show_hide'        => $show_hide_val,
            'result_show_ans'  => $result_show_ans_val,
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
            'exam_type'        => $request->get('exam_type'),
        );
        $open_date = $close_date = "";
        if ($_REQUEST['open_date'] != "") {
            $open_date = date('Y-m-d H:i:s', strtotime($_REQUEST['open_date']));
            $questionpaper['open_date'] = $open_date;
        }
        if ($_REQUEST['close_date'] != "") {
            $close_date = date('Y-m-d 23:59:59', strtotime($_REQUEST['close_date']));
            $questionpaper['close_date'] = $close_date;
        }

        $query = questionpaperModel::where("id",$id)->update($questionpaper);
        // dd($query);

        if($query==false){
        $res = [
                "status_code" => 0,
                "message"     => "Question-Paper Update Cancel Or failed",
            ];
        }else{
        $res = [
            "status_code" => 1,
            "message"     => "Question-Paper Updated Successfully",
        ];
        }

        $type = $request->input('type');

        return is_mobile($type, "question_paper.index", $res, "redirect");
        // return back()->with($res);
    }

    public function show(Request $request, $id)
    {

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['questionpaper_data'] = questionpaperModel::find($id)->toArray();

        //Get all questions subject wise
        $question_ids = explode(",", $data['questionpaper_data']['question_ids']);
        $data['question_arr'] = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();
        $answer = [];
        foreach ($data['question_arr'] as $key => $val) {
            $answer_arr = answermasterModel::where("question_id", $val['id'])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }
        }
        $data['answer_arr'] = $answer;

        return is_mobile($type, "lms/view_questionpaper", $data, "view");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $query = questionpaperModel::where(["id" => $id])->delete();
            if($query == true){
                $res['status_code'] = "1";
                $res['message'] = "Question-Paper Deleted Successfully";
            }else{
                $res['status_code'] = "0";
                $res['message'] = "Question-Paper Failed Delete";
            }
        return is_mobile($type, "question_paper.index", $res);
    }

    public function ajax_SubjectwiseQuestion(Request $request)
    {
        $sub_id = $request->input("sub_id");
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $getIsLms = DB::table('school_setup')
        ->where('Id', $sub_institute_id)
        ->value('is_Lms');

    // Specify table name for sub_institute_id in the condition
        $sub_institute_id_by_lms = ($getIsLms == 'Y') ? "(qm.sub_institute_id = 1 or qm.sub_institute_id = $sub_institute_id)" : "qm.sub_institute_id = $sub_institute_id";
        $extra = "";
        $outer_extra = "WHERE 1 = 1";
        if ($request->has('search_chapter')) {
            $search_chapter = $request->input("search_chapter");
            $extra .= " AND qm.chapter_id IN (".$search_chapter.") ";
        }
        if ($request->has('search_topic')) {
            $search_topic = $request->input("search_topic");
            $extra .= " AND qm.topic_id IN (".$search_topic.") ";
        }
        if ($request->has('search_mapping_type')) {
            $search_mapping_type = $request->input("search_mapping_type");
            $mapping_types = explode(",", $search_mapping_type);
            $outer_extra_type = " AND (";
            foreach ($mapping_types as $key => $mapping_type_val) {
                $outer_extra_type .= " find_in_set('".$mapping_type_val."',a.mapping_type) OR";
            }
            $outer_extra_type .= ")";
            $outer_extra .= str_replace(') OR)', '))', $outer_extra_type);
        }
        if ($request->has('search_mapping_value')) {
            $search_mapping_value = $request->input("search_mapping_value");
            $mapping_values = explode(",", $search_mapping_value);
            $outer_extra_mapping = " AND (";
            foreach ($mapping_values as $key1 => $mapping_val) {
                $outer_extra_mapping .= " find_in_set('".$mapping_val."',a.mapping_value) OR";
            }
            $outer_extra_mapping .= ")";
            $outer_extra .= str_replace(') OR)', '))', $outer_extra_mapping);
        }

        /*$sql = "
            SELECT * FROM
            (SELECT qm.id,question_title,points,t.question_type,
            ifnull(GROUP_CONCAT(DISTINCT(am.answer)),'-') AS correct_answer,c.chapter_name,c.sort_order,
            tm.name as topic_name,GROUP_CONCAT(lqm.mapping_type_id) as mapping_type,GROUP_CONCAT(lqm.mapping_value_id) as mapping_value
            FROM lms_question_master qm
            INNER JOIN question_type_master t ON t.id = qm.question_type_id
            INNER JOIN chapter_master c ON c.id = qm.chapter_id
            LEFT JOIN topic_master tm ON tm.id = qm.topic_id
            LEFT JOIN lms_question_mapping lqm ON lqm.questionmaster_id = qm.id
            LEFT JOIN answer_master am ON am.question_id = qm.id AND correct_answer=1
            WHERE qm.standard_id =  '".$std_id."' AND qm.subject_id = '".$sub_id."' AND qm.status = 1
            AND qm.sub_institute_id = '".$sub_institute_id."'  ".$extra."
            GROUP BY qm.id
            ORDER BY chapter_name)
            AS a
            ".$outer_extra."
            ";
        $questionData = DB::select($sql);

        $questionData = json_decode(json_encode($questionData), true);*/

        $questionData = DB::table(DB::raw('
        (SELECT qm.id,question_title,points,t.question_type,
        ifnull(GROUP_CONCAT(DISTINCT(am.answer)),"-") AS correct_answer,c.chapter_name,c.sort_order,
        tm.name as topic_name,GROUP_CONCAT(lqm.mapping_type_id) as mapping_type,GROUP_CONCAT(lqm.mapping_value_id) as mapping_value
        FROM lms_question_master qm
        INNER JOIN question_type_master t ON t.id = qm.question_type_id
        INNER JOIN chapter_master c ON c.id = qm.chapter_id
        LEFT JOIN topic_master tm ON tm.id = qm.topic_id
        LEFT JOIN lms_question_mapping lqm ON lqm.questionmaster_id = qm.id
        LEFT JOIN answer_master am ON am.question_id = qm.id AND correct_answer=1
        WHERE qm.standard_id = ? AND qm.subject_id = ? AND qm.status = 1
        AND qm.sub_institute_id = ?  '.$extra.'
        GROUP BY qm.id
        ORDER BY chapter_name) AS a'.$outer_extra
        ))
            ->select('a.id', 'a.question_title', 'a.points', 'a.question_type', 'a.correct_answer', 'a.chapter_name', 'a.sort_order', 'a.topic_name', 'a.mapping_type', 'a.mapping_value')
            ->setBindings([$std_id, $sub_id, $sub_institute_id])
            ->get();

        $questionData = $questionData->toArray();


        foreach ($questionData as $key => $val) {
            $lmsquestionmapping_arr = lmsQuestionMappingModel::select('lms_question_mapping.questionmaster_id',
                't.name as type_name', 't.id as type_id'
                , 't1.name as value_name', 't1.id as value_id')
                ->join('lms_mapping_type as t', 't.id', 'lms_question_mapping.mapping_type_id')
                ->join('lms_mapping_type as t1', 't1.id', 'lms_question_mapping.mapping_value_id')
                ->where(["questionmaster_id" => $val['id']])
                ->get()->toArray();
            if (count($lmsquestionmapping_arr) > 0) {
                $mapping_html = "";
                $i = 1;
                foreach ($lmsquestionmapping_arr as $lkey => $lval) {
                    $mapping_html .= $i++.") ".$lval['type_name']." - ".$lval['value_name']."<br><br>";
                    $questionData[$key]['LMS_MAPPING_DATA'] = $mapping_html;
                }

            }
        }

        return $questionData;
    }

public function search(Request $request){

$validate = Validator::make($request->all(), [
            'paper_name' => 'required',
            'paper_desc' => 'required',
            'attempt_allowed' => 'required',
            'time_allowed' => 'required',
        ]);

    $sub_institute_id = $request->session()->get("sub_institute_id");
    $syear = $request->session()->get("syear");
    $user_id = $request->session()->get('user_id');

    $grade = $request->grade;
    $subject = $request->subject;
    $standard = $request->standard;
    $search_chapter = $request->search_chapter;

    // print_r($search_chapter);exit;
    $search_topic = $request->input('search_topic');
    $search_mapping_type = $request->search_mapping_type;
    $search_mapping_value = $request->search_mapping_value;

    $type = $request->input('type');

        $paper_name       = $request->get('paper_name');
        $paper_desc       = $request->get('paper_desc');
        $open_date        = $request->get('open_date');
        $close_date       = $request->get('close_date');
        $timelimit_enable = $request->get('timelimit_enable');
        $time_allowed     = $request->get('time_allowed');
        $total_ques       = $request->get('total_ques');
        $total_marks      = $request->get('total_marks');
        $question_ids     = $request->get('questions');
        $shuffle_question =$request->get('shuffle_question');
        $attempt_allowed  = $request->get('attempt_allowed');
        $show_feedback    = $request->get('show_feedback');
        $show_hide        = $request->get('show_hide');
        $result_show_ans  = $request->get('result_show_ans');
        $exam_type        = $request->get('exam_type');

if(!isset($request->paper_name) && !isset($request->attempt_allowed) && !isset($request->time_allowed) || $request->action=="Search" ){
    if(!empty($grade) && !empty($standard) && !empty($subject) && !empty($search_chapter)){
         $all_data = array(
                "grade"=>$grade,
                "subject"=>$subject,
                "standard"=>$standard,
                "search_chapter"=>$search_chapter,
                "search_topic"=>$search_topic,
                "search_mapping_type"=>$search_mapping_type,
                "search_mapping_value"=>$search_mapping_value,
                "sub_institute_id"=> $sub_institute_id,
            );

        return $this->search_question($all_data);

    }else{
        return back()->with("failed","Please Select Required Fileds !");
        }
}
if(isset($request->paper_name) && isset($request->attempt_allowed) && isset($request->time_allowed) || $request->action=="Save"){
            // return $request;exit;
        if($validate->fails()){
          return back()->with('failed','Please Fill Required Fileds Paper Name,Exam Descripton,Attempt Allowed or Allowed Time');
        }else{
        $array = array(
            'grade'            => $grade,
            'standard'         => $standard,
            'subject'          => $subject,
            'paper_name'       => $paper_name,
            'paper_desc'       => $paper_desc,
            'open_date'        => $open_date,
            'close_date'       => $close_date,
            'timelimit_enable' => $timelimit_enable,
            'time_allowed'     => $time_allowed,
            'total_ques'       => $total_ques,
            'total_marks'      => $total_marks,
            'question_ids'     => $question_ids,
            'shuffle_question' => $shuffle_question,
            'attempt_allowed'  => $attempt_allowed,
            'show_feedback'    => $show_feedback,
            'show_hide'        => $show_hide,
            'result_show_ans'  => $result_show_ans,
            'created_by'       => $user_id,
            'exam_type'        => $exam_type,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
            'type'             => $type,
    );
        // return $array;
        return $this->store($array);
    }

}

}
public function search_question($all_data){
    // Validate required parameters
      if (!isset($all_data['standard']) || empty($all_data['standard'])) {
        return response()->json([
            'status_code' => 0,
            'message' => 'Standard ID is required'
        ]);
    }

    $sub_id = $all_data['subject'];
    $std_id = $all_data['standard'];
    $sub_institute_id = $all_data["sub_institute_id"];
    $user_profile_id = session()->get('user_profile_id');
    $user_profile_name = session()->get('user_profile_name');
    $user_id = session()->get('user_id');
    
    // Debug: Log the received parameters
    \Log::info('Search Question Params:', [
        'subject_id' => $sub_id,
        'standard_id' => $std_id,
        'sub_institute_id' => $sub_institute_id
    ]);

    // Add LMS conditional logic
    $getIsLms = DB::table('school_setup')
        ->where('Id', $sub_institute_id)
        ->value('is_Lms');

    // Specify table name for sub_institute_id in the condition
    $sub_institute_id_by_lms = ($getIsLms == 'Y') ? 
        "(qm.sub_institute_id = 1 or qm.sub_institute_id = $sub_institute_id)" : 
        "qm.sub_institute_id = $sub_institute_id";

    $extra = "";
    $outer_extra = "1 = 1";
    
    // Build chapter filter
    if (isset($all_data["search_chapter"]) && !empty($all_data["search_chapter"])) {
        $search_chapter = $all_data["search_chapter"];
        // Ensure it's an array and filter out empty values
        $search_chapter = is_array($search_chapter) ? array_filter($search_chapter) : [$search_chapter];
        if (!empty($search_chapter)) {
            $extra .= "qm.chapter_id IN (" . implode(",", $search_chapter) . ")";
        }
    }
    
    // Build topic filter
    if (isset($all_data["search_topic"]) && $all_data["search_topic"] != [null] && !empty(array_filter($all_data["search_topic"]))) {
        $search_topic = $all_data["search_topic"];
        // Ensure it's an array and filter out empty values
        $search_topic = is_array($search_topic) ? array_filter($search_topic) : [$search_topic];
        if (!empty($search_topic)) {
            if (!empty($extra)) $extra .= " AND ";
            $extra .= "qm.topic_id IN (".implode(",",$search_topic).") ";
        }
    }

    // Build mapping type filter
    if (isset($all_data["search_mapping_type"]) && !empty($all_data["search_mapping_type"])) {
        $search_mapping_type = $all_data["search_mapping_type"];
        $mapping_types = is_array($search_mapping_type) ? array_filter($search_mapping_type) : [$search_mapping_type];
        if (!empty($mapping_types)) {
            $outer_extra_type = " AND (";
            foreach ($mapping_types as $key => $mapping_type_val) {
                $outer_extra_type .= " find_in_set('".$mapping_type_val."',a.mapping_type) OR";
            }
            $outer_extra_type = rtrim($outer_extra_type, "OR") . ")";
            $outer_extra .= $outer_extra_type;
        }
    }

    // Build mapping value filter
    if (isset($all_data["search_mapping_value"]) && !empty($all_data["search_mapping_value"])) {
        $search_mapping_value = $all_data["search_mapping_value"];
        $mapping_values = is_array($search_mapping_value) ? array_filter($search_mapping_value) : [$search_mapping_value];
        if (!empty($mapping_values)) {
            $outer_extra_mapping = " AND (";
            foreach ($mapping_values as $key1 => $mapping_val) {
                $outer_extra_mapping .= " find_in_set('".$mapping_val."',a.mapping_value) OR";
            }
            $outer_extra_mapping = rtrim($outer_extra_mapping, "OR") . ")";
            $outer_extra .= $outer_extra_mapping;
        }
    }

    try {
        // Main query with proper error handling
        $questionData = DB::table(function ($query) use ($std_id, $sub_id, $sub_institute_id, $extra, $sub_institute_id_by_lms) {
            $query->select(
                    'qm.id', 
                    'qm.question_title', 
                    'qm.points', 
                    't.question_type', 
                    DB::raw("ifnull(GROUP_CONCAT(DISTINCT(am.answer)),'-') AS correct_answer"), 
                    'c.chapter_name', 
                    'c.sort_order', 
                    'tm.name as topic_name', 
                    DB::raw("GROUP_CONCAT(lqm.mapping_type_id) as mapping_type"), 
                    DB::raw("GROUP_CONCAT(lqm.mapping_value_id) as mapping_value")
                )
                ->from('lms_question_master as qm')
                ->join('question_type_master as t', 't.id', '=', 'qm.question_type_id')
                ->join('chapter_master as c', 'c.id', '=', 'qm.chapter_id')
                ->leftJoin('topic_master as tm', 'tm.id', '=', 'qm.topic_id')
                ->leftJoin('lms_question_mapping as lqm', 'lqm.questionmaster_id', '=', 'qm.id')
                ->leftJoin('lms_mapping_type as lmt', 'lmt.id', '=', 'lqm.mapping_value_id')
                ->leftJoin('answer_master as am', function($join) {
                    $join->on('am.question_id', '=', 'qm.id')
                         ->where('am.correct_answer', '=', 1);
                })
                ->where('qm.standard_id', '=', $std_id)
                ->where('qm.subject_id', '=', $sub_id)
                ->where('qm.status', '=', 1)
                ->whereRaw($sub_institute_id_by_lms);
                
            // Add extra conditions only if they exist
            if (!empty($extra)) {
                $query->whereRaw($extra);
            }
                
            $query->groupBy('qm.id')
                ->orderBy('c.chapter_name');
        }, 'a')
        ->select('*')
        ->whereRaw($outer_extra)
        ->get();

        $questionData = json_decode(json_encode($questionData), true);

        // Process mapping data
        foreach ($questionData as $key => $val) {
            $lmsquestionmapping_arr = lmsQuestionMappingModel::select(
                    'lms_question_mapping.questionmaster_id',
                    't.name as type_name', 
                    't.id as type_id',
                    't1.name as value_name', 
                    't1.id as value_id'
                )
                ->join('lms_mapping_type as t', 't.id', 'lms_question_mapping.mapping_type_id')
                ->join('lms_mapping_type as t1', 't1.id', 'lms_question_mapping.mapping_value_id')
                ->where(["questionmaster_id" => $val['id']])
                ->get()->toArray();
                
            if (count($lmsquestionmapping_arr) > 0) {
                $mapping_html = "";
                $i = 1;
                foreach ($lmsquestionmapping_arr as $lkey => $lval) {
                    $mapping_html .= $i++.") ".$lval['type_name']." - ".$lval['value_name']."<br><br>";
                }
                $questionData[$key]['LMS_MAPPING_DATA'] = $mapping_html;
            } else {
                $questionData[$key]['LMS_MAPPING_DATA'] = "";
            }
        }

        // Get subject data based on user profile
        if ($user_profile_name == 'Teacher') {
            $wherecondition = [
                't.sub_institute_id' => $sub_institute_id, 
                't.teacher_id' => $user_id,
                't.subject_id' => $sub_id  // Add subject_id condition
            ];
            if ($std_id != "") {
                $wherecondition['t.standard_id'] = $std_id;
            }
            
            $stdData = subjectModel::from("timetable as t")
                ->select('sst.display_name', 'sst.subject_id')
                ->join('subject as s', 's.id', '=', 't.subject_id')
                ->join("sub_std_map as sst", function ($join) {
                    $join->on("sst.subject_id", "=", "s.id")
                        ->on("sst.standard_id", "=", "t.standard_id");
                })
                ->where($wherecondition)
                ->groupby('sst.id')
                ->orderBy('sst.display_name')
                ->get()->toArray();
        } else {
            $stdData = sub_std_mapModel::where('standard_id', $std_id)
                ->where('subject_id', $sub_id)  // Add subject_id condition
                ->where("sub_institute_id", $sub_institute_id)
                ->orderBy('display_name')
                ->get()->toArray();
        }
        
        // Get chapters
        if(isset($all_data['search_chapter'])){
            $chapters = chapterModel::where([
                'subject_id'       => $sub_id,
                'standard_id'      => $std_id,
            ])
            ->where("sub_institute_id", $sub_institute_id)
            ->get()->toArray();
        }
        
        $chapter_ids = isset($all_data['search_chapter']) ? $all_data['search_chapter'] : [];

        // Get topics
        if(isset($all_data['search_chapter']) && !empty($chapter_ids)){
            $topics = topicModel::whereIn("chapter_id", $chapter_ids)
                ->where(['sub_institute_id' => $sub_institute_id])
                ->get()->toArray();
            $res['topics'] = is_array($topics) ? $topics : [];
        } else {
            $res['topics'] = [];
        }

        // Get LMS mapping types
        $lms_mapping = lmsmappingtypeModel::select('*')
            ->where(['globally' => '1', 'parent_id' => '0'])
            ->get()->toArray();

        $mapping_types = isset($all_data['search_mapping_type']) ? $all_data['search_mapping_type'] : [];

        // Get mapping values
        if(isset($all_data['search_mapping_type']) && !empty($mapping_types)){
            $map_val = DB::table('lms_mapping_type')
                ->select(['id', 'name'])
                ->whereIn("parent_id", $mapping_types)
                ->where(['status' => '1'])
                ->get()->toArray();
            $res['mapping_value'] = $map_val;
        } else {
            $res['mapping_value'] = [];
        }

        $type = " ";
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['grade_id'] = $all_data['grade'] ?? null;
        $res['standard_id'] = $std_id;
        $res['subject_id'] = $sub_id;
        $res['chapter_id'] = isset($all_data['search_chapter']) ? $all_data['search_chapter'] : [];
        $res['topic_id'] = isset($all_data['search_topic']) ? $all_data['search_topic'] : [];
        $res['map_type'] = isset($all_data["search_mapping_type"]) ? $all_data["search_mapping_type"] : [];
        $res['map_value'] = isset($all_data["search_mapping_value"]) ? $all_data["search_mapping_value"] : [];
        $res['subjects'] = $stdData;
        $res['questionData'] = $questionData;
        $res['chapters'] = isset($chapters) ? $chapters : [];
        $res['lms_mapping_type'] = $lms_mapping;
        
        if(isset($all_data['question_ids'])){
            $res['questionpaper_data']['question_ids'] = $all_data['question_ids'];
        }

    } catch (\Exception $e) {
        \Log::error('Question Search Error: ' . $e->getMessage());
        
        $res['status_code'] = 0;
        $res['message'] = "Error searching questions: " . $e->getMessage();
        $res['questionData'] = [];
        $res['subjects'] = [];
        $res['chapters'] = [];
        $res['topics'] = [];
    }

    if (request()->ajax()) {
        return response()->json($res);
    }
    
    return is_mobile($type, "lms/add_questionpaper", $res, "view");
}
    public function ajax_LMS_StandardwiseSubject(Request $request)
    {
       $std_id = $request->input("std_id");
    $sub_institute_id = session()->get("sub_institute_id");
    $user_profile_id = session()->get('user_profile_id');
    $user_profile_name = session()->get('user_profile_name');
    $user_id = session()->get('user_id');

    // Add LMS conditional logic - same as used in other methods
    $getIsLms = DB::table('school_setup')
        ->where('Id', $sub_institute_id)
        ->value('is_Lms');

    $sub_institute_id_by_lms = ($getIsLms == 'Y') ? "(sub_institute_id = 1 or sub_institute_id = $sub_institute_id)" : "sub_institute_id = $sub_institute_id";

    if ($user_profile_name == 'Teacher') {
            $wherecondition = ['t.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $user_id];
            if ($std_id != "") {
                $wherecondition['t.standard_id'] = $std_id;
            }
            $stdData = subjectModel::from("timetable as t")
                ->select('sst.display_name', 'sst.subject_id')
                ->join('subject as s', 's.id', '=', 't.subject_id')
                ->join("sub_std_map as sst", function ($join) {
                    $join->on("sst.subject_id", "=", "s.id")
                        ->on("sst.standard_id", "=", "t.standard_id");
                })
                ->where($wherecondition)
                ->groupby('sst.id')
                ->orderBy('sst.display_name')
                ->get()->toArray();
        } else {
        $stdData = sub_std_mapModel::where('standard_id', $std_id)
            ->whereRaw($sub_institute_id_by_lms) // Apply LMS condition here
            ->orderBy('display_name')->get()->toArray();
    }

    return response()->json($stdData); // Return as JSON
    }

    public function ajax_questionpaperDependencies(Request $request)
    {
        $id = $request->input("id");
        $exam_type = $request->input("exam_type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        $data = DB::table("lms_".$exam_type."_exam")
            ->selectRaw('count(*) as total')
            ->where('question_paper_id', $id)->get()->toArray();
        $count = 0;
        if(isset($data[0]->total)){
            $count =$data[0]->total;
        }
        return $count;
    }

    /**
     * AI Question Paper Generator - Show UI
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generateAIPaper(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $user_profile_name = $request->session()->get('user_profile_name');
        
        // Get difficulty levels (parent_id = 1)
        $data['difficulty_levels'] = lmsmappingtypeModel::select('*')
            ->where(['parent_id' => '1', 'status' => '1'])
            ->orWhere(function($query) {
                $query->where('parent_id', '1')->where('globally', '1');
            })
            ->get()->toArray();
        
        // Get Bloom's Taxonomy (parent_id = 2)
        $data['bloom_taxonomy'] = lmsmappingtypeModel::select('*')
            ->where(['parent_id' => '2', 'status' => '1'])
            ->orWhere(function($query) {
                $query->where('parent_id', '2')->where('globally', '1');
            })
            ->get()->toArray();
        
        // Get question types
        $data['questiontype_data'] = questiontypeModel::select('*')->where('status', '1')->get()->toArray();
        
        // Get LMS mapping types for filtering
        $data['lms_mapping_type'] = lmsmappingtypeModel::select('*')
            ->where(['globally' => '1', 'parent_id' => '0'])
            ->get()->toArray();
        
        return is_mobile($type, 'lms/generate_ai_questionpaper', $data, "view");
    }
    
    /**
     * Get Mapping Types for AI Generator
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMappingTypes(Request $request)
    {
        $type = $request->input('type');
        $parent_id = $request->input('parent_id');
        
        $mapping_types = lmsmappingtypeModel::select('*')
            ->where(['parent_id' => $parent_id, 'status' => '1'])
            ->orWhere(function($query) use ($parent_id) {
                $query->where('parent_id', $parent_id)->where('globally', '1');
            })
            ->get()->toArray();
        
        return response()->json([
            'status_code' => 1,
            'data' => $mapping_types
        ]);
    }
    
    /**
     * Validate Question Availability
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateQuestionAvailability(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        
        $standard_id = $request->input('standard_id');
        $subject_id = $request->input('subject_id');
        $chapter_ids = $request->input('chapter_ids', []);
        $difficulty_distribution = $request->input('difficulty_distribution', []);
        $taxonomy_distribution = $request->input('taxonomy_distribution', []);
        $total_questions = $request->input('total_questions', 0);
        $question_types = $request->input('question_types', []);
        
        // Get IsLms condition
        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');
        $sub_institute_id_by_lms = ($getIsLms == 'Y') ? 
            "(qm.sub_institute_id = 1 or qm.sub_institute_id = $sub_institute_id)" : 
            "qm.sub_institute_id = $sub_institute_id";
        
        // Build base query
        $query = DB::table('lms_question_master as qm')
            ->select('qm.id', 'qm.points', 'qm.question_type_id')
            ->where('qm.standard_id', $standard_id)
            ->where('qm.subject_id', $subject_id)
            ->where('qm.status', 1)
            ->whereRaw($sub_institute_id_by_lms);
        
        // Filter by chapters
        if (!empty($chapter_ids)) {
            $query->whereIn('qm.chapter_id', $chapter_ids);
        }
        
        // Filter by question types
        if (!empty($question_types)) {
            $query->whereIn('qm.question_type_id', $question_types);
        }
        
        $questions = $query->get();
        
        // Get difficulty and taxonomy counts for each question
        $questionIds = $questions->pluck('id')->toArray();
        
        $difficultyCounts = [];
        $taxonomyCounts = [];
        $typeCounts = [];
        $totalMarks = 0;
        
        if (!empty($questionIds)) {
            // Get difficulty mappings
            $difficultyMappings = DB::table('lms_question_mapping as lqm')
                ->join('lms_mapping_type as lmt', 'lmt.id', '=', 'lqm.mapping_value_id')
                ->whereIn('lqm.questionmaster_id', $questionIds)
                ->where('lqm.mapping_type_id', 1) // Difficulty type
                ->groupBy('lqm.mapping_value_id')
                ->select('lqm.mapping_value_id', DB::raw('count(*) as count'), DB::raw('group_concat(lqm.questionmaster_id) as question_ids'))
                ->get();
            
            foreach ($difficultyMappings as $dm) {
                $difficultyCounts[$dm->mapping_value_id] = [
                    'count' => $dm->count,
                    'question_ids' => explode(',', $dm->question_ids)
                ];
            }
            
            // Get taxonomy mappings
            $taxonomyMappings = DB::table('lms_question_mapping as lqm')
                ->join('lms_mapping_type as lmt', 'lmt.id', '=', 'lqm.mapping_value_id')
                ->whereIn('lqm.questionmaster_id', $questionIds)
                ->where('lqm.mapping_type_id', 2) // Bloom's taxonomy type
                ->groupBy('lqm.mapping_value_id')
                ->select('lqm.mapping_value_id', DB::raw('count(*) as count'), DB::raw('group_concat(lqm.questionmaster_id) as question_ids'))
                ->get();
            
            foreach ($taxonomyMappings as $tm) {
                $taxonomyCounts[$tm->mapping_value_id] = [
                    'count' => $tm->count,
                    'question_ids' => explode(',', $tm->question_ids)
                ];
            }
            
            // Count by question type
            foreach ($questions as $q) {
                $typeCounts[$q->question_type_id] = ($typeCounts[$q->question_type_id] ?? 0) + 1;
                $totalMarks += $q->points;
            }
        }
        
        // Calculate required questions
        $requiredDifficulty = [];
        $requiredTaxonomy = [];
        $canGenerate = true;
        $warnings = [];
        
        foreach ($difficulty_distribution as $diff) {
            $required = round($total_questions * ($diff['percentage'] / 100));
            $requiredDifficulty[$diff['id']] = $required;
            
            $available = $difficultyCounts[$diff['id']]['count'] ?? 0;
            if ($required > $available) {
                $canGenerate = false;
                $warnings[] = "Need {$required} {$diff['name']} questions, but only {$available} available";
            }
        }
        
        foreach ($taxonomy_distribution as $tax) {
            $required = round($total_questions * ($tax['percentage'] / 100));
            $requiredTaxonomy[$tax['id']] = $required;
            
            $available = $taxonomyCounts[$tax['id']]['count'] ?? 0;
            if ($required > $available) {
                $canGenerate = false;
                $warnings[] = "Need {$required} {$tax['name']} questions, but only {$available} available";
            }
        }
        
        return response()->json([
            'status_code' => $canGenerate ? 1 : 0,
            'message' => $canGenerate ? 'Sufficient questions available' : 'Insufficient questions',
            'total_questions_available' => count($questions),
            'total_marks_available' => $totalMarks,
            'difficulty_counts' => $difficultyCounts,
            'taxonomy_counts' => $taxonomyCounts,
            'type_counts' => $typeCounts,
            'required_difficulty' => $requiredDifficulty,
            'required_taxonomy' => $requiredTaxonomy,
            'warnings' => $warnings
        ]);
    }
    
    /**
     * Generate AI Question Paper Preview
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function previewAIPaper(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        
        $standard_id = $request->input('standard_id');
        $subject_id = $request->input('subject_id');
        $chapter_ids = $request->input('chapter_ids', []);
        $difficulty_distribution = $request->input('difficulty_distribution', []);
        $taxonomy_distribution = $request->input('taxonomy_distribution', []);
        $total_questions = $request->input('total_questions', 0);
        $total_marks = $request->input('total_marks', 0);
        $marks_per_question = $request->input('marks_per_question', 1);
        $question_types = $request->input('question_types', []);
        $sections = $request->input('sections', []);
        $generate_sets = $request->input('generate_sets', 1);
        $exclude_question_ids = $request->input('exclude_question_ids', []);
        
        // Get IsLms condition
        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');
        $sub_institute_id_by_lms = ($getIsLms == 'Y') ? 
            "(qm.sub_institute_id = 1 or qm.sub_institute_id = $sub_institute_id)" : 
            "qm.sub_institute_id = $sub_institute_id";
        
        // Build base query
        $query = DB::table('lms_question_master as qm')
            ->select(
                'qm.id', 
                'qm.question_title', 
                'qm.points', 
                'qm.question_type_id',
                't.question_type',
                'c.chapter_name',
                'c.sort_order'
            )
            ->join('question_type_master as t', 't.id', '=', 'qm.question_type_id')
            ->join('chapter_master as c', 'c.id', '=', 'qm.chapter_id')
            ->where('qm.standard_id', $standard_id)
            ->where('qm.subject_id', $subject_id)
            ->where('qm.status', 1)
            ->whereRaw($sub_institute_id_by_lms);
        
        // Filter by chapters
        if (!empty($chapter_ids)) {
            $query->whereIn('qm.chapter_id', $chapter_ids);
        }
        
        // Filter by question types
        if (!empty($question_types)) {
            $query->whereIn('qm.question_type_id', $question_types);
        }
        
        // Exclude already used questions
        if (!empty($exclude_question_ids)) {
            $query->whereNotIn('qm.id', $exclude_question_ids);
        }
        
        $allQuestions = $query->get();
        $questionIds = $allQuestions->pluck('id')->toArray();
        
        if (count($questionIds) < $total_questions) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Not enough questions available. Required: ' . $total_questions . ', Available: ' . count($questionIds)
            ]);
        }
        
        // Get mappings for each question
        $questionMappings = DB::table('lms_question_mapping')
            ->whereIn('questionmaster_id', $questionIds)
            ->get()
            ->groupBy('questionmaster_id');
        
        // Group questions by difficulty and taxonomy
        $difficultyQuestions = [];
        $taxonomyQuestions = [];
        
        foreach ($allQuestions as $q) {
            $mappings = $questionMappings[$q->id] ?? collect();
            
            // Find difficulty
            $difficulty = null;
            foreach ($mappings as $m) {
                if ($m->mapping_type_id == 1) { // Difficulty type
                    $difficulty = $m->mapping_value_id;
                    break;
                }
            }
            
            // Find taxonomy
            $taxonomy = null;
            foreach ($mappings as $m) {
                if ($m->mapping_type_id == 2) { // Bloom's taxonomy
                    $taxonomy = $m->mapping_value_id;
                    break;
                }
            }
            
            $difficultyQuestions[$difficulty][] = $q;
            $taxonomyQuestions[$taxonomy][] = $q;
        }
        
        // Select questions based on distribution
        $selectedQuestions = [];
        $usedQuestionIds = [];
        
        // Calculate and select based on difficulty distribution
        $questionsPerDifficulty = [];
        foreach ($difficulty_distribution as $diff) {
            $count = round($total_questions * ($diff['percentage'] / 100));
            $questionsPerDifficulty[$diff['id']] = $count;
        }
        
        // Select questions for each difficulty level
        foreach ($questionsPerDifficulty as $diffId => $count) {
            $availableQuestions = $difficultyQuestions[$diffId] ?? [];
            
            // Shuffle available questions
            shuffle($availableQuestions);
            
            // Select required number
            $selected = array_slice($availableQuestions, 0, $count);
            foreach ($selected as $q) {
                if (!in_array($q->id, $usedQuestionIds)) {
                    $selectedQuestions[] = $q;
                    $usedQuestionIds[] = $q->id;
                }
            }
        }
        
        // If we don't have enough questions, fill from remaining
        $remainingNeeded = $total_questions - count($selectedQuestions);
        if ($remainingNeeded > 0) {
            foreach ($allQuestions as $q) {
                if (!in_array($q->id, $usedQuestionIds)) {
                    $selectedQuestions[] = $q;
                    $usedQuestionIds[] = $q->id;
                    $remainingNeeded--;
                    if ($remainingNeeded <= 0) break;
                }
            }
        }
        
        // Shuffle selected questions
        shuffle($selectedQuestions);
        
        // Organize into sections
        $sectionData = [];
        $sectionLetters = ['A', 'B', 'C', 'D', 'E'];
        
        if (empty($sections)) {
            // Single section default
            $sectionData[] = [
                'name' => 'A',
                'questions' => $selectedQuestions,
                'total_marks' => array_sum(array_column($selectedQuestions, 'points'))
            ];
        } else {
            $currentIndex = 0;
            foreach ($sections as $idx => $section) {
                $sectionQuestions = array_slice($selectedQuestions, $currentIndex, $section['questions']);
                $sectionMarks = array_sum(array_column($sectionQuestions, 'points'));
                
                $sectionData[] = [
                    'name' => $sectionLetters[$idx] ?? chr(65 + $idx),
                    'questions' => $sectionQuestions,
                    'total_marks' => $sectionMarks
                ];
                
                $currentIndex += $section['questions'];
            }
        }
        
        // Generate multiple sets if requested
        $allSets = [];
        for ($setNum = 0; $setNum < $generate_sets; $setNum++) {
            // Create a shuffled copy for each set
            $setQuestions = $selectedQuestions;
            shuffle($setQuestions);
            
            // Reorganize into sections
            $setSectionData = [];
            $currentIndex = 0;
            foreach ($sections as $idx => $section) {
                $sectionQuestions = array_slice($setQuestions, $currentIndex, $section['questions']);
                $sectionMarks = array_sum(array_column($sectionQuestions, 'points'));
                
                $setSectionData[] = [
                    'name' => $sectionLetters[$idx] ?? chr(65 + $idx),
                    'questions' => $sectionQuestions,
                    'total_marks' => $sectionMarks
                ];
                
                $currentIndex += $section['questions'];
            }
            
            $allSets[] = [
                'set_name' => 'Set ' . chr(65 + $setNum),
                'sections' => $setSectionData,
                'total_questions' => count($setQuestions),
                'total_marks' => array_sum(array_column($setQuestions, 'points')),
                'question_ids' => array_column($setQuestions, 'id')
            ];
        }
        
        return response()->json([
            'status_code' => 1,
            'message' => 'Question paper generated successfully',
            'sets' => $allSets,
            'total_questions' => count($selectedQuestions),
            'total_marks' => array_sum(array_column($selectedQuestions, 'points'))
        ]);
    }
    
    /**
     * Save AI Generated Question Paper
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function saveAIPaper(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $paper_name = $request->input('paper_name');
        $paper_desc = $request->input('paper_desc');
        $grade_id = $request->input('grade');
        $standard_id = $request->input('standard');
        $subject_id = $request->input('subject');
        $question_ids = $request->input('question_ids');
        $total_questions = $request->input('total_questions', 0);
        $total_marks = $request->input('total_marks', 0);
        $difficulty_distribution = $request->input('difficulty_distribution', []);
        $taxonomy_distribution = $request->input('taxonomy_distribution', []);
        $sections_config = $request->input('sections_config', []);
        $exam_type = $request->input('exam_type', 'online');
        $time_allowed = $request->input('time_allowed', 60);
        $attempt_allowed = $request->input('attempt_allowed', 1);
        $open_date = $request->input('open_date');
        $close_date = $request->input('close_date');
        $type = $request->input('type');
        
        // Validate
        $message = '';

        if (empty($paper_name)) {
            $message = 'Please provide paper name';
        } elseif (empty($question_ids)) {
            $message = 'Please select questions';
        }

        if ($message != '') {
            return is_mobile($type, 'question_paper.index', [
                'status_code' => 0,
                'message' => $message
            ], 'redirect');
        }
        
        // Prepare dates
        $openDate = !empty($open_date) ? date('Y-m-d H:i:s', strtotime($open_date)) : null;
        $closeDate = !empty($close_date) ? date('Y-m-d 23:59:59', strtotime($close_date)) : null;
        
        // Create question paper
        $questionpaper = questionpaperModel::create([
            'grade_id' => $grade_id,
            'standard_id' => $standard_id,
            'subject_id' => $subject_id,
            'paper_name' => $paper_name,
            'paper_desc' => $paper_desc,
            'open_date' => $openDate,
            'close_date' => $closeDate,
            'timelimit_enable' => 1,
            'time_allowed' => $time_allowed,
            'total_ques' => $total_questions,
            'total_marks' => $total_marks,
            'question_ids' => $question_ids,//implode(',', $question_ids),
            'shuffle_question' => 1,
            'attempt_allowed' => $attempt_allowed,
            'show_feedback' => 1,
            'show_hide' => 1,
            'result_show_ans' => 1,
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
            'exam_type' => $exam_type,
            'ai_generated' => '1',
            'difficulty_distribution' => json_encode($difficulty_distribution),
            'taxonomy_distribution' => json_encode($taxonomy_distribution),
            'sections_config' => json_encode($sections_config)
        ]);
        
        $questionpaper_id = $questionpaper->id;
        
        // Generate PDF
        $this->generatePDF([
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear
        ], $questionpaper_id);
        
        $res = [
            'status_code' => 1,
            'message' => 'AI Question Paper Generated Successfully'
        ];
        
        return is_mobile($type, 'question_paper.index', $res, 'redirect');
    }
    
    /**
     * Export AI Generated Question Paper as PDF
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportAIPaperPDF(Request $request)
    {
        $questionpaper_id = $request->input('questionpaper_id');
        $set_index = $request->input('set_index', 0);
        
        if (!$questionpaper_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Question paper ID is required'
            ]);
        }
        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        
        $questionpaper = questionpaperModel::find($questionpaper_id);
        
        if (!$questionpaper) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Question paper not found'
            ]);
        }
        
        $question_ids = explode(",", $questionpaper->question_ids);
        
        $question_arr = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();
        
        $answer = [];
        foreach ($question_arr as $key => $val) {
            $answer_arr = answermasterModel::where("question_id", $val['id'])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }
        }
        
        $data['questionpaper_data'] = $questionpaper->toArray();
        $data['questionpaper_data']['set_name'] = 'Set ' . chr(65 + $set_index);
        $data['question_arr'] = $question_arr;
        $data['answer_arr'] = $answer;
        
        $sections_config = json_decode($questionpaper->sections_config, true);
        if (!empty($sections_config)) {
            $sectionLetters = ['A', 'B', 'C', 'D', 'E'];
            $sections = [];
            $currentIndex = 0;
            
            foreach ($sections_config as $idx => $section) {
                $sectionQuestions = array_slice($question_arr, $currentIndex, $section['questions']);
                $sections[] = [
                    'name' => $sectionLetters[$idx] ?? chr(65 + $idx),
                    'questions' => $sectionQuestions,
                    'total_marks' => array_sum(array_column($sectionQuestions, 'points'))
                ];
                $currentIndex += $section['questions'];
            }
            $data['sections'] = $sections;
        }
        
        $data['show_answer_space'] = true;
        $html = view('lms/questionpaper_cbse', compact('data'))->render();
        
        $pdf_folder = public_path('storage/QuestionPaper');
        if (!file_exists($pdf_folder)) {
            mkdir($pdf_folder, 0777, true);
        }
        
        $pdf_filename = $questionpaper_id . '_set' . ($set_index + 1) . '_' . $sub_institute_id . '_' . $syear . '.pdf';
        $pdf_file_path = $pdf_folder . '/' . $pdf_filename;
        
        $dom = '<!DOCTYPE html>
        <html>
            <head>
                <title>' . $questionpaper->paper_name . '</title>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body>
                <div>##HTML_SEC##</div>
            </body>
        </html>';
        
        $html = str_replace('##HTML_SEC##', $html, $dom);
        
        $html_filename = $questionpaper_id . '_set' . ($set_index + 1) . '_' . $sub_institute_id . '_' . $syear . '.html';
        $html_file_path = $pdf_folder . '/' . $html_filename;
        
        file_put_contents($html_file_path, $html);
        
        $this->htmlToPDF($html_file_path, $pdf_file_path);
        
        if (file_exists($html_file_path)) {
            unlink($html_file_path);
        }
        
        if (file_exists($pdf_file_path)) {
            return response()->json([
                'status_code' => 1,
                'message' => 'PDF generated successfully',
                'pdf_url' => asset('storage/QuestionPaper/' . $pdf_filename)
            ]);
        }
        
        return response()->json([
            'status_code' => 0,
            'message' => 'Failed to generate PDF'
        ]);
    }
    
    /**
     * Export AI Generated Question Paper as HTML
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportAIPaperHTML(Request $request)
    {
        $questionpaper_id = $request->input('questionpaper_id');
        $set_index = $request->input('set_index', 0);
        
        if (!$questionpaper_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Question paper ID is required'
            ]);
        }
        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        
        $questionpaper = questionpaperModel::find($questionpaper_id);
        
        if (!$questionpaper) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Question paper not found'
            ]);
        }
        
        $question_ids = explode(",", $questionpaper->question_ids);
        
        $question_arr = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();
        
        $answer = [];
        foreach ($question_arr as $key => $val) {
            $answer_arr = answermasterModel::where("question_id", $val['id'])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }
        }
        
        $data['questionpaper_data'] = $questionpaper->toArray();
        $data['questionpaper_data']['set_name'] = 'Set ' . chr(65 + $set_index);
        $data['question_arr'] = $question_arr;
        $data['answer_arr'] = $answer;
        
        $sections_config = json_decode($questionpaper->sections_config, true);
        if (!empty($sections_config)) {
            $sectionLetters = ['A', 'B', 'C', 'D', 'E'];
            $sections = [];
            $currentIndex = 0;
            
            foreach ($sections_config as $idx => $section) {
                $sectionQuestions = array_slice($question_arr, $currentIndex, $section['questions']);
                $sections[] = [
                    'name' => $sectionLetters[$idx] ?? chr(65 + $idx),
                    'questions' => $sectionQuestions,
                    'total_marks' => array_sum(array_column($sectionQuestions, 'points'))
                ];
                $currentIndex += $section['questions'];
            }
            $data['sections'] = $sections;
        }
        
        $data['show_answer_space'] = true;
        $htmlContent = view('lms/questionpaper_cbse', compact('data'))->render();
        
        $fullHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . ($questionpaper->paper_name ?? 'Question Paper') . '</title>
</head>
<body>' . $htmlContent . '</body>
</html>';
        
        return response()->json([
            'status_code' => 1,
            'message' => 'HTML generated successfully',
            'html' => $fullHtml
        ]);
    }

}
