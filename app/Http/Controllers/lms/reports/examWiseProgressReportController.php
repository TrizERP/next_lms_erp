<?php

namespace App\Http\Controllers\lms\reports;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use function App\Helpers\SearchStudent;
use function App\Helpers\getStudents;
use App\Models\lms\questionpaperModel;
use App\Models\school_setup\sub_std_mapModel;

class examWiseProgressReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){        
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS"; 
        $res['subject_data'] = array();       
        $res['exams_data'] = array();       
                
        return is_mobile($type,'lms/reports/show_examwise_progress_report',$res,"view");  
    }

    public function create(Request $request)
    {          
        // dd($request);       
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $subject = $request->input('subject');
        $exams = $request->input('exam_id');
        $type = $request->input('type');
        
        if($type == "API")
        {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        }else{
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $student_data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);       

        $examData = questionpaperModel::where(['sub_institute_id' => $sub_institute_id,'standard_id' => $standard,'subject_id' => $subject])
                    ->whereIn('id', $exams)
                    ->orderby('id')
                    ->get(); 

        $exam_ids = implode(',',$exams);

        $marks_array = $grade_array = array();
        $data = DB::SELECT("SELECT s.id,s.enrollment_no,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
                            st.name AS std_name,d.name AS div_name,se.standard_id,se.grade_id,qp.id AS question_paper_id,qp.paper_name,
                            qp.total_marks,ifnull(le.obtain_marks,'-') AS obtain_marks
                            FROM tblstudent s
                            INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id AND se.syear = '".$syear."' AND se.end_date IS NULL
                            INNER JOIN academic_section ac ON ac.id = se.grade_id AND ac.sub_institute_id = se.sub_institute_id
                            INNER JOIN standard st ON st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id
                            LEFT JOIN division d ON d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id
                            INNER JOIN question_paper qp ON qp.standard_id = se.standard_id AND qp.grade_id = se.grade_id AND qp.sub_institute_id = s.sub_institute_id
                            LEFT JOIN lms_online_exam le ON le.question_paper_id = qp.id AND le.student_id = s.id
                            WHERE s.sub_institute_id = '".$sub_institute_id."' AND se.grade_id = '".$grade."' AND se.standard_id = '".$standard."' AND qp.id IN (".$exam_ids.")
                            GROUP BY s.id,qp.id");            
        $data = json_decode(json_encode($data),true);
        foreach ($data as $k => $v) 
        {
            $marks_array[$v['id']][$v['question_paper_id']] = $v['obtain_marks'];
        }

        $grade_data = DB::SELECT("SELECT gm.title,gm.breakoff
                                FROM result_std_grd_maping rgm
                                INNER JOIN grade_master_data gm ON gm.grade_id = rgm.grade_scale AND gm.sub_institute_id = rgm.sub_institute_id
                                WHERE rgm.standard = '".$standard."' AND rgm.sub_institute_id = '".$sub_institute_id."' ");
        $grade_data = json_decode(json_encode($grade_data),true);
        // foreach ($grade_data as $k1 => $v1) 
        // {
        //     $grade_array[$v1['title']] = $v1['breakoff'];
        // }

        $subject_data = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id,'standard_id' => $standard])
        ->orderBy('display_name')->get()->toArray(); 

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $student_data;        
        $res['marks_data'] = $marks_array;        
        $res['grade_data'] = $grade_data;        
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['subject_id'] = $subject;
        $res['exam_id'] = $exams;
        $res['exams_data'] = $examData;
        $res['subject_data'] = $subject_data;

        return is_mobile($type, "lms/reports/show_examwise_progress_report", $res, "view");
    }  

    public function ajax_LMS_SubjectWiseExam(Request $request)
    { 
        $std_id = $request->input("std_id");        
        $sub_id = $request->input("sub_id");        
        $sub_institute_id = session()->get("sub_institute_id");
        
        $examData = questionpaperModel::where(['sub_institute_id' => $sub_institute_id,'standard_id' => $std_id,'subject_id' => $sub_id])
        ->get()->toArray();
        return $examData;    
    }

}
