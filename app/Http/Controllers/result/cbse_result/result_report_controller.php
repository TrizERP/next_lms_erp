<?php

namespace App\Http\Controllers\result\cbse_result;

use App\Http\Controllers\Controller;
use App\Models\school_setup\sub_std_mapModel;
use DB;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use function App\Helpers\aut_token;


class result_report_controller extends Controller {


    use GetsJwtToken;

    public function index(Request $request) 
    { 
        $sub_institute_id = session()->get('sub_institute_id');
        $exam_master_sql = DB::SELECT("SELECT * FROM result_exam_master r WHERE r.SubInstituteId = '".$sub_institute_id."' ");
        $exam_master_data = json_decode(json_encode($exam_master_sql),true);

        $data['exam_master'] =  $exam_master_data;    
        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/result_report/search", $data, "view");
    }

    public function show_result_report(Request $request)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $term_id = session()->get('term_id');
        $type = $request->input('type');
        $report_of = $request->input('report_of');
        $grade_id = $request->input('grade');
        $standard_id = $request->input('standard');
        $division_id = $request->input('division');
        $subject = $request->input('subject');
        $top_students = $request->input('top_students');
        $roll_no = $request->input('roll_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $exam_type = $request->input('exam_type');

        if ($report_of == 'overall_report') {
            $controller = app(cbse_1t5_result_controller::class);
            $all_student = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
            $responce_arr = [];

            $syear = session()->get('syear');
            $next_year = session()->get('syear') + 1;
            $academicTerms = session()->get('academicTerms');

            $result_year = $syear."-".$next_year;
            session()->put('term_id', $academicTerms[0]->term_id);
            session()->put('standard', $_REQUEST['standard']);
            //getting year detail
            //getting all exam name with mark
            $all_exam = $controller->getAllExam($_REQUEST['standard']);

            //getting all subject name
            $all_subject = $controller->getAllSubject($_REQUEST['standard']);

            //getting all mark
            $all_subject_mark = $controller->getAllMark($all_exam, $all_subject, $all_student);

            //getting Co Scholastic        
            $all_co_data = $controller->getCoArea($all_student);

            //getting attendance
            $all_att_data = $controller->getAttendance($all_student);

            //getting scholastic grade range
            $all_grd_data = $controller->getGradeRange();

            //getting currunt term name
            $term_name = $controller->getTermName();

            //getting result header
            $header_data = $controller->getHeader($_REQUEST['standard']);

            //get exam master settigs
            $footer_data = $controller->getExamMasterSettigs($_REQUEST['standard']);

            //getting all student detail

            foreach ($all_student as $id => $arr) {
                $cur_student_id = $arr['student_id'];
                $responce_arr[$cur_student_id]['year'] = $result_year;
                $responce_arr[$cur_student_id]['term'] = $term_name;
                $responce_arr[$cur_student_id]['total_mark'] = $all_exam[count($all_exam) - 1]['mark'];
                $responce_arr[$cur_student_id]['name'] = $arr['first_name']." ".$arr['middle_name']." ".$arr['last_name'];
                $responce_arr[$cur_student_id]['roll_no'] = $arr['roll_no'];
                $responce_arr[$cur_student_id]['mother_name'] = $arr['mother_name'];
                $responce_arr[$cur_student_id]['class'] = $arr['standard_name'];
                $responce_arr[$cur_student_id]['father_name'] = $arr['father_name'];
                $responce_arr[$cur_student_id]['division'] = $arr['division_name'];
                $responce_arr[$cur_student_id]['date_of_birth'] = date("d-m-Y", strtotime($arr['dob']));
                $responce_arr[$cur_student_id]['gr_no'] = $arr['enrollment_no'];
                $responce_arr[$cur_student_id]['exam'] = $all_exam;
                $responce_arr[$cur_student_id]['mark'] = $all_subject_mark[$cur_student_id];
                $responce_arr[$cur_student_id]['per'] = $controller->getPer($responce_arr[$cur_student_id]['total_mark'],
                    $all_subject_mark[$cur_student_id]);
                $responce_arr[$cur_student_id]['final_grade'] = $controller->getFinalGrade($responce_arr[$cur_student_id]['per']);
                if (isset($all_co_data[$cur_student_id])) {
                    $responce_arr[$cur_student_id]['co_scholastic_area'] = $all_co_data[$cur_student_id];
                }
                $responce_arr[$cur_student_id]['att'] = '';
                if (isset($all_att_data[$cur_student_id])) {
                    $responce_arr[$cur_student_id]['att'] = $all_att_data[$cur_student_id];
                }
                $responce_arr[$cur_student_id]['grade_range'] = $all_grd_data;
            }

            session()->put('term_id', $academicTerms[1]->term_id);
            //getting year detail
            //getting all exam name with mark
            $all_exam = $controller->getAllExam($_REQUEST['standard']);

            //getting all subject name
            $all_subject = $controller->getAllSubject($_REQUEST['standard']);

            //getting all mark
            $all_subject_mark = $controller->getAllMark($all_exam, $all_subject, $all_student);

            //getting Co Scholastic        
            $all_co_data = $controller->getCoArea($all_student);

            //getting attendance
            $all_att_data = $controller->getAttendance($all_student);

            //getting scholastic grade range
            $all_grd_data = $controller->getGradeRange();

            //getting currunt term name
            $term_name = $controller->getTermName();

            //getting result header
            $header_data = $controller->getHeader($_REQUEST['standard']);

            //get exam master settigs
            $footer_data = $controller->getExamMasterSettigs($_REQUEST['standard']);

            $responce_arr_term2 = [];
            foreach ($all_student as $id => $arr) {
                $cur_student_id = $arr['student_id'];
                $responce_arr_term2[$cur_student_id]['year'] = $result_year;
                $responce_arr_term2[$cur_student_id]['term'] = $term_name;
                $responce_arr_term2[$cur_student_id]['total_mark'] = $all_exam[count($all_exam) - 1]['mark'];
                $responce_arr_term2[$cur_student_id]['name'] = $arr['first_name']." ".$arr['middle_name']." ".$arr['last_name'];
                $responce_arr_term2[$cur_student_id]['roll_no'] = $arr['roll_no'];
                $responce_arr_term2[$cur_student_id]['mother_name'] = $arr['mother_name'];
                $responce_arr_term2[$cur_student_id]['class'] = $arr['standard_name'];
                $responce_arr_term2[$cur_student_id]['father_name'] = $arr['father_name'];
                $responce_arr_term2[$cur_student_id]['division'] = $arr['division_name'];
                $responce_arr_term2[$cur_student_id]['date_of_birth'] = date("d-m-Y", strtotime($arr['dob']));
                $responce_arr_term2[$cur_student_id]['gr_no'] = $arr['enrollment_no'];
                $responce_arr_term2[$cur_student_id]['exam'] = $all_exam;
                $responce_arr_term2[$cur_student_id]['mark'] = $all_subject_mark[$cur_student_id];
                $responce_arr_term2[$cur_student_id]['per'] = $controller->getPer($responce_arr_term2[$cur_student_id]['total_mark'],
                    $all_subject_mark[$cur_student_id]);
                $responce_arr_term2[$cur_student_id]['final_grade'] = $controller->getFinalGrade($responce_arr_term2[$cur_student_id]['per']);
                if (isset($all_co_data[$cur_student_id])) {
                    $responce_arr_term2[$cur_student_id]['co_scholastic_area'] = $all_co_data[$cur_student_id];
                }
                $responce_arr_term2[$cur_student_id]['att'] = '';
                if (isset($all_att_data[$cur_student_id])) {
                    $responce_arr_term2[$cur_student_id]['att'] = $all_att_data[$cur_student_id];
                }
                $responce_arr_term2[$cur_student_id]['grade_range'] = $all_grd_data;
            }

            $data['data'] = $responce_arr;
            $data['term_2_data'] = $responce_arr_term2;
            $data['header_data'] = $header_data;
            $data['footer_data'] = $footer_data;
            $data['standard_id'] = $_REQUEST['standard'];
            $data['grade_id'] = $_REQUEST['grade'];
            $data['division_id'] = $_REQUEST['division'];
            $data['syear'] = session()->get('syear');
            $data['term_id'] = session()->get('term_id');


            session()->put('over_all_data', $data);

            return \App\Helpers\is_mobile($type, "result/result_report/overall_report_show", $data, "view");
        }

        if ($report_of == 'merit_report') {
            $rank = $this->getRank($standard_id, $division_id, $passing_ratio = 35, $type, $top_students, $from_date,
                $to_date);

            $data['students_data'] = $rank;
            $data['grade_id'] = $grade_id;
            $data['standard_id'] = $standard_id;
            $data['division_id'] = $division_id;

            return \App\Helpers\is_mobile($type, "result/result_report/merit_report_show", $data, "view");
        }

        if($report_of == 'subject_progress_report')
        {   
            $all_student = \App\Helpers\SearchStudent($grade_id,$standard_id,$division_id,$sub_institute_id,$syear,$roll_no);
            $students_data = array();
            foreach ($all_student as $key => $value) 
            {
                $students_data[$value['id']] = $value;
            }

            //getting all exam marks        
            $all_WRT_data = $this->getWRTData($all_student,$standard_id,$subject,$type,$exam_type,$from_date, $to_date); 

            $student_id_arr = array();
            foreach ($all_student as $id => $arr) 
            {
                $student_id_arr[] = $arr['student_id'];
            }
            $student_id = implode(',', $student_id_arr);

            $extra = '';
            if($exam_type != '')
            {
                $extra .= " AND e.exam_id = '".$exam_type."' ";
            }

            if($from_date != '' && $to_date != '')
            {
                $extra .= " AND DATE_FORMAT(e.exam_date, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
            }

            $str = "SELECT e.title as ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),e.points,e.con_point) AS total_points, 
                e.subject_id,s.display_name as subject_name,date_format(e.exam_date,'%d-%m-%Y') as exam_date,dayname(e.exam_date) as exam_day,rm.student_id,rm.points as obtained_points
                FROM result_create_exam e        
                INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
                LEFT JOIN result_marks rm on rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
                WHERE e.term_id = '".$term_id."' AND e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
                AND e.standard_id = '".$standard_id."' AND e.subject_id = '".$subject."' AND student_id in (".$student_id.") $extra
                ORDER BY e.title";                
        
            $result = DB::select(DB::raw($str));
            $result = json_decode(json_encode($result),true);
            $date_arr = array();

            foreach ($result as $id => $arr) 
            {
                $date_arr[$arr['exam_date'].'/'.$arr['ExamTitle']] = $arr['exam_date'].'('.$arr['total_points'].')';  
            }


            
            $data['grade_id'] = $grade_id;
            $data['standard_id'] = $standard_id;
            $data['division_id'] = $division_id;
            $data['date_arr'] = $date_arr;
            $data['WRT_data'] = $all_WRT_data;
            $data['all_student'] = $students_data;

//echo "<pre>";
//print_r($data['WRT_data']);
//die();
           
            return \App\Helpers\is_mobile($type, "result/result_report/subject_progress_report_show", $data, "view");
        }

        if($report_of == 'classwise_report')
        {   
            $all_student = \App\Helpers\SearchStudent($grade_id,$standard_id,$division_id,$sub_institute_id,$syear,$roll_no);
            $students_data = array();
            foreach ($all_student as $key => $value) 
            {
                $students_data[$value['id']] = $value;
            }

            //getting all exam marks        
            $all_WRT_data = $this->getClasswise($all_student,$standard_id,$subject,$type,$exam_type,$from_date, $to_date); 

            $student_id_arr = array();
            foreach ($all_student as $id => $arr) 
            {
                $student_id_arr[] = $arr['student_id'];
            }
            $student_id = implode(',', $student_id_arr);

            $extra = '';
            if($exam_type != '')
            {
                $extra .= " AND e.exam_id = '".$exam_type."' ";
            }

            if($from_date != '' && $to_date != '')
            {
                $extra .= " AND DATE_FORMAT(e.exam_date, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
            }

            /*$str = "SELECT e.title as ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),e.points,e.con_point) AS total_points, 
                e.subject_id,s.display_name as subject_name,date_format(e.exam_date,'%d-%m-%Y') as exam_date,dayname(e.exam_date) as exam_day,rm.student_id,rm.points as obtained_points
                FROM result_create_exam e        
                INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
                LEFT JOIN result_marks rm on rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
                WHERE e.term_id = '".$term_id."' AND e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
                AND e.standard_id = '".$standard_id."' AND e.subject_id = '".$subject."' AND student_id in (".$student_id.") $extra
                ORDER BY e.title";*/


            $str = "SELECT e.title as ExamTitle, SUM(e.points) AS total_points,
                e.subject_id,s.display_name as subject_name,rm.student_id,SUM(rm.points) as obtained_points
                FROM result_create_exam e
                INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
                LEFT JOIN result_marks rm on rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
                WHERE e.term_id = '".$term_id."' AND e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
                AND e.standard_id = '".$standard_id."' AND student_id in (".$student_id.") $extra
                GROUP BY rm.student_id,e.subject_id
                ORDER BY e.sort_order";//AND e.subject_id = '".$subject."' 

            //echo "<pre>".$str;die();
        
            $result = DB::select(DB::raw($str));
            $result = json_decode(json_encode($result),true);
            $date_arr = array();

            foreach ($result as $id => $arr) 
            {
                $date_arr[$arr['subject_name']] = $arr['subject_name'].'('.$arr['total_points'].')';
            }

            $data['grade_id'] = $grade_id;
            $data['standard_id'] = $standard_id;
            $data['division_id'] = $division_id;
            $data['date_arr'] = $date_arr;
            $data['WRT_data'] = $all_WRT_data;
            $data['all_student'] = $students_data;

            return \App\Helpers\is_mobile($type, "result/result_report/classwise_report_show", $data, "view");
        }

    }

