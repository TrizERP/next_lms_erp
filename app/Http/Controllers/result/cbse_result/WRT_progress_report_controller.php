<?php

namespace App\Http\Controllers\result\cbse_result;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\htmlToPDF;
use function App\Helpers\htmlToPDFLandscape;
use App\Http\Controllers\result\cbse_result\cbse_1t5_result_controller;

class WRT_progress_report_controller extends Controller {


    use GetsJwtToken;

    public function index(Request $request) 
    { 
        $sub_institute_id = session()->get('sub_institute_id');
        $exam_master_sql = DB::SELECT("SELECT * FROM result_exam_master r WHERE r.SubInstituteId = '".$sub_institute_id."' ");
        $exam_master_data = json_decode(json_encode($exam_master_sql),true);

        $data['exam_master'] =  $exam_master_data;    
        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/WRT_progress_report/search", $data, "view");
    }

    public function show_result(Request $request) {

        $type = $request->input('type');
        $exam_type = $request->input('exam_type');
        $all_student = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);        

        $students_data = array();
        foreach ($all_student as $key => $value) 
        {
            $students_data[$value['id']] = $value;
        }

        $syear = session()->get('syear');
        $next_year = session()->get('syear') + 1;
        $result_year = $syear . "-" . $next_year;

        //getting all exam master heading        
        $all_exam_master = $this->getAllExamMaster($_REQUEST['standard'],$_REQUEST['from_date'],$_REQUEST['to_date'],$type);

        //getting all exam marks        
        $all_WRT_data = $this->getWRTData($all_student,$_REQUEST['standard'],$type,$exam_type,$_REQUEST['from_date'],$_REQUEST['to_date']);      

        //getting result header        
        $header_data = $this->getHeader($_REQUEST['standard'],$type);

        $data['WRT_data'] = $all_WRT_data;
        $data['WRT_exam_master'] = $all_exam_master;
        
        // echo '<pre>';
        // print_r($students_data);
        // die;

        $data['all_student'] = $students_data;
        $data['result_year'] = $result_year;
        $data['header_data'] = $header_data;                
        $data['standard_id'] = $_REQUEST['standard'];
        $data['grade_id'] = $_REQUEST['grade'];
        $data['division_id'] = $_REQUEST['division'];
        $data['syear'] = session()->get('syear');
        $data['term_id'] = session()->get('term_id');
       
        return \App\Helpers\is_mobile($type, "result/WRT_progress_report/WRT_show", $data, "view");
    }

    public function getHeader($standard_id,$type)
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

        $str = "SELECT * from result_book_master b
                INNER JOIN result_trust_master t on b.trust_id = t.id
                WHERE b.standard = '".$standard_id."' AND b.sub_institute_id = '".$sub_institute_id."'
                LIMIT 1";
        $result = DB::select(DB::raw($str));
        $result = json_decode(json_encode($result),true);

        return $result[0];        
    }       

    public function getWRTData($all_student,$standard_id,$type,$exam_type=null,$from_date=null,$to_date=null)
    {
        if($type == 'API')
        {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
            $standard_id = $all_student[0]['standard_id'];
            $division_id = $all_student[0]['division_id'];
            $from_date = $_REQUEST['from_date'];
            $to_date = $_REQUEST['to_date'];
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
            $extra = " AND e.exam_id = '".$exam_type."' ";
        }

        $str = "SELECT e.title as ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),e.points,e.con_point) AS total_points, 
                e.subject_id,s.display_name as subject_name,date_format(e.exam_date,'%d-%m-%Y') as exam_date,dayname(e.exam_date) as exam_day,rm.student_id,rm.points as obtained_points,rm.is_absent
                FROM result_create_exam e        
                INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
                LEFT JOIN result_marks rm on rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
                WHERE e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
                AND e.standard_id = '".$standard_id."' AND student_id in (".$student_id.") $extra
                AND e.exam_date BETWEEN '".$from_date."' AND '".$to_date."'
                ORDER BY e.exam_date ASC";//e.term_id = '".$term_id."' AND 

                                
        // echo '<pre>';
        // print_r($str);
        // die; 
        $result = DB::select(DB::raw($str));
        $result = json_decode(json_encode($result),true);
        $cbse_1t5_result_controller = new cbse_1t5_result_controller;
        $grade_arr = $cbse_1t5_result_controller->getGradeScale($standard_id,$type);

        // getting data and making readable format student wise
        $marks_arr = array();
        
        $rank = $this->getRank($standard_id, $division_id, $passing_ratio=35,$type,$from_date,$to_date);
        foreach ($result as $id => $arr) 
        {
            $grade_scale = $cbse_1t5_result_controller->getGrade($grade_arr, $arr['total_points'], $arr['obtained_points']);
            
            $per = ( ($arr['obtained_points'] * 100) / $arr['total_points']);
            $per = number_format($per,2);
            $arr['percentage'] = $per;
            $arr['grade'] = $grade_scale;
            $arr['rank'] = $rank[$arr['student_id']];
            $marks_arr[$arr['student_id']][] = $arr;             
        }
        $marks_arr['total_student'] = count($marks_arr);
        return $marks_arr;
    }

    public function getAllExamMaster($standard_id,$from_date,$to_date,$type)
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

        // $sql = "SELECT * FROM result_exam_master r WHERE r.SubInstituteId = '".$sub_institute_id."'";
        $sql = "SELECT *,title as ExamTitle 
                FROM result_create_exam r 
                WHERE r.sub_institute_id = '".$sub_institute_id."' AND standard_id = '".$standard_id."' 
                AND syear = '".$syear."'
                AND r.exam_date BETWEEN '".$from_date."' AND '".$to_date."'
                GROUP BY title";//AND term_id = '".$term_id."' 
        
        $result = DB::select($sql);
        $result = json_decode(json_encode($result),true);

        return $result;
    }

    public static function getRank($standard_id,$division_id,$passing_ratio,$type,$from_date=null,$to_date=null)
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

        if(isset($from_date) && $from_date != '' && isset($to_date) && $to_date != '' )
            $extra = " AND DATE_FORMAT(rc.exam_date, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";

       $sql = "SELECT s.id AS student_id,
                        SUM(IFNULL(rm.points,0)) AS obtainedMarks,
                        SUM(IFNULL(rc.points,0)) AS totalMarks, 
                        ((SUM(IFNULL(rm.points,0))/ SUM(IFNULL(rc.points,0)))*100) AS percentage,
                        COUNT(if(((IFNULL(rm.points,0)/rc.points)*100) < " . $passing_ratio . ",1, NULL)) AS failed
                        FROM tblstudent s
                        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id
                        INNER JOIN result_marks rm ON rm.student_id = s.id AND rm.sub_institute_id = s.sub_institute_id
                        INNER JOIN result_create_exam rc ON rc.id = rm.exam_id AND rc.sub_institute_id = rm.sub_institute_id AND rc.standard_id = se.standard_id AND rc.syear = '".$syear."'
                        WHERE se.syear = '".$syear."' AND se.standard_id = '".$standard_id."' AND se.section_id = '".$division_id."' AND se.end_date IS NULL AND s.sub_institute_id = '".$sub_institute_id."' $extra
                        GROUP BY s.id
                        ORDER BY percentage DESC,s.roll_no ASC";// AND rc.term_id = '".$term_id."'
        //echo $sql;die();                        
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
            $rankArr[$val['student_id']] = $percentageArr[$val['percentage']];
        }
        return $rankArr;
    }

}
