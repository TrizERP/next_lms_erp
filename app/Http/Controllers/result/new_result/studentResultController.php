<?php

namespace App\Http\Controllers\result\new_result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\result\result_template;
use function App\Helpers\SearchStudent;
use function App\Helpers\getStudents;
use DB;
class studentResultController extends Controller
{
    //
    public function index(Request $request)
    {   $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $data['data'] =result_template::where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->get()->toArray();
        if (empty($data['data'])) {
            $data['data'] = result_template::where('sub_institute_id', 0)->orderBy('sort_order')->get()->toArray();
        }
        $data['terms'] = DB::table('academic_year')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->get()->toArray();
        // echo "<pre>";print_r($data['data']);exit;
        return is_mobile($type, "result/new_result/student_results/show", $data, "view");
    }

    public function create(Request $request){
        
        $type = $request->input('type');
        $template = $request->input('template');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $format = $request->input('format');        
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');        
        
        $studentData = SearchStudent($grade, $standard, $division);
        // echo "<pre>";print_r($studentData);exit;
        if (! isset($studentData[0]['enrollment_no'])) {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";

            return is_mobile($type, "student-result.index", $res);
        }
        $res['data'] =result_template::where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->get()->toArray();
        if (empty($res['data'])) {
            $res['data'] = result_template::where('sub_institute_id', 0)->orderBy('sort_order')->get()->toArray();
        }
        $res['terms'] = DB::table('academic_year')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->get()->toArray();
        
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['template']=$template;
        $res['format']=$format;        
        $res['student_data'] = $studentData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;

        return is_mobile($type, "result/new_result/student_results/show", $res, "view");
    }

    public function store(Request $request){
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $template = $request->input('template_id') ?? 0;
        $student_ids = $request->input('students');
        $format = $request->input('format');        
        
        $data = getStudents($student_ids);
        $tData =result_template::where('id', $template)
            ->whereRaw('sub_institute_id = IFNULL(
                (SELECT sub_institute_id FROM result_template_master WHERE id ="'.$template.'" AND 
                    sub_institute_id = "'.  $sub_institute_id.'"
                ),0)')->get()->toArray();
        $tData = json_decode(json_encode($tData), true);

        $result = DB::table('fees_receipt_book_master')
        ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
        ->where('syear', $syear)
        ->where('sub_institute_id', $sub_institute_id)
        ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
        ->orderBy('sort_order')->limit(1)->get()->toArray();

            $receipt_book_arr = array();
            foreach ($result as $temp_id => $receipt_detail) {
                $receipt_book_arr = $receipt_detail;
            }    
        $last_insert_ids = '';
        $new_html = '';
        $all_stud_html = array();
        foreach ($data as $key => $value) {
            $html_content =$tData[0]['html_content'];
            $new_html_content = $this->create_html_content($syear, $sub_institute_id, $html_content, $value, $template,$receipt_book_arr,$format);
            $new_html .= $new_html_content;   
            $all_stud_html[$value['id']] = $new_html_content;         
        }
        // echo "<pre>";print_r($all_stud_html);exit;
        $type="";
        if($format=="yearly"){
            $format=session()->get('term_id');
        }
        // return $request;exit;
        $data['html']=$new_html;
        $data['standard_id'] = $request->standard_id;
        $data['grade_id'] =  $request->grade_id;
        $data['division_id'] =  $request->division_id;
        $data['term_id'] = $format;
        $data['syear'] = $syear;
        $data['all_stud_html'] = $all_stud_html;
        $data['students_ids'] = $request->students;       
        
        return is_mobile($type, "result/new_result/student_results/result_view", $data, "view");
    }

   
    public function create_html_content($syear,$sub_institute_id,$html_content,$value,$template,$receipt_book_arr,$format) {
        
        $display_year = $syear."-".($syear + 1);
       
        $image_path1 = "http://".$_SERVER['HTTP_HOST']."/storage/fees/".$receipt_book_arr->receipt_logo;
        $image_path = '<img src="'.$image_path1.'" alt="SCHOOL LOGO" style="width: 100px !important;height: 100px !important;">';

        $student_image_path1 = "http://".$_SERVER['HTTP_HOST']."/storage/student/".$value['image'];
        $student_image_path = '<img class="logo" src="'.$student_image_path1.'" alt="Student Logo" >';

        $html_content = str_replace(htmlspecialchars("<<receipt_logo>>"), $image_path, $html_content);
        if ($receipt_book_arr->receipt_line_1 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_1>>"), $receipt_book_arr->receipt_line_1,
                $html_content);
        }
        