    public function downloadOverAllReportExcel()
    {
        $data = session()->get('over_all_data');

        $html = view('result/result_report/overall_report_excel', compact('data'))->render();

        header("Content-type: application/excel");
        header("Content-Disposition: attachment; filename=OverallReport.xls");
        echo $html;
        exit;
    }

    public function getClasswise(
        $all_student,
        $standard_id,
        $subject,
        $type,
        $exam_type = null,
        $from_date = null,
        $to_date = null
    ) {
        if ($type == 'API') {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
            $standard_id = $all_student[0]['standard_id'];
            $division_id = $all_student[0]['division_id'];
        }else{
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
            $standard_id = request()->input('standard');
            $division_id = request()->input('division');
        }

        $student_id_arr = array();
        foreach ($all_student as $id => $arr) 
        {
            $student_id_arr[] = $arr['student_id'];
        }
        $student_id = implode(',', $student_id_arr);

        $extra = '';
        if($exam_type != '')
        {
            $extra .= " AND e.exam_id = '".$exam_type."' ";
        }

        if($from_date != '' && $to_date != '')
        {
            $extra .= " AND DATE_FORMAT(e.exam_date, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
        }

        $str = "SELECT e.title AS ExamTitle, sum(e.points) AS total_points, e.subject_id,s.display_name AS subject_name,rm.student_id,round(SUM(rm.points),0) AS obtained_points,rm.is_absent
                FROM result_create_exam e
                INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
                LEFT JOIN result_marks rm ON rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
                WHERE e.term_id = '".$term_id."' AND e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
                AND e.standard_id = '".$standard_id."' AND student_id in (".$student_id.") $extra
                GROUP BY rm.student_id,e.subject_id
                ORDER BY s.sort_order";//AND e.subject_id = '".$subject."' 

       // echo "<pre>".$str;die();
       //print_r($responce_arr);
       //print_r($all_student);
       // exit;
        
        $result = DB::select(DB::raw($str));
        $result = json_decode(json_encode($result),true);
        $cbse_1t5_result_controller = new cbse_1t5_result_controller;
        $grade_arr = $cbse_1t5_result_controller->getGradeScale($standard_id,$type);

        // getting data and making readable format student wise
        $marks_arr = array();
        
        $rank = $this->getRank($standard_id, $division_id, $passing_ratio=35,$type);
        
        foreach ($result as $id => $arr) 
        {
            $grade_scale = $cbse_1t5_result_controller->getGrade($grade_arr, $arr['total_points'], $arr['obtained_points']);
            
            $per = ( ($arr['obtained_points'] * 100) / $arr['total_points']);
            $per = number_format($per,2);
            $arr['percentage'] = $per;
            $arr['grade'] = $grade_scale;
            $marks_arr[$arr['student_id']][$arr['subject_name']] = $arr;      
            $marks_arr[$arr['student_id']][$arr['subject_name']]['student_name'] = $rank[$arr['student_id']]['student_name'];
            $marks_arr[$arr['student_id']][$arr['subject_name']]['roll_no'] = $rank[$arr['student_id']]['roll_no'];      
        }
        $marks_arr['total_student'] = count($marks_arr);
        //echo "<pre>";
        //print_r($marks_arr);
        //die();        
        return $marks_arr;
    }

