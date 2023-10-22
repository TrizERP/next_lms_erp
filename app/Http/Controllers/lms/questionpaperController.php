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
                        ->on('se.syear', '=', DB::raw($syear))  // Remove quotes from $syear
                        ->on('se.sub_institute_id', '=', DB::raw($sub_institute_id));
                })                
                ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
                ->join('sub_std_map as ssm', function ($join) use ($sub_institute_id) {
                    $join->on('ssm.subject_id', '=', 'question_paper.subject_id')
                        ->on('ssm.standard_id', '=', 'se.standard_id');
                })
                ->leftJoin('lms_online_exam', function ($join) use ($student_id) {
                    $join->on('lms_online_exam.question_paper_id', '=', 'question_paper.id')
                        ->on('lms_online_exam.student_id', '=', DB::raw($student_id));
                })
                ->where('question_paper.sub_institute_id', $sub_institute_id)
                ->where('question_paper.syear', $syear)
                ->where('standard.id', $stu_data[0]['standard_id'])
                ->where('question_paper.exam_type', 'online')
            //Rajesh - Student Display optional subject map created exam
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
                'academic_section.title as grade_name', 'subject_name', DB::raw('date_format(open_date,"%Y-%m-%d") as open_date,
                date_format(close_date,"%Y-%m-%d") as close_date,if(now() between open_date and close_date,"yes","no") as active_exam'))
                ->join('standard', function($join) use($marking_period_id){
                    $join->on('standard.id', '=', 'question_paper.standard_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('standard.marking_period_id',$marking_period_id);
                    // });
                })
                ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
                ->join('subject', 'subject.id', '=', 'question_paper.subject_id')
                ->where('question_paper.sub_institute_id', $sub_institute_id)
                ->where('question_paper.syear', $syear)
                ->where('question_paper.created_by', $user_id)
                ->orderBy('question_paper.id', 'desc')
                ->get();
        }
        else
        {
            $data['questionpaper_data'] = questionpaperModel::select('question_paper.*',
                'standard.name as standard_name',
                'academic_section.title as grade_name', 'subject_name', DB::raw('date_format(open_date,"%Y-%m-%d") as open_date,
                date_format(close_date,"%Y-%m-%d") as close_date,if(now() between open_date and close_date,"yes","no") as active_exam'))
                ->join('standard', function($join) use($marking_period_id){
                    $join->on('standard.id', '=', 'question_paper.standard_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('standard.marking_period_id',$marking_period_id);
                    // });
                })
                ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
                ->join('subject', 'subject.id', '=', 'question_paper.subject_id')
                ->where('question_paper.sub_institute_id', $sub_institute_id)
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
            $close_date = date('Y-m-d H:i:s', strtotime($_REQUEST['close_date']));
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
        file_put_contents($html_file_path, $html);
        $this->htmlToPDF($html_file_path, $pdf_file_path);
        unlink($html_file_path);
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

        $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();
        $data['subjects'] = $stdData;

        // echo "<pre>";print_r($data['questionpaper_data']['question_ids']); exit;
        $sub_id = $data['questionpaper_data']['subject_id'];

    //     $questionData = DB::table('lms_question_master as qm')
    //         ->join('question_type_master as t', function ($join) {
    //             $join->whereRaw('t.id = qm.question_type_id');
    //         })->join('chapter_master as c', function ($join) {
    //             $join->whereRaw('c.id = qm.chapter_id');
    //         })->leftJoin('answer_master as am', function ($join) {
    //             $join->whereRaw('am.question_id = qm.id AND correct_answer=1');
    //         })
    //         ->join('question_paper as qp', function ($join) use ($odate, $cdate) {
    //     $join->on('qm.id', '=', 'qp.question_ids')
    //          ->where('qp.open_date', '=', $odate)
    //          ->where('qp.close_date', '=', $cdate);
    // })
    // ->selectRaw("qm.id,question_title,points,t.question_type,
    //             ifnull(am.answer,'-') AS correct_answer,c.chapter_name,c.sort_order,qm.standard_id,qm.chapter_id")
    //         ->where('qm.standard_id', $std_id)
    //         ->where('qm.subject_id', $sub_id)
    //         ->where('qm.sub_institute_id', $sub_institute_id)
    //         ->groupBy('qm.id')
    //         ->orderBy('chapter_name')->get();

    $questionIds = explode(',',$data['questionpaper_data']['question_ids']);

// $questionIds = explode(',', $data['questionpaper_data']['question_ids']);

$chapters = DB::table('lms_question_master')
    ->whereIn('id', $questionIds)
    ->distinct()
    ->pluck('chapter_id')
    ->toArray();
    $chapterIds = DB::table('lms_question_master')
    ->whereIn('id', $questionIds)
    ->pluck('chapter_id', 'id');

// foreach ($questionIds as $questionId) {
//     echo "Question $questionId belongs to chapter {$chapterIds[$questionId]}\n";
// }
// exit;
// dd($chapters);
// echo "<pre>";print_r($questionIds);exit;
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
    ->where('qm.sub_institute_id', $sub_institute_id)
    ->where('qm.status', 1)
    ->groupBy('qm.id')
    ->orderBy('chapter_master.sort_order')
    ->get();

$questionData = json_decode(json_encode($questionData),true);
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


            // $chapters = $questionData[0]->chapter_id;
            // dd($questionData);exit;
        // echo "<pre>";print_r($odate);exit;
        $data['questionData'] = $questionData;
        $data['grade_id'] = $grade_id;
        $data['standard_id'] = $std_id;
        $data['edit_id'] = $id;

        // $data['chapter_id'] = $chapters;

        // $data['questionData'] = $questionData;
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
            $close_date = date('Y-m-d H:i:s', strtotime($_REQUEST['close_date']));
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
    // return $all_data['sub_institute_id'];exit;
    $sub_id = $all_data['subject'];
        $std_id = $all_data['standard'];
        $sub_institute_id = $all_data["sub_institute_id"];
        $user_profile_id = session()->get('user_profile_id');
        $user_profile_name = session()->get('user_profile_name');
        $user_id = session()->get('user_id');

        $extra = "";
        $outer_extra = "1 = 1";
        if (isset($all_data["search_chapter"])) {
            $search_chapter = $all_data["search_chapter"];
            $extra .= "qm.chapter_id IN (" . implode(",", $search_chapter) . ")";
        }
        if (isset($all_data["search_topic"]) && $all_data["search_topic"] != [null]) {

                $search_topic = $all_data["search_topic"];
                $extra .= " AND qm.topic_id IN (".implode(",",$search_topic).") ";
            }

        if (isset($all_data["search_mapping_type"])) {
            $search_mapping_type = $all_data["search_mapping_type"];
            $mapping_types =  $search_mapping_type;
            $outer_extra_type = " AND (";
            foreach ($mapping_types as $key => $mapping_type_val) {
                $outer_extra_type .= " find_in_set('".$mapping_type_val."',a.mapping_type) OR";
            }
            $outer_extra_type .= ")";
            $outer_extra .= str_replace(') OR)', '))', $outer_extra_type);
        }
        if (isset($all_data["search_mapping_value"])) {
            $search_mapping_value = $all_data["search_mapping_value"];
            $mapping_values = $search_mapping_value;
            $outer_extra_mapping = " AND (";
            foreach ($mapping_values as $key1 => $mapping_val) {
                $outer_extra_mapping .= " find_in_set('".$mapping_val."',a.mapping_value) OR";
            }
            $outer_extra_mapping .= ")";
            $outer_extra .= str_replace(') OR)', '))', $outer_extra_mapping);
        }

        // $sql = "
        //     SELECT * FROM
        //     (SELECT qm.id,question_title,points,t.question_type,
        //     ifnull(GROUP_CONCAT(DISTINCT(am.answer)),'-') AS correct_answer,c.chapter_name,c.sort_order,
        //     tm.name as topic_name,GROUP_CONCAT(lqm.mapping_type_id) as mapping_type,GROUP_CONCAT(lqm.mapping_value_id) as mapping_value
        //     FROM lms_question_master qm
        //     INNER JOIN question_type_master t ON t.id = qm.question_type_id
        //     INNER JOIN chapter_master c ON c.id = qm.chapter_id
        //     LEFT JOIN topic_master tm ON tm.id = qm.topic_id
        //     LEFT JOIN lms_question_mapping lqm ON lqm.questionmaster_id = qm.id
        //     LEFT JOIN answer_master am ON am.question_id = qm.id AND correct_answer=1
        //     WHERE qm.standard_id =  '".$std_id."' AND qm.subject_id = '".$sub_id."'
        //     AND qm.sub_institute_id = '".$sub_institute_id."'  ".$extra."
        //     GROUP BY qm.id
        //     ORDER BY chapter_name)
        //     AS a
        //     ".$outer_extra."
        //     ";
        // DB::EnableQueryLog();
        $questionData = DB::table(function ($query) use ($std_id, $sub_id, $sub_institute_id, $extra, $outer_extra) {
        $query->select('qm.id', 'question_title', 'points', 't.question_type', DB::raw("ifnull(GROUP_CONCAT(DISTINCT(am.answer)),'-') AS correct_answer"), 'c.chapter_name', 'c.sort_order', 'tm.name as topic_name', DB::raw("GROUP_CONCAT(lqm.mapping_type_id) as mapping_type"), DB::raw("GROUP_CONCAT(lqm.mapping_value_id) as mapping_value"))
            ->from('lms_question_master as qm')
            ->join('question_type_master as t', 't.id', '=', 'qm.question_type_id')
            ->join('chapter_master as c', 'c.id', '=', 'qm.chapter_id')
            ->leftJoin('topic_master as tm', 'tm.id', '=', 'qm.topic_id')
            ->leftJoin('lms_question_mapping as lqm', 'lqm.questionmaster_id', '=', 'qm.id')
            ->leftJoin('lms_mapping_type as lmt', 'lmt.id', '=', 'lqm.mapping_value_id')
            ->leftJoin('answer_master as am', function($join) {
                $join->on('am.question_id', '=', 'qm.id')
                     ->where('correct_answer', '=', 1);
            })
            ->where('qm.standard_id', '=', $std_id)
            ->where('qm.subject_id', '=', $sub_id)
            ->where('qm.status', '=', 1)
            ->where('qm.sub_institute_id', '=', $sub_institute_id)
            ->whereRaw($extra)
            ->groupBy('qm.id')
            ->orderBy('chapter_name');
    }, 'a')
    ->select('*')
    ->orderByRaw($outer_extra)
    ->get();
    // dd(DB::getQueryLog($questionData));
        // $questionData = DB::select($sql);
        $questionData = json_decode(json_encode($questionData), true);
        // return $sql;exit;

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
            $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
                ->orderBy('display_name')->get()->toArray();
        }
        if(isset($all_data['search_chapter'])){
        $chapters = chapterModel::where([
            'sub_institute_id' => $sub_institute_id,
            'subject_id'       => $sub_id,
            'standard_id'      => $std_id,
        ])->get()->toArray();
}
        $chapter_ids = $all_data['search_chapter'];

        if(isset($all_data['search_chapter'])){
    $topics = topicModel::whereIn("chapter_id",$chapter_ids)
        ->where(['sub_institute_id' => $sub_institute_id])
        ->get()->toArray();
    if(is_array($topics)){
        $res['topics'] = $topics;
    } else {
        $res['topics'] = array();
    }
    }

        $lms_mapping =lmsmappingtypeModel::select('*')
            ->where(['globally' => '1', 'parent_id' => '0'])
            ->get()->toArray();

        $mapping_types = $all_data['search_mapping_type'];

        if(isset($all_data['search_mapping_type'])){

         $map_val = DB::table('lms_mapping_type')
            ->select(['id', 'name'])
            ->whereIn("parent_id", $mapping_types)
            ->where(['status' => '1'])
            ->get()->toArray();
        $res['mapping_value'] = $map_val;

}
        $type = " ";
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['grade_id'] = $all_data['grade'];
        $res['standard_id'] = $std_id;
        $res['subject_id'] = $sub_id;
        $res['chapter_id'] = $all_data['search_chapter'];
        $res['topic_id'] = $all_data['search_topic'];
        $res['map_type'] = $all_data["search_mapping_type"];
        $res['map_value'] = $all_data["search_mapping_value"];
        $res['subjects'] = $stdData;
        $res['questionData'] = $questionData;
        $res['chapters'] = $chapters;
        $res['lms_mapping_type'] = $lms_mapping;
        if(isset($all_data['question_ids'])){
        $res['questionpaper_data']['question_ids'] = $all_data['question_ids'];
        }
        // <img alt="" src="https://erp.triz.co.in/lms_editor_upload/2736ch-3 1.jpg" style="width: 500px; height: 111px;" />
        // echo "<pre>";print_r($res['questionData']);exit;
        return is_mobile($type, "lms/add_questionpaper", $res, "view");

        // return view('lms.add_questionpaper')->with("questionData",$questionData);
}
    public function ajax_LMS_StandardwiseSubject(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_institute_id = session()->get("sub_institute_id");
        $user_profile_id = session()->get('user_profile_id');
        $user_profile_name = session()->get('user_profile_name');
        $user_id = session()->get('user_id');

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
            $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
                ->orderBy('display_name')->get()->toArray();
        }

        return $stdData;
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

        return $data[0]->total;
    }

}
