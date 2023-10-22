<?php

namespace App\Http\Controllers\lms\counselling;

use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingAnswerModel;
use App\Models\lms\counselling\counsellingOnlineExamAnswerModel;
use App\Models\lms\counselling\counsellingOnlineExamModel;
use App\Models\lms\counselling\counsellingQuestionModel;
use App\Models\lms\lmsOnlineExamModel;
use App\Models\lms\questionpaperModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class counsellingExamController extends Controller
{

    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['answer_arr'] = $data['answer_arr'];
        $res['question_arr'] = $data['question_arr'];
        $res['exam_data'] = $data['exam_data'];

        return is_mobile($type, 'lms/counselling/counselling_exam', $res, "view");
    }

    public function getData($request)
    {

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $course_id = $request->get('course_id');

        //Get all questions counselling course wise
        $data['question_arr'] = counsellingQuestionModel::select('counselling_question_master.*',
            'c.title as course_title')
            ->join('counselling_course as c', 'c.id', 'counselling_question_master.counselling_course_id')
            ->where("counselling_course_id", $course_id)->get()->toArray();

        if (count($data['question_arr']) > 0) {
            $data['exam_data']['course_id'] = $course_id;
            $data['exam_data']['course_title'] = $data['question_arr'][0]['course_title'];
            $data['exam_data']['total_question'] = count($data['question_arr']);
            $total_marks = 0;
            foreach ($data['question_arr'] as $key => $val) {
                $total_marks += $val['points'];
                $answer_arr = counsellingAnswerModel::where("question_id", $val['id'])->get()->toArray();
                if (count($answer_arr) > 0) {
                    foreach ($answer_arr as $anskey => $ansval) {
                        $answer[$val['id']][] = $ansval;
                    }
                }
            }
            $data['exam_data']['total_marks'] = $total_marks;
        }
        $data['answer_arr'] = $answer;

        return $data;
    }


    public function create(Request $request)
    {
    }

    public function store(Request $request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $result = $this->get_calculate_marks($request);

        //START Insert into lms_online_exam table
        $online_exam = [
            'user_id'          => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'course_id'        => $request->get('course_id'),
            'total_right'      => $result['total_right_ans'],
            'total_wrong'      => $result['total_wrong_ans'],
            'obtain_marks'     => $result['obtain_marks'],
        ];


        counsellingOnlineExamModel::insert($online_exam);
        $online_exam_id = DB::getPDO()->lastInsertId();
        //END Insert into lms_online_exam table

        //START Insert into lms_online_exam_answer table
        $answer_single = $request->get('answer_single');
        $answer_multiple = $request->get('answer_multiple');
        $answer_narrative = $request->get('answer_narrative');

        if (is_array($answer_single)) {
            foreach ($answer_single as $single_question_id => $single_answer_ids) {
                $ans_status = "wrong";
                $single_ans_arr = explode("##", $single_answer_ids);
                if ($single_ans_arr[1] == 1) {
                    $ans_status = "right";
                }
                $single = [
                    'online_exam_id' => $online_exam_id,
                    'user_id'        => $user_id,
                    'question_id'    => $single_question_id,
                    'answer_id'      => $single_ans_arr[0],
                    'ans_status'     => $ans_status,
                ];
                counsellingOnlineExamAnswerModel::insert($single);
            }
        }

        if (is_array($answer_multiple)) {
            foreach ($answer_multiple as $multiple_question_id => $multiple_answer_ids) {
                if (is_array($multiple_answer_ids))//Insert MCQ Answers
                {
                    foreach ($multiple_answer_ids as $key => $val) {
                        $ans_status = "wrong";
                        $multiple_ans_arr = explode("##", $val);
                        if ($multiple_ans_arr[1] == 1) {
                            $ans_status = "right";
                        }
                        $multiple = [
                            'online_exam_id' => $online_exam_id,
                            'user_id'        => $user_id,
                            'question_id'    => $multiple_question_id,
                            'answer_id'      => $multiple_ans_arr[0],
                            'ans_status'     => $ans_status,
                        ];
                        counsellingOnlineExamAnswerModel::insert($multiple);
                    }
                }
            }
        }

        if (is_array($answer_narrative)) {
            foreach ($answer_narrative as $narrative_question_id => $narrative_answer_ids) {
                $ans_status = "right";
                $narrative = [
                    'online_exam_id'   => $online_exam_id,
                    'user_id'          => $user_id,
                    'question_id'      => $narrative_question_id,
                    'narrative_answer' => $narrative_answer_ids,
                    'ans_status'       => $ans_status,
                ];
                counsellingOnlineExamAnswerModel::insert($narrative);
            }
        }


        // if(is_array($answer_ids))//Insert MCQ Answers
        // {
        //     foreach($answer_ids as $key => $val)
        //     {
        //         $online_exam_answer['answer_id'] = $key;
        //         lmsOnlineExamAnswerModel::insert($online_exam_answer);
        //     }
        // }
        // else //Insert Narrative Answers
        // {
        //     $online_exam_answer['narrative_answer'] = $answer_ids;
        //     lmsOnlineExamAnswerModel::insert($online_exam_answer);
        // }
        //END Insert into lms_online_exam_answer table

        //return is_mobile($type,'lms/online_exam_result',$res,"view");
        return redirect()->route('lmsCounsellingExam.show',
            ['course_id' => $request->get('course_id'), 'online_exam_id' => $online_exam_id]);
    }


    public function get_calculate_marks(Request $request)
    {

        $answer_single = $request->get('answer_single');
        $answer_multiple = $request->get('answer_multiple');
        $answer_narrative = $request->get('answer_narrative');

        $wrong_single_ans = $right_single_ans = $right_multiple_ans = $wrong_multiple_ans = $right_narrative_ans = $wrong_narrative_ans = 0;
        $right_question_ids_arr = $given_ans_arr = array();

        //START Check for single answer
        if (isset($answer_single)) {
            foreach ($answer_single as $single_question_id => $single_answer_ids) {
                $s_ans = explode("##", $single_answer_ids);

                if ($s_ans[1] == 1) {
                    $right_single_ans++;
                    $right_question_ids_arr[] = $single_question_id;
                } else {
                    if ($s_ans[1] == 0) {
                        $wrong_single_ans++;
                    }
                }
            }
        }
        //END Check for single answer

        //START Check for multiple answer
        if (isset($answer_multiple)) {
            foreach ($answer_multiple as $key => $multiple_answer_ids) {
                $original_ans = DB::table('counselling_answer_master')
                    ->selectRaw('GROUP_CONCAT(id) AS right_answers')
                    ->where('question_id', $key)
                    ->where('correct_answer', '=', 1)->get()->toArray();

                $original_ans = explode(",", $original_ans[0]->right_answers);

                foreach ($multiple_answer_ids as $multiple_question_id => $multiple_answer) {
                    $a_ans = explode("##", $multiple_answer);
                    $ans = $a_ans[1];
                    if ($ans == 1) {
                        $given_ans_arr[] = $a_ans[0];
                    }
                }

                $diff = array_diff($original_ans, $given_ans_arr);

                if (count($diff) == 0) {
                    $right_multiple_ans++;
                    $right_question_ids_arr[] = $key;
                } else {
                    $wrong_multiple_ans++;
                }
            }
        }

        //END Check for multiple answer

        //START Check for Narrative answer
        if (isset($answer_narrative)) {
            foreach ($answer_narrative as $narrative_question_id => $narrative_answer) {
                if (isset($narrative_answer) && $narrative_answer != "") {
                    $right_narrative_ans++;
                    $right_question_ids_arr[] = $narrative_question_id;
                } else {
                    $wrong_narrative_ans++;
                }
            }
        }
        //END Check for Narrative answer

        $obtain_marks = $this->get_obtain_marks($right_question_ids_arr);

        // echo "right_single_ans->".$right_single_ans."<br>";
        // echo "wrong_single_ans->".$wrong_single_ans."<br><br>";
        // echo "right_multiple_ans->".$right_multiple_ans."<br>";
        // echo "wrong_multiple_ans->".$wrong_multiple_ans."<br><br>";
        // echo "right_narrative_ans->".$right_narrative_ans."<br>";
        // echo "wrong_narrative_ans->".$wrong_narrative_ans."<br><br>";
        // echo '<pre>';
        // print_r($right_question_ids_arr);
        // echo "<br>";
        // echo "obtain_marks->".$obtain_marks."<br>";

        $data['obtain_marks'] = $obtain_marks;
        $data['total_wrong_ans'] = $wrong_single_ans + $wrong_multiple_ans + $wrong_narrative_ans;
        $data['total_right_ans'] = $right_single_ans + $right_multiple_ans + $right_narrative_ans;

        return $data;

    }

    public function get_obtain_marks($arr)
    {
        if (empty($arr)) {
            $obt_marks = 0;
        } else {
            $obtain_marks = DB::table('counselling_question_master')
                ->selectRaw('SUM(points) AS obtain_marks')
                ->whereIn('id', $arr)->get()->toArray();

            $obt_marks = $obtain_marks[0]->obtain_marks;
        }

        return $obt_marks;
    }

    public function get_questionpaper_details($questionpaper_id)
    {
        $data = questionpaperModel::select('question_paper.*', 'sub_std_map.display_name as subject_name')
            ->join('sub_std_map', 'sub_std_map.id', '=', 'question_paper.subject_id')
            ->where('question_paper.id', $questionpaper_id)->get()->toArray();

        return $data[0];
    }

    public function edit(Request $request, $id)
    {
        // dd($request);
        // $type = $request->input('type');
        // $sub_institute_id = $request->session()->get('sub_institute_id');
        // $questionpaper_id = $request->get('questionpaper_id');
        // $data['questionpaper_data'] = questionpaperModel::find($id)->toArray();

        // //Get all questions subject wise
        // $question_ids = explode(",",$data['questionpaper_data']['question_ids']);
        // $data['question_arr'] = lmsQuestionMasterModel::whereIn("id",$question_ids)->get()->toArray();

        // foreach($data['question_arr'] as $key => $val)
        // {
        //     $answer_arr = answermasterModel::where("question_id",$val['id'])->get()->toArray();
        //     if(count($answer_arr) > 0)
        //     {
        //         foreach($answer_arr as $anskey => $ansval)
        //         {
        //             $answer[$val['id']][] = $ansval;
        //         }
        //     }
        // }

        // $type = $request->input('type');
        // $res['status_code'] = 1;
        // $res['message'] = "SUCCESS";
        // $res['answer_arr'] = $answer;
        // $res['questionpaper_data'] = $data['questionpaper_data'];
        // $res['question_arr'] = $data['question_arr'];
        // return is_mobile($type,'lms/online_exam_div',$res,"view");

    }

    public function update(Request $request, $id)
    {

    }

    public function destroy(Request $request, $id)
    {

    }

    public function show(Request $request, $id)
    {

        $course_id = $id;

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $online_exam_id = $request->get('online_exam_id');

        //$data['questionpaper_data'] = questionpaperModel::find($questionpaper_id)->toArray();

        //Get all questions subject wise
        //$question_ids = explode(",",$data['questionpaper_data']['question_ids']);
        $data['question_arr'] = counsellingQuestionModel::select('counselling_question_master.*',
            'c.title as course_title')
            ->join('counselling_course as c', 'c.id', 'counselling_question_master.counselling_course_id')
            ->where("counselling_course_id", $course_id)->get()->toArray();

        if (count($data['question_arr']) > 0) {
            $data['exam_data']['course_title'] = $data['question_arr'][0]['course_title'];
            $data['exam_data']['total_ques'] = count($data['question_arr']);
            $total_marks = 0;

            foreach ($data['question_arr'] as $key => $val) {
                $total_marks += $val['points'];
                $answer_arr = counsellingAnswerModel::where("question_id", $val['id'])->get()->toArray();
                if (count($answer_arr) > 0) {
                    foreach ($answer_arr as $anskey => $ansval) {
                        $answer[$val['id']][] = $ansval;
                    }
                }
            }
            $data['exam_data']['total_marks'] = $total_marks;
            $data['exam_data']['result_show_ans'] = 1;
        }

        $data['answer_arr'] = $answer;

        $data['online_exam_data'] = counsellingOnlineExamModel::where([
            'id'      => $online_exam_id,
            'user_id' => $user_id,
        ])->get()->toArray();
        $data['online_exam_data'] = $data['online_exam_data'][0];

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
                FROM counselling_online_exam_answer
                WHERE online_exam_id = '".$online_exam_id."' AND user_id = '".$user_id."'
                GROUP BY question_id) AS a
                INNER JOIN counselling_question_master q ON q.id = a.question_id
                LEFT JOIN counselling_answer_master am ON a.question_id = am.question_id AND correct_answer = 1
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

        return is_mobile($type, 'lms/counselling/counselling_online_exam_result', $data, "view");
    }

    public function online_exam_attempt(Request $request)
    {
        $questionpaper_id = $request->get("questionpaper_id");
        $student_id = $request->get("student_id");

        $data['questionpaper_data'] = $this->get_questionpaper_details($questionpaper_id);

        $data['attempted_data'] = lmsOnlineExamModel::where('student_id',
            $student_id)->orderby('start_time')->get()->toArray();


        $type = $request->input('type');

        return is_mobile($type, 'lms/online_attempted_result', $data, "view");
    }
}