    public function getWRTData($all_student,$standard_id,$subject,$type,$exam_type=null,$from_date=null,$to_date=null)
    {
        if($type == 'API')
        {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
            $standard_id = $all_student[0]['standard_id'];
            $division_id = $all_student[0]['division_id'];
        }else{
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
            $standard_id = request()->input('standard');
            $division_id = request()->input('division');
        }

        $student_id_arr = array();
        foreach ($all_student as $id => $arr) 
        {
            $student_id_arr[] = $arr['student_id'];
        }
        $student_id = implode(',', $student_id_arr);

        $extra = '';
        if($exam_type != '')
        {
            $extra .= " AND e.exam_id = '".$exam_type."' ";
        }

        if($from_date != '' && $to_date != '')
        {
            $extra .= " AND DATE_FORMAT(e.exam_date, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
        }

        $str = "SELECT e.title as ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),e.points,e.con_point) AS total_points, 
                e.subject_id,s.display_name as subject_name,date_format(e.exam_date,'%d-%m-%Y') as exam_date,dayname(e.exam_date) as exam_day,rm.student_id,rm.points as obtained_points,rm.is_absent
                FROM result_create_exam e        
                INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
                LEFT JOIN result_marks rm on rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
                WHERE e.term_id = '".$term_id."' AND e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
                AND e.standard_id = '".$standard_id."' AND e.subject_id = '".$subject."' AND student_id in (".$student_id.") $extra
                ORDER BY e.title";      

//                echo "<pre>".$str;die();
       // print_r($responce_arr);
       // print_r($all_student);
       // exit;          
        
        $result = DB::select(DB::raw($str));
        $result = json_decode(json_encode($result),true);
        $cbse_1t5_result_controller = new cbse_1t5_result_controller;
        $grade_arr = $cbse_1t5_result_controller->getGradeScale($standard_id,$type);

        // getting data and making readable format student wise
        $marks_arr = array();
        
        $rank = $this->getRank($standard_id, $division_id, $passing_ratio=35,$type);
        
        foreach ($result as $id => $arr) 
        {
            $grade_scale = $cbse_1t5_result_controller->getGrade($grade_arr, $arr['total_points'], $arr['obtained_points']);
            
            $per = ( ($arr['obtained_points'] * 100) / $arr['total_points']);
            $per = number_format($per,2);
            $arr['percentage'] = $per;
            $arr['grade'] = $grade_scale;
            $marks_arr[$arr['student_id']][$arr['exam_date'].'/'.$arr['ExamTitle']] = $arr;      
            $marks_arr[$arr['student_id']][$arr['exam_date'].'/'.$arr['ExamTitle']]['student_name'] = $rank[$arr['student_id']]['student_name'];
            $marks_arr[$arr['student_id']][$arr['exam_date'].'/'.$arr['ExamTitle']]['roll_no'] = $rank[$arr['student_id']]['roll_no'];      
        }
        $marks_arr['total_student'] = count($marks_arr);
        return $marks_arr;
    }

