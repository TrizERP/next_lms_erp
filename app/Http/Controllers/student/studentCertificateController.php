<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;
use function App\Helpers\FeeMonthId;
use function App\Helpers\SearchStudent;
use App\Http\Controllers\result\new_result\studentResultController;

class studentCertificateController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $type = $request->input('type');
        $res=session()->has('data');
        // if (session()->has('data')) { 
        //     $data_arr = session('data'); 
        //     if (isset($data_arr['message']) && is_array($data_arr['message'])) {
        //         $res['message'] = $data_arr['message'];
        //         $res['status_code'] = $data_arr['status_code'] ;                
        //     }
        // }
        return is_mobile($type, "student/student_certificate/show_student", $res, "view");
    }

    public function showStudent(Request $request)
    {
        $type = $request->input('type');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');

        $res['stu_name'] = $stu_name = $request->stu_name;
        $res['uniqueid'] = $uniqueid = $request->uniqueid;
        $res['mobile'] = $mobile = $request->mobile;
        $res['grno'] = $grno = $request->grno;
        $res['from_date'] = $from_date = $request->from_date;
        $res['to_date'] = $to_date = $request->to_date;
        
        // serach student from helper  serachStudent function
        $studentData = SearchStudent($grade, $standard, $division, "","","", $stu_name , $uniqueid, $mobile, $grno,"","");

        if (! isset($studentData[0]['enrollment_no'])) {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";

            return is_mobile($type, "student_certificate.index", $res);
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $studentData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;

        return is_mobile($type, "student/student_certificate/show_student", $res, "view");
    }

    public function showStudentCertificate(Request $request)
    {
        $type = $request->input('type');
        $template = $request->input('template');
        $certificate_reason = $request->input('certificate_reason');
        $student_ids = $request->input('students');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');
        $studentEnroll=[];

        foreach ($student_ids as $key => $value) {
            # code...
            $studentDetails = DB::table('certificate_history as a')
            ->join('tblstudent as s','s.id','=','a.student_id')
            ->selectRaw('a.certificate_number')
            ->where(['a.sub_institute_id'=>$sub_institute_id,'a.syear'=>$syear])
            ->where('a.certificate_type', $template)
            ->where('a.student_id',$value)
            ->first();

            if(isset($studentDetails->certificate_number) && !in_array($studentDetails->certificate_number,$studentEnroll)){
                $studentEnroll[] = $studentDetails->certificate_number;
            }
        }

        if(!empty($studentEnroll) && $template == 'Transfer Certificate'){
            $res['status_code'] = 0;
            $res['message'] = "Certificate is already Issued with Certificate No <a href='".route('student_certificate_report.index')."' target='_blank'>".implode(',',$studentEnroll)."</a>. Please check in Student Certificate Report.";
            return is_mobile($type, "student/student_certificate/show_student", $res, "view");     
        }
        $data = getStudents($student_ids);

        // START Dynamic Template Logic
        $tData = DB::table('template_master')
            ->where('module_name', $template)
            ->whereRaw('sub_institute_id = IFNULL(
                (SELECT sub_institute_id FROM template_master WHERE module_name ="'.$template.'" AND 
                    sub_institute_id = "'.session()->get('sub_institute_id').'"
                ),0)')->get()->toArray();
        
        $tData = json_decode(json_encode($tData), true);
        //echo("<pre>");print_r($tData);exit;

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

        $new_html = '';
        $insert_ids = '';
        $i = 0;
        foreach ($data as $key => $value) {
         // get certificate new number 
            $certificate_no_result = DB::table('certificate_history as c')
                ->selectRaw('(IFNULL(MAX(cast(c.certificate_number AS UNSIGNED)),0) + 1) AS certificate_no')
                ->where('c.sub_institute_id', $sub_institute_id)
                ->where('certificate_type', $template)
                ->where('syear', $syear)->get()->toArray();
            $certificate_no = $certificate_no_result[0]->certificate_no;
            
            if (in_array($template,['Transfer Certificate','Bonafide']) &&  $sub_institute_id==254) 
            {
                $certificate_no_result = DB::table('certificate_history as c')
                ->selectRaw('c.certificate_number')
                ->where('c.sub_institute_id', $sub_institute_id)
                ->where('certificate_type', $template)
                ->where('syear', $syear)->orderBy('id','DESC')->first();
                
                $year = substr($syear,'-2');
                $prefix = $year.'-'.($year+1);
                $getLastNo = explode('/',$certificate_no_result->certificate_number ?? 0);
            //   echo "<pre>";print_r($getLastNo);exit;
                if($template=='Bonafide'){
                    $cno =($getLastNo[2] ?? 0)+ 1 + $i;
                    $certificate_no1 = 'BC/'.$prefix.'/'.$cno;
                }else{
                    $cno =($getLastNo[1] ?? 0)+ 1 + $i;
                    $certificate_no1 = $prefix.'/'.$cno;
                }
                $i++;
            }else{
                $certificate_no1 = $certificate_no + $i;
                $i++;
            }
            // echo "<pre>";print_r($certificate_no1);exit;
            if(!isset($tData[0]['html_content']) || empty($tData[0]['html_content']) || $tData[0]['html_content']==null){
                $res['status_code'] = 0;
                $res['message'] = "Please Set Template For ".$request->template;
                return is_mobile($type, "student/student_certificate/show_student", $res, "view");                
            }
            else{
                $html_content = $tData[0]['html_content'];                
            }
            $new_html_content = $this->create_html_content($syear, $sub_institute_id, $html_content, $value,
            $receipt_book_arr, $template, $certificate_no1, $certificate_reason);

            if ($template == 'Transfer Certificate') 
            {
                if(!empty($CheckExistCertificate) && $sub_institute_id==47){
                    $new_html_content = $CheckExistCertificate->certificate_html.'<br><h6 style="width:100%;text-align:right !important">Duplicate</h6>';
                }

                $new_html .= '<div class="row" style="margin-right: 2% !important;margin-left: 2% !important;">'.$new_html_content.'</div>
                              <div class="pagebreak"></div>';
            }
            else if($template == 'Student Fees Certificate'){
                $new_html .= '<div class="row" style="margin-right: 2% !important;margin-left: 2% !important;">'.$new_html_content.'</div>
                <div class="pagebreak"></div>';
            } else {
                $new_html .= '<div class="row" style="margin-right: 2% !important;margin-left: 2% !important;">'.$new_html_content.'</div>
                              <div class="pagebreak"></div>'; //margin-left: 5% !important;
            }

            $insert_ids .= $value['id'].',';
        }
        // END Dynamic Template Logic

        $insert_ids = rtrim($insert_ids, ',');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['template'] = $template;
        $res['str'] = $new_html;
        $res['insert_ids'] = $insert_ids;
        if ($certificate_reason != '') {
            $res['certificate_reason'] = $certificate_reason;
        }

        return is_mobile($type, "student/student_certificate/show_student_certificate", $res, "view");
    }

    public function studentCertificateAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $type = $request->input("type");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('certificate_history')
                ->where('syear', $syear)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('student_id', $student_id)->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }
        if($type=="JSON"){
            return response()->json($res);
        }else{
            return json_encode($res);
        }
    }

    public function create_html_content($syear,$sub_institute_id,$html_content,$value,$receipt_book_arr,$template,$certificate_no,$certificate_reason) {
//         if($sub_institute_id==254 && $value['id']==187555){
    //         echo("<pre>");
    // print_r($value);
    // echo("</pre>");
    // die;
// }
        if($sub_institute_id == 61)
            $display_year = "Apr-".$syear." to Mar-".($syear + 1);
        else
            $display_year = $syear."-".($syear + 1);

        if($sub_institute_id == 254)
            $logo_height = 'style="height: 50px !important;"';
        else
            $logo_height = 'style="width: 100px !important;height: 100px !important;"';

        $image_path1 = "https://".$_SERVER['HTTP_HOST']."/storage/fees/".$receipt_book_arr->receipt_logo;
        $image_path = '<img src="'.$image_path1.'" alt="SCHOOL LOGO" '.$logo_height.'>';

        $student_image_path1 = "https://".$_SERVER['HTTP_HOST']."/storage/student/".$value['image'];
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

        $number_controller = new numberWordsController;
        $date_in_word = ucwords($number_controller->toWords(date('dS', strtotime($value['dob'])))." of ".
            date('F', strtotime($value['dob']))." ".
            $this->convert_number_to_words(date('Y', strtotime($value['dob']))));
        if(in_array($sub_institute_id,[47,76])){
            $date_in_word = str_replace([' And ', ' Of '], ' ', $date_in_word);
        }
        // echo "<pre>";print_r($date_in_word);exit;
        $males = ["male","Male","MALE","M"];
        $females =["female","Female","FEMALE","F"];

        $cap_his_her =  $small_his_her = $cap_he_she = $small_he_she = $mr_miss = $daughter_son = $mast_miss = '';
        if(in_array($value['gender'],$males)){
            $cap_his_her = 'His';
            $small_his_her = 'his';
            $cap_he_she = 'He';
            $small_he_she = 'he';
            $mr_miss = 'Mr.';
            $daughter_son = 'son';
            $mast_miss = 'Mast.';
        } else if(in_array($value['gender'],$females)){
            $cap_his_her = 'Her';
            $small_his_her = 'her';
            $cap_he_she = 'She';
            $small_he_she = 'she';
            $mr_miss = 'Miss.';
            $daughter_son = 'daughter';
            $mast_miss = 'Miss.';
        }    
        $resultController = new studentResultController;
        $getSub = $resultController->get_subject($sub_institute_id,$syear,$value['id'],$value['standard_id']);
        $subject_names="";
        if(!empty($getSub)){
            $subject_names = array_map(function($subject) {
                return $subject->subject_name;
            }, $getSub);
            $subject_names = implode(',',$subject_names);
        }
        /*
        $get_standard_subjects = DB::table('sub_std_map as ssm')
      ->select(DB::raw('GROUP_CONCAT(IFNULL(ssm.display_name, "-")) as subject_name'),DB::raw('GROUP_CONCAT(IFNULL(ssm.elective_subject, "-")) as elective_subject'))
        ->join('standard as s', 's.id', '=', 'ssm.standard_id')
        ->where('ssm.sub_institute_id', session()->get('sub_institute_id'))
        ->where('ssm.standard_id', $value['standard_id'])
        ->where('ssm.allow_grades', 'Yes')
        ->groupBy('ssm.standard_id')
        ->orderBy('ssm.sort_order')
        ->first();
        */
        
        /*$get_standard_subjects = DB::table('sub_std_map as ssm')
            ->select(
                DB::raw('GROUP_CONCAT(IFNULL(ssm.display_name, "-") ORDER BY ssm.sort_order ASC) as subject_name'),
                DB::raw('GROUP_CONCAT(IFNULL(ssm.elective_subject, "No") ORDER BY ssm.sort_order ASC) as elective_subject')
            )
            ->join('standard as s', 's.id', '=', 'ssm.standard_id')
            ->join('tblstudent_enrollment as te', 'te.standard_id', '=', 's.id')
            ->leftJoin('student_optional_subject as sos', function($join) {
                $join->on('sos.subject_id', '=', 'ssm.subject_id')
                     ->where('sos.student_id', '=', 'te.student_id')
                     ->where('sos.syear', '=', 'te.syear');
            })
            ->where('ssm.sub_institute_id', session()->get('sub_institute_id'))
            ->where('te.standard_id', $value['standard_id'])
            ->where('te.student_id', $value['id'])
            ->where('ssm.allow_grades', 'Yes')
            ->where(function($query) {
                $query->where('ssm.elective_subject', 'No')
                      ->orWhereNotNull('sos.id');
            })
            ->where('te.syear', session()->get('syear'))
            ->groupBy('ssm.standard_id')
            ->first();
*/

        $get_standard_subjects = DB::select("SELECT GROUP_CONCAT(IFNULL(ssm.display_name, '-') ORDER BY ssm.sort_order ASC) AS subject_name,
            GROUP_CONCAT(IFNULL(ssm.elective_subject, '-') ORDER BY ssm.sort_order ASC) AS elective_subject
FROM `sub_std_map` AS `ssm`
INNER JOIN `standard` AS `s` ON `s`.`id` = `ssm`.`standard_id`
INNER JOIN `tblstudent_enrollment` AS `te` ON `te`.`standard_id` = `s`.`id`
LEFT JOIN `student_optional_subject` AS `sos` ON `sos`.`subject_id` = `ssm`.`subject_id` AND `sos`.`student_id` = te.student_id AND `sos`.`syear` = te.syear
WHERE `ssm`.`sub_institute_id` = ".session()->get('sub_institute_id')." AND `te`.`standard_id` = ".$value['standard_id']." AND `te`.`student_id` = ".$value['id']." AND `ssm`.`allow_grades` = 'Yes' AND (`ssm`.`elective_subject` = 'NO' OR `sos`.`id` IS NOT NULL) AND `te`.`syear` = ".session()->get('syear')."
GROUP BY `ssm`.`standard_id`
ORDER BY `ssm`.`sort_order` ASC
LIMIT 1");            
        $mainSub = $optionalSub = [];
        //echo "<pre>";print_r($get_standard_subjects[0]);exit;
        // mmis optional and main subject in different lines
        if($sub_institute_id==47){
            if(isset($get_standard_subjects[0]->elective_subject)){

                $electiveExplode = explode(',',$get_standard_subjects[0]->elective_subject);
                $subjectExplode = explode(',',$get_standard_subjects[0]->subject_name);

                foreach($electiveExplode as $key=>$val){
                    if($val=='Yes'){
                        $optionalSub[] = $subjectExplode[$key]; 
                    }else{
                        $mainSub[] = $subjectExplode[$key]; 
                    }
                }
            }
            $optionalImplode = implode(', ',$optionalSub);
            $subjectImplode = implode(', ',$mainSub);
            $tdOptionalData = $subjectImplode.'<br>'.$optionalImplode;
        }else{
            $tdOptionalData = $get_standard_subjects[0]->subject_name ?? '-';
        }

        $get_student_attendances = DB::table('attendance_student')
        ->select(DB::raw('COUNT(id) as total_att_days,sum(CASE WHEN attendance_code = "P" THEN 1 ELSE 0 END) as present_att_days'))
        ->where('sub_institute_id', session()->get('sub_institute_id'))
        ->where('syear', session()->get('syear'))
        ->where('standard_id', $value['standard_id'])
        ->where('student_id', $value['id'])
        ->first();

        // for mr/miss and daughter/son 
        $html_content = str_replace(htmlspecialchars("<<mr_miss>>"), $mr_miss, $html_content); 
        $html_content = str_replace(htmlspecialchars("<<daughter_or_son>>"), $daughter_son, $html_content);

        //Start Bonafide certificate Tags

        $student_full_name = strtoupper($value['student_full_name']);
        $student_first_name = strtoupper($value['student_first_name']);
        $student_middle_name = strtoupper($value['student_middle_name']);
        $student_last_name = strtoupper($value['student_last_name']);
        $father_name = strtoupper($value['father_name']);
        $mother_name = strtoupper($value['mother_name']);
        $nationality = strtoupper($value['nationality']);
        $place_of_birth = strtoupper($value['place_of_birth']);

        // make first letter capital only for ssmission school added on 08-04-2025
        if(in_array($sub_institute_id,[76])){
            $firstLetterArr = ['student_full_name','student_first_name','student_middle_name','student_last_name','father_name','mother_name','nationality','place_of_birth'];
            foreach ($firstLetterArr as $fkey => $fvalue) {
                $explodeFval = explode(' ', strtolower($value[$fvalue]));
                $explodeFval = array_map('ucwords', array_map('trim', $explodeFval));
                $$fvalue = implode(' ', $explodeFval);
            }
        }

        $html_content = str_replace(htmlspecialchars("<<student_image_value>>"), $student_image_path, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_name_value>>"), $student_full_name,$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_first_name_value>>"), $student_first_name,$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_middle_name_value>>"), $student_middle_name,$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_last_name_value>>"), $student_last_name,$html_content);
        $html_content = str_replace(htmlspecialchars("<<father_name_value>>"), $father_name,$html_content);
        $html_content = str_replace(htmlspecialchars("<<mother_name_value>>"), $mother_name,$html_content);
        $html_content = str_replace(htmlspecialchars("<<nationality_value>>"), $nationality,$html_content);
        $html_content = str_replace(htmlspecialchars("<<place_of_birth_value>>"), $place_of_birth,$html_content);
        
        $html_content = str_replace(htmlspecialchars("<<student_enrollment_value>>"), $value['enrollment_no'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_roll_no_value>>"), $value['roll_no'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_standard_value>>"), $value['standard_name'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_division_value>>"), $value['division_name'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_year_value>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<aadhar_number>>"), $value['adharnumber'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_gender>>"), strtoupper($value['gender']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<admission_no>>"), $value['admission_no'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_unique_id>>"), $value['unique_id'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_mobile_value>>"), $value['mobile'],$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_value>>"), date('d-m-Y', strtotime($value['dob'])),$html_content);
        $html_content = str_replace(htmlspecialchars("<<current_date>>"), date('d-M-Y'), $html_content);
        $html_content = str_replace(htmlspecialchars("<<current_date_dmy>>"), date('d-m-Y'), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_word_value>>"), $date_in_word, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dise_uid_value>>"), $value['dise_uid'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<certificate_no>>"), $certificate_no, $html_content);
        $html_content = str_replace(htmlspecialchars("<<HIS_HER>>"), $cap_his_her, $html_content);
        $html_content = str_replace(htmlspecialchars("<<his_her>>"), $small_his_her, $html_content);
        $html_content = str_replace(htmlspecialchars("<<HE_SHE>>"), $cap_he_she, $html_content);
        $html_content = str_replace(htmlspecialchars("<<he_she>>"), $small_he_she, $html_content);
        $html_content = str_replace(htmlspecialchars("<<mast_miss>>"), $mast_miss, $html_content);
        $html_content = str_replace(htmlspecialchars("<<certificate_reason>>"), $certificate_reason, $html_content);
        //END Bonafide certificate Tags
        // transfer certificate detals
        $candidate_belongs_to = $last_school_board=$whether_failed = $whether_qualified = $general_conduct = $games_played = $any_other_remarks= $reason_for_leave="<br>";
        $transfer_details = DB::table('tblstudent_tc_details')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->where('student_id',$value['id'])->first();
        if(isset($transfer_details->student_id)){
            $candidate_belongs_to = $transfer_details->candidate_belongs_to;
            $whether_failed =$transfer_details->whether_failed;
            $whether_qualified =$transfer_details->whether_qualified;
            $general_conduct =$transfer_details->general_conduct;
            $games_played =$transfer_details->games_played;
            $any_other_remarks=$transfer_details->any_other_remarks;
            $last_school_board = $transfer_details->last_school_board;
            $reason_for_leave = $transfer_details->reason_leaving_school;
        }
        // for ssmission first letter capital values
        if(in_array($sub_institute_id,[76])){
            $candidate_belongs_to = explode(' ', strtolower($candidate_belongs_to));
            $candidate_belongs_to = array_map('ucwords', array_map('trim', $candidate_belongs_to));
            $candidate_belongs_to = implode(' ', $candidate_belongs_to);
        }

        $html_content = str_replace(htmlspecialchars("<<candidate_belongs_to>>"), $candidate_belongs_to,$html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_failed>>"), $whether_failed,$html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_qualified>>"), $whether_qualified,$html_content);
        $html_content = str_replace(htmlspecialchars("<<general_conduct>>"), $general_conduct,$html_content);
        $html_content = str_replace(htmlspecialchars("<<games_played>>"), $games_played,$html_content);
        $html_content = str_replace(htmlspecialchars("<<any_other_remarks>>"), $any_other_remarks,$html_content);
        $html_content = str_replace(htmlspecialchars("<<last_school_board>>"), $last_school_board,$html_content);  
        $html_content = str_replace(htmlspecialchars("<<reason_to_leave>>"), $reason_for_leave,$html_content);        

        //Start Transfer certificate Tags
        $html_content = str_replace(htmlspecialchars("<<affiliation_no_value>>"), strtoupper($value['affiliation_no']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<school_code_value>>"), strtoupper($value['school_code']),
            $html_content);
  
        $html_content = str_replace(htmlspecialchars("<<religion_name_value>>"), strtoupper($value['religion_name']),
            $html_content);
            $caste_name = $value['caste_name'];
            if($sub_institute_id==47 && isset($caste_name) && $caste_name!=''){
                $caste_name = "(".$caste_name.")";
            }
        $html_content = str_replace(htmlspecialchars("<<caste_name_value>>"), strtoupper($caste_name),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<subcast_value>>"), strtoupper($value['subcast']),
            $html_content);
            $admission_std = '';
            
        $html_content = str_replace(htmlspecialchars("<<admission_date_value>>"), date('d-m-Y', strtotime($value['admission_date'])),
            $html_content);
		$html_content = str_replace(htmlspecialchars("<<short_standard_name_value>>"), strtoupper($value['short_standard_name']), $html_content);
        //$standard_array = ['I' => 1,'II' => 2,'III' => 3,'IV' => 4,'V' => 5,'VI' => 6,'VII' => 7,'VIII' => 8,'IX' => 9,'X' => 10,'XI' => 11,'XII' => 12];
        //$std = $standard_array[$value['short_standard_name']] ?? 0;
        $html_content = str_replace(htmlspecialchars("<<short_standard_name_in_word_value>>"), $value['school_stream'], $html_content);
        /*if($sub_institute_id==254){
            $html_content = str_replace(htmlspecialchars("<<subjects_studied_system>>"),strtoupper($subject_names),$html_content);
        }else*/{
            $html_content = str_replace(htmlspecialchars("<<subjects_studied_system>>"),strtoupper($tdOptionalData),$html_content);
        }

        // for mmis auto fetch remarks from result_remarks
        $curr_std = DB::table('standard')->where('id', $value['standard_id'])->first();
        $next_std = DB::table('standard')->where('id', $curr_std->next_standard_id)->first();
        $getRemarks = DB::table('result_remarks')->where('student_id', $value['id'])->where('syear',$syear)->latest()->first();
        $nextStd = (isset($next_std->school_stream) && $next_std->school_stream!='') ? $next_std->school_stream: '';
        $currentStd = (isset($curr_std->school_stream) && $curr_std->school_stream!='') ? $curr_std->school_stream : '';

        $result_remarks = $stu_remarks = $stu_remarks_input = '';
        if(!empty($getRemarks) && isset($getRemarks->result_remarks)){
            $explodeVal = explode('||',$getRemarks->result_remarks); 
            $stu_remarks = (isset($explodeVal[0]) && $explodeVal[0]!='') ? $explodeVal[0] : '';
            $stu_remarks_input = (isset($explodeVal[1]) && $explodeVal[1]!='') ? $explodeVal[1] : '';
        }
        if($stu_remarks!='' && $stu_remarks_input!=''){
            $result_remarks = $stu_remarks.'-'.$stu_remarks_input;
        }
        else if ($stu_remarks=='' && $stu_remarks_input!='') {
            $result_remarks = 'Passed & Promoted to Class ' . $nextStd .'-'.$stu_remarks_input; 
        }
        else if($stu_remarks!=''){
            $result_remarks = $stu_remarks;
        }
        else if($stu_remarks_input!=''){
            $result_remarks = $stu_remarks_input;
        }
        else{
            $result_remarks = isset($value['last_school_board']) ? strtoupper($value['last_school_board']) : 'Passed & Promoted to Class ' . $nextStd;
        }
        // echo "<pre>";print_r($value);exit;
        $curr_std_medium = (isset($curr_std->medium)) ? $curr_std->medium: '';
        $next_std_medium = (isset($next_std->medium)) ? $next_std->medium: '';

        $nextStdName = (isset($next_std->name)) ? $next_std->name: '';
        $nextStdStream = (isset($next_std->school_stream)) ? $next_std->school_stream: '';
        $nextStdShortName = (isset($next_std->short_name)) ? $next_std->short_name: '';
        // make first letter capital only for ssmission school added on 08-04-2025
        $next_std_name = strtoupper($nextStdName);
        $next_std_stream = strtoupper($nextStdStream);
        $next_std_short_name = strtoupper($nextStdShortName);
        
        if(in_array($sub_institute_id,[76])){
            $firstLetterArr = ['nextStdName'=>'next_std_name','nextStdStream'=>'next_std_stream','nextStdShortName'=>'next_std_short_name'];
            foreach ($firstLetterArr as $fkey => $fvalue) {
                $explodeFval = explode(' ', strtolower($$fkey));
                $explodeFval = array_map('ucwords', array_map('trim', $explodeFval));
                $$fvalue = implode(' ', $explodeFval);
            }
        }
        $html_content = str_replace(htmlspecialchars("<<next_std_name>>"),$next_std_name, $html_content);
        $html_content = str_replace(htmlspecialchars("<<next_std_stream>>"),$next_std_stream, $html_content);
        $html_content = str_replace(htmlspecialchars("<<next_std_short_name>>"),$next_std_short_name, $html_content);
        $html_content = str_replace(htmlspecialchars("<<current_medium>>"),$curr_std_medium, $html_content);
        $html_content = str_replace(htmlspecialchars("<<next_medium>>"),$next_std_medium, $html_content);

        $html_content = str_replace(htmlspecialchars("<<annual_value_result>>"),strtoupper($result_remarks), $html_content); 

        $failPass = 'CLASS '.$nextStd;
        if(isset($value['whether_failed']) && in_array($value['whether_failed'],['Yes','YES','yes','Y'])){
            $failPass = "CLASS ".$currentStd;
        }
        $html_content = str_replace(htmlspecialchars("<<whether_qualified_value_result>>"),
        strtoupper($failPass), $html_content);
        // for mmis auto fetch remarks from end
        $subjects_studied = strtoupper($value['subjects_studied']);
        // for ssmission make only first letter capital on 08-04-2025
        if(in_array($sub_institute_id,[76])){
            $subjects = explode(',', strtolower($value['subjects_studied']));
            $subjects = array_map('ucwords', array_map('trim', $subjects));
            $subjects_studied = implode(', ', $subjects);
        }
        $html_content = str_replace(htmlspecialchars("<<subjects_studied_value>>"),$subjects_studied, $html_content);
        $html_content = str_replace(htmlspecialchars("<<candidate_belongs_to_value>>"),$candidate_belongs_to, $html_content); 
        // ssmission first letter capital
        $html_content = str_replace(htmlspecialchars("<<date_of_first_admission_value>>"),$value['date_of_first_admission'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_in_which_pupil_last_studied_value>>"),
            strtoupper($value['class_in_which_pupil_last_studied']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<last_school_board_value>>"),
            strtoupper($value['last_school_board']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_failed_value>>"), strtoupper($value['whether_failed']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_qualified_value>>"),
            strtoupper($value['whether_qualified']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<teacher_remark_value>>"),
            $value['teacher_remark'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<if_to_which_class_value>>"),
            strtoupper($value['if_to_which_class']), $html_content);

    $months = FeeMonthId();
    $month = '';
    // added on 15-04-2024 by uma to get last paid fees month
        $getLastFeesPaid = DB::table('fees_collect')->where('sub_institute_id',$sub_institute_id)->where('syear',$syear)->where('student_id',$value['id'])->where('is_deleted','N')->groupBy('term_id')->get();

        $lastPaidfees = [];
        foreach ($getLastFeesPaid as $key => $values) {
            $term_id = $values->term_id; 
            $FeesYear = substr($term_id, -4);
            $FeesMonth = substr($term_id, 0,-4);
            $lastPaidfees[$term_id] = ($FeesYear * 12) + $FeesMonth;
        }
        if(!empty($lastPaidfees)){
            $maxMonth = array_keys($lastPaidfees, max($lastPaidfees))[0];
            if(isset($months[$maxMonth])){
                $month = $months[$maxMonth];    
            }
        }else{
            // (Hills) if RTE quota fees paid not getting data so print as per TC Info
            $month = $value['month_up_paid_school_dues'];
        }
        // echo "<pre>";print_r($month);exit;
        // end 15-04-2024
        // start 20-03-2025 
        
        $monthList = ['Jan' => 'JANUARY','Feb' => 'FEBRUARY','Mar' => 'MARCH','Apr' => 'APRIL','May' => 'MAY','Jun' => 'JUNE','Jul' => 'JULY','Aug' => 'AUGUST','Sep' => 'SEPTEMBER','Oct' => 'OCTOBER','Nov' => 'NOVEMBER','Dec' => 'DECEMBER'];
        if($sub_institute_id==47){

            $getFees = DB::table('fees_collect')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'is_deleted'=>'N','student_id'=>$value['id']])->latest('id')->first();

            $getFeesTitle = DB::table('fees_title')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->orderBy('sort_order')->get()->toArray();
            $paidFees = '';
            foreach ($getFeesTitle as $fk => $ft) {
                if(isset($getFees->{$ft->fees_title}) && $getFees->{$ft->fees_title}!=0){
                    $paidFees =$ft->append_name;
                    foreach ($months as $mk => $mv) {
                        if (strpos($mv, $paidFees) !== false) {
                            $explode = explode('/',$mv); // Output: Apr/2024
                            $feeMoth = $explode[0] ? $explode[0] : '-';
                            $feeYear = $explode[1] ? $explode[1] : '-';
                            $monthName = isset($monthList[$feeMoth]) ? $monthList[$feeMoth] : $feeMoth;
                            $paidFees = $monthName.'/'.$feeYear;
                            break;
                        }
                    }
                    
                }
            }
            $month = $paidFees;
        }
        // echo "<pre>";print_r($paidFees);exit;

        // end 20-03-2025
        $html_content = str_replace(htmlspecialchars("<<month_name_value>>"),
            strtoupper($month), $html_content);
        $html_content = str_replace(htmlspecialchars("<<month_up_paid_school_dues_value>>"),
            strtoupper($value['month_up_paid_school_dues']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<admission_under_value>>"),
            strtoupper($value['admission_under']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<total_working_days_value>>"),strtoupper($value['total_working_days']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<total_working_days_present_value>>"),strtoupper($value['total_working_days_present']), $html_content);

       
        $standard_id = $value['standard_id'];
        $student_id = $value['id'];

        $get_term = DB::table('academic_year')->selectRaw('group_concat(id) as id,group_concat(term_id) as term_id,group_concat(title) as title,group_concat(start_date) as start_date,group_concat(end_date) as end_date,group_concat(post_start_date) as post_start_date,group_concat(post_end_date) as post_end_date')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->groupBy('syear')->first();
        // echo "<pre>";print_r($student_id);exit;
        $post_start_date_ex = explode(',',$get_term->post_start_date);
        $post_end_date_ex = explode(',',$get_term->post_end_date);
        $post_start_date_final_ex = explode(',',$get_term->post_start_date);
        $post_end_date_final_ex = explode(',',$get_term->post_end_date);

        $post_start_date =  isset($post_start_date_ex[0]) ? $post_start_date_ex[0] : '-';
        $post_end_date = isset($post_end_date_ex[1]) ? $post_end_date_ex[1] : $post_end_date_ex[0];
        $post_start_date_final =  isset($post_start_date_final_ex[0]) ? $post_start_date_final_ex[0] : '-';
        $post_end_date_final = isset($post_end_date_final_ex[1]) ? $post_end_date_final_ex[1] : $post_end_date_final_ex[0];

        $cal_event = DB::table('calendar_events as ce')
            ->join('academic_year as ay', 'ce.syear', '=', 'ay.syear')
            ->where(['ce.sub_institute_id' => $sub_institute_id, 'ce.syear' => $syear])
            ->WhereRaw("FIND_IN_SET('$standard_id', ce.standard) AND ce.event_type in ('holiday','vacation')")
            ->whereBetween('ce.school_date', [$post_start_date, $post_end_date])
            ->groupBy('ce.school_date')
            ->get()
            ->toArray();

        $calArr = array();
        foreach ($cal_event as $calRow) {
            $calArr[] = $calRow->school_date;
        }

        $attTotDays = 0;
        while ($post_start_date <= $post_end_date) {
            if (date('w', strtotime($post_start_date)) != 0) {
                $attTotDays++;
            }
            $post_start_date = date('Y-m-d', strtotime($post_start_date . ' +1 day'));
        }
        $attTotDays = $attTotDays - count($calArr);

        $attarray = DB::table('attendance_student as ap')
            ->join('tblstudent as s', 'ap.student_id', '=', 's.id')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('s.id', '=', 'se.student_id')
                    ->whereNull('se.end_date');
            })
            ->select('s.id', 's.first_name', DB::raw('COUNT(DISTINCT ap.attendance_date) AS present_day'))
            ->where('se.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->where('se.standard_id', $standard_id)
            ->where('ap.student_id', $student_id)
            ->where('ap.attendance_code', 'P')
            ->whereBetween('ap.attendance_date', [$post_start_date_final, $post_end_date_final])
            ->groupBy('s.id')
            ->get();
        $attarray = $attarray->pluck('present_day', 'id')->all();

        if($sub_institute_id==254){
            $working_day =  $attTotDays;
            $present_day = isset($attarray[$student_id]) ? $attarray[$student_id] : '-';
        }else{
            $working_day = $get_student_attendances->total_att_days;
            $present_day = $get_student_attendances->present_att_days;
        }

        $html_content = str_replace(htmlspecialchars("<<total_working_days_system>>"),strtoupper($working_day), $html_content);
        $html_content = str_replace(htmlspecialchars("<<total_working_days_present_system>>"),strtoupper($present_day), $html_content);

        $html_content = str_replace(htmlspecialchars("<<games_played_value>>"), strtoupper($value['games_played']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<general_conduct_value>>"),
            strtoupper($value['general_conduct']), $html_content);
        $date_application_for_certificate = now();
        $date_on_which_pupil_name = now();
        $date_of_issue_of_certificate_new = now();
        $html_content = str_replace(htmlspecialchars("<<date_application_for_certificate_value>>"),
            date('d-m-Y', strtotime($date_application_for_certificate)), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_on_which_pupil_name_value>>"),
            date('d-m-Y', strtotime($date_on_which_pupil_name)), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_issue_of_certificate_new_value>>"),
            date('d-m-Y', strtotime($date_of_issue_of_certificate_new)), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_application_for_certificate_value>>"),
            date('d-m-Y', strtotime($value['date_of_application_for_certificate'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_issue_of_certificate_value>>"),
            date('d-m-Y', strtotime($value['date_of_issue_of_certificate'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<reason_leaving_school_value>>"),
            strtoupper($value['reason_leaving_school']), $html_content);
        $proof_for_dob = $value['proof_for_dob'];
        if(in_array($sub_institute_id,[76])){
            $explodeFval = explode(' ', strtolower($value['proof_for_dob']));
            $explodeFval = array_map('ucwords', array_map('trim', $explodeFval));
            $proof_for_dob = implode(' ', $explodeFval);
        }
    
        $html_content = str_replace(htmlspecialchars("<<proof_for_dob_value>>"), $proof_for_dob,$html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_school_is_under_goverment_value>>"),
            strtoupper($value['whether_school_is_under_goverment']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_on_which_pupil_name_was_struck_value>>"),
            date('d-m-Y', strtotime($value['date_on_which_pupil_name_was_struck'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<any_fees_concession_value>>"),
            strtoupper(isset($value['any_fees_concession']) && !empty($value['any_fees_concession']) ? $value['any_fees_concession'] : 'No'), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_ncc_cadet_value>>"),
            strtoupper($value['whether_ncc_cadet']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<any_other_remarks_value>>"),
            strtoupper($value['any_other_remarks']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_uniqueid_value>>"), strtoupper($value['unique_id']),
            $html_content);
       
        // student fees cetificate

    $html_content = str_replace(htmlspecialchars("<<student_father_name>>"),  strtoupper($value['father_name']) ,$html_content);
        
    $fees_data = DB::table('fees_collect')
    ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'student_id' => $value['id'], 'is_deleted' => 'N']);

    $fees_data_other = DB::table('fees_paid_other')
        ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'student_id' => $value['id'], 'is_deleted' => 'N'])
        ->get();

    $fees_heads = DB::table('fees_title')
        ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])
        ->orderBy('sort_order')
        ->get();

    $fees_data_month =  $fees_data;
    $fees_data =  $fees_data->get();

    $fees_month = $fees_data_month->groupBy('term_id')->get();

    $totalAmount = 0;
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
        10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

        $fees_details = "<h4 style='text-align:center;line-height: 150%;'><u>Apr-" . $syear . " To Mar-" . ($syear + 1) . "</u></h4>
                <div style='width:100%'>
                    <table align='center' width='60%'>";

        foreach ($fees_heads as $title) {
            $fees_data_sum = $fees_data->sum($title->fees_title);
            $fees_data_other_sum = $fees_data_other->sum($title->fees_title);

            if ($fees_data_sum > 0 || $fees_data_other_sum > 0) {
                $fees_details .= "<tr><td width='50%' style='font-weight:600;line-height: 150%;text-align:left'>" . $title->display_name . "</td>";

                $termIds = $fees_month->filter(function ($fees) use ($title) {
                    return isset($fees->{$title->fees_title});
                })->pluck('term_id')->toArray();

                $month_name = [];

                foreach ($termIds as $arr) {
                    $y = $arr / 10000;
                    $month = (int)$y;
                    $year = substr($arr, -4);
                    $month_name[$year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)] = $months[$month] . '-' . $year;
                }

                if (!empty($month_name)) {
                    uksort($month_name, function ($a, $b) {
                        return strtotime($a . '-01') <=> strtotime($b . '-01');
                    });
                }

                $fees_details .= "<td width='50%' style='font-size:14px;text-align:left'>" . ($fees_data_sum + $fees_data_other_sum) . "/- ";

                if (!empty($month_name)) {
                    $firstMonth = reset($month_name);
                    $lastMonth = end($month_name);
                    $print_month = ["Tuition Fees", "Computer Fees", "Security Charges", "Smart Class"];

                    if (in_array($title->display_name, $print_month)) {
                        $fees_details .= "(" . $firstMonth;
                        $fees_details .= ($firstMonth !== $lastMonth) ? " To " . $lastMonth . ")" : ")";
                    }
                }

                $fees_details .= "</td></tr>";
                $totalAmount += $fees_data_sum + $fees_data_other_sum;
            }
        }

        $fees_details .="<tr>
        <td style='font-weight:600;line-height: 170%;text-align:left;font-size:16px'>Total</td>
        <td style='font-weight:600;line-height: 170%;text-align:left;font-size:16px'>".$totalAmount."/- </td>
        </tr>";
        $fees_details .= "
            </table></div>
        ";
        // Output the total amount
        // $fees_details .= "<p>Total Amount: $totalAmount</p>";
        $html_content = str_replace(htmlspecialchars("<<fees_details>>"), $fees_details ,$html_content);
        $html_content = str_replace(htmlspecialchars("<<total_amount_in_words>>"), ucwords($this->convert_number_to_words($totalAmount)) ,$html_content);
        return $html_content;
    }

    public function ajax_saveData(Request $request)
    {
        $student_id = $request->input('insert_student_ids');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $template = $request->input('template');
        $student_ids = explode(',', $student_id);
        $certificate_reason = $request->input('certificate_reason');

        // 22-03-2025 start duplication prevent
        $studentDetails = DB::table('certificate_history as a')
        ->join('tblstudent as s','s.id','=','a.student_id')
        ->selectRaw('a.certificate_number')
        ->where(['a.sub_institute_id'=>$sub_institute_id,'a.syear'=>$syear])
        ->where('a.certificate_type', $template)
        ->where('a.student_id',$student_ids)
        ->first();
        $studentEnroll = [];
        if(isset($studentDetails->certificate_number) && !in_array($studentDetails->certificate_number,$studentEnroll)){
            $studentEnroll[] = $studentDetails->certificate_number;
        }
        if(!empty($studentEnroll) && $template == 'Transfer Certificate'){
            $res['status_code'] = 0;
            $res['message'] = "Certificate is already Issued with Certificate No ".implode(',',$studentEnroll).". Please check in Student Certificate Report.";
            return $res;     
        }
        // 22-03-2025 end duplication prevent
        
        $data = getStudents($student_ids);

        $tData = DB::table('template_master')
            ->where('module_name', $template)
            ->whereRaw('sub_institute_id = IFNULL(
                (SELECT sub_institute_id FROM template_master WHERE module_name ="'.$template.'" AND 
                    sub_institute_id = "'.session()->get('sub_institute_id').'"
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
        $i=0;
        foreach ($data as $key => $value) {
            
       $certificate_no_result = DB::table('certificate_history as c')
                ->selectRaw('(IFNULL(MAX(cast(c.certificate_number AS UNSIGNED)),0) + 1) AS certificate_no')
                ->where('c.sub_institute_id', $sub_institute_id)
                ->where('certificate_type', $template)
                ->where('syear', $syear)->get()->toArray();

            $certificate_no = $certificate_no_result[0]->certificate_no;

            if(in_array($template,['Transfer Certificate','Bonafide']) && $sub_institute_id==254){
                $certificate_no_result = DB::table('certificate_history as c')
                ->selectRaw('c.certificate_number')
                ->where('c.sub_institute_id', $sub_institute_id)
                ->where('certificate_type', $template)
                ->where('syear', $syear)->orderBy('id','DESC')->first();
                $year = substr($syear,'-2');
                $prefix = $year.'-'.($year+1);
                $getLastNo = explode('/',$certificate_no_result->certificate_number ?? 0);
                
                if($template=='Bonafide'){
                    $cno =($getLastNo[2] ?? 0)+ 1 + $i;
                    $certificate_no = 'BC/'.$prefix.'/'.$cno;
                }else{
                    $cno =($getLastNo[1] ?? 0)+ 1;
                    $certificate_no = $prefix.'/'.$cno;
                }
            }
            $html_content = $tData[0]['html_content'];
            
            $new_html_content = $this->create_html_content($syear, $sub_institute_id, $html_content, $value,
                $receipt_book_arr, $template, $certificate_no, $certificate_reason);
                
                DB::table('certificate_history')->insert([
                    'syear'              => $syear,
                    'student_id'         => $value['id'],
                    'certificate_type'   => $template,
                    'sub_institute_id'   => $sub_institute_id,
                    'certificate_number' => $certificate_no,
                    'certificate_html'   => $new_html_content,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                $last_inserted_id = DB::getPdo()->lastInsertId();
                $last_insert_ids .= $last_inserted_id.',';
    }
        $res['status_code'] = 1;
        $res['message'] ='Added Successfully !!';
        $res['certificate_id'] = rtrim($last_insert_ids, ',');
        return $res;
    }

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = [
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty',
            30                  => 'thirty',
            40                  => 'forty',
            50                  => 'fifty',
            60                  => 'sixty',
            70                  => 'seventy',
            80                  => 'eighty',
            90                  => 'ninety',
            100                 => 'hundred',
            1000                => 'thousand',
            100000              => 'lakh',
            10000000            => 'crore',
        ];

        if (!is_numeric($number)) {
            return false;
        }

        if ($number < 0) {
            return $negative . $this->convert_number_to_words(abs($number));
        }

        $string = '';

        if ($number >= 10000000) {
            $crores = floor($number / 10000000);
            $number %= 10000000;
            $string .= $this->convert_number_to_words($crores) . ' crore ';
        }

        if ($number >= 100000) {
            $lakhs = floor($number / 100000);
            $number %= 100000;
            $string .= $this->convert_number_to_words($lakhs) . ' lakh ';
        }

        if ($number >= 1000) {
            $thousands = floor($number / 1000);
            $number %= 1000;
            $string .= $this->convert_number_to_words($thousands) . ' thousand ';
        }

        if ($number >= 100) {
            $hundreds = floor($number / 100);
            $number %= 100;
            $string .= $this->convert_number_to_words($hundreds) . ' hundred ';
        }

        if ($number > 0) {
            if ($string !== '') {
                $string .= $conjunction;
            }

            if ($number < 21) {
                $string .= $dictionary[$number];
            } else {
                $tens = floor($number / 10) * 10;
                $units = $number % 10;
                $string .= $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
            }
        }

        return trim($string);
    }

}
