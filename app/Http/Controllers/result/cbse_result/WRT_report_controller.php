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

class WRT_report_controller extends Controller {


    use GetsJwtToken;

    public function index(Request $request) {      
        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/WRT_report/search", $data, "view");
    }

    public function show_result(Request $request)
    {

        // echo '<pre>';
        // print_r($_REQUEST);
        // die;
        $type = $request->input('type');
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
        $all_WRT_data = $this->getWRTData($all_student,$_REQUEST['standard'],$type,$_REQUEST['from_date'],$_REQUEST['to_date']);      

        //getting result header        
        $header_data = $this->getHeader($_REQUEST['standard'],$type);

        $data['WRT_data'] = $all_WRT_data;
        $data['WRT_exam_master'] = $all_exam_master;
        
        // echo '<pre>';
        // print_r($data);
        // die;

        $data['all_student'] = $students_data;
        $data['result_year'] = $result_year;
        $data['header_data'] = $header_data;                
        $data['standard_id'] = $_REQUEST['standard'];
        $data['grade_id'] = $_REQUEST['grade'];
        $data['division_id'] = $_REQUEST['division'];
        $data['syear'] = session()->get('syear');
        $data['term_id'] = session()->get('term_id');

        return \App\Helpers\is_mobile($type, "result/WRT_report/WRT_show", $data, "view");

        // echo '<pre>';
        // print_r($all_exam_master);
        // print_r($all_WRT_data);
        // die;
       
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

    public function getWRTData($all_student,$standard_id,$type,$from_date=null,$to_date=null)
    {
        if($type == 'API')
        {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
            $from_date = $_REQUEST['from_date'];
            $to_date = $_REQUEST['to_date'];
        }else{
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
        }

        $student_id_arr = array();
        foreach ($all_student as $id => $arr) 
        {
            $student_id_arr[] = $arr['student_id'];
        }
        $student_id = implode(',', $student_id_arr);

        // $str = "SELECT em.ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),e.points,e.con_point) AS total_points, 
        //         e.subject_id,s.display_name as subject_name,e.exam_date,dayname(e.exam_date) as exam_day,rm.student_id,rm.points as obtained_points
        //         FROM result_create_exam e
        //         INNER JOIN result_exam_master em ON em.Id = e.exam_id
        //         INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
        //         left join result_marks rm on rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
        //         WHERE e.term_id = '".$term_id."' AND e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
        //         AND e.standard_id = '".$standard_id."' AND student_id in (".$student_id.")
        //         order by em.ExamTitle";
        $str = "SELECT e.title as ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),e.points,e.con_point) AS total_points, 
                e.subject_id,s.display_name as subject_name,date_format(e.exam_date,'%d-%m-%Y') as exam_date,dayname(e.exam_date) as exam_day,rm.student_id,rm.points as obtained_points,rm.is_absent
                FROM result_create_exam e        
                INNER JOIN sub_std_map s ON s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id
                LEFT JOIN result_marks rm on rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id
                WHERE e.term_id = '".$term_id."' AND e.sub_institute_id = '".$sub_institute_id."' AND e.syear = '".$syear."' 
                AND e.standard_id = '".$standard_id."' AND student_id in (".$student_id.")
                AND e.exam_date BETWEEN '".$from_date."' AND '".$to_date."'
                ORDER BY e.title";  

        // echo '<pre>';
        // print_r($str);
        // die;              
        
        $result = DB::select(DB::raw($str));
        $result = json_decode(json_encode($result),true);

        // getting data and making readable format student wise
        $marks_arr = array();
        
        foreach ($result as $id => $arr) 
        {
            $per = ( ($arr['obtained_points'] * 100) / $arr['total_points']);
            $per = number_format($per,2);
            $arr['percentage'] = $per;
            $marks_arr[$arr['student_id']][$arr['ExamTitle']][] = $arr;             
        }
      
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
        $sql = "SELECT *,title as ExamTitle FROM result_create_exam r WHERE r.sub_institute_id = '".$sub_institute_id."' AND standard_id = '".$standard_id."' 
                AND term_id = '".$term_id."' AND syear = '".$syear."'
                AND r.exam_date BETWEEN '".$from_date."' AND '".$to_date."'
                GROUP BY title";
        
        $result = DB::select($sql);
        $result = json_decode(json_encode($result),true);

        return $result;
    }

}