    public static function getRank($standard_id,$division_id,$passing_ratio,$type,$top_students=null,$from_date=null,$to_date=null)
    {   
        if($type == 'API')
        {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
        }else{
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
        }

        $limit = $extra = '';
        if(isset($top_students) && $top_students != '')
        {
            $limit = ' LIMIT '.$top_students;
        }

        if(isset($from_date) && $from_date != '' && isset($to_date) && $to_date != '' )
        {
            $extra = " AND DATE_FORMAT(rc.exam_date, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
        }    
        $sql = "SELECT s.id AS student_id,s.roll_no,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name,
                SUM(IFNULL(rm.points,0)) AS obtainedMarks,
                SUM(IFNULL(rc.points,0)) AS totalMarks, 
                ((SUM(IFNULL(rm.points,0))/ SUM(IFNULL(rc.points,0)))*100) AS percentage,
                COUNT(if(((IFNULL(rm.points,0)/rc.points)*100) < " . $passing_ratio . ",1, NULL)) AS failed
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id
                INNER JOIN result_marks rm ON rm.student_id = s.id AND rm.sub_institute_id = s.sub_institute_id
                INNER JOIN result_create_exam rc ON rc.id = rm.exam_id AND rc.sub_institute_id = rm.sub_institute_id AND rc.standard_id = se.standard_id AND rc.syear = '".$syear."' AND rc.term_id = '".$term_id."'
                WHERE se.syear = '".$syear."' AND se.standard_id = '".$standard_id."' AND se.section_id = '".$division_id."' AND se.end_date IS NULL AND s.sub_institute_id = '".$sub_institute_id."' $extra
                GROUP BY s.id
                ORDER BY percentage DESC,s.roll_no ASC
                ".$limit." ";
        $rank_data = DB::select(DB::raw($sql));                        
        $rank_data = json_decode(json_encode($rank_data),true);

        $percentageArr = array();
        $i = 1;
        foreach ($rank_data as $key => $val)
        {
            if (!isset($percentageArr[$val['percentage']])) { 
                $percentageArr[$val['percentage']] = $i;
                $i++;
            }
        }
        $rankArr = array();
        foreach ($rank_data as $key => $val)
        {
            $rankArr[$val['student_id']] = $val;
            $rankArr[$val['student_id']]['rank'] = $percentageArr[$val['percentage']];
        }
        return $rankArr;
    }

    public function ajax_StandardwiseSubject(Request $request)
    {        
        $std_id = $request->input("std_id");        
        $sub_institute_id = session()->get("sub_institute_id");

        $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id,'standard_id' => $std_id])
                ->orderBy('display_name')->get()->toArray();
    
        return $stdData;    
    }

}
