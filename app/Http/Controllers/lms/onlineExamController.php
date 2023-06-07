<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsOnlineExamAnswerModel;
use App\Models\lms\lmsOnlineExamModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questionpaperModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use function App\Helpers\is_mobile;

class onlineExamController extends Controller
{

    public function index(Request $request)
    {

        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['answer_arr'] = $data['answer_arr'];
        $res['questionpaper_data'] = $data['questionpaper_data'];
        $res['question_arr'] = $data['question_arr'];

        return is_mobile($type, 'lms/online_exam', $res, "view");
    }

    public function getData($request)
    {

        if (Session::has('session_quiz')) {
            //Nothing to do     
        } else {
            Session::put('session_quiz', date('Y-m-d H:i:s'));
            Session::save();
        }

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $questionpaper_id = $request->get('questionpaper_id');
        $data['questionpaper_data'] = questionpaperModel::find($questionpaper_id)->toArray();

        //Get all questions subject wise        
        $question_ids = explode(",", $data['questionpaper_data']['question_ids']);
        $data['question_arr'] = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();

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
        }
        $data['answer_arr'] = $answer;

        return $data;
    }


    public function create(Request $request)
    {
    }

    public function store(Request $request)
    {
        //Clear session for timer
        Session::forget('session_quiz');

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        //$questionpaper_details = $this->get_questionpaper_details($request->get('questionpaper_id'));
        $result = $this->get_calculate_marks($request);

        //START Insert into lms_online_exam table
        $online_exam = [
            'student_id'        => $user_id,
            'question_paper_id' => $request->get('questionpaper_id'),
            'total_right'       => $result['total_right_ans'],
            'total_wrong'       => $result['total_wrong_ans'],
            'obtain_marks'      => $result['obtain_marks'],
            'start_time'        => $request->get('hid_session_quiz'),
        ];


        lmsOnlineExamModel::insert($online_exam);
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
                    'question_paper_id' => $request->get('questionpaper_id'),
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $single_question_id,
                    'answer_id'         => $single_ans_arr[0],
                    'ans_status'        => $ans_status,
                ];
                lmsOnlineExamAnswerModel::insert($single);
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
                            'question_paper_id' => $request->get('questionpaper_id'),
                            'online_exam_id'    => $online_exam_id,
                            'student_id'        => $user_id,
                            'question_id'       => $multiple_question_id,
                            'answer_id'         => $multiple_ans_arr[0],
                            'ans_status'        => $ans_status,
                        ];
                        lmsOnlineExamAnswerModel::insert($multiple);
                    }
                }
            }
        }

        if (is_array($answer_narrative)) {
            foreach ($answer_narrative as $narrative_question_id => $narrative_answer_ids) {
                $ans_status = "right";
                $narrative = [
                    'question_paper_id' => $request->get('questionpaper_id'),
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $narrative_question_id,
                    'narrative_answer'  => $narrative_answer_ids,
                    'ans_status'        => $ans_status,
                ];
                lmsOnlineExamAnswerModel::insert($narrative);
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
        return redirect()->route('online_exam.show',
            [$request->get('questionpaper_id'),"online_exam_id"=> $online_exam_id]);
    }


    public function get_calculate_marks(Request $request)
    {
        $answer_single = $request->get('answer_single');
        $answer_multiple = $request->get('answer_multiple');
        $answer_narrative = $request->get('answer_narrative');

        $wrong_single_ans = $right_single_ans = $right_multiple_ans = $wrong_multiple_ans = $right_narrative_ans = $wrong_narrative_ans = 0;
        $right_question_ids_arr = $given_ans_arr = [];

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
                $original_ans = DB::table('answer_master')
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
            $obtain_marks = DB::table('lms_question_master')
                ->selectRaw('SUM(points) AS obtain_marks')
                ->whereIn('id', $arr)
                ->get()->toArray();

            $obt_marks = $obtain_marks[0]->obtain_marks;
        }

        return $obt_marks;
    }

    public function get_questionpaper_details($questionpaper_id)
    {
        $data = questionpaperModel::select('question_paper.*', 'sub_std_map.display_name as subject_name')
            ->join("sub_std_map", function ($join) {
                $join->on("sub_std_map.subject_id", "=", "question_paper.subject_id")
                    ->on("sub_std_map.standard_id", "=", "question_paper.standard_id");
            })
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

        $data['online_exam_data'] = lmsOnlineExamModel::where([
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
                FROM lms_online_exam_answer
                WHERE online_exam_id = '".$online_exam_id."' AND student_id = '".$user_id."'
                GROUP BY question_id) AS a
                INNER JOIN lms_question_master q ON q.id = a.question_id
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
        // echo "<pre>";print_r($data);exit;
        return is_mobile($type, 'lms/online_exam_result', $data, "view");
    }

    public function online_exam_attempt(Request $request)
    {
        $questionpaper_id = $request->get("questionpaper_id");
        $student_id = $request->get("student_id");

        $data['questionpaper_data'] = $this->get_questionpaper_details($questionpaper_id);

        $data['attempted_data'] = lmsOnlineExamModel::where([
            'student_id'        => $student_id,
            'question_paper_id' => $questionpaper_id,
        ])->orderby('start_time')->get()->toArray();
        foreach ($data['attempted_data'] as $key => $val) {
            $pdata = DB::select("SELECT *,'100' as total_percentage,
                round(((a.right_answer*100)/total_question),2) as obtained_percentage from (
                SELECT lt.parent_id,plt.name as parent_name,lt.id,lt.name,COUNT(mapping_type_id) as total_question,group_concat(e.question_id) as ques_list,
                sum((case when e.ans_status = 'right' then '1' end)) as right_answer
                FROM lms_question_mapping l
                INNER JOIN lms_mapping_type lt ON lt.id = l.mapping_value_id
                INNER JOIN lms_mapping_type plt ON plt.id = lt.parent_id
                LEFT JOIN lms_online_exam_answer e on e.question_id = l.questionmaster_id and e.question_paper_id = '".$val['question_paper_id']."' AND 
                e.student_id = '".$val['student_id']."' and e.online_exam_id = '".$val['id']."'
                WHERE questionmaster_id IN (
                        SELECT question_id
                        FROM lms_online_exam_answer
                        WHERE question_paper_id = '".$val['question_paper_id']."' AND student_id = '".$val['student_id']."' 
                        AND online_exam_id = '".$val['id']."'
                    ) 
                GROUP BY mapping_value_id
                ORDER BY mapping_type_id,mapping_value_id) as a
            ");
            $progressbar_data[$val['id']] = json_decode(json_encode($pdata), true);
        }
        $data['progressbar_data'] = $progressbar_data;
        //dd($progressbar_data);

        $final_progressbar_data = array();

        foreach ($progressbar_data as $pkey => $pval) {
            $parent_mapping_arr[$pkey] = array();
            foreach ($pval as $okey => $oval) {
                if (! array_key_exists($oval['parent_name'], $parent_mapping_arr[$pkey])) {
                    $parent_mapping_arr[$pkey][$oval['parent_name']] = $oval['total_question'];
                } else {
                    $temp = $parent_mapping_arr[$pkey][$oval['parent_name']];
                    $temp = $temp + $oval['total_question'];
                    $parent_mapping_arr[$pkey][$oval['parent_name']] = $temp;
                }
                if (! array_key_exists($oval['parent_name'], $final_progressbar_data)) {
                    $final_progressbar_data[$pkey][$oval['parent_name']][] = $oval;
                }
            }
        }
        $data['parent_mapping_arr'] = $parent_mapping_arr;
        $data['final_progressbar_data'] = $final_progressbar_data;

        //dd($data);

        $type = $request->input('type');

        //return is_mobile($type,'lms/online_attempted_result',$data,"view");
        return is_mobile($type, 'lms/online_attempted_result', $data, "view");
    }

    public function ajax_getQuestionList(Request $request)
    {
        $ques_list = explode(",", $request->input("ques_list"));
        $online_exam_id = $request->input("online_exam_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return lmsQuestionMasterModel::select('lms_question_master.id', 'question_title', 'a.ans_status')
            ->join('lms_online_exam_answer as a', 'a.question_id', 'lms_question_master.id')
            ->where(['sub_institute_id' => $sub_institute_id, 'a.online_exam_id' => $online_exam_id])
            ->whereIn('lms_question_master.id', $ques_list)
            ->get()->toArray();
    }
}
