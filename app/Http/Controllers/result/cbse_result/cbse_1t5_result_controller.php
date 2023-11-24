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
use App\Models\result\result_html_model;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use function App\Helpers\FeeBreackoff;

class cbse_1t5_result_controller extends Controller {


    use GetsJwtToken;

    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
//        $data['data'] = $this->getData();
        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/cbse_result/search", $data, "view");
    }

    public function show_result(Request $request) 
    {

        $all_student = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $responce_arr = [];
//echo "<pre>";
//print_r($all_student);
//exit;
        $syear = session()->get('syear');
        $next_year = session()->get('syear') + 1;
        $academicTerms = session()->get('academicTerms');

        $result_year = $syear . "-" . $next_year;
        if(session()->get('sub_institute_id')!=72){
        session()->put('term_id', $academicTerms[0]->term_id);
        session()->put('standard', $_REQUEST['standard']);
    }else{
         session()->put('standard', $_REQUEST['standard']);
    }
        //getting year detail
        //getting all exam name with mark
        $all_exam = $this->getAllExam($_REQUEST['standard']);

        //getting all subject name
        $all_subject = $this->getAllSubject($_REQUEST['standard']);

        //getting all mark
        $all_subject_mark = $this->getAllMark($all_exam, $all_subject, $all_student);

        //getting Co Scholastic        
        $all_co_data = $this->getCoArea($all_student);

        //getting attendance
        $all_att_data = $this->getAttendance($all_student);

        //getting scholastic grade range
        $all_grd_data = $this->getGradeRange();        

        //getting currunt term name
        $term_name = $this->getTermName();

        //getting result header
        $header_data = $this->getHeader($_REQUEST['standard']);

        //get exam master settigs
        $footer_data = $this->getExamMasterSettigs($_REQUEST['standard']);      

        //getting all student detail
       
        foreach ($all_student as $id => $arr) {
            $cur_student_id = $arr['student_id'];             
            $responce_arr[$cur_student_id]['year'] = $result_year;
            $responce_arr[$cur_student_id]['term'] = $term_name;
            $responce_arr[$cur_student_id]['total_mark'] = $all_exam[count($all_exam) - 1]['mark'];
            $responce_arr[$cur_student_id]['name'] = $arr['first_name'] . " " . $arr['middle_name'] . " " . $arr['last_name'];
            $responce_arr[$cur_student_id]['roll_no'] = $arr['roll_no'];
            $responce_arr[$cur_student_id]['mother_name'] = $arr['mother_name'];
            $responce_arr[$cur_student_id]['class'] = $arr['standard_name'];
            $responce_arr[$cur_student_id]['father_name'] = $arr['father_name'];
            $responce_arr[$cur_student_id]['division'] = $arr['division_name'];
            $responce_arr[$cur_student_id]['date_of_birth'] = date("d-m-Y", strtotime($arr['dob']));
            $responce_arr[$cur_student_id]['gr_no'] = $arr['enrollment_no'];
            $responce_arr[$cur_student_id]['image'] = $arr['image'];
            $responce_arr[$cur_student_id]['height'] = $arr['height'];
            $responce_arr[$cur_student_id]['weight'] = $arr['weight'];
            $responce_arr[$cur_student_id]['exam'] = $all_exam;
            $responce_arr[$cur_student_id]['mark'] = $all_subject_mark[$cur_student_id];
            $responce_arr[$cur_student_id]['per'] = $this->getPer($all_subject_mark[$cur_student_id]);
            $responce_arr[$cur_student_id]['final_grade'] = $this->getFinalGrade($responce_arr[$cur_student_id]['per']);
            if(isset($all_co_data[$cur_student_id])){
                $responce_arr[$cur_student_id]['co_scholastic_area'] = $all_co_data[$cur_student_id];
            }            
            $responce_arr[$cur_student_id]['att'] = '';
            $responce_arr[$cur_student_id]['total_working_day'] = '';
            $responce_arr[$cur_student_id]['teacher_remark'] = '';
            if(isset($all_att_data[$cur_student_id]))
            {
                $responce_arr[$cur_student_id]['att'] = $all_att_data[$cur_student_id]['attendance'];
                $responce_arr[$cur_student_id]['total_working_day'] = $all_att_data[$cur_student_id]['total_working_day'];
                $responce_arr[$cur_student_id]['teacher_remark'] = $all_att_data[$cur_student_id]['teacher_remark'];
            }
            $responce_arr[$cur_student_id]['grade_range'] = $all_grd_data;
        }
        //RAJESH - Session temoarary extend HIDE 06_11_2023
        /*if(session()->get('sub_institute_id')!=72){
        session()->put('term_id', $academicTerms[1]->term_id);
        }
        */
        //getting year detail
        //getting all exam name with mark
        $all_exam = $this->getAllExam($_REQUEST['standard']);

        //getting all subject name
        $all_subject = $this->getAllSubject($_REQUEST['standard']);

        //getting all mark
        $all_subject_mark = $this->getAllMark($all_exam, $all_subject, $all_student);

        //getting Co Scholastic        
        $all_co_data = $this->getCoArea($all_student);

        //getting attendance
        $all_att_data = $this->getAttendance($all_student);

        //getting scholastic grade range
        $all_grd_data = $this->getGradeRange();

        //getting currunt term name
        $term_name = $this->getTermName();

        //getting result header
        $header_data = $this->getHeader($_REQUEST['standard']);

        //get exam master settigs
        $footer_data = $this->getExamMasterSettigs($_REQUEST['standard']);

        $responce_arr_term2 = [];
        foreach ($all_student as $id => $arr) {
            $cur_student_id = $arr['student_id'];
            $responce_arr_term2[$cur_student_id]['year'] = $result_year;
            $responce_arr_term2[$cur_student_id]['term'] = $term_name;
            $responce_arr_term2[$cur_student_id]['total_mark'] = $all_exam[count($all_exam) - 1]['mark'];
            $responce_arr_term2[$cur_student_id]['name'] = $arr['first_name'] . " " . $arr['middle_name'] . " " . $arr['last_name'];
            $responce_arr_term2[$cur_student_id]['roll_no'] = $arr['roll_no'];
            $responce_arr_term2[$cur_student_id]['mother_name'] = $arr['mother_name'];
            $responce_arr_term2[$cur_student_id]['class'] = $arr['standard_name'];
            $responce_arr_term2[$cur_student_id]['father_name'] = $arr['father_name'];
            $responce_arr_term2[$cur_student_id]['division'] = $arr['division_name'];
            $responce_arr_term2[$cur_student_id]['date_of_birth'] = date("d-m-Y", strtotime($arr['dob']));
            $responce_arr_term2[$cur_student_id]['gr_no'] = $arr['enrollment_no'];
            $responce_arr_term2[$cur_student_id]['image'] = $arr['image'];
            $responce_arr_term2[$cur_student_id]['height'] = $arr['height'];
            $responce_arr_term2[$cur_student_id]['weight'] = $arr['weight'];
            $responce_arr_term2[$cur_student_id]['exam'] = $all_exam;
            $responce_arr_term2[$cur_student_id]['mark'] = $all_subject_mark[$cur_student_id];
            $responce_arr_term2[$cur_student_id]['per'] = $this->getPer($all_subject_mark[$cur_student_id]);
            $responce_arr_term2[$cur_student_id]['final_grade'] = $this->getFinalGrade($responce_arr_term2[$cur_student_id]['per']);
            if(isset($all_co_data[$cur_student_id])){
                $responce_arr_term2[$cur_student_id]['co_scholastic_area'] = $all_co_data[$cur_student_id];
            }
            $responce_arr_term2[$cur_student_id]['att'] = '';
            $responce_arr_term2[$cur_student_id]['total_working_day'] = '';
            $responce_arr_term2[$cur_student_id]['teacher_remark'] = '';
            if(isset($all_att_data[$cur_student_id]))
            {
                $responce_arr[$cur_student_id]['att'] = $all_att_data[$cur_student_id]['attendance'];
                $responce_arr[$cur_student_id]['total_working_day'] = $all_att_data[$cur_student_id]['total_working_day'];
                $responce_arr[$cur_student_id]['teacher_remark'] = $all_att_data[$cur_student_id]['teacher_remark'];
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

        $type = $request->input('type');
        // return session()->get('sub_institute_id');exit;
        if(session()->get('sub_institute_id')==61){
            return \App\Helpers\is_mobile($type, "result/cbse_result/1t5_s1_show", $data, "view");
        }elseif(session()->get('sub_institute_id')==195){
            return \App\Helpers\is_mobile($type, "result/cbse_result/1t5_s1_altius", $data, "view");
        }else{
            return \App\Helpers\is_mobile($type, "result/cbse_result/1t5_s1_show2", $data, "view");
        }
    }

    public function getHeader($standard_id)
    {
        $str = "SELECT * from result_book_master b
                INNER JOIN result_trust_master t on b.trust_id = t.id
                WHERE b.standard = '".$standard_id."' AND t.syear = '".session()->get('syear')."' AND b.sub_institute_id = '".session()->get('sub_institute_id')."'
                LIMIT 1";
        $result = DB::select(DB::raw($str));
        $result = json_decode(json_encode($result),true);

        if (!empty($result) && isset($result[0])) {
            return $result[0];
        } else {
            return null;
        }        
    }

    public function getExamMasterSettigs($standard_id)
    {        
        $str = 'select rm.* 
                from result_master_confrigration rm
                where rm.standard_id = ' . $standard_id . ' 
                and rm.syear = ' . session()->get('syear') . '
                and rm.sub_institute_id = ' . session()->get('sub_institute_id') . '';
        $str=str_replace("\r\n", "", $str);
        $result = DB::select(DB::raw($str));
       
        $responce = array();
        foreach ($result as $id => $obj) {
            $responce['teacher_sign'] = $obj->teacher_sign;
            $responce['principal_sign'] = $obj->principal_sign;
            $responce['director_signatiure'] = $obj->director_signatiure;
            $responce['reopen_date'] = $obj->reopen_date;
        }

        return $responce;
    }

    public function getAllExam($standard_id,$sub_id='') {
        $str = 'SELECT em.ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ""), e.points, e.con_point) AS points, em.Id
        FROM result_create_exam e
        INNER JOIN result_exam_master em ON em.Id = e.exam_id
        WHERE e.term_id = ' . session()->get('term_id') . ' 
        AND e.sub_institute_id = ' . session()->get('sub_institute_id') . '
        AND e.syear = ' . session()->get('syear') . '  
        AND e.standard_id = ' . $standard_id . '
        AND e.report_card_status ="Y"
        GROUP BY em.ExamTitle
        ORDER BY em.SortOrder';

        $result = DB::select(DB::raw($str));

        $responce = array();
        $total_mark = 0;
        foreach ($result as $obj) {
            $responce[] = array(
                'exam_id' => $obj->Id,
                'exam' => $obj->ExamTitle,
                'mark' => $obj->points,
            );
            $total_mark = $total_mark + $obj->points;
        }

        $responce[] = array(
            'exam' => "Marks Obtained",
            'mark' => $total_mark,
        );


//        echo "<pre>";
//        print_r($responce);
//        exit;

        return $responce;
    }

    public function getTermName() {
        $str = 'select * 
                from academic_year 
                where term_id = ' . session()->get('term_id') . ' and sub_institute_id = ' . session()->get('sub_institute_id') . '';
        $result = DB::select(DB::raw($str));

        foreach ($result as $id => $obj) {
            $responce = $obj->title;
        }

        return $responce;
    }

    public function getAllSubject($std) {
        $str = 'SELECT ssm.display_name,ssm.subject_id,ssm.elective_subject 
                FROM sub_std_map ssm
                INNER JOIN standard s ON s.id = ssm.standard_id
                WHERE ssm.sub_institute_id = ' . session()->get('sub_institute_id') . ' AND 
                    ssm.standard_id = ' . $std . ' AND 
                    ssm.allow_grades = "Yes" 
                ORDER BY ssm.sort_order
                ';
        $result = DB::select(DB::raw($str));

        $responce = array();
        foreach ($result as $id => $obj) {
            $responce[$obj->subject_id] = $obj->display_name.'####'.$obj->elective_subject;            
        }

//        echo "<pre>";
//        print_r($responce);
//        exit;

        return $responce;
    }

    public function getAllMark($all_exam, $all_subject, $all_student) 
    {

       // echo "<pre>";
       // print_r($all_exam);
       // print_r($all_subject);
       // print_r($all_student);
       // exit;

        $exam_id_arr = array();
        foreach ($all_exam as $id => $arr) {
            if ($id != count($all_exam) - 1)
                $exam_id_arr[] = $arr['exam_id'];
        }
        $exam_id = implode(',', $exam_id_arr);

        if (empty($exam_id)) {
            $exam_id = "0";
        }

        $student_id_arr = array();
        foreach ($all_student as $id => $arr) {
            $student_id_arr[] = $arr['student_id'];
        }
        $student_id = implode(',', $student_id_arr);

if(session()->get('sub_institute_id') == 61)
    $decimal = 0;
else
    $decimal = 2;

        $str = 'SELECT ex.id,rm.student_id,s.subject_id,s.display_name,s.elective_subject,SUM(ex.points) total_points,ex.con_point,SUM(rm.points) points,exm.Id exam_id,rm.is_absent
        FROM result_marks rm
        INNER JOIN result_create_exam ex ON ex.id = rm.exam_id
        INNER JOIN result_exam_master exm ON exm.Id = ex.exam_id
        INNER JOIN sub_std_map s ON s.subject_id = ex.subject_id AND s.standard_id = ex.standard_id
        WHERE exm.Id IN (' . $exam_id . ') ' . (is_int($student_id) ? 'AND rm.student_id IN (' . $student_id . ')' : '') . '
         AND ex.term_id = "' . session()->get('term_id') . '" 
        AND ex.syear = ' . session()->get('syear') . ' AND ex.report_card_status ="Y"
        GROUP BY rm.student_id,s.display_name,ex.points,exm.Id
        ORDER BY rm.student_id,s.display_name,exm.Id
        ';
//        echo $str;die();
        $result = DB::select(DB::raw($str));
    //    echo "<pre>";print_r($result);
        // getting data and making readable format student wise
        $marks_arr = array();
        foreach ($result as $id => $arr) 
        {
            $arr->display_name = strtoupper($arr->display_name);
            $temp_arr['id'] = $arr->id;
            $temp_arr['student_id'] = $arr->student_id;
            $temp_arr['subject_id'] = $arr->subject_id;
            $temp_arr['subject_name'] = $arr->display_name;
            $temp_arr['optional_subject'] = $arr->elective_subject;
            $temp_arr['total_points'] = $arr->total_points;
            $temp_arr['con_point'] = $arr->con_point;
            $temp_arr['points'] = $arr->points;
            $temp_arr['exam_id'] = $arr->exam_id;
            $temp_arr['is_absent'] = $arr->is_absent;

            if($arr->elective_subject == 'Yes')
            {
                $check_optional_subject_with_student = DB::select("SELECT * FROM student_optional_subject WHERE student_id = '".$arr->student_id."' AND subject_id = '".$arr->subject_id."' AND syear = '".session()->get('syear')."' ");

                if((count($check_optional_subject_with_student) > 0))
                {
                    $marks_arr[$arr->student_id][$arr->display_name][$arr->exam_id] = $temp_arr;                    
                }
            }
            else
            {
                $marks_arr[$arr->student_id][$arr->display_name][$arr->exam_id] = $temp_arr;
            }            
           
        }
        // die;
        //getting grade scale data
        $grade_arr = $this->getGradeScale();


        $responce_arr = array();
        foreach ($all_student as $students => $arr_student) 
        {
            foreach ($all_subject as $subject_id => $subject) 
            {
                $subject_arr = explode("####",$subject);
                $subject = $subject_arr[0];
                $subject_elective = $subject_arr[1];

                $total_gain_mark = $total_con_point = 0;
                $total_mark = 0;
                
                foreach ($all_exam as $exam_id => $exam_detail) 
                { $abFlag = $naFlag = $exFlag = 0;
                    // last exam have total mark so calculate before it
                    if (count($all_exam) - 1 != $exam_id) 
                    {
                        $mark = 0;
                        $total_mark = 0;
                        $con_point = 0;

                        $subject = strtoupper($subject);

                        if(isset($marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]))
                        {
                            $is_absent = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['is_absent'];

                            if(isset($is_absent) && $is_absent == "N.A."){
                                $naFlag = 1;
                                //continue;
                            }
                            elseif(isset($is_absent) && $is_absent == "EX"){
                                $exFlag = 1;
                                //continue;
                            }
                            elseif(isset($is_absent) && $is_absent == "AB"){
                                $abFlag = 1;
                                $mark = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['points'];
                                $total_mark = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['total_points'];
                                $con_point = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['con_point'];

                                if ($con_point != NULL && $con_point != $total_mark) {
                                $mark = ($con_point * $mark) / $total_mark;
                                }
                                $total_gain_mark = $total_gain_mark + $mark;
                                $total_con_point = $total_con_point + $con_point;
                            }
                            else{
                                $mark = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['points'];
                                $total_mark = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['total_points'];
                                $con_point = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['con_point'];

                                if ($con_point != NULL && $con_point != $total_mark) {
                                    $mark = ($con_point * $mark) / $total_mark;
                                }
                                $total_gain_mark = $total_gain_mark + $mark;
                                $total_con_point = $total_con_point + $con_point;
                            }
            
                        }
                        /*else
                        {                            
                            $mark = 0;
                            $total_mark = 0;
                            $con_point = 0;
                        }*/

                        // if 1 type have multiple exam then convert mark
                        

            //$get_absent = $marks_arr[$arr_student['student_id']][$subject][$exam_detail['exam_id']]['is_absent'];
            if ($abFlag == 1){
                $e_points = "AB";
            }
            elseif ($naFlag == 1){
                $e_points = "N.A.";
            }
            elseif($exFlag == 1){
                $e_points = "EX";
            }else
                $e_points = number_format($mark,$decimal);

                        //$responce_arr[$arr_student['student_id']][$subject][$exam_detail['exam']] = number_format($mark,0);
                        $responce_arr[$arr_student['student_id']][$subject][$exam_detail['exam']] = $e_points;
                    } 
                    else {
                        $total_mark = $exam_detail['mark'];
                    }
                }

                if($subject_elective == 'Yes')
                {                    
                    $check_optional_subject_with_student = DB::select("SELECT * FROM student_optional_subject 
                        WHERE student_id = '".$arr_student['student_id']."' 
                        AND subject_id = '".$subject_id."' AND syear = '".session()->get('syear')."' ");

                    if((count($check_optional_subject_with_student) == 0))
                    {                 
                        unset($responce_arr[$arr_student['student_id']][$subject]);
                    }
                    else
                    {
                        $responce_arr[$arr_student['student_id']][$subject]['TOTAL_GAIN'] = number_format($total_gain_mark,$decimal);
                        $responce_arr[$arr_student['student_id']][$subject]['TOTAL_MARKS'] = number_format($total_con_point,$decimal);
                        $responce_arr[$arr_student['student_id']][$subject]['GRADE'] = $this->getGrade($grade_arr, $total_con_point, $total_gain_mark);
                    }
                }
                else
                {   
                    //echo "totoa-".$total_gain_mark."/".$total_con_point."<br/>";
                    $responce_arr[$arr_student['student_id']][$subject]['TOTAL_GAIN'] = number_format($total_gain_mark,$decimal);
                    $responce_arr[$arr_student['student_id']][$subject]['TOTAL_MARKS'] = number_format($total_con_point,$decimal);
                    $responce_arr[$arr_student['student_id']][$subject]['GRADE'] = $this->getGrade($grade_arr, $total_con_point, $total_gain_mark);
                }

            }
           
        }
        //echo "<pre>";
        //print_r($responce_arr);
        //exit;

        return $responce_arr;
    }

    public static function getGrade($grade_arr, $total_mark, $total_gain_mark) {
        if ($total_mark == 0) {
            return "-";
        }
        //echo $total_gain_mark."/".$total_mark."<br/>";
        $per = round((100 * $total_gain_mark) / $total_mark,0);

        foreach ($grade_arr as $id => $data) {
            if (!isset($grade)) {
                if ($per >= $data['breakoff']) {
                    $grade = $data['title'];
                }
            }
        }

        if (!isset($grade)) {
            $grade = "-";
        }
        return $grade;
    }

    public function getPer($all_gain_mark) {
        $total_subject_mark = 0;
        $total_gain_mark = 0;
 
        if (is_array($all_gain_mark)) {
            foreach ($all_gain_mark as $id => $arr) {
                if (is_numeric($arr['TOTAL_GAIN'])) {
                    $total_gain_mark += $arr['TOTAL_GAIN'];
                    $total_subject_mark += $arr['TOTAL_MARKS'];
                }
            }
        } else {
            return 0; // or whatever value you want to return if $all_gain_mark is not an array
        }
 
        if ($total_subject_mark == 0) {
            return 0; // or whatever value you want to return if $total_subject_mark is zero
        }
        $per = round((100 * $total_gain_mark) / $total_subject_mark,2);
        return $per;
    }




    public static function getGradeScale($standard_id = '',$type = '') {
        if($type == 'API')
        {
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $standard_id;
        }else{
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
            $standard_id = $_REQUEST['standard'];
        }

        $sql_grade = "SELECT dt.* 
                    FROM result_std_grd_maping  sgm
                    INNER JOIN grade_master_data dt on dt.grade_id = sgm.grade_scale AND dt.syear = " . $syear . "
                    WHERE sgm.standard = " . $standard_id. " AND 
                    sgm.sub_institute_id = " .$sub_institute_id. "
                    ORDER BY dt.breakoff DESC
                ";
        $ret_grade = DB::select(DB::raw($sql_grade));

        //converting it into array 
        $grade_arr = array();
        foreach ($ret_grade as $id => $arr) {
            $grade_arr[$id]['id'] = $arr->id;
            $grade_arr[$id]['grade_id'] = $arr->grade_id;
            $grade_arr[$id]['title'] = $arr->title;
            $grade_arr[$id]['breakoff'] = $arr->breakoff;
            $grade_arr[$id]['gp'] = $arr->gp;
            $grade_arr[$id]['sort_order'] = $arr->sort_order;
            $grade_arr[$id]['comment'] = $arr->comment;
            $grade_arr[$id]['sub_institute_id'] = $arr->sub_institute_id;
            $grade_arr[$id]['created_at'] = $arr->created_at;
            $grade_arr[$id]['updated_at'] = $arr->updated_at;
        }
        return $grade_arr;
    }

    public function getFinalGrade($per) {
        $grade_arr = $this->getGradeScale();
        foreach ($grade_arr as $id => $data) {
            if (!isset($grade)) {
                if ($per >= $data['breakoff']) {
                    $grade = $data['title'];
                }
            }
        }
        if (!isset($grade)) {
            $grade = "-";
        }
        return $grade;
    }

    public function getCoArea($all_student) {
//        echo "<pre>";
//        print_r($all_student);
//        exit;

        $responce_arr = array();

        $sql_mark_grade = "select * from result_co_scholastic
                          where sub_institute_id = " . session()->get('sub_institute_id') . "
                              and term_id = " . session()->get('term_id') . "
                              and standard_id = " . $_REQUEST['standard'] . "
                          ";

        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));

        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
                $sql_data = "select comark.student_id,comark.co_scholastic_id, cop.title parent_title,co.title child_title,cograde.title obtain_grade
                                from result_co_scholastic_marks_entries comark
                                inner join result_co_scholastic_grades cograde on cograde.id = comark.grade
                                inner join result_co_scholastic co on co.id = comark.co_scholastic_id
                                inner join result_co_scholastic_parent cop on cop.id = co.parent_id
                                where comark.syear = " . session()->get('syear') . " and 
                                comark.term_id = " . session()->get('term_id') . " and 
                                comark.standard_id = " . $_REQUEST['standard'] . " and 
                                comark.sub_institute_id = " . session()->get('sub_institute_id') . "
                                order by comark.student_id,cop.sort_order,co.sort_order
                          ";
                         // dd($sql_data);
                $ret_data = DB::select(DB::raw($sql_data));
                // converting data in array
                $data_arr = array();
                foreach ($ret_data as $id => $arr) {
                    $data_arr[$id]['student_id'] = $arr->student_id;
                    $data_arr[$id]['co_scholastic_id'] = $arr->co_scholastic_id;
                    $data_arr[$id]['parent_title'] = $arr->parent_title;
                    $data_arr[$id]['child_title'] = $arr->child_title;
                    $data_arr[$id]['obtain_grade'] = $arr->obtain_grade;
                }
                foreach ($data_arr as $id => $arr) {
                    $responce_arr[$arr['student_id']]['co_area'][$arr['parent_title']][$arr['child_title']] = $arr['obtain_grade'];
                }
            } else {
                
            }
        }

        return $responce_arr;
    }

    public function getAttendance($all_student) {
//        echo "<pre>";
//        print_r($all_student);
//        exit;
        $sql_data = "select atd.student_id,wrkd.total_working_day,atd.attendance,atd.teacher_remark
                from result_student_attendance_master atd
                inner join result_working_day_master wrkd on wrkd.standard = atd.standard and wrkd.sub_institute_id = atd.sub_institute_id
                where atd.standard = " . $_REQUEST['standard'] . " and 
                    atd.sub_institute_id = " . session()->get('sub_institute_id') . " and 
                    atd.syear = " . session()->get('syear') . " and atd.term_id = " . session()->get('term_id') . "
                ";
        $ret_data = DB::select(DB::raw($sql_data));
        $data_arr = array();
        foreach ($ret_data as $id => $arr) {
            $data_arr[$arr->student_id]['attendance'] = $arr->attendance ?? '0';
            $data_arr[$arr->student_id]['total_working_day'] = $arr->total_working_day ?? '0';
            $data_arr[$arr->student_id]['teacher_remark'] = $arr->teacher_remark ?? '-';
        }

        return $data_arr;
    }

    public function getGradeRange() {
        $grade_arr = $this->getGradeScale();

        $responce_arr = array();
        foreach ($grade_arr as $id => $arr) {
            if (!isset($last_breckoff)) {
                $last_breckoff = "100";
            }
            $responce_arr['mark_range']['SCHOLASTIC MARKS RANGE'][] = $arr['breakoff'] . "-" . $last_breckoff;
            $responce_arr['mark_range']['GRADE'][] = $arr['title'];
            $last_breckoff = $arr['breakoff'] - 1;
        }
//        echo "<pre>";
//        print_r($responce_arr);
//        exit;

        return $responce_arr;
    }

    public function save_result_html(Request $request)
    {        
        $student_array = explode(",",$request->get('student_arr'));
        $term_id = $request->get('term_id');
        $grade_id = $request->get('grade_id');
        $standard_id = $request->get('standard_id');
        $division_id = $request->get('division_id');
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        foreach($student_array as $key => $val)
        {
            $result_data['student_id'] = $val; 
            $result_data['term_id'] = $term_id; 
            $result_data['grade_id'] = $grade_id; 
            $result_data['standard_id'] = $standard_id; 
            $result_data['division_id'] = $division_id; 
            $result_data['syear'] = $syear; 
            $result_data['sub_institute_id'] = $sub_institute_id; 
            $result_data['html'] = $request->get('html_'.$val); 

            $data = DB::select("SELECT * FROM result_html WHERE student_id = '".$val."' AND term_id = '".$request->get('term_id')."'
                    AND grade_id = '".$request->get('grade_id')."'  AND standard_id = '".$request->get('standard_id')."'
                     AND division_id = '".$request->get('division_id')."'  AND syear = '".$request->get('syear')."'
                     AND sub_institute_id = '".session()->get('sub_institute_id')."'
                    ");
            if(count($data) > 0)
            {
                $html = $request->get('html_'.$val);
                $finalArray['html'] = $html;
                $data = result_html_model::where(['student_id'=>$val,'term_id'=>$term_id,'grade_id'=>$grade_id,'standard_id'=>$standard_id,'division_id'=>$division_id,'syear'=>$syear])->update($finalArray);

                // DB::table("result_html")->update(["html"=>$html])
                // ->where(['student_id'=>$val,'term_id'=>$term_id,'grade_id'=>$grade_id,'standard_id'=>$standard_id,'division_id'=>$division_id,'syear'=>$syear]);        
            }
            else
            {
                DB::table("result_html")->insert($result_data);        
            }
        }
        return 1;
    }

    public function studentResultPDFAPI(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }

        $response = array();
        $validator =  Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',                    
            'term_id' => 'required|numeric',                    
        ]);
        if ($validator->fails()) 
        {
            $response['response'] = $validator->messages();
        } 
        else 
        {    
            $fees_check = 1;
            $stu_arr = array(
                "0" => $request->get('student_id')
            );
            $reg_bk_off = FeeBreackoff($stu_arr);
            $total_bf = 0;

            foreach ($reg_bk_off as $key => $val) 
            {
                if(($val->month_id == '42023' || $val->month_id == '72023') && $val->student_quota != '2383') 
                //Condition added by Rajesh 21_07_2022 only Quarter-1 fees paid to display result 
                //Condition added by jinal 07_10_2022 only Quarter-2 fees paid to display result
                //condition remove by rajesh 29-07-2023  || $val->month_id == '102023' for Altius
                    $total_bf = $total_bf + $val->bkoff;
                else
                    break;
            }

            $paid_fees = DB::select("SELECT SUM(amount) paid_amt,student_id id
                                    FROM(
                                    SELECT SUM(fc.amount)+ SUM(fc.fees_discount) amount,se.student_id
                                    FROM tblstudent s
                                    INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.syear = '".$request->get('syear')."'
                                    INNER JOIN academic_section g ON g.id = se.grade_id
                                    INNER JOIN standard st ON st.id = se.standard_id
                                    LEFT JOIN division d ON d.id = se.section_id
                                    INNER JOIN fees_collect fc ON 
                                    (
                                     fc.student_id = s.id AND 
                                     fc.is_deleted = 'N' AND
                                     fc.sub_institute_id = '".$request->get('sub_institute_id')."' AND
                                     fc.syear = '".$request->get('syear')."'
                                    )
                                    WHERE s.sub_institute_id = '".$request->get('sub_institute_id')."' AND s.id = '".$request->get('student_id')."'
                                    GROUP BY s.id 
                                    UNION
                                    SELECT SUM(fpo.actual_amountpaid)+ SUM(fpo.fees_discount) aa,se.student_id
                                    FROM tblstudent s
                                    INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.syear = '".$request->get('syear')."'
                                    INNER JOIN academic_section g ON g.id = se.grade_id
                                    INNER JOIN standard st ON st.id = se.standard_id
                                    LEFT JOIN division d ON d.id = se.section_id
                                    INNER JOIN fees_paid_other fpo ON (fpo.student_id = s.id AND fpo.is_deleted = 'N')
                                    WHERE s.sub_institute_id = '".$request->get('sub_institute_id')."' AND s.id = '".$request->get('student_id')."'
                                    GROUP BY s.id
                                    ) temp_table
                                    GROUP BY student_id");

            $paid_fees_data = json_decode(json_encode($paid_fees),true);

            $total_paid_amt = 0;
            if(isset($paid_fees_data[0]) && count($paid_fees_data[0]) > 0)
            {
                $total_paid_amt = $paid_fees_data[0]['paid_amt'];
            }

            $remaining_amt = $total_bf - $total_paid_amt;

            $data = DB::select("SELECT * FROM result_html 
                        WHERE SUB_INSTITUTE_ID = '".$request->get('sub_institute_id')."' and student_id = '".$request->get('student_id')."'
                        AND syear = '".$request->get('syear')."' AND term_id = '".$request->get('term_id')."'"); // AND is_allowed='Y' 

            $second_sql = DB::select("SELECT ur.id,ur.syear,ur.sub_institute_id,ur.student_id,ay.title as term_name,
                            if(ur.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/upload_result/',ur.file_name)) as file_name
                            FROM upload_result ur
                            INNER JOIN academic_year ay ON ay.term_id = ur.term_id AND ay.sub_institute_id = ur.sub_institute_id
                            WHERE ur.student_id = '".$request->get('student_id')."' AND ur.sub_institute_id = '".$request->get('sub_institute_id')."' 
                            AND ur.syear = '".$request->get('syear')."'  AND ur.term_id = '".$request->get('term_id')."'"); 

            if($fees_check == 1 && $request->get('sub_institute_id') == '195')
            {
                if($remaining_amt <= 0)
                {
                    if(count($data) > 0)
                    {

                        $html = $data[0]->html;

                        $css_name = "https://".$_SERVER['SERVER_NAME'];
                        $result_css = '<link rel="stylesheet" href="'.$css_name.'/css/result.css" />';
                        $dom = '<!DOCTYPE html>
                            <html>
                                <head>
                                   <title></title>
                                   <meta charset="UTF-8">
                                   <meta name="viewport" content="width=device-width, initial-scale=1.0">';
                        $dom .= "<style>

                                </style>";       
                        $dom .= $result_css;       
                        $dom .= '</head>
                                <body>
                                    <div>
                                        ##HTML_SEC##
                                    </div>
                                </body>
                            </html>';

                        $path = 'src="https://' . $_SERVER['HTTP_HOST'];
                        $html = str_replace('src="', $path, $html);
                        $html = str_replace('display:flex;', 'display: -webkit-box; -webkit-box-pack: center;', $html);
                        $html = str_replace('##HTML_SEC##', $html, $dom);                
            

                        //Start For Empty folder before creating new PDF
                        $folder_path = $_SERVER['DOCUMENT_ROOT'] . '/storage/result_pdf/*';
                        $files = glob($folder_path); // get all file names
                        foreach($files as $file){ // iterate files
                          if(is_file($file)) {
                            unlink($file); // delete file
                          }
                        }
                        //END For Empty folder before creating new PDF
                        
                        $save_path = $_SERVER['DOCUMENT_ROOT'] . '/storage/result_pdf';

                        $CUR_TIME = date('YmdHis');
                        $html_filename = $request->get('student_id').'_'.$CUR_TIME . ".html";
                        $pdf_filename = $request->get('student_id').'_'.$CUR_TIME . ".pdf";
                        
                        $html_file_path = $save_path . '/' . $html_filename;
                        $pdf_file_path = $save_path . '/' . $pdf_filename;
                        file_put_contents($html_file_path, $html);
                        //$soni = $save_path."/95634_20211130160457.html";                
                        htmlToPDF($html_file_path, $pdf_file_path); 
                        // htmlToPDFLandscape($html_file_path, $pdf_file_path); 
                        unlink($html_file_path);

                        $new_data['student_id'] = $request->get('student_id');
                        $new_data['pdf_link'] = "https://".$_SERVER['SERVER_NAME']."/storage/result_pdf/".$pdf_filename;

                        $response['status'] = 1;
                        $response['message'] = "Success";  
                        $response['data'] = $new_data;             
                    }
                    elseif( count($second_sql) > 0)
                    {
                        $new_data['student_id'] = $request->get('student_id');
                        $new_data['pdf_link'] = $second_sql[0]->file_name;

                        $response['status'] = 1;
                        $response['message'] = "Success";  
                        $response['data'] = $new_data;  
                    }
                    else
                    {
                        $response['status'] = 0;
                        $response['message'] = "No Record";                  
                    }
                }
                else
                { 
                    $response['status'] = 0;
                    $response['message'] = "Please paid reamaining fees for view report card.";                  
                }
            }else{
                if(count($data) > 0)
                {

                    $html = $data[0]->html;

                    $css_name = "http://".$_SERVER['SERVER_NAME'];
                    $result_css = '<link rel="stylesheet" href="'.$css_name.'/css/result.css" />';
                    $dom = '<!DOCTYPE html>
                        <html>
                            <head>
                               <title></title>
                               <meta charset="UTF-8">
                               <meta name="viewport" content="width=device-width, initial-scale=1.0">';
                    $dom .= "<style>

                            </style>";       
                    $dom .= $result_css;       
                    $dom .= '</head>
                            <body>
                                <div>
                                    ##HTML_SEC##
                                </div>
                            </body>
                        </html>';

                    $path = 'src="http://' . $_SERVER['HTTP_HOST'];
                    $html = str_replace('src="', $path, $html);
                    $html = str_replace('display:flex;', 'display: -webkit-box; -webkit-box-pack: center;', $html);
                    $html = str_replace('##HTML_SEC##', $html, $dom);                
        

                    //Start For Empty folder before creating new PDF
                    $folder_path = $_SERVER['DOCUMENT_ROOT'] . '/storage/result_pdf/*';
                    $files = glob($folder_path); // get all file names
                    foreach($files as $file){ // iterate files
                      if(is_file($file)) {
                        unlink($file); // delete file
                      }
                    }
                    //END For Empty folder before creating new PDF
                    
                    $save_path = $_SERVER['DOCUMENT_ROOT'] . '/storage/result_pdf';

                    $CUR_TIME = date('YmdHis');
                    $html_filename = $request->get('student_id').'_'.$CUR_TIME . ".html";
                    $pdf_filename = $request->get('student_id').'_'.$CUR_TIME . ".pdf";
                    
                    $html_file_path = $save_path . '/' . $html_filename;
                    $pdf_file_path = $save_path . '/' . $pdf_filename;
                    file_put_contents($html_file_path, $html);
                    //$soni = $save_path."/95634_20211130160457.html";                
                    htmlToPDF($html_file_path, $pdf_file_path); 
                    // htmlToPDFLandscape($html_file_path, $pdf_file_path); 
                    unlink($html_file_path);

                    $new_data['student_id'] = $request->get('student_id');
                    $new_data['pdf_link'] = "http://".$_SERVER['SERVER_NAME']."/storage/result_pdf/".$pdf_filename;

                    $response['status'] = 1;
                    $response['message'] = "Success";  
                    $response['data'] = $new_data;             
                }
                elseif( count($second_sql) > 0)
                {
                    $new_data['student_id'] = $request->get('student_id');
                    $new_data['pdf_link'] = $second_sql[0]->file_name;

                    $response['status'] = 1;
                    $response['message'] = "Success";  
                    $response['data'] = $new_data;  
                }
                else
                {
                    $response['status'] = 0;
                    $response['message'] = "No Record";                  
                }
            }

        }

        return json_encode($response);
        exit;
    }

}