        if ($receipt_book_arr->receipt_line_2 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_2>>"), $receipt_book_arr->receipt_line_2,
                $html_content);
        }
        if ($receipt_book_arr->receipt_line_3 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_3>>"), $receipt_book_arr->receipt_line_3,
                $html_content);
        }
        if ($receipt_book_arr->receipt_line_4 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_4>>"), $receipt_book_arr->receipt_line_4,
                $html_content);
        }
       $standard_id=$value['standard_id'];
        // for teachers signature standard_wise
        $result_teacher =  $this->getExamMasterSettigs($standard_id);
        $teacher_sign = '<img src="/storage/result/teacher_sign/'.$result_teacher['teacher_sign'].'" alt="teacher_sign" style="width: 100px !important;height: 100px !important;">';
        $principal_sign = '<img src="/storage/result/teacher_sign/'.$result_teacher['principal_sign'].'" alt="principal_sign" style="width: 100px !important;height: 100px !important;">';
        $director_signatiure = '<img src="/storage/result/teacher_sign/'.$result_teacher['director_signatiure'].'" alt="director_signatiure" style="width: 100px !important;height: 100px !important;">';
        
        $html_content = str_replace(htmlspecialchars("<<teacher_sign_value>>"), $teacher_sign, $html_content);
        $html_content = str_replace(htmlspecialchars("<<principle_sign_value>>"), $principal_sign, $html_content);
        $html_content = str_replace(htmlspecialchars("<<director_sign_value>>"), $director_signatiure, $html_content);
        
         $date_in_word = "";

        $his_her = '';
        if ($value['gender'] == 'male') {
            $his_her = 'His';
        } elseif ($value['gender'] == 'female') {
            $his_her = 'Her';
        }
        $he_she = '';

        if ($value['gender'] == 'male') {
            $he_she = 'he';
        } elseif ($value['gender'] == 'female') {
            $he_she = 'she';
        }
        //Start Bonafide certificate Tags
        $html_content = str_replace(htmlspecialchars("<<academic_years>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_image_value>>"), $student_image_path, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_id>>"), strtoupper($value['id']),
        $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_name_value>>"), strtoupper($value['student_full_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_enrollment_value>>"), $value['enrollment_no'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_roll_no_value>>"), $value['roll_no'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_standard_value>>"), $value['standard_name'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_division_value>>"), $value['division_name'],
            $html_content);

        $html_content = str_replace(htmlspecialchars("<<student_year_value>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_mobile_value>>"), $value['mobile'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_value>>"), date('d-m-Y', strtotime($value['dob'])),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<current_date>>"), date('d-M-Y'), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_word_value>>"), $date_in_word, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dise_uid_value>>"), $value['dise_uid'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<his_her_value>>"), $his_her, $html_content);
        $html_content = str_replace(htmlspecialchars("<<he_she_value>>"), $he_she, $html_content);
        //END Bonafide certificate Tags

        //Start Transfer certificate Tags
        $html_content = str_replace(htmlspecialchars("<<affiliation_no_value>>"), strtoupper($value['affiliation_no']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<school_code_value>>"), strtoupper($value['school_code']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<nationality_value>>"), strtoupper($value['nationality']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<place_of_birth_value>>"), strtoupper($value['place_of_birth']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<father_name_value>>"), strtoupper($value['father_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<mother_name_value>>"), strtoupper($value['mother_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<religion_name_value>>"), strtoupper($value['religion_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<caste_name_value>>"), strtoupper($value['caste_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<subcast_value>>"), strtoupper($value['subcast']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<candidate_belongs_to_value>>"),
            strtoupper($value['candidate_belongs_to']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_first_admission_value>>"),
            strtoupper($value['date_of_first_admission']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_in_which_pupil_last_studied_value>>"),
            strtoupper($value['class_in_which_pupil_last_studied']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<last_school_board_value>>"),
            strtoupper($value['last_school_board']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_failed_value>>"), strtoupper($value['whether_failed']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<subjects_studied_value>>"),
            strtoupper($value['subjects_studied']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_qualified_value>>"),
            strtoupper($value['whether_qualified']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<if_to_which_class_value>>"),
            strtoupper($value['if_to_which_class']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<month_up_paid_school_dues_value>>"),
            strtoupper($value['month_up_paid_school_dues']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<admission_under_value>>"),
            strtoupper($value['admission_under']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<total_working_days_value>>"),
            strtoupper($value['total_working_days']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<total_working_days_present_value>>"),
            strtoupper($value['total_working_days_present']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<games_played_value>>"), strtoupper($value['games_played']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<general_conduct_value>>"),
            strtoupper($value['general_conduct']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_application_for_certificate_value>>"),
            date('d-m-Y', strtotime($value['date_of_application_for_certificate'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_issue_of_certificate_value>>"),
            date('d-m-Y', strtotime($value['date_of_issue_of_certificate'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<reason_leaving_school_value>>"),
            strtoupper($value['reason_leaving_school']), $html_content);

        $html_content = str_replace(htmlspecialchars("<<proof_for_dob_value>>"), strtoupper($value['proof_for_dob']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_school_is_under_goverment_value>>"),
            strtoupper($value['whether_school_is_under_goverment']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_on_which_pupil_name_was_struck_value>>"),
            date('d-m-Y', strtotime($value['date_on_which_pupil_name_was_struck'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<any_fees_concession_value>>"),
            strtoupper($value['any_fees_concession']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_ncc_cadet_value>>"),
            strtoupper($value['whether_ncc_cadet']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<any_other_remarks_value>>"),
            strtoupper($value['any_other_remarks']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_uniqueid_value>>"), strtoupper($value['unique_id']),
            $html_content);
       
        // student Result
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_no_zero>>')) !== false) {
             $main_result = $this->get_scholastic($standard_id,$value['id'],$format,"no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_no_zero>>"),$main_result['table'],$html_content);             
        } else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_single_zero>>')) !== false) {
             $main_result = $this->get_scholastic($standard_id,$value['id'],$format,"signle_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_single_zero>>"),$main_result['table'],$html_content);             
        } else{
            $main_result = $this->get_scholastic($standard_id,$value['id'],$format,"double_zero"); 
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks>>"),$main_result['table'],$html_content);                       
        }
        // co scholastic
        $co_result = $this->get_co_scholastic($standard_id,$value['id'],$format,"double_zero");    
        $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks>>"),$co_result,$html_content); 
        // attendance 
        if (strpos($html_content, htmlspecialchars('<<total_attendance>>')) !== false) {
            $atten = $this->get_attendance($standard_id,$value['id'],$format,"total_attendance");
           $html_content = str_replace(htmlspecialchars("<<total_attendance>>"),$atten,$html_content);             
       } else if (strpos($html_content, htmlspecialchars('<<total_attendance_manual>>')) !== false) {
            $atten = $this->get_attendance($standard_id,$value['id'],$format,"total_attendance_manual");
           $html_content = str_replace(htmlspecialchars("<<total_attendance_manual>>"),$atten,$html_content);             
       }
      
       $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), strtoupper($main_result['remark']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<result>>"), strtoupper($main_result['result']),
            $html_content);
            $html_content = str_replace(htmlspecialchars("<<school_open_date>>"), date_format(date_create($result_teacher['reopen_date']), 'm-d-Y'), $html_content);

         return $html_content;
        //  return $main_result;         
    }

    public function getExamMasterSettigs($standard_id)
    {
        $result = DB::table('result_master_confrigration')
        ->select('teacher_sign', 'principal_sign', 'director_signatiure', 'reopen_date')
        ->where('standard_id', $standard_id)
        ->where('sub_institute_id', session()->get('sub_institute_id'))
        ->first();
    
    $responce = [];
    if ($result) {
        $responce = [
            'teacher_sign' => $result->teacher_sign,
            'principal_sign' => $result->principal_sign,
            'director_signatiure' => $result->director_signatiure,
            'reopen_date' => $result->reopen_date,
        ];
    }
    
    return $responce;    
    
    }

    public function get_scholastic($standard_id,$student_id,$format,$digit){
        // dd($student_id);
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        // sub_institute want foramt like lions 
        $format_sub_different = [61];

        if($format == "yearly"){
            $extra_term = "1=1";
            $extra_exam = "1=1";            
        }else{
            $extra_term = "term_id = ".$format;            
            $extra_exam = "rce.term_id = ".$format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->get()->toArray();

        // get subject
        $get_subject = DB::table("sub_std_map as ssm")->join('subject as sub','ssm.subject_id','=','sub.id')->selectRaw('ssm.id as map_id,sub.id as subject_id,sub.subject_name')->where(['ssm.sub_institute_id'=>$sub_institute_id,'ssm.standard_id'=>$standard_id,'allow_grades'=>"Yes"])->get()->toArray();

        //get exam name termwise
        $exam_title = DB::table('result_create_exam as rce')->join('result_exam_master as rem','rem.id','=','rce.exam_id')->whereRaw($extra_exam)->where(['rce.sub_institute_id'=>$sub_institute_id,'rce.syear'=>$syear,'rce.standard_id'=>$standard_id])
        ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rce.points,rem.ExamTitle')->groupbyRaw('rce.term_id,rce.title')->orderBy('rem.SortOrder')->get()->toArray();

        // get exam name
        $exam_name = DB::table('result_create_exam as rce')->whereRaw($extra_exam)->where(['rce.sub_institute_id'=>$sub_institute_id,'rce.syear'=>$syear,'rce.standard_id'=>$standard_id])->get()->toArray();
        
        $exam_marks = DB::table('result_marks as rce')->where(['rce.sub_institute_id'=>$sub_institute_id,'rce.student_id'=>$student_id])->get()->toArray();
        // dd($exam_marks);
        $head = count($exam_title);

        $table = '<table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th><b>Scholastic Areas:</b></th>';
                
    $col = 1;
    $total_term_marks = [];
    $total_sub_marks = [];    
    
    foreach ($term_name as $keys => $terms) {
        $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
            return $title->term_id == $terms->term_id;
        });
        
        $table .=  '<th colspan="' . (count($term_exam_titles) + 2) . '" style="text-align:center"><b>' . $terms->title . '</b></th>';
        
        // Initialize the total marks for each term to zero
        $total_term_marks[$terms->term_id] = 0;
        $total_sub_marks[$terms->term_id] = 0;        
    }
    
    $table .= '</tr>
        <tr>
            <th><b>Subject</b></th>';
            
    foreach ($term_name as $keys => $terms) {
        $total_mark = 0;
        
        foreach ($exam_title as $key => $title) {
            if ($terms->term_id == $title->term_id) {
                $table .= '<th><b>' . $title->ExamTitle . '(' . $title->points . ')</b></th>';
                $total_mark += $title->points;
            }
        } 

        // Store the total marks for each term
        // $total_term_marks[$terms->term_id] = $total_mark;
        
        $table .= '<th><b>Marks Obtained (' . $total_mark . ')</b></th>';
        $table .= '<th><b>Grade (' . $terms->title . ')</b></th>';
    }
    
    $table .= '</tr>
        </thead>
        <tbody>';
        
    $tot_ob_mark = 0;
    $tot_sub_mark = 0;    
    
    foreach ($get_subject as $val) {
        $table .= '<tr>
            <td>' . $val->subject_name . '</td>';
            
        foreach ($term_name as $keys => $terms) {
            $obtained_mark = 0;
            $ob_mark =0;
            
            foreach ($exam_name as $key => $title) {
                if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                    $foundMarks = false;
                    
                    foreach ($exam_marks as $index => $marks) {
                        if ($title->id == $marks->exam_id) {
                            if ($marks->points == "0.00" || $marks->points == "") {
                                $ab_ex_na=$marks->is_absent;
                                if($marks->is_absent == ''){
                                    $ab_ex_na = 0;
                                }
                                $table .= '<td>' . $ab_ex_na . '</td>';
                                
                            } else {
                                if ($digit == "no_zero") {
                                    $ob_mark = intval($marks->points);
                                } else if ($digit == "signle_zero") {
                                    $ob_mark = number_format(round($marks->points, 1), 1);
                                } else {
                                    $ob_mark = $marks->points;
                                }
                                $obtained_mark += $ob_mark;
                               
                                $table .= '<td>' . $ob_mark . '</td>';
                            }
                            $foundMarks = true;
                            break;
                        }
                    }
                    
                    if (!$foundMarks) {
                        $table .= '<td>0</td>';
                    }
                }
            }
            // dd($obtained_mark);
            if ($digit == "no_zero") {
                $obtained_mark_formatted = $obtained_mark; // 
            } else if ($digit == "single_zero") {
                $obtained_mark_formatted = number_format($obtained_mark, 1); // 
            } else {
                $obtained_mark_formatted = number_format($obtained_mark, 2); // 
            }
            
            $table .= '<td>' . $obtained_mark_formatted . '</td>';
            
            // Update the total marks for the current term
            $total_term_marks[$terms->term_id] += $obtained_mark;
            $total_sub_marks[$terms->term_id] += $total_mark;   
            $grade_arr = $this->getGradeScale($standard_id,'');         
            $table .= '<td>'.$this->getGrade($grade_arr, $total_mark, $obtained_mark_formatted).'</td>';
        }
        
        $table .= '</tr>';
    }
    // dd($term_name);
    $table .='<tr>';
    $table_per = $rep_val ='';
    $table_all='';    
    $ov_ob_mark  = $ov_sub_mark = 0;
    $ov_ob_mark2 = $ov_sub_mark2 = 0;  
    $result = "Pass"; 
    // Calculate the total marks for each term
    foreach ($term_name as $keys => $terms) {
        $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
            return $title->term_id == $terms->term_id;
        });
        $tot_ob_mark = $total_term_marks[$terms->term_id];
        $tot_sub_mark = $total_sub_marks[$terms->term_id];
           if($keys==0){
            $cols=1;
            $val = "Overall Percentage";
            $ov_ob_mark = $total_term_marks[$terms->term_id];
            $ov_sub_mark = $total_sub_marks[$terms->term_id];
           }else{
            $cols=0;
            $val = "Overall Grade";   
            $ov_ob_mark2 = $total_term_marks[$terms->term_id];
            $ov_sub_mark2 = $total_sub_marks[$terms->term_id];                                 
           }
           $all_ob_mark = ($ov_ob_mark + $ov_ob_mark2);
           $all_sub_mark = ($ov_sub_mark + $ov_sub_mark2);
        // get percentage   
        $finalPer = $this->getPer($tot_ob_mark,$tot_sub_mark);  
        // get overall percentage  
        $overall_per = $this->getPer($all_ob_mark,$all_sub_mark);    
        // get overall grade  
        $all_grade = \App\Helpers\getGrade($grade_arr,100, $overall_per);                                     
        $all_per = $overall_per."%";  
        // echo $all_grade;
        
        if($keys==0){
            $rep_val="&lt;&lt;per&gt;&gt;";
            if($finalPer < 33){
                $result = 'Promoted';
            }
        }else{
            $rep_val="&lt;&lt;grade&gt;&gt;";   
            if($finalPer < 33){
                $result = 'Promoted';
            }         
        }

        if(in_array($sub_institute_id,$format_sub_different)){
        $table .= '<td colspan="' .(count($term_exam_titles)) + $cols . '"><b>Total</b></td><td>' . $tot_ob_mark . '</td><td rowspan="2">'.\App\Helpers\getGrade($grade_arr, $total_mark, $finalPer).'</td>';
        $table_per .= '<td colspan="' .(count($term_exam_titles)) + $cols . '"><b>Percentage</b></td><td>' . $finalPer . '% </td>';
        $table_all .= '<td colspan="' .(count($term_exam_titles)) + $cols . '"><b>'.$val.'</b></td><td colspan="2">' . $rep_val . '</td>';
      
    }else{
            $table_per .= '<td colspan="' .(count($term_exam_titles)) + $cols . '"><b>Percentage</b></td><td>' . $finalPer . '% </td></td></td>';
        }
    }
    // exit;
    $table_all= str_replace(htmlspecialchars("<<per>>"),$all_per,$table_all); 
    $table_all= str_replace(htmlspecialchars("<<grade>>"),$all_grade,$table_all); 
    $table .='<tr>'.$table_per.'</tr>';
    $table .='<tr>'.$table_all.'</tr>';
    $res['remark']=\App\Helpers\getGradeComment($grade_arr, 100, $overall_per) ?? '';
    $res['result']=$result;  
    $table .= '</tr></tbody>
    </table>';
    $res['table']=$table;
    return $res;
    
    }


    public function getPer($total_obtained_marks,$total_marks) {
        if ($total_marks == 0) {
            return 0; // To avoid division by zero error
        }
    
        $percentage = ($total_obtained_marks / $total_marks) * 100;
        return number_format($percentage,2);
    }


    // co scholastic
    public function get_co_scholastic($standard_id,$student_id,$format,$digit){

        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        // co scholoastic like lions
        $format_sub_different = [61];

        if($format == "yearly"){
            $extra_term = "1=1";
            $extra_exam = "1=1";            
        }else{
            $extra_term = "term_id = ".$format;            
            $extra_exam = 'comark.term_id='.$format;  
        }
           // get term_name 
           $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->get()->toArray();
        $responce_arr=array();

        $sql_mark_grade = "select * 
                          from result_co_scholastic
                          where sub_institute_id = " .$sub_institute_id . "
                              and " . $extra_term . "
                          ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));

        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
              // Define your query using the query builder
            //   db::enableQueryLog();
              $ret_data = DB::table('result_co_scholastic_marks_entries as comark')
              ->selectRaw(
                  'comark.student_id,comark.co_scholastic_id,comark.term_id,cop.title as parent_title,co.title as child_title,
                  IFNULL(cograde.title,"-") as obtain_grade'
              )
              ->join('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
              ->join('result_co_scholastic as co', 'co.id', '=', 'comark.co_scholastic_id')
              ->join('result_co_scholastic_parent as cop', 'cop.id', '=', 'co.parent_id')
              ->where('comark.syear', $syear)
              ->whereRaw($extra_exam)
              ->where('comark.standard_id', $standard_id)
              ->where('co.standard_id', $standard_id)              
              ->where('comark.student_id', $student_id)              
              ->where('comark.sub_institute_id', $sub_institute_id)
              ->orderBy('comark.student_id')
              ->orderBy('cop.sort_order')
              ->orderBy('co.sort_order')
              ->orderBy('comark.term_id')      
              ->get();
            //  dd(DB::getQueryLog($ret_data));
        // echo "<pre>";print_r($responce_arr);
            
                $data_arr = array();
                foreach ($ret_data as $id => $arr) {
                    $data_arr[$id]['student_id'] = $arr->student_id;
                    $data_arr[$id]['co_scholastic_id'] = $arr->co_scholastic_id;
                    $data_arr[$id]['term_id'] = $arr->term_id;
                    $data_arr[$id]['parent_title'] = $arr->parent_title;                    
                    $data_arr[$id]['child_title'] = $arr->child_title;
                    $data_arr[$id]['obtain_grade'] = $arr->obtain_grade;
                }
                foreach ($data_arr as $id => $arr) {
                    $responce_arr[$arr['child_title']][$arr['term_id']] = $arr['obtain_grade'];
                                 
                }
            } else {
                
            }
        }
        // echo "<pre>";print_r($responce_arr);exit;
        if(in_array($sub_institute_id,$format_sub_different)){
        $table = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
        <tr>
        <th colspan="3" width="15%" style="text-align: left;">
            <b>Co-Scholastic Areas</b></th>
    </tr><tr>  <th width="50%" style="text-align: left;"><b>Optional
    Subject</b></th>';
                
    $col = 1;
    $total_term_marks = [];
    $total_sub_marks = [];    
    
    foreach ($term_name as $keys => $terms) {
        $table .=  '<th style="text-align:center"><b>' . $terms->title . '</b></th>';          
    }
    
    $table .='</tr>';
    $table .='</tr></thead><tbody>';
    $val_grade=["N.A.","NA","E.X.","EX","A.B.","AB"];
    $maxCount = 0;
    foreach($responce_arr as $sub =>$term_data){
      $table .='<tr><td>'.$sub.'</td>';
        foreach ($term_name as $key => $terms) { 
            if(isset($term_data[$terms->term_id])){
                $table .='<td>'.$term_data[$terms->term_id].'</td>';  
            }else{
                $table .='<td>-</td>'; 
            }
        }
    $table .='</tr>';    
    }  
    $table .='</tbody>
    </table>';
    }else{
        // echo "<pre>";print_r($this->getGradeRange($standard_id));exit;
        $get_grade_ranges = $this->getGradeRange($standard_id); 
        $table = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
        <tr>
        <th><b>SCHOLASTIC MARKS RANGE</b></th>';
        if(!empty($get_grade_ranges)){
        foreach($get_grade_ranges['mark_range']['SCHOLASTIC_MARKS_RANGE'] as $key => $value){
            $table .='<td>'.$value.'</td>';
        }
    }   
        $table .= '</tr>
        <tr>
        <th><b>GRADE</b></th>';
        if(!empty($get_grade_ranges)){        
        foreach($get_grade_ranges['mark_range']['GRADE'] as $key => $value){
            $table .='<td>'.$value.'</td>';
        }
    }
        $table .= '<tr>
        </thead></table>';
    }
        return $table;
    }


    public function getGradeRange($standard_id) {
        $grade_arr = $this->getGradeScale($standard_id);

        $responce_arr = array();
        foreach ($grade_arr as $id => $arr) {
            if (!isset($last_breckoff)) {
                $last_breckoff = "100";
            }
            $responce_arr['mark_range']['SCHOLASTIC_MARKS_RANGE'][] = $arr['breakoff'] . "-" . $last_breckoff;
            $responce_arr['mark_range']['GRADE'][] = $arr['title'];
            $last_breckoff = $arr['breakoff'] - 1;
        }
        return $responce_arr;
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
            $standard_id = $standard_id;
        }

        $query = DB::table('result_std_grd_maping AS sgm')
        ->join('grade_master_data AS dt', 'dt.grade_id', '=', 'sgm.grade_scale')
        ->select('dt.*')
        ->where('dt.syear', $syear)
        ->where('sgm.standard', $standard_id)
        ->where('sgm.sub_institute_id', $sub_institute_id)
        ->orderByDesc('dt.breakoff');
    
    // Execute the query and get the results
    $ret_grade = $query->get();

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

    public function get_attendance($standard_id,$student_id,$format,$type){
        
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        if($format == "yearly"){
            $extra_term = "1=1";
        }else{
            $extra_term = "atd.term_id = ".$format;        
        }
        $ret_data = DB::table('result_student_attendance_master as atd')
        ->join('result_working_day_master as wrkd', function ($join) use ($standard_id, $sub_institute_id) {
            $join->on('wrkd.standard', '=', 'atd.standard')
                ->on('wrkd.sub_institute_id', '=', 'atd.sub_institute_id');
        })
        ->select('atd.student_id', 'wrkd.total_working_day', 'atd.attendance', 'atd.teacher_remark')
        ->where('atd.standard', $standard_id)
        ->where('atd.sub_institute_id', $sub_institute_id)
        ->where('atd.student_id', $student_id)
        ->where('atd.syear', $syear)
        ->whereRaw($extra_term)
        ->first();
    // echo "<pre>";print_r($ret_data);exit;
        $table='<table class="aca-year" style="width: 100%;height:fit-content;margin-top:8%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <tbody>
        <tr>
            <th colspan="2" style="text-align: left;">
                <b>Total Attendance</b></th>
        </tr>
        <tr>
            <td width="75%">No. Of Working Days</td>';
        if(isset($ret_data->total_working_day) && $type=="total_attendance"){       
            $table.='<td width="25%" align="center">'.$ret_data->total_working_day.'</td>';
        }else{
            $table.='<td width="25%" align="center"></td>';
        }
        $table.=' </tr>
        <tr>
            <td>Days Attended</td>';
        if(isset($ret_data->total_working_day) && $type=="total_attendance"){                   
            $table.='<td align="center">'.$ret_data->attendance.'</td>';
        }else{
            $table.='<td width="25%" align="center"></td>';
        }
        $table.='</tr>                                                                    
        </tbody>
    </table>';
        return $table;
    }
}
