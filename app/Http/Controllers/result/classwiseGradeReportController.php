<?php

namespace App\Http\Controllers\result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use GenTux\Jwt\GetsJwtToken;

class classwiseGradeReportController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }

        $res = session()->get('data');

        return is_mobile($type, "result/result_report/classwise_grade_report_v2", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }

        $term_id = $request->input('term');
        $grade_id = $request->input('grade');
        $standard_id = $request->input('standard');
        $division_id = $request->input('division');
        $exam_create = $request->input('exam_type');

        $all_student = SearchStudent($grade_id, $standard_id, $division_id, $sub_institute_id, $syear, "");
        $student_id_arr = [];
        foreach ($all_student as $id => $arr) {
            $student_id_arr[] = $arr['student_id'];
        }
    
        $student_id = implode(',', $student_id_arr);

        $result = DB::table("result_create_exam as e")
            ->join('sub_std_map as s', function ($join) {
                $join->whereRaw("s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id");
            })
            ->leftJoin('result_marks as rm', function ($join) {
                $join->on("rm.exam_id","=" ,"e.id")->on("rm.sub_institute_id", "=", "e.sub_institute_id");
            })
            ->selectRaw("e.id,e.title AS ExamTitle, sum(e.points) AS total_points, e.subject_id,s.display_name AS subject_name,rm.student_id,round(SUM(rm.points),0) AS obtained_points,rm.is_absent,s.elective_subject")
            ->where("e.term_id", "=", $term_id)
            ->where("e.sub_institute_id", "=", $sub_institute_id)
            ->where("e.syear", "=", $syear)
            ->where("e.standard_id", "=", $standard_id)
            ->where("e.report_card_status", "=", 'Y')
            //->whereIn("e.subject_id", $additional_subjects)
            ->whereIn("student_id", $student_id_arr);

            if ($exam_create != '') {
                $result = $result->where('e.title', $exam_create);
            }
      

        $result = $result->groupByRaw('rm.student_id,rm.id')
            ->orderBy('rm.student_id')->get()->toArray();

        $grade_arr = \App\Helpers\getGradeScale($standard_id, $type);

        // getting data and making readable format student wise
        $studentResults = [];
        // echo "<pre>";print_r($result);exit;
        foreach ($result as $key => $value) {
            if($value->elective_subject=="Yes"){
                $value->obtained_points = \App\Helpers\getGrade($grade_arr, $value->total_points, $value->obtained_points);
            }

            $studentResults[$value->student_id][$value->ExamTitle][$value->subject_name] = $value;
            $obt = $tot = 0;
            if(is_numeric($value->obtained_points)){
                $obt = $value->obtained_points;
            }
            if(is_numeric($value->total_points)){
                $tot= $value->total_points;
            }
            if($exam_create==''){
                if(!isset($studentResults[$value->student_id]['Grand Total'][$value->subject_name])){
                    $studentResults[$value->student_id]['Grand Total'][$value->subject_name] = 0;
                }
                if(!isset($studentResults[$value->student_id]['Average'][$value->subject_name])){
                    $studentResults[$value->student_id]['Average'][$value->subject_name] = 0;
                }
            // echo $obt.'<br>';
            // echo $tot.'<br>.....................<br>';
                $studentResults[$value->student_id]['Grand Total'][$value->subject_name] += $obt;
                $studentResults[$value->student_id]['Average'][$value->subject_name] += $tot;
            }
        }
        // echo "<pre>";print_r($studentResults);exit;

        $req = New Request(['stdId'=>$standard_id,'termID'=>$term_id,'title'=>$exam_create]);

        $getExamTitles = $this->getCreateExamName($req);
        // echo "<pre>";print_r($exam_create);exit;

        $examNames = [];
        foreach ($getExamTitles as $key => $value) {
            $examNames[] = $value->title;
        }

        if(!empty($examNames) && $exam_create==''){
            array_push($examNames,'Grand Total','Average');
        }
        if (in_array($grade_id,[148,149])) {
            $passing_ratio = 33;
        } else {
            $passing_ratio = 35;
        }
        $studentMarks = [];
        foreach ($all_student as $id => $arr) {
            $studentMarks[$id] = $arr;
            $studentMarks[$id]['exams'] = $examNames;
            
            foreach ($getExamTitles as $key => $value) {
                $studentMarks[$id]['examData'][$value->title] = isset($studentResults[$arr['id']][$value->title]) ? $studentResults[$arr['id']][$value->title] : [];
                if(!empty($examNames) && $exam_create==''){
                    $studentMarks[$id]['examData']['Grand Total'] = isset($studentResults[$arr['id']]['Grand Total']) ? $studentResults[$arr['id']]['Grand Total'] : [];
                    $studentMarks[$id]['examData']['Average'] = isset($studentResults[$arr['id']]['Average']) ? $studentResults[$arr['id']]['Average'] : [];
                }
                
                $rank = $this->getRank($standard_id, $division_id, $passing_ratio, $type,$term_id,$value->title);
                // echo "<pre>";print_r($rank);
                $studentMarks[$id]['examData'][$value->title]['rank'] = isset($rank[$arr['id']][$value->title]) ? $rank[$arr['id']][$value->title] : [];

                $studentRank =  isset($rank[$arr['id']][$value->title]['rank']) ? $rank[$arr['id']][$value->title]['rank'] : 0;
                $studentFailed = isset($rank[$arr['id']][$value->title]['failed']) ? $rank[$arr['id']][$value->title]['failed'] : 0;

                $appText = "Good";
                $remarksText = "Can do better";
                if ($studentRank >= 1 && $studentRank <= 3) {
                    $appText = "Excellent";
                    $remarksText = "Keep it up";
                    if ($studentRank == 1) {
                        $remarksText = "Keep it up";
                    } else {
                        $remarksText = "Aim higher";
                    }
                    
                    if (isset($studentFailed) && $studentFailed>0) {
                        if ($studentFailed == 1) {
                            $appText = "Fair";
                            $remarksText = 'Work Hard in Failed Subject';
                        } else if ($studentFailed == 2) {
                            $appText = "Satisfaction";
                            $remarksText = 'Work Hard in Failed Subjects';
                        } else if ($studentFailed >= 3) {
                            $appText = "Not Satisfaction";
                            $remarksText = 'Work Hard in Failed Subjects';
                        }
                    }
                } else if ($studentRank >= 4 && $studentRank <= 10) {
                    $appText = "V. Good";
                    $remarksText = "Aim higher";
                    
                    if (isset($studentFailed) && $studentFailed>0) {
                        if ($studentFailed == 1) {
                            $appText = "Fair";
                            $remarksText = 'Work Hard in Failed Subject';
                        } else if ($studentFailed == 2) {
                            $appText = "Satisfaction";
                            $remarksText = 'Work Hard in Failed Subjects';
                        } else if ($studentFailed >= 3) {
                            $appText = "Not Satisfaction";
                            $remarksText = 'Work Hard in Failed Subjects';
                        }else{
                            $appText = "V. Good";
                            $remarksText = 'Work Hard in Failed Subjects';
                        }
                    }   
                } else {
                    $appText = "Good";
                    $remarksText = "Can do better";
                    
                    if (isset($studentFailed) && $studentFailed>0) {
                        if ($studentFailed == 1) {
                            $appText = "Fair";
                            $remarksText = 'Work Hard in Failed Subject';
                        } else if ($studentFailed == 2) {
                            $appText = "Satisfaction";
                            $remarksText = 'Work Hard in Failed Subjects';
                        } else if ($studentFailed >= 3) {
                            $appText = "Not Satisfaction";
                            $remarksText = 'Work Hard in Failed Subjects';
                        }else{
                            $appText = "Good";
                            $remarksText = 'Work Hard in Failed Subjects';
                        }
                    }   
                }

                $studentMarks[$id]['examData'][$value->title]['applied'] = $appText; // .'//'.$studentRank.'//'.$studentFailed;
                $studentMarks[$id]['examData'][$value->title]['remark'] = $remarksText; 
            }

            $getTermDates = DB::table('academic_year')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'term_id'=>$term_id])->first();
            $att = DB::table('attendance_student')
            ->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'attendance_code'=>'P','student_id'=>$arr['id']])
            ->whereBetween('attendance_date',[$getTermDates->post_start_date,$getTermDates->post_end_date])
            ->count();
            $studentMarks[$id]['attendance'] = $att;
        }
        // echo "<pre>";print_r($studentMarks);
        // exit;

        $examSubject = DB::table('result_create_exam as rce')
        ->join('sub_std_map as s', function ($join) {
            $join->on("s.subject_id", "=", "rce.subject_id")->on("s.standard_id", "=", "rce.standard_id");
        })
        // ->join('result_marks as rm', function ($join) {
        //     $join->on("rm.exam_id","=" ,"rce.id")->on("rm.sub_institute_id", "=", "rce.sub_institute_id");
        // }) // uncomment if only attempted exam to be display
        ->selectRaw('rce.id,rce.title,s.display_name as subject_name,s.id as subject_id')
        ->where("rce.term_id", "=", $term_id)
        ->where("rce.sub_institute_id", "=", $sub_institute_id)
        ->where("rce.syear", "=", $syear)
        ->where("rce.standard_id", "=", $standard_id)
        ->where("rce.report_card_status", "=", 'Y')
        ->when($exam_create!='',function($q) use($exam_create){
            $q->where('rce.title',$exam_create);
        })
        ->groupBy('s.display_name')
        ->orderBy('s.sort_order')
        ->get()->toArray();
        
        // echo "<pre>";print_r($examSubject);exit;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['term_id'] = $term_id;
        $res['exam_type'] = $exam_create;
        $res['examSubject'] = $examSubject;
        $res['studentData'] = $studentMarks;

        return is_mobile($type, "result/result_report/classwise_grade_report_v2", $res, "view");
    }

    public function getCreateExamName(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $standard_id = $request->stdId;
        $term_id = $request->termID;
    
        if($type=="API"){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        // db::enableQueryLog();
        $examName =DB::table('result_create_exam')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'standard_id'=>$standard_id,'term_id'=>$term_id])
        ->when(isset($request->title),function($q) use($request){
            $q->where('title',$request->title);
        })
        ->groupBy('title')->get()->toArray();
        // dd(db::getQueryLog($examName));
        return $examName;
    }

    public function getRank($standard_id,$division_id,$passing_ratio,$type,$term_id,$exam_title='') {
        if ($type == 'API') {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
        } else {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
        }

        $rank_data = DB::table("tblstudent as s")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id");
            })
            ->join('result_marks as rm', function ($join) {
                $join->whereRaw("rm.student_id = s.id AND rm.sub_institute_id = s.sub_institute_id");
            })
            ->join('result_create_exam as rc', function ($join) use ($syear, $term_id) {
                $join->whereRaw("rc.id = rm.exam_id AND rc.sub_institute_id = rm.sub_institute_id
    AND rc.standard_id = se.standard_id AND rc.syear = '" . $syear . "' AND rc.term_id = '" . $term_id . "'");
            })
            ->selectRaw("s.id AS student_id,se.roll_no,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name,
    SUM(IFNULL(rm.points,0)) AS obtainedMarks,SUM(IFNULL(rc.points,0)) AS totalMarks,
    ((SUM(IFNULL(rm.points,0))/ SUM(IFNULL(rc.points,0)))*100) AS percentage,COUNT(if(((IFNULL(rm.points,0)/rc.points)*100)
    < " . $passing_ratio . ",1, NULL)) AS failed")
            ->where("se.syear", "=", $syear)
            ->where("se.standard_id", "=", $standard_id)
            ->where("se.section_id", "=", $division_id)
            ->where("rc.report_card_status", "=", 'Y')
            ->when($exam_title!='',function($q) use($exam_title){
                $q->where("rc.title",$exam_title);
            })
            ->whereNull("se.end_date")
            ->where('s.sub_institute_id', $sub_institute_id);

        $rank_data = $rank_data->groupBy('s.id')
            ->orderByRaw('percentage DESC,se.roll_no ASC')
            ->get()->toarray();

        $rank_data = json_decode(json_encode($rank_data), true);

        $percentageArr = [];
        $i = 1;
        foreach ($rank_data as $key => $val) {
            if (!isset($percentageArr[$val['percentage']])) {
                $percentageArr[$val['percentage']] = $i;
                $i++;
            }
        }
        $rankArr = [];
        if($exam_title!=''){
            foreach ($rank_data as $key => $val) {
                $rankArr[$val['student_id']][$exam_title] = $val;
                $rankArr[$val['student_id']][$exam_title]['rank'] = $percentageArr[$val['percentage']];
                $rankArr[$val['student_id']][$exam_title]['failed'] = $val['failed'];
            }
        }else{
            foreach ($rank_data as $key => $val) {
                $rankArr[$val['student_id']] = $val;
                $rankArr[$val['student_id']]['rank'] = $percentageArr[$val['percentage']];
                $rankArr[$val['student_id']]['failed'] = $val['failed'];
            }
        }

        return $rankArr;
    }

    public function getPer($total_obtained_marks, $total_marks)
    {
        if ($total_marks == 0) {
            return 0; // To avoid division by zero error
        }
        $percentage = ($total_obtained_marks / $total_marks) * 100;
        return number_format($percentage, 2);
    }

}
