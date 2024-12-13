<?php

namespace App\Http\Controllers\result\new_result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\result\result_template;
use function App\Helpers\SearchStudent;
use function App\Helpers\getStudents;
use DB;
use PDF;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class studentResultController extends Controller
{
    //
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $data['data'] = result_template::where('sub_institute_id', $sub_institute_id)->where('status', 1)->orderBy('sort_order')->get()->toArray();
        if (empty($data['data'])) {
            $data['data'] = result_template::where('sub_institute_id', 0)->where('status', 1)->orderBy('sort_order')->get()->toArray();
        }
        $data['terms'] = DB::table('academic_year')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
         if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            $data['status_code']=$data_arr['status_code'];
            $data['message']=$data_arr['message'];
        }
        $data['result_types'] = ['Regular','HPC'];
        // echo "<pre>";print_r($data['data']);exit;
        return is_mobile($type, "result/new_result/student_results/show", $data, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $template = $request->input('template');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $format = $request->input('format');
        $result_type = $request->input('result_type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        // get students
        $studentData = SearchStudent($grade, $standard, $division);
        
        $res['data'] = result_template::where('sub_institute_id', $sub_institute_id)->where('status', 1)->orderBy('sort_order')->get()->toArray();
        if (empty($res['data'])) {
            $res['data'] = result_template::where('sub_institute_id', 0)->where('status', 1)->orderBy('sort_order')->get()->toArray();
        }
        $res['terms'] = DB::table('academic_year')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['template'] = $template;
        $res['format'] = $format;
        $res['student_data'] = $studentData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['result_type'] = $result_type;
        $res['result_types'] = ['Regular','HPC'];
        if (empty($studentData)) {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";
            // echo "no student";exit;
            return is_mobile($type, "student-result.index", $res,'redirect');
        }else{
            return is_mobile($type, "result/new_result/student_results/show", $res, "view");
        }
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $template = $request->input('template_id') ?? 0;
        $student_ids = $request->input('students');
        $format = $request->input('format');
        $result_type = $request->input('result_type');

        // get selectd students 
        $data = getStudents($student_ids);

        $tData = result_template::where('id', $template)
            ->whereRaw('sub_institute_id = IFNULL(
                (SELECT sub_institute_id FROM result_template_master WHERE id ="' . $template . '" AND 
                    sub_institute_id = "' . $sub_institute_id . '"
                ),0)')->get()->toArray();
        $tData = json_decode(json_encode($tData), true);

        $result_trust = DB::table('result_book_master as rbm')
            ->join('result_trust_master as rtm', 'rtm.id', '=', 'rbm.trust_id')
            ->where('rbm.sub_institute_id', $sub_institute_id)
            ->when($request->standard_id,function($q) use($request){
                $q->where('rbm.standard', $request->standard_id);
            })
            ->where('rtm.syear', $syear)
            ->select('rbm.*', 'rtm.*') // You can specify the columns you want to select
            ->first();

        $last_insert_ids = '';
        $new_html = '';
        $all_stud_html = array();
        foreach ($data as $key => $value) {
            $html_content = $tData[0]['html_content'];
            $class = '';
            if (in_array($sub_institute_id,[201,202,203,254])){ 
                $class = 'class="report-card-bg"';
            }else{
                $class = 'class="report-card-bg"';
            }
            
            $new_html_content = '<div id="' . $value['id'] . '" ' . $class . ' style="page-break-before:avoid !important;page-break:always !important;">' . $this->create_html_content($syear, $sub_institute_id, $html_content, $value, $template, $result_trust, $format) . '</div>';
            $new_html .= $new_html_content;
            $all_stud_html[$value['id']] = $new_html_content;
        }
        $type = "";
        if ($format == "yearly") {
            $format = session()->get('term_id');
        }
        $data['html'] = $new_html;
        $data['standard_id'] = $request->standard_id;
        $data['grade_id'] = $request->grade_id;
        $data['division_id'] = $request->division_id;
        $data['term_id'] = $format;
        $data['syear'] = $syear;
        $data['result_type'] = $result_type;
        $data['all_stud_html'] = $all_stud_html;
        $data['students_ids'] = $request->students;

        return is_mobile($type, "result/new_result/student_results/result_view", $data, "view");
    }

    public function create_html_content($syear, $sub_institute_id, $html_content, $value, $template, $result_trust, $format)
    {
        // echo "<pre>";print_r($value);exit;
        $height_large = array(47);
        $height_medium = array(195,72);
        $width = array(61);
        $grade_id = $value['grade_id'] ?? 0;

        $logo_height = "50px !important";
        $photo_height = "90px !important";
        $photo_width = "";
        if (in_array($sub_institute_id, $height_large)) {
            $logo_height = "120px !important";
            $photo_height = "100px !important";
            $photo_width = "";
        }elseif(in_array($sub_institute_id, $height_medium)){
            $logo_height = "90px !important";
            $photo_height = "100px !important";
            $photo_width = "";
        }elseif(in_array($sub_institute_id, $width)){
            $logo_height = "90px !important";
            $photo_height = "100px !important";
            $photo_width = "width:70px !important";
        }else{
            $logo_height = "50px !important";
            $photo_height = "90px !important";
            $photo_width = "";
        }

        if (isset($result_trust->left_logo)) {
            $image_path1 = "/storage/result/left_logo/" . $result_trust->left_logo ?? '';
            $image_path_1 = '<img src="' . $image_path1 . '" alt="SCHOOL LEFT LOGO" style="height: ' . $logo_height . ';">';
            $html_content = str_replace(htmlspecialchars("<<result_left_logo>>"), $image_path_1, $html_content);
        }
        if (isset($result_trust->right_logo)) {
            $image_path2 = "/storage/result/right_logo/" . $result_trust->right_logo ?? '';
            $image_path_2 = '<img src="' . $image_path2 . '" alt="SCHOOL RIGHT LOGO" style="height: ' . $logo_height . ';">';
            $html_content = str_replace(htmlspecialchars("<<result_right_logo>>"), $image_path_2, $html_content);
        }
        $display_year = $syear . "-" . ($syear + 1);
        if($sub_institute_id==254){
            $display_year =  $syear . "-" . substr($syear + 1,2);
        }

        $student_image_path1 = "/storage/student/" . $value['image'];
        $student_image_path = '<img class="logo" src="' . $student_image_path1 . '" alt="Student Logo" style="height: ' . $photo_height . ';'.$photo_width.'">';

        if (isset($result_trust->line1)) {
            $html_content = str_replace(htmlspecialchars("<<result_line_1>>"), $result_trust->line1, $html_content);
        }

        if (isset($result_trust->line2)) {
            $html_content = str_replace(htmlspecialchars("<<result_line_2>>"), $result_trust->line2, $html_content);
        }
        if (isset($result_trust->line3)) {
            $html_content = str_replace(htmlspecialchars("<<result_line_3>>"), $result_trust->line3, $html_content);
        }
        if (isset($result_trust->line4)) {
            $html_content = str_replace(htmlspecialchars("<<result_line_4>>"), $result_trust->line4, $html_content);
        }
        $standard_id = $value['standard_id'];
        $reopen_date = '';

        $teacher_name = DB::table('class_teacher as ct')->join('tbluser as us', 'ct.teacher_id', '=', 'us.id')->selectRaw('ct.standard_id,ct.division_id,ct.teacher_id,concat_ws(" ",us.first_name,us.last_name) as teacher_name,us.last_name')->where(['ct.syear' => $syear, 'ct.sub_institute_id' => $sub_institute_id, 'ct.standard_id' => $value['standard_id'], 'ct.division_id' => $value['section_id']])->first();
           // for teachers signature standard_wise
        $result_teacher = $this->getExamMasterSettigs($standard_id);
        if (!empty($result_teacher)) {
            $teacher_sign = '';
            if ($sub_institute_id == 254) {
                $teacher_sign = $teacher_name->teacher_name;
            } else {
                if (isset($result_teacher['teacher_sign'])) {
                    $teacher_sign = '<img src="/storage/result/teacher_sign/' . $result_teacher['teacher_sign'] . '" alt="teacher_sign" style="height: 50px !important;">';
                }
            }
            /*$sign_width = "100%";
            $sign_height = "50px";            
            if($sub_institute_id==254){
                if($template==18){
                    $sign_width = "20%";
                }else{
                    $sign_width = "50%";
                } 
                $sign_height = "50px";                                           
            }
            */
            $sign_height = "50px";            
            if($sub_institute_id==254){
                $sign_height = "40px";                                           
            }
            $principal_sign = '<img src="/storage/result/principle_sign/' . $result_teacher['principal_sign'] . '" alt="principal_sign" style="height: '.$sign_height.' !important;">';//width:'.$sign_width.' !important
            $director_signatiure = '<img src="/storage/result/director_sign/' . $result_teacher['director_signatiure'] . '" alt="director_signatiure" style="height: '.$sign_height.' !important;">';//width:'.$sign_width.' !important

            $html_content = str_replace(htmlspecialchars("<<teacher_sign_value>>"), $teacher_sign, $html_content);
            $html_content = str_replace(htmlspecialchars("<<principle_sign_value>>"), $principal_sign, $html_content);
            $html_content = str_replace(htmlspecialchars("<<director_sign_value>>"), $director_signatiure, $html_content);
            $reopen_date = date_format(date_create($result_teacher['reopen_date']), 'd-m-Y');
        }
           // echo "<pre>";print_r($teacher_sign->teacher_name);exit;
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
        $term_name = '';
        if($format != 'yearly'){
            $term_name = DB::table('academic_year')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->where('term_id',$format)->value('title');
        }
        //Start Bonafide certificate Tags
        $html_content = str_replace(htmlspecialchars("<<class_teacher_name>>"), isset($teacher_name->teacher_name) ? $teacher_name->teacher_name : ' ', $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_teacher_lastname>>"), isset($teacher_name->last_name) ? $teacher_name->last_name : ' ', $html_content);
        $html_content = str_replace(htmlspecialchars("<<academic_years>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<term_name>>"), $term_name, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_image_value>>"), $student_image_path, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_id>>"),strtoupper($value['id']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_name_value>>"), strtoupper($value['student_full_name']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<short_standard_name>>"), strtoupper($value['short_standard_name']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_enrollment_value>>"),$value['enrollment_no'],$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_roll_no_value>>"),$value['roll_no'],$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_standard_value>>"),$value['standard_name'],$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_division_value>>"),$value['division_name'],$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_year_value>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_mobile_value>>"), $value['mobile'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_value>>"),date('d-m-Y', strtotime($value['dob'])),$html_content);
        $html_content = str_replace(htmlspecialchars("<<current_date>>"), date('d-M-Y'), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_word_value>>"), $date_in_word,$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dise_uid_value>>"), $value['dise_uid'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<his_her_value>>"), $his_her, $html_content);
        $html_content = str_replace(htmlspecialchars("<<he_she_value>>"), $he_she, $html_content);

        $html_content = str_replace(htmlspecialchars("<<place_of_birth_value>>"),strtoupper($value['place_of_birth']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<father_name_value>>"),strtoupper($value['father_name']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<mother_name_value>>"),strtoupper($value['mother_name']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<religion_name_value>>"),strtoupper($value['religion_name']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<caste_name_value>>"),strtoupper($value['caste_name']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<subcast_value>>"),strtoupper($value['subcast']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_first_admission_value>>"),strtoupper($value['date_of_first_admission']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_uniqueid_value>>"),strtoupper($value['unique_id']),$html_content);
        $html_content = str_replace(htmlspecialchars("<<student_admission_no>>"),strtoupper($value['admission_no']),$html_content);

        $standard = DB::table('standard')->where(['sub_institute_id'=>$sub_institute_id,'id'=>$standard_id])->first();
        $next_standard = DB::table('standard')->where(['sub_institute_id'=>$sub_institute_id,'id'=>$standard->next_standard_id])->first();
         
         if($sub_institute_id==254){
            $html_content = str_replace(htmlspecialchars("<<next_std>>"), $next_standard->short_name ?? '', $html_content);
         }else{
            $html_content = str_replace(htmlspecialchars("<<next_std>>"), $next_standard->name ?? '', $html_content);
         }
        // for hills high tags
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_hills>>')) !== false) {
            $main_result = $this->get_scholastic_hills($standard_id, $value['id'], $format, 'primary');
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_hills>>"), $main_result['scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['teacher_remark'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<pass_or_fail>>"), $main_result['pass_or_fail'], $html_content);            
        } 
        else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_hills_upper>>')) !== false) {
            $main_result = $this->get_scholastic_hills($standard_id, $value['id'], $format, 'upper');
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_hills_upper>>"), $main_result['scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['teacher_remark'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<pass_or_fail>>"), $main_result['pass_or_fail'], $html_content);
        } 
        else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_hills_11>>')) !== false) {
            $main_result = $this->get_scholastic_hills_11($standard_id, $value['id'], $format, 'upper');
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_hills_11>>"), $main_result['scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['teacher_remark'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<pass_or_fail>>"), $main_result['pass_or_fail'], $html_content);
        } else if(strpos($html_content, htmlspecialchars('<<scholastic_marks_hills_10_11>>')) !== false) {
            $main_result = $this->get_scholastic_hills_10_11($standard_id, $value['id'], $format, 'upper');
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_hills_10_11>>"), $main_result['scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['teacher_remark'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<pass_or_fail>>"), $main_result['pass_or_fail'], $html_content);
        }else if(strpos($html_content, htmlspecialchars('<<scholastic_marks_hills_t1_9>>')) !== false) {
            $main_result = $this->get_scholastic_hills_t1_9($standard_id, $value['id'], $format, 'upper');
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_hills_t1_9>>"), $main_result['scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['teacher_remark'], $html_content);
        }
        // for mmis co scholastic
        if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks_mmis>>')) !== false) {
            $co_result = $this->get_co_scholastic_mmis($standard_id, $value['id'], $format, "primary");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_mmis>>"), $co_result['co_scholastic'], $html_content);
        }
        // 2024-09-11
        if (strpos($html_content, htmlspecialchars('<<discipline_marks_mmis>>')) !== false) {
            $co_result = $this->get_co_scholastic_mmis($standard_id, $value['id'], $format, "primary");
            $html_content = str_replace(htmlspecialchars("<<discipline_marks_mmis>>"), $co_result['discipline'], $html_content);
        }
        // for 9th std format
        if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks_mmis9>>')) !== false) {
            $co_result = $this->get_co_scholastic_mmis($standard_id, $value['id'], 151, "primary");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_mmis9>>"), $co_result['co_scholastic'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<discipline_marks_mmis9>>')) !== false) {
            $co_result = $this->get_co_scholastic_mmis($standard_id, $value['id'], 151, "primary");
            $html_content = str_replace(htmlspecialchars("<<discipline_marks_mmis9>>"), $co_result['discipline'], $html_content);
        }
        // 2024-09-11 end

        if (strpos($html_content, htmlspecialchars('<<optional_marks_mmiss>>')) !== false) {
            $co_result = $this->get_co_scholastic_mmis($standard_id, $value['id'], $format, "primary");
            $html_content = str_replace(htmlspecialchars("<<optional_marks_mmiss>>"), $co_result['optional'], $html_content);
        }
        // for hills co scholastic 
        if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks_hills>>')) !== false) {
            $co_result = $this->get_co_scholastic_hills($standard_id, $value['id'], $format, "primary");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_hills>>"), $co_result['co_scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<other_tags_hills>>"), $co_result['other_tags'], $html_content);
        } else if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks_hills_upper>>')) !== false) {
            $co_result = $this->get_co_scholastic_hills($standard_id, $value['id'], $format, "upper");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_hills_upper>>"), $co_result['co_scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<other_tags_hills>>"), $co_result['other_tags'], $html_content);
        }

        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_lions>>')) !== false) {
            $main_result = $this->get_scholastic_lions($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_lions>>"), $main_result['table'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<lions_result>>"), strtoupper($main_result['result']), $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['remark'], $html_content);                        
        }
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_lions11>>')) !== false) {
            $main_result = $this->get_scholastic_lions11($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_lions11>>"), $main_result['table'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<lions_result>>"), strtoupper($main_result['result']), $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['remark'], $html_content);                        
        }
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_lions9>>')) !== false) {
            $main_result = $this->get_scholastic_lions9($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_lions9>>"), $main_result['table'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['remark'], $html_content); 
            $html_content = str_replace(htmlspecialchars("<<lions_result>>"), strtoupper($main_result['result']), $html_content);
        }
         // student Result get_scholastic_lions
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_no_zero>>')) !== false) {
            $main_result = $this->get_scholastic($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_no_zero>>"), $main_result['table'], $html_content);
        } else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_single_zero>>')) !== false) {
            $main_result = $this->get_scholastic($standard_id, $value['id'], $format, "signle_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_single_zero>>"), $main_result['table'], $html_content);
        }
        // saraswati scholastic_marks_saraswati
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_saraswati>>')) !== false) {
            $main_result = $this->get_scholastic_saraswati($standard_id, $value['id'], $format, "signle_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_saraswati>>"), $main_result['table'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<student_details>>"), $main_result['saraswati_stu'], $html_content);
        }else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_saraswati_v2>>')) !== false) {
            $main_result = $this->scholastic_marks_saraswati_v2($standard_id, $value['id'], $format, "signle_zero");
            $html_content = str_replace(htmlspecialchars("<<student_details>>"), $main_result['saraswati_stu'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_v2>>"), $main_result['co_scholastic'], $html_content);                                 
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_saraswati_v2>>"), $main_result['table'], $html_content);
        }else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_saraswati_9_v2>>')) !== false) {
            $main_result = $this->scholastic_marks_saraswati_9_v2($standard_id, $value['id'], $format, "signle_zero");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_9_v2>>"), $main_result['co_scholastic'], $html_content);                                 
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_saraswati_9_v2>>"), $main_result['table'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<scholastic_grade_marks__9_11>>"), $main_result['grade_range'], $html_content);
        }else {
            $main_result = $this->get_scholastic($standard_id, $value['id'], $format, "double_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks>>"), $main_result['table'], $html_content);
           
        // student_details            
        }
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_t1_mmis>>')) !== false) {
            $main_result = $this->get_scholastic_t1_mmis($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_t1_mmis>>"), $main_result['table'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<teacher_remark>>"), $main_result['remark'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<result>>"), $main_result['result'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<custom_note_1>>"), $main_result['custom_note_1'], $html_content);
        }

        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_mmis>>')) !== false) {
            $main_result = $this->get_scholastic_mmis($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_mmis>>"), $main_result['table'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<teacher_remark>>"), $main_result['remark'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<result>>"), $main_result['result'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<custom_note_1>>"), $main_result['custom_note_1'], $html_content);
        }

        if ((strpos($html_content, htmlspecialchars('<<scholastic_grade_marks>>')) !== false) || (strpos($html_content, htmlspecialchars('<<co_scholastic_grade_marks>>')) !== false)) {
            $co_result = $this->get_co_scholastic($standard_id, $value['id'], $format, "double_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_grade_marks>>"), $co_result['grade_range'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_grade_marks>>"), $co_result['co_scholastic'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks>>')) !== false) {
        $co_result = $this->get_co_scholastic($standard_id, $value['id'], $format, "double_zero");
        $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks>>"), $co_result['scholastic'] ?? '', $html_content); 
        }
        // attendance
        if (strpos($html_content, htmlspecialchars('<<total_attendance_simple>>')) !== false) {
            $atten = $this->get_attendance($standard_id, $value['id'], $format, "simple");
            $html_content = str_replace(htmlspecialchars("<<total_attendance_simple>>"), $atten['attendance'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark_simple>>"), $atten['remark'], $html_content);
        }
         if(strpos($html_content, htmlspecialchars('<<total_attendance>>')) !== false || strpos($html_content, htmlspecialchars('<<attandance_altius>>')) ){
        $atten = $this->get_attendance($standard_id, $value['id'], $format, "total_attendance");
        $html_content = str_replace(htmlspecialchars("<<total_attendance>>"), $atten['table'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $atten['remark'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_teacher_remark_anual>>"), $atten['anual'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<attandance_altius>>"), $atten['table'], $html_content);
    }
    
    $termAtten = $this->getTermAttendance($standard_id, $value['id'], $format,'');
    $html_content = str_replace(htmlspecialchars("<<term_attendance>>"), $termAtten, $html_content);
    
        if (strpos($html_content, htmlspecialchars('<<total_attendance_manual>>')) !== false) {
            $atten = $this->get_attendance($standard_id, $value['id'], $format, "total_attendance_manual");
            $html_content = str_replace(htmlspecialchars("<<total_attendance_manual>>"), $atten['table'], $html_content);
        } else if (strpos($html_content, htmlspecialchars('<<attendance_hills_simple>>')) !== false) {
            $grade_ids = [851,852];
            if(in_array($grade_id,$grade_ids)){
                $atten = $this->get_attendance($standard_id, $value['id'], $format, "attendance_hills");
                $html_content = str_replace(htmlspecialchars("<<attendance_hills_simple>>"), $atten['table'], $html_content);
            }else{
                $atten = $this->get_attendance($standard_id, $value['id'], $format, "simple");  
                $html_content = str_replace(htmlspecialchars("<<attendance_hills_simple>>"),$atten['attendance'], $html_content);
            }
        }
        else if (strpos($html_content, htmlspecialchars('<<attendance_hills>>')) !== false) {
            $atten = $this->get_attendance($standard_id, $value['id'], $format, "attendance_hills");
            $html_content = str_replace(htmlspecialchars("<<attendance_hills>>"), $atten['table'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_altius>>')) !== false) {
            $main_result = $this->get_scholastic_altius($standard_id, $value['id'], $format, "signle_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_altius>>"), $main_result['table'], $html_content);
        }
        if(isset($main_result['result'])){
        //$html_content = str_replace(htmlspecialchars("<<result>>"), strtoupper($main_result['result']), $html_content);
            $html_content = str_replace(htmlspecialchars("<<result>>"), $main_result['result'], $html_content);
        }
        $html_content = str_replace(htmlspecialchars("<<school_open_date>>"),                                                                                          $reopen_date, $html_content);

        if (strpos($html_content, htmlspecialchars('<<activity_tag_marks>>')) !== false) {
            $main_result = $this->get_activity_marks($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<activity_tag_marks>>"), $main_result['table'], $html_content);
        }

        if (strpos($html_content, htmlspecialchars('<<hpc_hills_nursary>>')) !== false) {
            $main_result = $this->get_hpc_hills_nursary($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<hpc_hills_nursary>>"), $main_result['table'], $html_content);
        }

        // 2024-09-11 MMIS std 9th co scholatic added 
        $mmisPartB9 = $this->mmisPartB9($standard_id, $value['id'], $format);
        $html_content = str_replace(htmlspecialchars("<<part_b_scholastic>>"), $mmisPartB9, $html_content);

        $skillPart9 = $this->skillPart9($standard_id, $value['id'], $format);
        $html_content = str_replace(htmlspecialchars("<<skill_subject>>"), $skillPart9, $html_content);

        //cma tags start  
        if (strpos($html_content, htmlspecialchars('<<cma_scholastic_marks>>')) !== false) {
            $cmaScholatic = $this->get_cma_scholatic($standard_id, $value['id'], $format, "");
            $html_content = str_replace(htmlspecialchars("<<cma_scholastic_marks>>"), $cmaScholatic['table'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<cma_detailed_assm_marks>>')) !== false) {
            $cmaDetails = $this->get_cma_detailAssm($standard_id, $value['id'], $format, "");
            $html_content = str_replace(htmlspecialchars("<<cma_detailed_assm_marks>>"), $cmaDetails['table'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<cma_co_scholastic_marks>>')) !== false) {
            $co_scholastic = $this->get_cma_co_scholatic($standard_id, $value['id'], $format, "");
            $html_content = str_replace(htmlspecialchars("<<cma_co_scholastic_marks>>"), $co_scholastic['table'], $html_content);
        }
        // cma tags end
        // 2024-09-11 end 
        return $html_content;
        //  return $main_result;         
    }

    public function getExamMasterSettigs($standard_id)
    {
        $result = DB::table('result_master_confrigration')
            ->select('teacher_sign', 'principal_sign', 'director_signatiure')->selectRaw('DATE_FORMAT(reopen_date, "%d-%m-%Y") as reopen_date')
            ->where('standard_id', $standard_id)
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))
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

    public function get_scholastic_mmis($standard_id, $student_id, $format, $digit)
    {
        // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term =$extra_exam = "1=1";
        if ($format != "yearly"){
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        // get subject
       $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
       $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
       // get exam title 
       $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
       //get exam marks
       $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id,'best_of_2');
        
        // scholastic table started 
       $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style="background:black;color:white"><b>Part 1-A - Scholastic Areas:</b></th>';
        $col = 1;
        $total_term_marks =  $total_sub_marks = [];
        $total_weightage = $overall_total  = $all_colspan = 0;
        $colspan = 2;
        // get both term name and total marks per subject 
        foreach ($term_name as $keys => $terms) {
            $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, &$total_weightage) {
                $total_weightage += $title->weightage;
                return $title->term_id == $terms->term_id;
            });
            $table .= '<th colspan="' . (count($term_exam_titles) + $colspan) . '" style="text-align:center;background:black;color:white"><b>Academic Year ('.$total_weightage.' marks)</b></th>';//' . $terms->title . '
            // Initialize the total marks for each term to zero
            $total_term_marks[$terms->term_id] = 0;
            $total_sub_marks[$terms->term_id] = 0;
            $all_colspan += count($term_exam_titles);
        }
        //$table .= '<th colspan="2" class="data_center" style="background:black;color:white"><b>Total</b></th>';
        $table .= '</tr><tr><th><b>Subject</b></th>';
        $weigthage = '';
        // get exam names heading like PA,SA,NB
        foreach ($term_name as $keys => $terms) {
            $total_mark = 0;
            foreach ($exam_title as $key => $title) {
                $weigthage = '(' . $title->weightage . ')';
                $exam_head = $title->ExamTitle;

                if ($terms->term_id == $title->term_id) {
                    $table .= '<th class="data_center"><b>' . $exam_head . '<br>' . $weigthage . '</b></th>';
                    // weightage term wise 
                    $total_mark += $title->weightage;

                }
            }
            $mark_tot = '(' . $total_mark . ')';
        // Store the total marks for each term
            $table .= '<th class="data_center"><b>Marks Obtained <br>' . $mark_tot . ' </b></th>';
            $overall_total += $total_mark;
        }
        //total marks of both term headings     
        //$table .= '<th class="data_center"><b>Total Marks <br>Obtained (' . $overall_total . ')</b></th>';
        $table .= '<th class="data_center"><b>Grade</b></th>';
        $table .= '</tr>
        </thead>
        <tbody>';
        $tot_ob_mark =$tot_sub_mark =$get_all_ob_mark=$get_all_tot_mark = $failed = 0;
        // get all subject name 
        foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr><td>' . $val->subject_name . '</td>';
            // get term wise eam and marks 
            foreach ($term_name as $keys => $terms) {
                $obtained_marks = $to_marks = $to_weight = $obtained_mark = $to_mark = [];
                $title_exam = []; 
                 // get marks by exam id wise
                foreach ($exam_name as $key => $title) {
                    if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                        $foundMarks = false;
                        $ob_mark = 0;
                        // By Rajesh - Display periodic test exam and other diaply type 06-08-2024
                        $arr = $title->exam_id;
                        $title_exam[$arr][] = $title->ExamTitle;
                        $weightage = $title->weightage;
                         // all exam marks 
                        //$obtained_mark = $to_mark = [];
                         foreach ($exam_marks as $index => $marks) {
                            if ($title->id == $marks->exam_id) {
                                // for AB,NA,EX
                                if ($marks->is_absent!='' && in_array($marks->is_absent,["N.A.","EX","AB"])) {
                                    $ab_ex_na = $marks->is_absent;
                                    $obtained_mark[$arr][] = $ab_ex_na;

                                    $to_mark[$arr][] = $title->points;
                                    if($ab_ex_na=="AB" && !in_array($title->ExamTitle,['PA1','PA2'])){
                                        $to_weight[$arr] = $weightage;
                                    }else{
                                        $to_weight[$arr][] = $title->con_point;    
                                    }
                                } else {
                                    $to_mark[$arr][] = $title->points;
                                    $ob_mark = $marks->points;

                                    if(!in_array($title->ExamTitle,['PA1','PA2'])){
                                        $to_weight[$arr] = $weightage;
                                    }else{
                                        $to_weight[$arr][] = $title->con_point;
                                    }
                                    // store marks in array to get best of 2 
                                    if(!in_array($marks->is_absent,["N.A.","EX","AB"])){
                                        $obtained_mark[$arr][] = $ob_mark;
                                    }
                                }
                                break;
                            }
                        }

                        foreach ($obtained_mark as $key => $values) {

                            // Check if all values in the array are "AB"
                            if (array_unique($values) === ['AB']) {
                                $obtained_marks[$key] = ['AB'];
                                continue; // Skip further processing for this key
                            }else{
                                // Convert all values to float to avoid string issues
                                $values = array_map('floatval', $values);

                                if (count($values) > 1) {
                                    // Compute the best of [0] and [2], and add [1] if it exists
                                    $bestOf = isset($values[2]) ? max($values[0], $values[2]) : $values[0];
                                    $total = $bestOf + (isset($values[1]) ? $values[1] : 0);
                                    $obtained_marks[$key] = [$total];
                                } else {
                                    // If there's only one value, retain it
                                    $obtained_marks[$key] = [$values[0]];
                                }
                            }
                        }
                        foreach ($to_mark as $key => $values) {
                            // Convert all values to float to avoid string issues
                            $values = array_map('floatval', $values);

                            if (count($values) > 1) {
                                // Compute the best of [0] and [2], and add [1] if it exists
                                $bestOf = isset($values[2]) ? max($values[0], $values[2]) : $values[0];
                                $total = $bestOf + (isset($values[1]) ? $values[1] : 0);
                                $to_marks[$key] = [$total];
                            } else {
                                // If there's only one value, retain it
                                $to_marks[$key] = [$values[0]];
                            }
                        }
                    }
                }
                // echo $val->subject_name.'<br>'.$val->subject_id.'<br>';
                //echo "<pre>";print_r($obtained_marks);
                //echo "<pre>";print_r($to_marks);exit();
                $ob_main_mark = $ab_ex_na = $total_marks = 0;
                // for best of 2 exam wise 
                if (!empty($title_exam)) {
                    foreach ($title_exam as $exam_id => $marksArray) {                                
                        $convert_mark = 0;
                        $obtained_mark_arr = $obtained_marks[$exam_id] ?? [];
                        // convert marks if best of 2
                        if(in_array('PA1',$marksArray)){
                            $pamarks=0;
                            $pAB = 1;
                            foreach ($obtained_mark_arr as $mk => $mv) {
                                // echo $mv;
                                $w_m = $to_weight[$exam_id][$mk] ?? 0; // Check if the key exists
                                $t_m = $to_marks[$exam_id][$mk];
                                if(is_numeric($mv)){
                                	$pAB = 0;
                                    $pamarks +=($t_m != 0) ? (($mv / $t_m) * $w_m) : 0;
                                }else{
                                   continue;
                                }
                            }
                            $convert_mark = $pamarks;
                        }
                        else if(in_array('PA2',$marksArray)){
                            $pamarks=0;
                            $pAB = 1;
                            foreach ($obtained_mark_arr as $mk => $mv) {
                                // echo $mv;
                                $w_m = $to_weight[$exam_id][$mk] ?? 0; // Check if the key exists
                                $t_m = $to_marks[$exam_id][$mk];
                                if(is_numeric($mv)){
                                	$pAB = 0;
                                    $pamarks +=($t_m != 0) ? (($mv / $t_m) * $w_m) : 0;
                                }else{
                                   continue;
                                }
                            }
                            $convert_mark = $pamarks;
                        }
                        else{
                        	$pAB = 1;
                            $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                            $t_m = array_sum(array_intersect_key($to_marks[$exam_id] ?? [], $marksArray));
                            $obtained_mark_sum = array_sum($obtained_mark_arr);
                            $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                            // for AB 
                            foreach ($obtained_mark_arr as $mk => $mv) {
                                $w_m = $to_weight[$exam_id] ?? 0;
                                $t_m = $to_marks[$exam_id];
                                if(is_numeric($mv))
                                   	$pAB = 0;
                                else
                                    continue;
                            }
                        }
                        // echo $pAB.'<br>';
                         // get mark for total mark 
                         $ob_main_mark += $convert_mark;

                        if(count($obtained_mark_arr) > 1) {
                            $total_marks += $w_m;
                            if($pAB){
                                $tdVal = 'AB';
                            }
                            else{
                                $tdVal = number_format($convert_mark, 2);
                            }
                            $table .= '<td class="data_center"  ' . $exam_id . '-'.$val->subject_id.'-'.$pAB.'>' . $tdVal . '</td>';
                        }else {
                            if (!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX"])) {
                                $total_marks += $w_m;
                            }
                            
                            if(!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX","AB"])){
                                $tdVal = number_format($convert_mark, 2);
                            }else{
                                $tdVal = $obtained_mark_arr[0] ?? "-"; // print AB,NA,EX
                            }
                            $table .= '<td class="data_center else" ' . $exam_id . '-'.$val->subject_id.'-'.$pAB.' >' . $tdVal . '</td>';
                        }
                    }
                } else {
                    // If marks not found
                    foreach ($title_exam as $exam_id => $marksArray) {
                        $table .= '<td class="data_center no_mark ' . $exam_id . '">-</td>';
                    }
                }  

                $obtained_mark_formatted = number_format($ob_main_mark, 0);
                
                $table .= '<td class="data_center all_mark">' . $obtained_mark_formatted . '</td>';
                $both_term_ob_mark += $obtained_mark_formatted;
                // Update the total marks for the current term
                $total_term_marks[$terms->term_id] += $ob_main_mark;
                $total_sub_marks[$terms->term_id] += $total_mark;
                // $grade_arr = $this->getGradeScale($standard_id, '');
            }
            // get percentage 
            $grade_arr_mmis = $this->getGradeScale($standard_id, '');
            //$table .= '<td class="data_center tot_of_both">' . number_format($both_term_ob_mark, 2) . '</td>';
            $table .= '<td class="data_center grade_of_both">' . $this->getGrade($grade_arr_mmis, $total_mark, $both_term_ob_mark)  . '</td>';

            $sub_per = $this->getPer($both_term_ob_mark, $total_mark);
            if ($sub_per < 33)
                $failed++;

            $get_all_ob_mark += $both_term_ob_mark;
            $get_all_tot_mark += $overall_total;

            $table .= '</tr>';
        }
        // exit;
        $table .= '<tr>';
        $table_per = $rep_val = '';
        $table_all = '';
        $ov_ob_mark = $ov_sub_mark = 0;
        $ov_ob_mark2 = $ov_sub_mark2 = 0;
        $result = "Pass";
        // get percentage and grade 
        $per = $this->getPer($get_all_ob_mark, $get_all_tot_mark);
        $table .= '<tr><td><b>Percentage</b></td><td colspan=' . ($all_colspan + 4) . '><b>' . $per . '%</b></td></tr>';
        $curr_std = DB::table('standard')->where('id', $standard_id)->first();
        $next_std = DB::table('standard')->where('id', $curr_std->next_standard_id)->first();

        if (empty($failed)) {
            $result = 'Passed & Promoted to Class : ' . $next_std->school_stream;
        } else {
            $result = "Failed";
        }

    // Calculate the total marks for each term
        $res['result'] = $result;
        $table .= '</tr></tbody></table>';
        $res['table'] = $table;
        $res['remark'] = \App\Helpers\getGradeComment($grade_arr_mmis, 100, $per) ?? '-';
        $res['custom_note_1'] = '(Term I & II)';
        return $res;
    }

    // 25-09-2024 start 
    public function get_scholastic_t1_mmis($standard_id, $student_id, $format, $digit)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term =$extra_exam = "1=1";
        $colspan = 1;
        $col = 2;
        if ($format != "yearly"){
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
            $colspan = 2;
            $col = 1;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        // get subject
       $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
       $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
       // get exam title 
       $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
       //get exam marks
       $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id,'best_of_2');
    //    echo "<pre>";print_r($exam_marks);exit;
    //    standar grade array 
       $grade_arr_mmis = $this->getGradeScale($standard_id, '');
        // scholastic table started 
       $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style="background:black;color:white"><b>Part 1-A - Scholastic Areas:</b></th>';
        
        $total_term_marks =  $total_sub_marks = [];
        $overall_total  = $all_colspan = 0; 
        // get both term name and total marks per subject 
        foreach ($term_name as $keys => $terms) {
        	$total_weightage = 0;
        	$term_exam_titles = [];
        	foreach ($exam_title as $key => $title) {
        		if ($terms->term_id == $title->term_id) {
        			$total_weightage += $title->weightage;
        			$term_exam_titles[$key] = $title;
        		}
        	}
            $table .= '<th colspan="' . (count($term_exam_titles) + $colspan) . '" style="text-align:center;background:black;color:white"><b>' . $terms->title . ' ('.$total_weightage.' marks) </b></th>';
            // Initialize the total marks for each term to zero
            $total_term_marks[$terms->term_id] = 0;
            $total_sub_marks[$terms->term_id] = 0;
            $all_colspan += count($term_exam_titles);
        }

        if ($format == "yearly"){
            $table .= '<th colspan="' . $col . '" style="text-align:center;background:black;color:white"><b>Total</b></th>';
        }

        $table .= '</tr><tr><th><b>Subject</b></th>';
        $weigthage = '';
        $subjectTot = [];
        // get exam names heading like PA,SA,NB
        foreach ($term_name as $keys => $terms) {
            $total_mark = 0;
            foreach ($exam_title as $key => $title) {
                $weigthage = '(' . $title->weightage . ')';
                $exam_head = $title->ExamTitle;

                if ($terms->term_id == $title->term_id) {
                    $table .= '<th class="data_center"><b>' . $exam_head . '<br>' . $weigthage . '</b></th>';
                    // weightage term wise 
                    $total_mark += $title->weightage;
                    if(!isset($subjectTot[$title->subject_id])){
                        $subjectTot[$title->subject_id] = 0;
                    }
                    $subjectTot[$title->subject_id] += $title->weightage;
                }
            }
            $mark_tot = '(' . $total_mark . ')';
        // Store the total marks for each term
            $table .= '<th class="data_center"><b>Marks Obtained <br>' . $mark_tot . ' </b></th>';
            $overall_total += $total_mark;
        }
        //total marks of both term headings  
        if ($format == "yearly"){
            $table .= '<th class="data_center"><b>Total Marks <br>Obtained (' . $overall_total . ')</b></th>';
        }
        $table .= '<th  class="data_center"><b>Grade</b></th>
        </tr>
        </thead>
        <tbody>';
        $tot_ob_mark =$tot_sub_mark =$get_all_ob_mark=$get_all_tot_mark = $failed = 0;
        // get all subject name 
        foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr><td>' . $val->subject_name . '</td>';
            // get term wise eam and marks 
            foreach ($term_name as $keys => $terms) {
                $obtained_marks = $to_marks = $to_weight = [];
                $title_exam = []; 
                 // get marks by exam id wise
                  $obtained_marks = $to_marks = $to_weight = $title_exam = []; 
                                // get marks by exam id wise
                            foreach ($exam_name as $key => $title) {
                                if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                                    $foundMarks = false;
                                    $ob_mark = 0;
                                    // By Rajesh - Display periodic test exam and other diaply type 06-08-2024
                                    $arr = $title->exam_id;
                                    $title_exam[$arr][] = $title->ExamTitle;
                                    $weightage = $title->weightage;
                                    
                                    // all exam marks 
                                    foreach ($exam_marks as $index => $marks) {
                                        if ($title->id == $marks->exam_id) {
                                            // for AB,NA,EX
                                            if ($marks->is_absent!='' && in_array($marks->is_absent,["N.A.","EX","AB"])) {
                                                $ab_ex_na = $marks->is_absent;
                                                $obtained_marks[$arr][] = $ab_ex_na;

                                                $to_marks[$arr][] = $title->points;
                                                if($ab_ex_na=="AB" && $title->ExamTitle!='PA1' && $title->ExamTitle!='PA2'){
                                                    $to_weight[$arr] = $weightage;
                                                }else{
                                                    $to_weight[$arr][] = $title->con_point;    
                                                }
                                            } else {
                                                $to_marks[$arr][] = $title->points;
                                                $ob_mark = $marks->points;

                                                if($title->ExamTitle!='PA1' && $title->ExamTitle!='PA2'){
                                                    $to_weight[$arr] = $weightage;
                                                }else{
                                                    $to_weight[$arr][] = $title->con_point;
                                                }
                                                // store marks in array to get best of 2 
                                                if(!in_array($marks->is_absent,["N.A.","EX","AB"])){
                                                    $obtained_marks[$arr][] = $ob_mark;
                                                }
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            // echo "<pre>";print_r($to_weight);
                            $ob_main_mark = $ab_ex_na = $total_marks = 0;
                            // for best of 2 exam wise 
                            if (!empty($title_exam)) {
                                foreach ($title_exam as $exam_id => $marksArray) {                                
                                    $convert_mark = 0;
                                    $obtained_mark_arr = $obtained_marks[$exam_id] ?? [];
                                    // convert marks if best of 2

                                    if(in_array('PA1',$marksArray) || in_array('PA2',$marksArray)){
                                        $pamarks=0;
                                        $pAB=1;
                                        foreach ($obtained_mark_arr as $mk => $mv) {
                                            $w_m = $to_weight[$exam_id][$mk] ?? 0; // Check if the key exists
                                            $t_m = $to_marks[$exam_id][$mk];
                                            //echo $to_weight[$exam_id][$mk]."*".$mv."/".$to_marks[$exam_id][$mk]."<br/>";
                                            if(is_numeric($mv)){
                                            	$pAB = 0;
                                                $pamarks +=($t_m != 0) ? (($mv / $t_m) * $w_m) : 0;
                                            }else{
                                               continue;
                                            }
                                        }
                                        //echo $pamarks;exit();
                                        $convert_mark = $pamarks;
                                    }else{
                                    	$pAB=1;
                                        $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                                        $t_m = array_sum(array_intersect_key($to_marks[$exam_id] ?? [], $marksArray));
                                        $obtained_mark_sum = array_sum($obtained_mark_arr);
                                        $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                                        // for AB 
                                        foreach ($obtained_mark_arr as $mk => $mv) {
                                            $w_m = $to_weight[$exam_id] ?? 0;
                                            $t_m = $to_marks[$exam_id];
                                            if(is_numeric($mv))
                                            	$pAB = 0;
                                            else
                                               continue;
                                        }
                                    }
                                    // echo $pAB.'<br>';
                                     // get mark for total mark 
                                     $ob_main_mark += $convert_mark;

                                    if(count($obtained_mark_arr) > 1) {
                                        $total_marks += $w_m;
                                        if($pAB){
                                            $tdVal = 'AB';
                                        }
                                        else{
                                            $tdVal = number_format($convert_mark, 2);
                                        }
                                        $table .= '<td class="data_center"  ' . $exam_id . '-'.$val->subject_id.'-'.$pAB.'>' . $tdVal . '</td>';
                                    }else {
                                        if (!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX"])) {
                                            $total_marks += $w_m;
                                        }
                                        
                                        if(!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX","AB"])){
                                            $tdVal = number_format($convert_mark, 2);
                                        }else{
                                            $tdVal = $obtained_mark_arr[0] ?? "-"; // print AB,NA,EX
                                        }
                                        $table .= '<td class="data_center else" ' . $exam_id . '-'.$val->subject_id.'-'.$pAB.' >' . $tdVal . '</td>';
                                    }
                                }
                            } else {
                                // If marks not found
                                foreach ($title_exam as $exam_id => $marksArray) {
                                    $table .= '<td class="data_center no_mark ' . $exam_id . '">-</td>';
                                }
                            }
                if ($format != "yearly")
                	$obtained_mark_formatted = round($ob_main_mark);
                else
                	$obtained_mark_formatted = number_format($ob_main_mark,2);
                
                $table .= '<td class="data_center all_mark" '.$ob_main_mark.'>' . $obtained_mark_formatted . '</td>';
                $both_term_ob_mark += $obtained_mark_formatted;
                // Update the total marks for the current term
                $total_term_marks[$terms->term_id] += $ob_main_mark;
                $total_sub_marks[$terms->term_id] += $total_mark;
            }
            // get percentage 
            // echo "<pre>";print_r($total_mark);exit;
            $both_term_ob_mark = round($both_term_ob_mark);
            $subTot = isset($subjectTot[$val->subject_id]) ? $subjectTot[$val->subject_id] : 0;
            if ($format == "yearly"){
                $table .= '<td class="data_center grade_of_both">' . $both_term_ob_mark . '</td>';
            }
            $table .= '<td class="data_center grade_of_both" ' . $total_mark.'-'.$both_term_ob_mark.'-'.$overall_total.' >' . $this->getGrade($grade_arr_mmis, $overall_total, $both_term_ob_mark) . '</td>';

            $sub_per = $this->getPer($both_term_ob_mark, $overall_total);
            if ($sub_per < 33)
                $failed++;

            $get_all_ob_mark += $both_term_ob_mark;
            $get_all_tot_mark += $overall_total;
            
            $table .= '</tr>';
        } 
        // print_r($grade_arr_mmis);
        // exit;
        $table .= '<tr>';
        $table_per = $rep_val = '';
        $table_all = '';
        $ov_ob_mark = $ov_sub_mark = 0;
        $ov_ob_mark2 = $ov_sub_mark2 = 0;
        $result = "Pass";
        $per = $this->getPer($get_all_ob_mark, $get_all_tot_mark);
        // get percentage and grade 
        $table .= '<tr>';
        if ($format == "yearly"){
            $table .= '<td colspan=' . ($all_colspan + $col)+1 . '><b>Percentage</b></td>';
        }else{
            $table .= '<td colspan=' . ($all_colspan + $col) . '><b>Percentage</b></td>';
        }
        $table .= '<td class="data_center"><b>' . round($per,1) . '%</b></td>
        <td class="data_center"><b>' . $this->getGrade($grade_arr_mmis, $get_all_tot_mark,$get_all_ob_mark) . '</b></td>
        </tr>';
        $curr_std = DB::table('standard')->where('id', $standard_id)->first();
        $next_std = DB::table('standard')->where('id', $curr_std->next_standard_id)->first();
        if (empty($failed)) {
            $result = 'Passed &amp; Promoted to Class : ' . $next_std->school_stream;
        } else {
            $result = "Failed";
        }
        
        $res_school = $custom_note_1 = "";
		if ($format == "yearly"){
            $custom_note_1 .= "(Term I & II)";
      		$res_school .= '<tr>
		       <td colspan="3" class="p-t-10">
		        <b>Result : '.$result.'</b>
		       </td>
		      </tr>
		      <tr>
		       <td colspan="3" class="p-t-10">
		        <b>School Reopens on : 01<sup>st</sup> April, 2025</b>
		       </td>
		      </tr>';
		  }

    // Calculate the total marks for each term
        $res['result'] = $res_school;
        $table .= '</tr></tbody></table>';
        $res['table'] = $table;
        $res['remark'] = \App\Helpers\getGradeComment($grade_arr_mmis, 100, $per) ?? '-';
        $res['custom_note_1'] = $custom_note_1;
        return $res;
    }
    // 25-09-2024 end 
    public function mmisPartB9($standard_id,$student_id,$format){
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        if ($format == "yearly") {
            $extra_os = $extra_term = $extra_exam = "1=1";
        } else {
            $extra_os = "rce.term_id = " . $format;
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
       
        $title = "1_5";
        if($standard_id >= 103){
            $title = "6_10";
        }
        $grade_scale = DB::table('grade_master')->where('grade_name',$title)->first();
        $query=DB::table('grade_master_data')->where('sub_institute_id',$sub_institute_id)->where('syear',$syear)->where('grade_id',$grade_scale->id ?? 0);
        
        $get_grade = DB::table('grade_master_data')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear,'grade_id'=>$grade_scale->id ?? 0])->get();

        // echo "<pre>";print_r($co_scholastic);exit;
        $check_optional_subject_with_student = DB::table('student_optional_subject as sos')
        ->select('ssm.display_name', 'sos.subject_id', 'sos.student_id', 'ssm.standard_id', 'rce.id as create_id', 'rce.title', 'rce.term_id', 'rce.standard_id', 'rem.weightage', 'rem.ExamTitle', 'rce.subject_id', 'rce.points as r_point', 'rce.con_point', 'rem.Id as ExamId', 'rce.exam_id', DB::raw('IFNULL(rm.points, 0) as points'))
        ->join('sub_std_map as ssm', 'sos.subject_id', '=', 'ssm.subject_id')
        ->join('result_create_exam as rce', function ($join) use($syear){
	        $join->on('rce.subject_id', '=', 'sos.subject_id')
	            ->on('rce.standard_id', '=', 'ssm.standard_id')
	            ->on('rce.sub_institute_id', '=', 'ssm.sub_institute_id')
	            ->where('rce.syear', '=', $syear);
    	})
        ->join('result_exam_master as rem', 'rem.Id', '=', 'rce.exam_id')
        ->leftJoin('result_marks as rm', function ($join) use($student_id) {
            $join->on('rm.exam_id', '=', 'rce.id')
                ->on('rm.student_id', '=', 'sos.student_id');
        })
        ->where('sos.student_id', '=',  $student_id)
        ->where('sos.sub_institute_id', '=', $sub_institute_id)
        ->where('ssm.standard_id', '=', $standard_id)
        ->where('sos.syear', '=', $syear)
        ->where('ssm.elective_subject', '=', 'YES')
        ->where('ssm.allow_grades', '=', 'YES')
        ->where('ssm.optional_type', '!=', 1)
        ->where('ssm.display_name', '!=', 'BANKING AND INSURANCE')
        ->groupBy('rem.Id', 'sos.subject_id', 'ssm.display_name')
        ->orderBy('ssm.sort_order', 'ASC')
        ->get();

        $subject_name="";

        //echo "<pre>";print_r($check_optional_subject_with_student);exit;
        $scho_table = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
        <tr>
        <th width="50%" style="text-center: left;"><b>Part 1-B-Scholastic Areas:</b></th>';

        $grade_arr_mmis = $this->getGradeScale($standard_id, '');

        $col = 1;
        $totalMark = 0;
        $total_term_marks = $total_sub_marks = $term_ids = [];
        /* RAJESH 29-11-2024 for all exam display in column
        foreach ($term_name as $keys => $terms) {
            $weightage = isset($check_optional_subject_with_student[$keys]->r_point) ? '('.$check_optional_subject_with_student[$keys]->r_point.')' : '';
            $scho_table .= '<th style="text-align:center"><b>' . $terms->title . ' '.$weightage.'</b></th>';
            $term_ids[] = $terms->term_id;
            $totalMark+=isset($check_optional_subject_with_student[$keys]->r_point) ? $check_optional_subject_with_student[$keys]->r_point  : 0;
        }
        */
        if (!empty($check_optional_subject_with_student)) {
            foreach ($check_optional_subject_with_student as $record) {
        		$scho_table .= '<th style="text-align:center"><b>' . $record->title . '<br/>('.$record->r_point.')</b></th>';
        		$totalMark += $record->r_point;
        		$term_ids[] = $record->term_id;
        		$subject_name = $record->display_name;
            }
        }
        $scho_table .= '<th style="text-align:center"><b>Marks Obtained <br/>('.$totalMark.')</b></th>
        <th style="text-align:center"><b>Grade</b></th>
        </tr>';

        $scho_table .= '</tr></thead><tbody>';
        if (!empty($check_optional_subject_with_student)) {
            $subtotal = 0;
            $grade_arr_mmis = $this->getGradeScale($standard_id, '');
	        $scho_table .= '<tr>';
	       	$scho_table .= '<td>' . $subject_name . '</td>';
            foreach ($check_optional_subject_with_student as $record) {
	                $scho_table .= '<td class="data_center">' . $record->points . '</td>';
	                $subtotal+=$record->points;
            }
            $obt_grade = $this->getGrade($grade_arr_mmis, $totalMark,$subtotal);   
            $scho_table .= '<td style="text-align:center">'.$subtotal.'</td>
            <td style="text-align:center">'.$obt_grade.'</td>
            </tr>';
        }
        $scho_table .= '</tbody></table>';
        return $scho_table;
    }

    public function skillPart9($standard_id,$student_id,$format){
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        if ($format == "yearly") {
            $extra_os = $extra_term = $extra_exam = "1=1";
        } else {
            $extra_os = "rce.term_id = " . $format;
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
       
        $title = "1_5";
        if($standard_id >= 103){
            $title = "6_10";
        }
        $grade_scale = DB::table('grade_master')->where('grade_name',$title)->first();
        $query=DB::table('grade_master_data')->where('sub_institute_id',$sub_institute_id)->where('syear',$syear)->where('grade_id',$grade_scale->id ?? 0);
        
        $get_grade = DB::table('grade_master_data')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear,'grade_id'=>$grade_scale->id ?? 0])->get();

        // echo "<pre>";print_r($co_scholastic);exit;
        $check_optional_subject_with_student = DB::table('student_optional_subject as sos')
        ->select('ssm.display_name', 'sos.subject_id', 'sos.student_id', 'ssm.standard_id', 'rce.id as create_id', 'rce.title', 'rce.term_id', 'rce.standard_id', 'rem.weightage', 'rem.ExamTitle', 'rce.subject_id', 'rce.points as r_point', 'rce.con_point', 'rem.Id as ExamId', 'rce.exam_id', DB::raw('IFNULL(rm.points, 0) as points'))
        ->join('sub_std_map as ssm', 'sos.subject_id', '=', 'ssm.subject_id')
        ->join('result_create_exam as rce', function ($join) use($syear){
	        $join->on('rce.subject_id', '=', 'sos.subject_id')
	            ->on('rce.standard_id', '=', 'ssm.standard_id')
	            ->on('rce.sub_institute_id', '=', 'ssm.sub_institute_id')
	            ->where('rce.syear', '=', $syear);
    	})
        ->join('result_exam_master as rem', 'rem.Id', '=', 'rce.exam_id')
        ->leftJoin('result_marks as rm', function ($join) use($student_id) {
            $join->on('rm.exam_id', '=', 'rce.id')
                ->on('rm.student_id', '=', 'sos.student_id');
        })
        ->where('sos.student_id', '=',  $student_id)
        ->where('sos.sub_institute_id', '=', $sub_institute_id)
        ->where('ssm.standard_id', '=', $standard_id)
        ->where('sos.syear', '=', $syear)
        ->where('ssm.elective_subject', '=', 'YES')
        ->where('ssm.allow_grades', '=', 'YES')
        ->where('ssm.display_name', 'BANKING AND INSURANCE')
        ->groupBy('rem.Id', 'sos.subject_id', 'ssm.display_name')
        ->orderBy('ssm.sort_order', 'ASC')
        ->get();

        $subject_name="";

        // echo "<pre>";print_r($check_optional_subject_with_student);exit;
        $skillTable = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
       <tr>  <th width="50%" style="text-center: left;"><b>Skill Subject</b></th>';

        $grade_arr_mmis = $this->getGradeScale($standard_id, '');

        $col = 1;
        $totalMark = 0;
        $total_term_marks = $total_sub_marks = $term_ids = [];
        if (!empty($check_optional_subject_with_student)) {
            foreach ($check_optional_subject_with_student as $record) {
        		$skillTable .= '<th style="text-align:center"><b>' . $record->title . '<br/>('.$record->r_point.')</b></th>';
        		$totalMark += $record->r_point;
        		$term_ids[] = $record->term_id;
        		$subject_name = $record->display_name;
            }
        }
        $skillTable .= '<th style="text-align:center"><b>Marks Obtained <br/>('.$totalMark.')</b></th>
        <th style="text-align:center"><b>Grade</b></th>
        </tr>';

        $skillTable .= '</tr></thead><tbody>';
        if (!empty($check_optional_subject_with_student)) {
            $subtotal = 0;
            $grade_arr_mmis = $this->getGradeScale($standard_id, '');
	        $skillTable .= '<tr>';
	       	$skillTable .= '<td>' . $subject_name. '</td>';
            foreach ($check_optional_subject_with_student as $record) {
	                $skillTable .= '<td class="data_center">' . $record->points . '</td>';
	                $subtotal+=$record->points;
            }
            $obt_grade = $this->getGrade($grade_arr_mmis, $totalMark,$subtotal);   
            $skillTable .= '<td style="text-align:center">'.$subtotal.'</td>
            <td style="text-align:center">'.$obt_grade.'</td>
            </tr>';
        }
        $skillTable .= '</tbody></table>';
        return $skillTable;
    }

    public function get_scholastic($standard_id, $student_id, $format, $digit)
    {

        // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        // sub_institute want foramt like lions 
        $format_sub_different = [61, 195];
        $extra_term =  $extra_exam = "1=1";
        if ($format != "yearly")  {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

       // get subject
       $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
       // get exam name
       $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
       // get exam title 
       $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
       //get exam marks
       $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id);

        $style = '';
        $heading = 'Scholastic Areas:';
        //only for mmis
      
        $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style=' . $style . '><b>' . $heading . '</b></th>';
        $col = 1;
        $total_term_marks = $total_sub_marks = [];
        $total_weightage = $overall_total =  $all_colspan = 0;
        $total_weightage_main ='';
        $colspan = 2;
        foreach ($term_name as $keys => $terms) {
            $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, $total_weightage) {
                $total_weightage += $title->weightage;
                return $title->term_id == $terms->term_id;
            });
            $table .= '<th colspan="' . (count($term_exam_titles) + $colspan) . '" style="text-align:center;' . $style . '"><b>' . $terms->title . $total_weightage_main . '</b></th>';
            // Initialize the total marks for each term to zero
            $total_term_marks[$terms->term_id] = 0;
            $total_sub_marks[$terms->term_id] = 0;
            $all_colspan += count($term_exam_titles);
        }
      
            $table .= '<th colspan="2" style=' . $style . '><b>Total</b></th>';
       
     $table .= '</tr>
        <tr>
            <th><b>Subject</b></th>';
        $weigthage = '';
        foreach ($term_name as $keys => $terms) {
            $total_mark = 0;
            foreach ($exam_title as $key => $title) {
                
                    $weigthage = '(' . $title->weightage . ')';
                
                $exam_head = $title->title;
               
                if ($terms->term_id == $title->term_id) {
                    $table .= '<th class="data_center"><b>' . $exam_head . '<br>' . $weigthage . '</b></th>';
                  
                        $total_mark += $title->weightage;
                   
                }
            }
            $mark_tot = '';
           
                $mark_tot = '(' . $total_mark . ')';
            
        // Store the total marks for each term
            $table .= '<th class="data_center"><b>Marks Obtained <br>' . $mark_tot . ' </b></th>';
            $overall_total += $total_mark;
            if ($sub_institute_id != 47) {
                $table .= '<th class="data_center"><b>Grade (' . $terms->title . ')</b></th>';
            }
        }
      
            $table .= '<th class="data_center"><b>Total Marks <br>Obtained (' . $overall_total . ')</b></th><th><b>Grade</b></th>';
        
        $table .= '</tr>
        </thead>
        <tbody>';
        $tot_ob_mark = $tot_sub_mark =  $get_all_ob_mark =  $get_all_tot_mark = 0;
        foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr>
            <td>' . $val->subject_name . '</td>';
            foreach ($term_name as $keys => $terms) {
                $obtained_mark = 0;
                $ob_mark = 0;
                foreach ($exam_name as $key => $title) {
                    if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                        $foundMarks = false;
                        $arr = [];
                        foreach ($exam_marks as $index => $marks) {
                            if ($title->id == $marks->exam_id) {
                                if ($marks->points == "0.00" || $marks->points == "") {
                                    $ab_ex_na = $marks->is_absent;
                                    if ($marks->is_absent == '') {
                                        $ab_ex_na = 0;
                                    }
                                    $table .= '<td class="data_center">' . $ab_ex_na . '</td>';
                                } else {
                                    if ($digit == "no_zero") {
                                        $ob_mark = intval($marks->points);
                                    } else if ($digit == "signle_zero") {
                                        $ob_mark = number_format(round($marks->points, 1), 1);
                                    } else {
                                        $ob_mark = $marks->points;
                                        
                                            $ob_mark = number_format((($ob_mark / $title->points) * $title->weightage), 2);
                                        
                                    }
                                   $obtained_mark += $ob_mark;
                                 // echo "<pre>";print_r($arr);
                                    $table .= '<td class="data_center ' . $title->exam_id . '">' . $ob_mark . '</td>';
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
               
                // echo $table;exit;
                if ($digit == "no_zero") {
                    $obtained_mark_formatted = $obtained_mark;
                } else if ($digit == "single_zero") {
                    $obtained_mark_formatted = number_format($obtained_mark, 1);
                } else {
                    $obtained_mark_formatted = number_format($obtained_mark, 2);
                }
                if ($sub_institute_id != 47) {
                    $table .= '<td class="data_center">' . $obtained_mark_formatted . '</td>';
                }
                $both_term_ob_mark += $obtained_mark_formatted;
            // Update the total marks for the current term
                $total_term_marks[$terms->term_id] += $obtained_mark;
                $total_sub_marks[$terms->term_id] += $total_mark;
                $grade_arr = $this->getGradeScale($standard_id, '');
            
                    $table .= '<td class="data_center">' . $this->getGrade($grade_arr, $total_mark, $obtained_mark_formatted) . '</td>';
                
            }
           
                $grade_arr_mmis = $this->getGradeScale($standard_id, '');
                $table .= '<td class="data_center">' . number_format($both_term_ob_mark, 2) . '</td><td class="data_center">' . $this->getGrade($grade_arr_mmis, $overall_total, $both_term_ob_mark) . '</td>';
                $get_all_ob_mark += $both_term_ob_mark;
                $get_all_tot_mark += $overall_total;
            
            $table .= '</tr>';
        }
        $table .= '<tr>';
        $table_per = $rep_val =  $table_all = '';
        $ov_ob_mark = $ov_sub_mark = $ov_ob_mark2 = $ov_sub_mark2 = 0;
        $result = "Pass";
        
        // Calculate the total marks for each term
    
            foreach ($term_name as $keys => $terms) {
                $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
                    return $title->term_id == $terms->term_id;
                });
                $tot_ob_mark = $total_term_marks[$terms->term_id];
                $tot_sub_mark = $total_sub_marks[$terms->term_id];
                if ($keys == 0) {
                    $cols = 1;
                    $val = "Overall Percentage";
                    $ov_ob_mark = $total_term_marks[$terms->term_id];
                    $ov_sub_mark = $total_sub_marks[$terms->term_id];
                } else {
                    $cols = 0;
                    $val = "Overall Grade";
                    $ov_ob_mark2 = $total_term_marks[$terms->term_id];
                    $ov_sub_mark2 = $total_sub_marks[$terms->term_id];
                }
                $all_ob_mark = ($ov_ob_mark + $ov_ob_mark2);
                $all_sub_mark = ($ov_sub_mark + $ov_sub_mark2);
        // get percentage   
                $finalPer = $this->getPer($tot_ob_mark, $tot_sub_mark);  
        // get overall percentage  
                $overall_per = $this->getPer($all_ob_mark, $all_sub_mark);    
        // get overall grade  
                $grade_arr = $this->getGradeScale($standard_id, '');

                $all_grade = \App\Helpers\getGrade($grade_arr, 100, $overall_per);
                $all_per = $overall_per . "%";
                if ($keys == 0) {
                    $rep_val = "&lt;&lt;per&gt;&gt;";
                    if ($finalPer < 33) {
                        $result = 'Promoted';
                    }
                } else {
                    $rep_val = "&lt;&lt;grade&gt;&gt;";
                    if ($finalPer < 33) {
                        $result = 'Promoted';
                    }
                }
                if (in_array($sub_institute_id, $format_sub_different)) {
                    $table .= '<td colspan="' . (count($term_exam_titles)) + $cols . '"><b>Total</b></td><td class="data_center">' . $tot_ob_mark . '</td><td rowspan="2" class="data_center">' . \App\Helpers\getGrade($grade_arr, $total_mark, $finalPer) . '</td>';
                    $table_per .= '<td colspan="' . (count($term_exam_titles)) + $cols . '"><b>Percentage</b></td><td class="data_center">' . $finalPer . '% </td>';
                    $table_all .= '<td colspan="' . (count($term_exam_titles)) + $cols . '"><b>' . $val . '</b></td><td colspan="2" class="data_center">' . $rep_val . '</td>';
                } else {
                    $table_per .= '<td colspan="' . (count($term_exam_titles)) + $cols . '"><b>Percentage</b></td><td>' . $finalPer . '% </td></td></td>';
                }
            }
         
        // exit;
            $table_all = str_replace(htmlspecialchars("<<per>>"), $all_per, $table_all);
            $table_all = str_replace(htmlspecialchars("<<grade>>"), $all_grade, $table_all);
            $table .= '<tr>' . $table_per . '</tr>';
           
                $table .= '<tr>' . $table_all . '</tr>';
            

            $res['remark'] = \App\Helpers\getGradeComment($grade_arr, 100, $overall_per) ?? '';
        
        $res['result'] = $result;
        $table .= '</tr></tbody></table>';
        $res['table'] = $table;
        return $res;
    }

    public function get_scholastic_saraswati($standard_id, $student_id, $format, $digit)
    {
        // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term = $extra_exam = "1=1";
        if ($format != "yearly")  {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->orderBy('sort_order')->get()->toArray();
       // get subject
       $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
       // get exam name
       $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
       // get exam title 
       $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam,'titlewise');
       //get exam marks
       $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id);
        $style = '';
        $heading = 'Scholastic Areas:';
         
        $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style=' . $style . '><b>' . $heading . '</b></th>';
        $col = 1;
        $total_term_marks = [];$total_sub_marks = [];
        $total_weightage = $overall_total =  $all_colspan = 0;
        $total_weightage_main ='';
        $colspan = 2;
        foreach ($term_name as $keys => $terms) {
            $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, $total_weightage) {
                $total_weightage += $title->weightage;
                return $title->term_id == $terms->term_id;
            });
            //only for mmis
            $table .= '<th colspan="' . (count($term_exam_titles) + $colspan) . '" style="text-align:center;' . $style . '"><b>' . $terms->title . $total_weightage_main . '</b></th>';
            // Initialize the total marks for each term to zero
            $total_term_marks[$terms->term_id] = 0;
            $total_sub_marks[$terms->term_id] = 0;
            $all_colspan += count($term_exam_titles);
        }
        if($format=="yearly"){
        $table .= '<th colspan="2" style=' . $style . '><b>Total</b></th>';
        }
        $table.='</tr>
        <tr>
            <th><b>Subject</b></th>';
        $weigthage = '';$tot_sub=$exam_head=[];
        foreach ($term_name as $keys => $terms) {
            $total_mark = 0;
            foreach ($exam_title as $key => $title) {
                $weigthage = '(' . $title->weightage . ')';
                if(!in_array($title->title,$exam_head)){
                    $exam_head[] = $title->title;
                
                    if ($terms->term_id == $title->term_id) {
                        $table .= '<th class="data_center"><b>' . $title->title . '<br>' . $weigthage . '</b></th>';
                        $total_mark += $title->weightage ?? 100;
                    }
                }
            }
            $mark_tot = '(' . $total_mark . ')';
            
            if($format=="yearly"){
            $table .= '<th class="data_center"><b>Marks Obtained <br>' . $mark_tot . ' </b></th>';
            $table .= '<th class="data_center"><b>Grade (' . $terms->title . ')</b></th>';
            }
           
            // Store the total marks for each term
            $overall_total += $total_mark;
          }
        // exit;
        $table .= '<th class="data_center"><b>Total Marks <br>Obtained (' . $overall_total . ')</b></th><th><b>Grade</b></th></tr>
        </thead>
        <tbody>';
        $tot_ob_mark = $tot_sub_mark =$get_all_ob_mark = $get_all_tot_mark = 0;
        // get subjets
        foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr><td>' . $val->subject_name . '</td>';
            // get term wise eam and marks 
            foreach ($term_name as $keys => $terms) {
                $obtained_marks = $to_marks = $to_weight = [];
                $title_exam = []; 
                
                 // get marks by exam id wise
                foreach ($exam_title as $key => $title) {
                    $title_exam[$title->title][] = $title->title;

                    if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                        $foundMarks = false;
                        $ob_mark = 0;
                        // all exam marks 
                        foreach ($exam_marks as $index => $marks) {
                            if ($title->id == $marks->exam_id) {
                                $total_sub_marks[$terms->term_id] +=$title->weightage;
                                $to_marks[$title->title][] = $title->points;
                                $to_weight[$title->title] = $title->weightage;

                                if ($marks->points == "0.00" || $marks->points == "") {
                                    $ab_ex_na = $marks->is_absent;
                                    if ($marks->is_absent == '') {
                                        $ab_ex_na = 0;
                                    }
                                    $obtained_marks[$title->title][] = $ab_ex_na;
                                   
                                } else {
                                    $ob_mark = $marks->points;
                                    // store marks in array to get best of 2 
                                    $obtained_marks[$title->title][] = $ob_mark;
                                    $foundMarks = true;
                                }
                                break;
                            }
                        }
                    }
                }
                // echo "<pre>";print_r($title_exam);
                $ob_main_mark = 0;
                // for best of 2 exam wise 
                if (!empty($title_exam)) {
                    foreach ($title_exam as $titleexam_id => $marksArray) {                                
                        $w_m = $to_weight[$titleexam_id] ?? 0; // Check if the key exists
                        $t_m = array_sum(array_intersect_key($to_marks[$titleexam_id] ?? [], $marksArray));
                        $obtained_mark_arr = $obtained_marks[$titleexam_id] ?? [];
                        $obtained_mark_sum = array_sum($obtained_mark_arr);
                        // get mark for total mark 
                        $ob_main_mark += ($t_m !== 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                        // convert marks if best of 2
                        $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                        $pt_per = ($convert_mark !== '0.00' && $w_m != 0) ? round(($convert_mark / $w_m) * 100, 0) : 0;
                        $table .= '<td class="data_center"' . $titleexam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . number_format($convert_mark, 2) . '</td>';
                    }
                } else {
                    // If marks not found
                    foreach ($title_exam as $exam_id => $marksArray) {
                        $table .= '<td class="data_center no_mark ' . $exam_id . '">0.00</td>';
                    }
                }            

                $obtained_mark_formatted = number_format($ob_main_mark, 2);
                
                // $table .= '<td class="data_center all_mark">' . $obtained_mark_formatted . '</td>';
                $both_term_ob_mark += $obtained_mark_formatted;
                // Update the total marks for the current term
                $total_term_marks[$terms->term_id] += $ob_main_mark;
               
                // $grade_arr = $this->getGradeScale($standard_id, '');
                if($format=="yearly"){
                   $grade_arr = $this->getGradeScale($standard_id, '');
                    $table .= '<td class="data_center tot_of_both">' . number_format($both_term_ob_mark, 2) . '</td><td class="data_center grade_of_both">' . $this->getGrade($grade_arr, $overall_total, $both_term_ob_mark) . '</td>';
                }
            }
            // get percentage 
            $grade_arr = $this->getGradeScale($standard_id, '');
            $table .= '<td class="data_center tot_of_both">' . number_format($both_term_ob_mark, 2) . '</td><td class="data_center grade_of_both">' . $this->getGrade($grade_arr, $overall_total, $both_term_ob_mark) . '</td>';
            $get_all_ob_mark += $both_term_ob_mark;
            $get_all_tot_mark += $overall_total;

            $table .= '</tr>';
        }
        // echo "<pre>";print_r($total_term_marks);
        // exit;
        $table .= '<tr>';
        $table_per = $rep_val = $table_all = '';
        $ov_ob_mark = $ov_sub_mark =$all_ob_mark=$all_sub_mark= 0;
        $result = "Pass";
        // Calculate the total marks for each term

            foreach ($term_name as $keys => $terms) {
                $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
                    return $title->term_id == $terms->term_id;
                });
                $tot_ob_mark = $total_term_marks[$terms->term_id];
                $tot_sub_mark = $total_sub_marks[$terms->term_id];
                if ($keys == 0) {
                    $cols = 2;
                } else if ($keys == 1) {
                    $cols = 1;    
                }else if ($keys == 2) {
                    $cols = 1; 
                }
                else {
                    $cols = 1;
                    $val = "Overall Grade";
                }
                $val = "Overall Percentage";
                $all_ob_mark += $total_term_marks[$terms->term_id];
                $all_sub_mark += $total_sub_marks[$terms->term_id];
                // get percentage   
                $finalPer = $this->getPer($tot_ob_mark, $tot_sub_mark);  
                
                // get overall percentage  
                $overall_per = $this->getPer($all_ob_mark, $all_sub_mark);    
                // get overall grade  
                $grade_arr = $this->getGradeScale($standard_id, '');

                $all_grade = \App\Helpers\getGrade($grade_arr, 100, $overall_per);
                $all_per = $overall_per . "%";
                // echo $overall_per;
                
                if ($keys == 0) {
                    $rep_val = "&lt;&lt;per&gt;&gt;";
                    if ($finalPer < 33) {
                        $result = 'Promoted';
                    }
                } else {
                    $rep_val = "&lt;&lt;grade&gt;&gt;";
                    if ($finalPer < 33) {
                        $result = 'Promoted';
                    }
                }
                if($format=="yearly"){
                $table_per .= '<td colspan="' . (count($term_exam_titles)) + $cols . '"><b>Percentage</b></td><td>' . $finalPer . '%</td></td>';
                $col_per=1;
                $col_grade=2;
                }else{
                    $table_per .="<td><b>Total</b></td>";//Percentage
                    $col_per=2;
                    $col_grade=1;
                    $overall_per= $finalPer."%";
                }
            }
           
        // exit; 31-03-23 29-03-24
            $table_all = str_replace(htmlspecialchars("<<per>>"), $overall_per, $table_all);
            $table_all = str_replace(htmlspecialchars("<<grade>>"), $all_grade, $table_all);
            $table .= '<tr>' . $table_per . '<td colspan="'.(count($exam_head)) .'" ><b>&nbsp;</b></td><td class="data_center" ><b>' . $tot_ob_mark . '</b></td><td class="data_center" ><b>' . $all_grade . '</b></td></tr></tbody></table>';
		
		$termAtten = $this->getTermAttendance($standard_id, $student_id, $format,'');

        $getStudentDetails = DB::table('tblstudent')->where('sub_institute_id',$sub_institute_id)->where('id',$student_id)->first();

        $getStu_hw = DB::table('student_height_weight')->select('height', 'weight', 'date')->where('sub_institute_id', $sub_institute_id)->where('syear', $syear)->where('student_id', $student_id)->orderBy('date', 'DESC')->first();

        $stu_detail='<table class="aca-year" style="width:100%;border-collapse:collapse; border:1px solid #e68023;margin-bottom:10px" cellspacing="0"  border="1" align="right">
        <tbody>
            <tr>
                <th><b><span style="font-size: medium;"><b>Date of Birth</span></b></th>
                <th><b><span style="font-size: medium;"><b>G.R.</span></b></th>
                <th><b><span style="font-size: medium;"><b>Per.</span></b></th>
            </tr>
            <tr>
                <td>'.\Carbon\Carbon::parse($getStudentDetails->dob)->format('d-m-Y').'</td>
                <td>'.$getStudentDetails->enrollment_no.'</td>
                <td>'.$overall_per.'</td>
            </tr>
            <tr>
                <th><b><span style="font-size: medium;"><b>Height</span></b></th>
                <th><b><span style="font-size: medium;"><b>Weight</span></b></th>
                <th><b><span style="font-size: medium;"><b>Att.</span></b></th>
            </tr>
            <tr>
                <td>'.(isset($getStu_hw->height) ? $getStu_hw->height : "-").'</td>
                <td>'.(isset($getStu_hw->weight) ? $getStu_hw->weight : "-").'</td>                
                <td>'.$termAtten.'</td>
            </tr>
        </tbody>
    </table>';

    $res['table'] = $table;
    $res['result'] = $result;
    $res['saraswati_stu'] = $stu_detail;        
        return $res;
    }
    
    public function scholastic_marks_saraswati_v2($standard_id, $student_id, $format, $digit)
    {
        // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term = $extra_exam = "1=1";
        if ($format != "yearly")  {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        $exam_title = DB::table('result_exam_master')->whereRaw("ExamTitle IN ('FA1','FA2','SA1','SA2')")->where(['SubInstituteId'=>$sub_institute_id,'standard_id'=>$standard_id])->orderBy('SortOrder')->get()->toArray();

        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->orderBy('sort_order')->get()->toArray();
        
        $get_subject = DB::table("sub_std_map as ssm")->join('subject as sub', 'ssm.subject_id', '=', 'sub.id')->selectRaw('ssm.id as map_id,sub.id as subject_id,ssm.display_name as subject_name,ssm.elective_subject,ssm.allow_grades')->where(['ssm.sub_institute_id' => $sub_institute_id, 'ssm.standard_id' => $standard_id, 'allow_grades' => "Yes"])->orderBy('ssm.sort_order')->get()->toArray();
        // Filter the elective subjects based on the condition
             $get_subject = array_filter($get_subject, function ($value) use ($student_id, $syear) {
                 if ($value->elective_subject == 'Yes') {
                     $check_optional_subject_with_student = DB::table('student_optional_subject')
                         ->where('student_id', $student_id)
                         ->where('subject_id', $value->subject_id)
                         ->where('syear', $syear)
                         ->count();
 
                     return $check_optional_subject_with_student > 0;
                 }
                 return true;
             });
        $total_weightage = 0;
        $table='<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" border="1">
            <thead>
                <tr>
                    <th class="data_center" width="50px"><b>No .</b></th>
                    <th class="data_center"><b>Subject</b></th>';
                    foreach ($exam_title as $key => $title) {
                        $table.='<th class="data_center"><b>'.$title->ExamTitle.'<br>('.$title->weightage.')</b></th>'; 
                        $total_weightage += $title->weightage;
                    }
            $table.='<th><b>Total<br/>('.$total_weightage.')</b></th>
            <th><b>Grade</b></th>
            </tr>            
            </thead>
            <tbody>';
        //   subjects 
        $total_mark_arr=$total_sub_arr =[];
        foreach ($get_subject as $key => $value) {
            $table .= '<tr>
            <td class="data_center" width="50px" '.$student_id.'>'.($key+1).'</td>
            <td style="text-align:left !important">' . $value->subject_name . '</td>';
            // exam wise marks
            $total_sub_mark = $total_ob_mark=0;
            foreach ($exam_title as $key2 => $exam) {
                $exam_marks = DB::table('result_marks as rm')
                ->select('rm.id', 'rm.student_id', 'rm.exam_id', 'rce.exam_id as ExamId', 'rce.title', DB::raw('SUM(rm.points) as points') ,DB::raw('sum(rce.points) as total'))
                ->join('result_create_exam as rce', 'rce.id', '=', 'rm.exam_id')
                ->where('rm.student_id', $student_id)
                ->where('rm.sub_institute_id', $sub_institute_id)
                ->where('rce.exam_id', $exam->Id)
                ->where('rce.subject_id', $value->subject_id)                
                ->where('rce.syear', $syear)
                ->groupBy('rce.exam_id')
                ->first();
                //marks
                $mark = $exam_marks->points ?? 0;
                $total = $exam_marks->total ?? 0;
                $ob_marks = ($total!=0) ? round(($mark/$total) * $exam->weightage,1) : 0;
                // total subject wise
                $total_ob_mark +=$ob_marks;
                $total_sub_mark  += $exam->weightage;
                // print marks
                $table .= '<td>'.$ob_marks.'</td>';

                // total marks array 
                if(!isset($total_sub_arr[$exam->ExamTitle])){
                    $total_sub_arr[$exam->ExamTitle]=0;
                }
                if(!isset($total_mark_arr[$exam->ExamTitle])){
                    $total_mark_arr[$exam->ExamTitle]=0;
                }
                $total_sub_arr[$exam->ExamTitle] += $exam->weightage;
                $total_mark_arr[$exam->ExamTitle] += $ob_marks; 
            }
            // get grade arr
            $grade_arr = $this->getGradeScale($standard_id, '');
            // total marks array 
            if(!isset($total_sub_arr['Total'])){
                $total_sub_arr['Total']=0;
            }
            if(!isset($total_mark_arr['Total'])){
                $total_mark_arr['Total']=0;
            }
            // total marks array             
            $total_mark_arr['Total']+=$total_ob_mark;
            $total_sub_arr['Total']+=$total_sub_mark;    
            // print subject wise marks and grade        
            $table .= '<td>'.$total_ob_mark.'</td>';
            $table .= '<td class="data_center">' . $this->getGrade($grade_arr, $total_sub_mark, $total_ob_mark) . '</td>';
            $table.='<tr>';
        }

        // all total marks and obatin marks
        $table.='<tr>
        <td colspan="2" class="data_center"><b>Total Marks</b></td>';
        if(!empty($total_sub_arr)){
            foreach ($total_sub_arr as $key => $value) {
                $table .='<td class="data_center"><b>'.$value.'</b></td>';
            }
        }
        $table .='<tr>
            <td colspan="2" class="data_center"><b>Obtained Marks</b></td>';
            if(!empty($total_mark_arr)){
                foreach ($total_mark_arr as $key => $value) {
                    $table .='<td class="data_center"><b>'.$value.'</b></td>';
                }
            }
        $table.='</tr></tbody></table>';

        // get student details
        $att = $this->get_attendance($standard_id, $student_id, $format, "attendance_hills");
        $per = $this->getPer(array_sum($total_mark_arr),array_sum($total_sub_arr));
        $getStudentDetails = DB::table('tblstudent')->where('sub_institute_id',$sub_institute_id)->where('id',$student_id)->first();

        $getStu_hw = DB::table('student_height_weight')->select('height', 'weight', 'date')->where('sub_institute_id', $sub_institute_id)->where('syear', $syear)->where('student_id', $student_id)->orderBy('date', 'DESC')->first();

        $stu_detail='<table class="aca-year" style="width:100%;border-collapse:collapse; border:1px solid #e68023;margin-bottom:10px" cellspacing="0"  border="1" align="right">
        <tbody>
            <tr>
                <th><b>Date of Birth</b></th>
                <th><b>G.R.</b></th>
                <th><b>Per.</b></th>
            </tr>
            <tr>
                <td><b>'.\Carbon\Carbon::parse($getStudentDetails->dob)->format('d-m-Y').'</b></td>
                <td><b>'.$getStudentDetails->enrollment_no.'</b></td>
                <td><b>'.$per.'</b></td>
            </tr>
            <tr>
            <th><b>Height</b></th>
            <th><b>Weight</b></th>
            <th><b>Att.</b></th>
            </tr>
            <tr>
                <td>'.(isset($getStu_hw->height) ? $getStu_hw->height : "-").'</td>
                <td>'.(isset($getStu_hw->weight) ? $getStu_hw->weight : "-").'</td>
                <td>'.$att.'</td>
            </tr>
        </tbody>
        </table>';

        $co_scholastic='';
        $getCoScholastic = DB::table('result_co_scholastic_marks_entries as comark')
                    ->selectRaw('comark.student_id,comark.co_scholastic_id,comark.term_id,cop.title as parent_title,co.title as child_title,co.co_grade,co.mark_type,IFNULL(cograde.title,"-") as obtain_grade,IFNULL(comark.points,"0") as obt_points,IFNULL(co.max_mark,"0") as max_mark')
                    ->join('result_co_scholastic as co', 'co.id', '=', 'comark.co_scholastic_id')
                    ->join('result_co_scholastic_parent as cop', 'cop.id', '=', 'co.parent_id')
                    ->LeftJoin('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
                    ->where('comark.syear', $syear)
                    ->where('comark.standard_id', $standard_id)
                    ->where('co.standard_id', $standard_id)
                    ->where('comark.student_id', $student_id)
                    ->where('comark.sub_institute_id', $sub_institute_id)
                    ->orderBy('comark.student_id')
                    ->orderBy('cop.sort_order')
                    ->orderBy('co.sort_order')
                    ->orderBy('comark.co_scholastic_id')
                    ->groupBy('co.id')
                    ->get()->toArray();

                    $grade_map =[];
                    $mapVals = array_filter($getCoScholastic, function ($value) use (&$grade_map) {
                        if ($value->co_grade != 0) {
                            $grade_map[] = $value->co_grade;
                            return true;
                        }
                        return false; 
                    });
                    
                $grade_arr_co = DB::table('result_co_scholastic_grades')->whereIN('map_id',$grade_map)->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
                                
                foreach ($getCoScholastic as $key => $value) 
                {
                    $ob_mark=0;
                    if($value->mark_type!='MARK'){
                        $ob_mark = $value->obtain_grade;
                    }else{
                        $max_mark = $value->max_mark;
                        $mark = round($value->obt_points);
                        $grade = '-';
                        foreach ($grade_arr_co as $id => $data) {
                            if ($mark == $data->break_off) {
                                $grade = $data->title;
                            }
                         }
                        $ob_mark = $grade;                
                    }
                    $getCoScholastic[$key]->ob_mark = $ob_mark;
                }
        // echo "<pre>";print_r($getCoScholastic);exit;
        // if(!empty($getCoScholastic)){
        //     $co_scholastic = '<table  class="aca-year" style="width:100%;border-collapse:collapse; border:1px solid #e68023;margin-bottom:10px" cellspacing="0"  border="1" align="right"><tr>';
        //     foreach ($getCoScholastic as $key => $value) {
        //         if($key <=2){
        //             $co_scholastic.='<th>'.$value->child_title.'</th>';
        //         }
        //     }
        //     $co_scholastic.='</tr><tr>';
        //     foreach ($getCoScholastic as $key => $value) {
        //         if($key <=2){
        //             $co_scholastic.='<td>'.$value->ob_mark.'</td>';
        //         }
        //     }
        //     $co_scholastic.='</tr><tr>';
        //     foreach ($getCoScholastic as $key => $value) {
        //         if($key >2){
        //             $co_scholastic.='<th>'.$value->child_title.'</th>';
        //         }
        //     }
        //     $co_scholastic.='</tr><tr>';
        //     foreach ($getCoScholastic as $key => $value) {
        //         if($key >2){
        //             $co_scholastic.='<td>'.$value->ob_mark.'</td>';
        //         }
        //     }
           
        //     $co_scholastic .='</tr></table>';
        // }
        $groupedData = [];
                foreach ($getCoScholastic as $key => $value) {
                    $groupedData[$value->child_title][$value->term_id] = $value->ob_mark;
                }
        // echo "<pre>";print_r($groupedData);exit;
        if(!empty($groupedData)){
            $co_scholastic = '<table  class="aca-year" style="width:100%;border-collapse:collapse; border:1px solid #e68023;margin-bottom:10px" cellspacing="0"  border="1" align="right">
            <tr>
                <th></th>';
            foreach ($term_name as $key => $terms) {
                if($terms->title=='TERM-1'){
                    $co_scholastic.='<th>Gujarati</th>';
                }elseif($terms->title=='TERM-2'){
                    $co_scholastic.='<th>Hindi</th>';
                }elseif($terms->title=='TERM-3'){
                    $co_scholastic.='<th>English</th>';
                }
            }

            $co_scholastic.='</tr>';
            $coTitle=[];
            foreach ($groupedData as $key => $value) {
               $co_scholastic.='<tr>
                <th>'.$key.'</th>';
                foreach ($term_name as $termskey => $terms) {
                    if(isset($value[$terms->term_id])){
                        $co_scholastic.='<td>'.$value[$terms->term_id].'</td>';
                    }
                }
               $co_scholastic.='</tr>';
            }
            $co_scholastic .='</table>';
    }

        $res['table'] = $table;
        $res['saraswati_stu'] = $stu_detail;  
        $res['co_scholastic'] = $co_scholastic;       
        return $res;
    }

    public function scholastic_marks_saraswati_9_v2($standard_id, $student_id, $format, $digit)
    {
        // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term = $extra_exam = "1=1";
        if ($format != "yearly")  {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        $exam_title = DB::table('result_exam_master')->whereRaw("ExamTitle IN ('FA1','FA2','SA1','SA2')")->where(['SubInstituteId'=>$sub_institute_id,'standard_id'=>$standard_id])->orderBy('SortOrder')->get()->toArray();

        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->orderBy('sort_order')->get()->toArray();

        $get_subject = DB::table("sub_std_map as ssm")->join('subject as sub', 'ssm.subject_id', '=', 'sub.id')->selectRaw('ssm.id as map_id,sub.id as subject_id,ssm.display_name as subject_name,ssm.elective_subject,ssm.allow_grades')->where(['ssm.sub_institute_id' => $sub_institute_id, 'ssm.standard_id' => $standard_id, 'allow_grades' => "Yes"])->orderBy('ssm.sort_order')->get()->toArray();
        // Filter the elective subjects based on the condition
             $get_subject = array_filter($get_subject, function ($value) use ($student_id, $syear) {
                 if ($value->elective_subject == 'Yes') {
                     $check_optional_subject_with_student = DB::table('student_optional_subject')
                         ->where('student_id', $student_id)
                         ->where('subject_id', $value->subject_id)
                         ->where('syear', $syear)
                         ->count();
 
                     return $check_optional_subject_with_student > 0;
                 }
                 return true;
             });
        $total_weightage = 0;
        $table='<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" border="1">
            <thead>
                <tr>
                    <th class="data_center" width="50px" rowspan="2"><b>No .</b></th>
                    <th rowspan="2"><b>Subject</b></th>
                    <th><b>1st<br>Term</b></th>
                    <th><b>2nd<br>Term</b></th>
                    <th colspan="2"><b>Annual<br>Evalution</b></th>
                    <th rowspan="2"><b>Total<br/>(100)</b></th>
                    <th rowspan="2"><b>Practical<br/>Assessment </b></th>
                </tr>   
                <tr>';
                foreach ($exam_title as $key => $exam) {
                    $table.='<th><b>'.$exam->weightage.'</b></th>';
                }
                $table.='</tr>         
            </thead>
            <tbody>';
             //   subjects 
        $total_mark_arr=$total_sub_arr =[];
        foreach ($get_subject as $key => $value) {

            $table .= '<tr>
            <td class="data_center" width="50px" '.$student_id.'>'.($key+1).'</td>
            <td style="text-align:left !important"><b>' . $value->subject_name . '</b></td>';
            // exam wise marks
            $total_sub_mark = $total_ob_mark=0;
            foreach ($exam_title as $key2 => $exam) {
                
                $exam_marks = DB::table('result_marks as rm')
                ->select('rm.id', 'rm.student_id', 'rm.exam_id', 'rce.exam_id as ExamId', 'rce.title', DB::raw('SUM(rm.points) as points') ,DB::raw('sum(rce.points) as total'),'rm.is_absent')
                ->join('result_create_exam as rce', 'rce.id', '=', 'rm.exam_id')
                ->where('rm.student_id', $student_id)
                ->where('rm.sub_institute_id', $sub_institute_id)
                ->where('rce.exam_id', $exam->Id)
                ->where('rce.subject_id', $value->subject_id)                
                ->where('rce.syear', $syear)
                ->groupBy('rce.exam_id')
                ->first();
                //marks
                $mark = $exam_marks->points ?? 0;
                $total = $exam_marks->total ?? 0;
                $is_absent = $exam_marks->is_absent ?? '-';
                $ob_marks = ($total!=0) ? round(($mark/$total) * $exam->weightage,1) : 0;
                // total subject wise
                $total_ob_mark +=$ob_marks;
                $total_sub_mark  += $exam->weightage;
                // print marks
                if($is_absent != null && $is_absent!='' && $is_absent!='-'){
                    $table .= '<td>'. $is_absent .'</td>';
                }else{
                    $table .= '<td>'. $ob_marks .'</td>';
                }

                // total marks array 
                if(!isset($total_sub_arr[$exam->ExamTitle])){
                    $total_sub_arr[$exam->ExamTitle]=0;
                }
                if(!isset($total_mark_arr[$exam->ExamTitle])){
                    $total_mark_arr[$exam->ExamTitle]=0;
                }
                $total_sub_arr[$exam->ExamTitle] += $exam->weightage;
                $total_mark_arr[$exam->ExamTitle] += $ob_marks; 
            }
            // get grade arr
            $grade_arr = $this->getGradeScale($standard_id, '');
           
            // total marks array 
            if(!isset($total_sub_arr['Total'])){
                $total_sub_arr['Total']=0;
            }
            if(!isset($total_mark_arr['Total'])){
                $total_mark_arr['Total']=0;
            }
            // convert into 100 
            $outof100 = ($total_sub_mark!=0) ? ($total_ob_mark * 100) / $total_sub_mark : 0 ;

            $total_mark_arr['Total']+=$outof100;
            $total_sub_arr['Total']+=100;    
            // print subject wise marks and grade        
            // $table .= '<td>18</td>';
            $table .= '<td class="data_center">'.$outof100.'</td><td class="data_center">' . $this->getGrade($grade_arr, $total_sub_mark, $total_ob_mark) . '</td>';
            $table.='<tr>';
        }

        // all total marks and obatin marks
        $allSubMark=0;
        $table.='<tr>
        <td colspan="2" class="data_center"><b>Total Marks</b></td>';
        if(!empty($total_sub_arr)){
            foreach ($total_sub_arr as $key => $value) {
                $table .='<td class="data_center"><b>'.$value.'</b></td>';
                $allSubMark +=$value;
            }
        }
        $allObMark=0;
        $table .='<td><b>Percentage</b></td>
        </tr>
        <tr>
            <td colspan="2" class="data_center"><b>Obtained Marks</b></td>';
            if(!empty($total_mark_arr)){
                foreach ($total_mark_arr as $key => $value) {
                    $table .='<td class="data_center"><b>'.$value.'</b></td>';
                    $allObMark +=$value;
                }
            }
        $per = $this->getPer($allObMark,$allSubMark);
        $table.='<td><b>'.$per.'%</b></td></tr></tbody></table>';

        $co_scholastic='';
        $getCoScholastic = DB::table('result_co_scholastic_marks_entries as comark')
                    ->selectRaw('comark.student_id,comark.co_scholastic_id,comark.term_id,cop.title as parent_title,co.title as child_title,co.co_grade,co.mark_type,IFNULL(cograde.title,"-") as obtain_grade,IFNULL(comark.points,"0") as obt_points,IFNULL(co.max_mark,"0") as max_mark')
                    ->join('result_co_scholastic as co', 'co.id', '=', 'comark.co_scholastic_id')
                    ->join('result_co_scholastic_parent as cop', 'cop.id', '=', 'co.parent_id')
                    ->LeftJoin('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
                    ->where('comark.syear', $syear)
                    ->where('comark.standard_id', $standard_id)
                    ->where('co.standard_id', $standard_id)
                    ->where('comark.student_id', $student_id)
                    ->where('comark.sub_institute_id', $sub_institute_id)
                    ->orderBy('comark.student_id')
                    ->orderBy('cop.sort_order')
                    ->orderBy('co.sort_order')
                    ->orderBy('comark.co_scholastic_id')
                    ->groupBy('co.id')
                    ->get()->toArray();

                    $grade_map =[];
                    $mapVals = array_filter($getCoScholastic, function ($value) use (&$grade_map) {
                        if ($value->co_grade != 0) {
                            $grade_map[] = $value->co_grade;
                            return true;
                        }
                        return false; 
                    });
                    
                $grade_arr_co = DB::table('result_co_scholastic_grades')->whereIN('map_id',$grade_map)->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
                                
                foreach ($getCoScholastic as $key => $value) 
                {
                    $ob_mark=0;
                    if($value->mark_type!='MARK'){
                        $ob_mark = $value->obtain_grade;
                    }else{
                        $max_mark = $value->max_mark;
                        $mark = round($value->obt_points);
                        $grade = '-';
                        foreach ($grade_arr_co as $id => $data) {
                            if ($mark == $data->break_off) {
                                $grade = $data->title;
                            }
                         }
                        $ob_mark = $grade;                
                    }
                    $getCoScholastic[$key]->ob_mark = $ob_mark;
                }
        // echo "<pre>";print_r($getCoScholastic);exit;
        if(!empty($getCoScholastic)){
            $co_scholastic = '<table  class="aca-year" style="width:80%;border-collapse:collapse; border:1px solid #e68023;margin-bottom:10px" cellspacing="0"  border="1">
            <tr>
                <th class="data_center"><b>Co-curricular Activities</b></th>
                <th class="data_center"><b>Participation</b></th>
            </tr>';
            foreach ($getCoScholastic as $key => $value) {
                $co_scholastic.=' <tr><td class="data_center"><b>'.$value->child_title.'</b></td>
                                <td class="data_center"><b>'.$value->ob_mark.'</b></td></tr>';
            }
            $co_scholastic .='</table>';
        }
        // echo "<pre>";print_r($co_scholastic);exit;
        $get_grade_ranges = $this->getGradeRange($standard_id);
        $grade_range = '';
        if (!empty($get_grade_ranges['mark_range'])) {
            $grade_range = '<table class="aca-year" style="width:80%;border-collapse:collapse; border:1px solid #e68023;margin-bottom:10px" cellspacing="0" border="1"><tr>';
        
            if (isset($get_grade_ranges['mark_range']['SCHOLASTIC_MARKS_RANGE'])) {
                foreach ($get_grade_ranges['mark_range']['SCHOLASTIC_MARKS_RANGE'] as $key => $value) {
                    if ($key <= 3) {
                        $grade_range .= '<td><b>' . $value . '</b></td><td><b>' . $get_grade_ranges['mark_range']['GRADE'][$key] . '</b></td>';
                    }
                }
        
                $grade_range .= '</tr><tr>';
        
                foreach ($get_grade_ranges['mark_range']['SCHOLASTIC_MARKS_RANGE'] as $key => $value) {
                    if ($key > 3) {
                        $grade_range .= '<td><b>' . $value . '</b></td><td><b>' . $get_grade_ranges['mark_range']['GRADE'][$key] . '</b></td>';
                    }
                }
            }
        
            $grade_range .= '</tr></table>';
        }        
        // exit;
        // echo "<pre>";print_r($grade_range);exit;
        $res['table'] = $table;
        $res['co_scholastic'] = $co_scholastic;     
        $res['grade_range'] = $grade_range ?? '';
        return $res;
    }

    public function get_scholastic_altius($standard_id, $student_id, $format, $digit)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $tot_col =0;
        $extra_term = $extra_exam = "1=1";
        $colspan_yearly=2;
        if ($format != "yearly") {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
            $colspan_yearly=2;   
            $tot_col =1;                     
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        // get subject
        $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
        // get exam name
        $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
        // get exam title 
        $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
        //get exam marks
        $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id);
        $style = '';
        $heading = 'Scholastic Areas:';
        //only for mmis
      
        $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style=' . $style . '><b>' . $heading . '</b></th>';
        $col = 1;
        $total_term_marks = $total_sub_marks = [];
        $total_weightage = $overall_total =  $all_colspan = 0;
        $total_weightage_main ='';
        $colspan = 0;//2 RAJESH
        foreach ($term_name as $keys => $terms) {
            $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, $total_weightage) {
                $total_weightage += $title->weightage;
                return $title->term_id == $terms->term_id;
            });
           
            $table .= '<th colspan="' . (count($term_exam_titles) + $colspan + $colspan_yearly) . '" style="text-align:center;' . $style . '"><b>' . $terms->title . $total_weightage_main . '</b></th>';
            // Initialize the total marks for each term to zero
            $total_term_marks[$terms->term_id] = 0;
            $total_sub_marks[$terms->term_id] = 0;
            $all_colspan += count($term_exam_titles);
        }
        //only for mmis        
       $table .= '<th class="data_center" rowspan="2"><b>Grand<br/>Total</b></th><th rowspan="2"><b>Grade</b></th>';//Total Marks <br>Obtained (' . $overall_total . ')
        $table .= '</tr><tr><th><b>Subject</b></th>';
        $weigthage = '';
        foreach ($term_name as $keys => $terms) {
            $total_mark = 0;
            foreach ($exam_title as $key => $title) {
                $exam_head = $title->title;
                if ($terms->term_id == $title->term_id) {
                    $table .= '<th class="data_center"><b>' . $exam_head . '<br>(' . $title->weightage . ')</b></th>';
                        $total_mark += $title->weightage;
                }
            }
            $mark_tot = '';
           
        // Store the total marks for each term
            $table .= '<th class="data_center"><b>Marks Obtained <br>(' . $total_mark . ')</b></th>';
            $overall_total += $total_mark;
                $table .= '<th class="data_center"><b>Grade</b></th>';//(' . $terms->title . ')
        }

        $table .= '</tr>';

//Added by rajesh for total by subjectwise
$overall_total = $overall_total / 2;

        $tot_ob_mark = $tot_sub_mark = $get_all_ob_mark = $get_all_tot_mark = 0;
        foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr>
            <td>' . $val->subject_name . '</td>';
            foreach ($term_name as $keys => $terms) {
                $obtained_mark = $ob_mark = 0;
                foreach ($exam_name as $key => $title) {
                    if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                        $foundMarks = false;
                        $arr = [];
                        foreach ($exam_marks as $index => $marks) {
                            if ($title->id == $marks->exam_id) {
                                if ($marks->points == "0.00" || $marks->points == "") {
                                    $ab_ex_na = $marks->is_absent;
                                    if ($marks->is_absent == '') {
                                        $ab_ex_na = 0.00;
                                    }
                                    $table .= '<td class="data_center">' . $ab_ex_na . '</td>';
                                } else {
                                    $ob_mark = $marks->points;
                                    // connvert marks from weightage
                                    $ob_mark = number_format((($ob_mark / $title->points) * $title->weightage), 2);
                                   $obtained_mark += $ob_mark;
                                 // echo "<pre>";print_r($arr);
                                    $table .= '<td class="data_center ' . $title->exam_id . '">' . $ob_mark . '</td>';
                                }
                                $foundMarks = true;
                                break;
                            }
                        }
                        if (!$foundMarks) {
                            $table .= '<td class="data_center">-</td>';//0.00 Rajesh
                        }
                    }
                }
               
            $obtained_mark_formatted = number_format($obtained_mark, 2);
            $table .= '<td class="data_center">' . $obtained_mark_formatted . '</td>';
                
            $both_term_ob_mark += $obtained_mark_formatted;
            // Update the total marks for the current term
            $total_term_marks[$terms->term_id] += $obtained_mark;
            $total_sub_marks[$terms->term_id] += $total_mark;
            $grade_arr = $this->getGradeScale($standard_id, '');
              
            $table .= '<td class="data_center">' . $this->getGrade($grade_arr, $total_mark, $obtained_mark_formatted) . '</td>';
                
            }
            //SubjectWise Grand Total & Grade
            $both_term_ob_mark = $both_term_ob_mark / 2;

            $grade_arr_mmis = $this->getGradeScale($standard_id, '');
            $table .= '<td class="data_center"><b>' . number_format($both_term_ob_mark, 2) . '</b></td>
                        <td class="data_center"><b>' . $this->getGrade($grade_arr_mmis, $overall_total, $both_term_ob_mark) . '</b></td>';
            $get_all_ob_mark += $both_term_ob_mark;
            $get_all_tot_mark += $overall_total;
            
            $table .= '</tr>';
        }
        // $table .= '<tr>';
        $table_per = $rep_val =  $table_all = '';
        $ov_ob_mark = $ov_sub_mark = $per1=$per2= $ov_ob_mark2 = $ov_sub_mark2 = $grade1 = $grade2= 0;
        $ov_headers = $ov_mark=$annual_grade=[];
        $pass_fail[] = "";
    // Calculate the total marks for each term

            foreach ($term_name as $keys => $terms) {
                $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
                    return $title->term_id == $terms->term_id;
                });
                $tot_ob_mark = $total_term_marks[$terms->term_id];
                $tot_sub_mark = $total_sub_marks[$terms->term_id];
                if ($keys == 0) {
                    $cols = $minus_cols = 0;//Added Rajesh
                    $val = "Half Yearly-Max";
                    $ov_ob_mark = $total_term_marks[$terms->term_id];
                    $ov_sub_mark = $total_sub_marks[$terms->term_id];
                    //$max = 33*$ov_sub_mark/100;
                    $max = $total_sub_marks[$terms->term_id];     
                    $per1 =$this->getPer($ov_ob_mark, $ov_sub_mark);  
                    $grade_arr = $this->getGradeScale($standard_id, '');

                    $grade1=$this->getGrade($grade_arr, 100, $per1);  
                    $ov_headers = ['Half Yearly Obtained','Half Yearly Percent','Half Yearly Grade'];
                    $ov_mark = ['Half Yearly Obtained'=>$ov_ob_mark,'Half Yearly Percent'=>$per1."%",'Half Yearly Grade'=>$grade1]; 
                } else {
                    $cols = 1;//0 Rajesh
                    $minus_cols = 3;//Added Rajesh
                    $val = "Annual Examination <br> Max";
                    $ov_ob_mark2 = $total_term_marks[$terms->term_id];
                    $ov_sub_mark2 = $total_sub_marks[$terms->term_id];
                    //$max = 33*$ov_sub_mark2/100;
                    $max = $total_sub_marks[$terms->term_id];
                    $per2 =$this->getPer($ov_ob_mark2, $ov_sub_mark2);   
                    $grade2=$this->getGrade($grade_arr, 100, $per2);
                    $ov_headers = ['Annual Examination Obtained','Annual Examination Percent','Annual Examination Grade'];  
                    $ov_mark = ['Annual Examination Obtained'=>$ov_ob_mark2,'Annual Examination Percent'=>$per2."%",'Annual Examination Grade'=>$grade2];     

                }
                $all_ob_mark = ($ov_ob_mark + $ov_ob_mark2);
                $all_sub_mark = ($ov_sub_mark + $ov_sub_mark2);
                // get percentage   
                $finalPer = $this->getPer($tot_ob_mark, $tot_sub_mark);
                
                // get overall percentage 
                //$overall_per = $this->getPer($all_ob_mark, $all_sub_mark); //Hide by rajesh already comes in avarage of GrandTotal
                $overall_per = $this->getPer($get_all_ob_mark, $get_all_tot_mark);

                // get overall grade  
                $all_grade = \App\Helpers\getGrade($grade_arr, 100, $overall_per);
                $all_per = $overall_per . "%";

                $annual_grade = [$get_all_ob_mark,$overall_per."%",$all_grade]; //By Rajesh $all_ob_mark
                if ($keys == 0) {
                    $rep_val = "&lt;&lt;per&gt;&gt;";
                    if ($finalPer < 33) {
                        $pass_fail[] = 'Promoted';
                    }
                } else {
                    $rep_val = "&lt;&lt;grade&gt;&gt;";
                    if ($finalPer < 33) {
                        $pass_fail[] = 'Promoted';
                    }
                }
                $table.='<td style="text-align:center" colspan="'.(1 + $cols).'"><b>'.$val.'</b></td><td style="text-align:center"><b>'.$max.'</b></td><td colspan='. (count($term_exam_titles) + $cols - $minus_cols) .' style="padding:0px !important">';
               
                $table.='<table class="aca-year" style="border:none !important;width:100%;padding:0px !important">';
                $border = 'border-top:none !important';
                // for exam heading grade,per,marks
                foreach ($ov_headers as $index => $value) {
                    $table.='<tr>';
                    
                    if($index==1 || $index==2){
                        $border = "border-top:1px solid #e68023 !important";
                    }
                    $table.='<td style="'.$border.';text-align:center"><b>'.$value.'</b></td>';
                    $table.='</tr>';
                }
                $table.='</table></td><td style="padding:0px !important">';
                $table.='<table class="aca-year" style="border:none !important;width:100%;padding:0px !important">';
                 // for exam grade,per,marks
                $border = 'border-top:none !important';
                foreach ($ov_headers as $index => $value) {
                    $table.='<tr>';
                    
                    if($index==1 ||  $index==2){
                        $border = "border-top:1px solid #e68023 !important";
                    }
                    $table.='<td style="'.$border.';text-align:center">'.$ov_mark[$value].'</td>';
                    $table.='</tr>';
                }
                $table.='</table></td>';
             
            }
            
            $table .= '<td style="padding:0px !important"><table class="aca-year" style="border:none !important;width:100%;padding:0px !important">';
            // for overall grade,per,marks 
            $border = 'border-top:none !important';
            foreach ($ov_headers as $index => $value) {
                $table.='<tr>';
                
                if($index==1 ||  $index==2){
                    $border = "border-top:1px solid #e68023 !important";
                }
                $table.='<td style="'.$border.';text-align:center"><b>'.$annual_grade[$index].'</b></td>';
                $table.='</tr>';
            }
            $table.='</table></td>';
    
           $table.='</tr>';

//Start Result Remark added by rajesh
            $get_remark = DB::table('result_remarks')
                    ->selectRaw("student_id,GROUP_CONCAT(Distinct term_id SEPARATOR '|') as term_id,GROUP_CONCAT(Distinct result_remarks SEPARATOR '|') as remark")
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('student_id', $student_id)
                    ->where('syear', $syear)
                    ->groupBy('student_id')
                    ->first();
                    
                    $get_next_std = DB::table('standard')->where('id',$standard_id)->first();
                        if(isset($get_next_std->next_standard_id)){
                        $next_std_name = DB::table('standard')->where('id',$get_next_std->next_standard_id)->first();
                            $next_std =$next_std_name->short_name;
                        }else{
                            $next_std ='';
                        }

                    if(isset($get_remark->remark)){
                        $result = str_replace('|','',$get_remark->remark);
                    }else if(in_array('Promoted',$pass_fail)){
                        $result='Promoted to Grade : '.$next_std;
                    }else{
                        $result ='Passed & Promoted to Grade : '.$next_std;
                    }
//End Result Remark added by rajesh           

        $res['remark'] = \App\Helpers\getGradeComment($grade_arr, 100, $overall_per) ?? '';
        $res['result'] = $result;
        $table .= '</tr></tbody></table>';
        $res['table'] = $table;
        return $res;
    }

    public function getPer($total_obtained_marks, $total_marks)
    {
        if ($total_marks == 0) {
            return 0; // To avoid division by zero error
        }
        $percentage = ($total_obtained_marks / $total_marks) * 100;
        return number_format($percentage, 2);
    }

    // co scholastic
    public function get_co_scholastic($standard_id, $student_id, $format, $digit)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        // co scholoastic like lions
        $format_sub_different = [61, 195];
        $extra_term =  $extra_exam = "1=1";
        if ($format != "yearly"){
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }else if($standard_id >= 2898 && $sub_institute_id==61){
            $extra_term = "term_id = 150";
            $extra_exam = "comark.term_id = 150";
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        $responce_arr = array();

        $sql_mark_grade = "select * from result_co_scholastic where sub_institute_id = " . $sub_institute_id . " and " . $extra_term . " ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));
        $extra_where="1=1";
        // if($sub_institute_id==47){
        //     $extra_where.=" AND id IN (5,6,7)";
        // }
        $co_grade_range = DB::table('result_co_scholatic_range')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereRaw($extra_where)->get()->toArray();
        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
              // Define your query using the query builder
                $ret_data = DB::table('result_co_scholastic_marks_entries as comark')
                    ->selectRaw('comark.student_id,comark.co_scholastic_id,comark.term_id,cop.title as parent_title,co.title as child_title,IFNULL(cograde.title,"-") as obtain_grade')
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
                    if($sub_institute_id==72){
                        $responce_arr[$arr['child_title']] = $arr['obtain_grade'];
                    }
                }
                if($sub_institute_id==72){
                    return $responce_arr;
                }
            }
        }
        $table='';
        if (in_array($sub_institute_id, $format_sub_different) && !empty($responce_arr)) {
            $table = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                <thead>
                <tr>';
                $optional_head = "Co-Scholastic Areas";
                if($sub_institute_id!=195 && ($sub_institute_id!=61 && $standard_id >2900  ) ){
                    $table .= '<th colspan="3" width="15%" style="text-align: left;">
                        <b>Co-Scholastic Areas</b></th>';
                    $optional_head = "Optional Subject";
    
                }
            $table .= '</tr><tr>  <th width="50%" style="text-align: left;"><b>'.$optional_head.'</b></th>';

            $col = 1;
            $total_term_marks = $total_sub_marks = [];
            foreach ($term_name as $keys => $terms) {
                if($sub_institute_id==61 && $standard_id >=2898 ){
                    $term_head= "Grade";
                }else{
                    $term_head =  $terms->title;
                }
                $table .= '<th style="text-align:center"><b>' . $term_head . '</b></th>';
            }
            $table .= '</tr>';
            $table .= '</tr></thead><tbody>';
            $val_grade = ["N.A.", "NA", "E.X.", "EX", "A.B.", "AB"];
            $maxCount = 0;
            foreach ($responce_arr as $sub => $term_data) {
                $table .= '<tr><td>' . $sub . '</td>';
                foreach ($term_name as $key => $terms) {
                    if (isset($term_data[$terms->term_id])) {
                        $table .= '<td class="data_center" style="text-align:center">' . $term_data[$terms->term_id] . '</td>';
                    } else {
                        $table .= '<td class="data_center" style="text-align:center">-</td>';
                    }
                }
                $table .= '</tr>';
            }
            $table .= '</tbody>
    </table>';
        } 
        // scholastic grade range 
        $get_grade_ranges = $this->getGradeRange($standard_id);
        // echo "<pre>";print_r($standard_id);exit;
        $head_scholastic=$head_co_scholastic=" ";

        if($sub_institute_id==47){
            $head_scholastic="SCHOLASTIC ";
            $head_co_scholastic="CO-SCHOLASTIC ";
        }
        $table_range = '<table class="aca-year hills_co" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
        <tr>
        <th class="data_center"  style="width:25%"><b>'.$head_scholastic.' MARKS RANGE</b></th>';
        if (!empty($get_grade_ranges)) {
            foreach ($get_grade_ranges['mark_range']['SCHOLASTIC_MARKS_RANGE'] as $key => $value) {
                $table_range .= '<td class="data_center">' . $value . '</td>';
            }
        }
        $table_range .= '</tr>
        <tr>
        <th class="data_center" style="width:20%"><b>GRADE</b></th>';
        if (!empty($get_grade_ranges)) {
            foreach ($get_grade_ranges['mark_range']['GRADE'] as $key => $value) {
                $table_range .= '<td class="data_center">' . $value . '</td>';
            }
        }
        $table_range .= '<tr>
        </thead></table>';

        //co grade range
        if (!empty($co_grade_range)) {
            $co_table = '<table class="aca-year hills_co" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
            <thead>
            <tr>
            <th class="data_center"  style="width:312px"><b>'.$head_co_scholastic.' MARKS RANGE</b></th>';
            foreach ($co_grade_range as $key => $value) {
                $co_table .= '<td class="data_center">' . $value->grade_max . '-' . $value->grade_min . '</td>';
            }
            $co_table .= '</tr>
            <tr>
            <th class="data_center"  style="width:312px"><b>GRADE</b></th>';
            foreach ($co_grade_range as $key => $value) {
                $co_table .= '<td class="data_center">' . $value->title . '</td>';
            }
            $co_table .= '<tr>';
            if ($sub_institute_id == 47) {
                $co_table .= '<th class="data_center"  style="width:312px"><b>REMARKS</b></th>';
                foreach ($co_grade_range as $key => $value) {
                    $co_table .= '<td class="data_center">' . $value->comment . '</td>';
                }
                $co_table .= '</tr>';
            }
            $co_table .= '</thead></table>';

        }
        $res['scholastic'] = $table ?? '';
        $res['grade_range'] = $table_range ?? '';
        $res['co_scholastic'] = $co_table ?? '';
        return $res;
    }


    public function getGradeRange($standard_id)
    {
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


    public static function getGradeScale($standard_id = '', $type = '')
    {
        if ($type == 'API') {
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $standard_id;
        } else {
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
            $standard_id = $standard_id;
        }
        $extra_where="1=1";
       $query = DB::table('result_std_grd_maping AS sgm')
            ->join('grade_master_data AS dt', 'dt.grade_id', '=', 'sgm.grade_scale')
            ->select('dt.*')
            ->where('dt.syear', $syear)
            ->where('sgm.standard', $standard_id)
            ->where('sgm.sub_institute_id', $sub_institute_id)
            ->orderByDesc('dt.breakoff');
        if($sub_institute_id==47){
            $title = "1_5";
            if($standard_id >= 103){
                $title = "6_10";
            }
            $grade_scale = DB::table('grade_master')->where('grade_name',$title)->first();
            $query=DB::table('grade_master_data')->where('sub_institute_id',$sub_institute_id)->where('syear',$syear)->where('grade_id',$grade_scale->id);
        }
        // $ret_grade = $query->toSql();

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

    public static function getGrade($grade_arr, $total_mark, $total_gain_mark, $type_co = '')
    {

        if ($total_mark == 0) {
            return "-";
        }
        // echo $total_gain_mark."/".$total_mark."<br/>";
        $per = round((100 * $total_gain_mark) / $total_mark, 0);
        //echo "<pre>";print_r($per);
        foreach ($grade_arr as $id => $data) {
           if (!isset($grade) && $type_co != '') {
                if ($per >= $data->breakoff) {
                    $grade = $data->title;
                }
            }  else if (!isset($grade)) {
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

    public function get_attendance($standard_id, $student_id, $format, $type)
    {
        // dd($type);
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term = "1=1";
        $altius_head = "Yearly";
        if ($format != "yearly") {
            $extra_term = "atd.term_id = " . $format;
            $altius_head = "Half Yearly";            
        }
        if($standard_id >= 2898 && $sub_institute_id==61){
            $extra_term = "atd.term_id = 150";            
        }
        if($sub_institute_id==254 && $type=="simple"){ 
            $extra_term = "atd.term_id = 2 and wrkd.term_id=2";      
            // echo "hello";   exit;                  
        }

        // db::enableQueryLog();
        $ret_data = DB::table('result_student_attendance_master as atd')
                ->join('result_working_day_master as wrkd', function ($join) use ($standard_id, $sub_institute_id) {
                    $join->on('wrkd.standard', '=', 'atd.standard')
                         ->on('wrkd.syear', '=', 'atd.syear')
                         ->on('wrkd.term_id', '=', 'atd.term_id');
                })
              ->select(
                'atd.student_id',
                DB::raw('SUM(total_working_day) as total_working_day'),
                DB::raw('SUM(attendance) as attendance'),
                DB::raw("GROUP_CONCAT(atd.teacher_remark SEPARATOR '|') as teacher_remark"),
                DB::raw('GROUP_CONCAT(atd.term_id) as terms')
            )
            ->where('atd.standard', $standard_id)
            ->where('atd.sub_institute_id', $sub_institute_id)
            ->where('atd.student_id', $student_id)
            ->where('atd.syear', $syear)
            ->whereRaw($extra_term)
            ->groupBy(['atd.student_id'])
            ->first();
            // dd(db::getQueryLog($ret_data));
    // echo "<pre>";print_r($ret_data);exit;
        $sim_tr = '';
        $sim_att = $sim_twd = 0;
        if (!empty($ret_data)) {
            $sim_tr = $ret_data->teacher_remark;
            $sim_att = $ret_data->attendance;
            $sim_twd = $ret_data->total_working_day;
        }
        if ($type == 'simple') {
            $res['remark'] = $sim_tr;
            $res['attendance'] = $sim_att . '/' . $sim_twd;
            return $res;
            exit;
        }


        $remark = explode('|', $ret_data->teacher_remark ?? '');
        $res['remark'] = $remark[0] ?? '';
        $res['anual'] = $remark[1] ?? '';

        if ($type == "attendance_hills") {
            $get_term = DB::table('academic_year')->selectRaw('group_concat(id) as id,group_concat(term_id) as term_id,group_concat(title) as title,group_concat(start_date) as start_date,group_concat(end_date) as end_date,group_concat(post_start_date) as post_start_date,group_concat(post_end_date) as post_end_date')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->groupBy('syear')->first();
            // echo "<pre>";print_r($student_id);exit;
            $post_start_date_ex = explode(',',$get_term->post_start_date);
            $post_end_date_ex = explode(',',$get_term->post_end_date);
            $post_start_date_final_ex = explode(',',$get_term->post_start_date);
            $post_end_date_final_ex = explode(',',$get_term->post_end_date);

            $post_start_date = $post_start_date_ex[0];
            $post_end_date = isset($post_end_date_ex[1]) ? $post_end_date_ex[1] : $post_end_date_ex[0];
            $post_start_date_final =  $post_start_date_final_ex[0];
            $post_end_date_final = isset($post_end_date_final_ex) ? $post_end_date_final_ex[1] : $post_end_date_final_ex[0];

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

/* Rajesh 02-05-2024            
$cal_event = DB::table('calendar_events as ce')
    ->join('academic_year as ay', 'ce.syear', '=', 'ay.syear')
    ->where(['ce.sub_institute_id' => $sub_institute_id, 'ce.syear' => $syear])
    ->whereRaw("FIND_IN_SET('$standard_id', ce.standard) AND ce.event_type IN ('holiday', 'vacation')")
    ->whereBetween('ce.school_date', [$post_start_date, $post_end_date])
    ->pluck('ce.school_date')
    ->toArray();

$attTotDays = 0;
$current_date = $post_start_date;
while ($current_date <= $post_end_date) {
    $day_of_week = date('w', strtotime($current_date)); // Get the day of the week (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
    if ($day_of_week != 0 && !in_array($current_date, $cal_event)) {
        $attTotDays++;
    }
    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
}
*/       
            // db::enableQueryLog();
            $attarray = DB::table('attendance_student as ap')
                ->join('tblstudent as s', 'ap.student_id', '=', 's.id')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->on('s.id', '=', 'se.student_id')
                    ->on('se.syear', '=', 'ap.syear')
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
                // dd(db::getQueryLog($attarray));
            $attarray = $attarray->pluck('present_day', 'id')->all();
            if (isset($attarray[$student_id])) {
                $table = $attarray[$student_id] . '/' . $attTotDays;
            } else {
                $table = '-/' . $attTotDays; 
            }
            if($sub_institute_id==72){
                return $table;
            }
    } else if($sub_institute_id == 195){
        // attandance_altius
        $student_details = DB::table('tblstudent')->where('id',$student_id)->first();
        $table = '<table class="aca-year" style="width: 100%;height:100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <tbody>
        <tr>
            <th style="text-align: left;"><b>Student Details</b></th>
            <th style="text-align: left;"><b>'.$altius_head.'</b></th>
        </tr>
        <tr>
        <td>Attendance</td>';
        if(isset($ret_data->total_working_day) && isset($ret_data->attendance)){
            $table .= '<td><b>'.$ret_data->attendance . '/' . $ret_data->total_working_day.'</b></td>';
        }
        $table .= '</tr>
        <tr>
        <td>Height (cm) </td>';
        if(isset($student_details)){
            $table .= '<td><b>'.$student_details->height.'</b></td>';
        }
        $table .= '</tr>

        <tr>
        <td>Weight (kg)</td>';
        if(isset($student_details)){
            $table .= '<td><b>'.$student_details->weight.'</b></td>';
        }
        $table .= '</tr>
        </tbody></table>';
    }
    // FOR LIONS 
    else {
        $table = '<table class="aca-year" style="width: 100%;height:fit-content;margin-top:8%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <tbody>
        <tr>
            <th colspan="2" style="text-align: left;"><b>Total Attendance</b></th>
        </tr>
        <tr>
            <td width="75%">No. Of Working Days</td>';
            if (isset($ret_data->total_working_day) && $type == "total_attendance") {
                $table .= '<td width="25%" align="center">' . $ret_data->total_working_day . '</td>';
            } else {
                $table .= '<td width="25%" align="center"></td>';
            }
        $table .= ' </tr>
        <tr>
            <td>Days Attended</td>';
            if (isset($ret_data->total_working_day) && $type == "total_attendance") {
                $table .= '<td align="center">' . $ret_data->attendance . '</td>';
            } else {
                $table .= '<td width="25%" align="center"></td>';
            }
            $table .= '</tr>                                                                    
            </tbody>
        </table>';
        }
        $res['table'] = $table;
        return $res;
    }

    public function get_scholastic_hills($standard_id, $student_id, $format, $academic_type)
    {
    $syear = session()->get('syear');
    $sub_institute_id = session()->get('sub_institute_id');
    $extra_term  = $extra_exam = "1=1";
    $att_term="atd.term_id = 2";
    if ($format != "yearly"){
        $extra_term = "term_id = " . $format;
        $extra_exam = "rce.term_id = " . $format;
        $att_term = "atd.term_id = " . $format;
    }

    // get term_name 
    $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
    // get subject
    $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
    // get exam master name
    $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
    // get exam title 
    $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
    //get exam marks
    $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id,'best_of_2');
    
    // scholastic table started 
    $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
    <thead>
        <tr>
            <th style="background:black;color:white"><b>Scholastic Areas:</b></th>';
                $col = 1;
                $total_term_marks = $total_sub_marks = [];
                $total_weightage = $overall_total  = $all_colspan =$last_col = 0;
                $colspan = 2;
                // get both term name and total marks per subject 
                foreach ($term_name as $keys => $terms) {
                    $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, &$total_weightage) {
                        $total_weightage += $title->weightage;
                        return $title->term_id == $terms->term_id;
                    });
                    if($academic_type=="primary"){
                        $last_col=1;
                    }
                    $colspan = 1;
                    // term tile for 1 to 5
                    if($academic_type=="primary"){
                        $table .= '<th colspan="' . (count($term_exam_titles) + $colspan + $last_col) . '" style="text-align:center;background:black;color:white"><b>' . $terms->title . '</b></th>';
                    }
                    // Initialize the total marks for each term to zero
                    $total_term_marks[$terms->term_id] = 0;
                    $total_sub_marks[$terms->term_id] = 0;
                    $all_colspan += count($term_exam_titles);
                }
                // 9 to 12 
                if($academic_type=="upper"){
                    $table .= '<th colspan="' . (count($term_exam_titles) + $colspan + 2) . '" style="text-align:center;background:black;color:white">&nbsp;</th>';//<b>ACADEMIC YEAR (100)</b> remove from rajesh 22_02_2024
                }
                $table .= '</tr><tr><th><b>Subject</b></th>';
                $weigthage = '';
                // get exam names heading like PA,SA,NB
                foreach ($term_name as $keys => $terms) {
                    $total_mark = 0;
                    foreach ($exam_title as $key => $title) {
                        $weigthage = '(' . $title->weightage . ')';

                        $exam_head = $title->ExamTitle;

                        if ($terms->term_id == $title->term_id) {
                            $table .= '<th class="data_center"><b>' . $exam_head . '<br>' . $weigthage . '</b></th>';
                            $total_mark += $title->weightage;
                        }
                    }
                    $mark_tot = '(' . $total_mark . ')';
                    $overall_total += $total_mark;
                    if($academic_type=="primary"){
                    // mark obt for term for 1 to 5                
                        $table .= '<th style="text-align:center"><b>Mark <br> Obtained ('.$total_mark.')</b></th><th style="text-align:center"><b>Grade</b></th>';
                    }
                }
                //total marks of both term headings    
                if($academic_type=="upper"){ 
                $table .= '<th class="data_center"><b>Total Marks <br>Obtained (' . $overall_total . ')</b></th>
                <th class="data_center"><b>Grade</b></th>';
                }
                $table .= '</tr>
                </thead>
                <tbody>';
                $tot_ob_mark =  $tot_sub_mark =   $get_all_ob_mark =  $get_all_tot_mark = 0;
                // grade array to get grade according to marks
                $grade_arr = $this->getGradeScale($standard_id, '');
                $pass_fail=[];
                // get all subject name 
                foreach ($get_subject as $val) {
                    $both_term_ob_mark = 0;
                    $table .= '<tr>
                    <td>' . $val->subject_name . '</td>';
                    // get term wise eam and marks 
                    foreach ($term_name as $keys => $terms) {
                        $obtained_marks = $to_marks = $to_weight = $title_exam = []; 
                            // get marks by exam id wise
                        foreach ($exam_name as $key => $title) {
                            if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                                $foundMarks = false;
                                $ob_mark = 0;
                                $title_exam[$title->exam_id][] = $title->ExamTitle;
                                // all exam marks 
                                foreach ($exam_marks as $index => $marks) {
                                    if ($title->id == $marks->exam_id) {

                                        // for AB,NA,EX
                                        if ($marks->is_absent != "") {
                                            $ab_ex_na = $marks->is_absent;
                                            // if ($marks->is_absent == '') {
                                            //     $ab_ex_na = 0;
                                            // }
                                            $obtained_marks[$title->exam_id][] = $ab_ex_na;

                                            if($ab_ex_na=="AB"){
                                                $to_marks[$title->exam_id][] = $title->points;
                                                $to_weight[$title->exam_id] = $title->weightage;
                                            }
                                        } else {
                                            $to_marks[$title->exam_id][] = $title->points;
                                            $to_weight[$title->exam_id] = $title->weightage;

                                            $ob_mark = $marks->points;
                                            // store marks in array to get best of 2 
                                            $obtained_marks[$title->exam_id][] = $ob_mark;
                                            $foundMarks = true;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                        // echo "<pre>";print_r($title_exam);exit;
                        $ob_main_mark = $ab_ex_na = $total_marks = 0;
                        // for best of 2 exam wise 
                        if (!empty($title_exam)) {
                            foreach ($title_exam as $exam_id => $marksArray) {                                
                                $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                                $t_m = array_sum(array_intersect_key($to_marks[$exam_id] ?? [], $marksArray));
                                $obtained_mark_arr = $obtained_marks[$exam_id] ?? [];
                                // // Sort the array in descending order
                                // rsort($obtained_mark_arr);
                                // // get best 2 from array
                                // $best_two = array_slice($obtained_mark_arr, 0, 2);
                                // $obtained_mark_sum = array_sum($best_two);
                                $obtained_mark_sum = array_sum($obtained_mark_arr);

                                // get mark for total mark 
                                $ob_main_mark += ($t_m !== 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                                // convert marks if best of 2
                                $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                                
                                $pt_per = ($convert_mark !== '0.00' && $w_m != 0) ? round(($convert_mark / $w_m) * 100, 0) : 0;
                        
                                $underline = ($pt_per < 33 && $academic_type == "upper") ? 'style="text-decoration: underline red 2px;"' : '';
                                if(count($obtained_mark_arr) > 1){
                                    $total_marks += $w_m;
                                    $table .= '<td class="data_center" ' . $underline . ' ' . $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . number_format($convert_mark, 2) . '</td>';
                                }else{
                                    if(isset($obtained_mark_arr[0]) && ($obtained_mark_arr[0]!='N.A.' || $obtained_mark_arr[0]!='EX')){
                                        $total_marks += $w_m;
                                    }
                                    $tdVal = number_format($convert_mark, 2);
                                    if(isset($obtained_mark_arr[0]) && $obtained_mark_arr[0]=="AB"){
                                        $tdVal = "AB";
                                    }
                                    /* (Hills) Hide by rajesh std-1 display without convert marks */
                                    //$table .= '<td class="data_center" '. $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . number_format((float)$obtained_mark_arr[0], 2) .'</td>';
                                    $table .= '<td class="data_center else" ' . $underline . ' ' . $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . $tdVal . '</td>';
                                }
                                
                        // echo $exam_id;echo "<pre>";print_r($obtained_mark_sum);
                                
                            }
                        } else {
                            // If marks not found
                            foreach ($title_exam as $exam_id => $marksArray) {
                                $table .= '<td class="data_center no_mark ' . $exam_id . '">0.00</td>';
                            }
                        }            
                        $obtained_mark_formatted = number_format($ob_main_mark, 2);
                        
                        if($academic_type=="primary"){
                            $total_obt_mark =  round($obtained_mark_formatted,0);
                            if($total_obt_mark==0){
                                $grade_std = $this->getGrade($grade_arr, 0,$total_obt_mark);
                            }else{
                                $grade_std = $this->getGrade($grade_arr,  $total_marks,$total_obt_mark);
                            }
                            // $grade_std = $this->getGrade($grade_arr,  $total_mark,$total_obt_mark);
                            if($grade_std=="E"){
                                $pass_fail[]='Failed';
                            }
                            $table .= '<td class="data_center all_mark">' . round($obtained_mark_formatted,0) . '</td><td class="data_center grade_of_both">'. $grade_std . '</td>';
                        }                
                        $both_term_ob_mark += $obtained_mark_formatted;
                        // Update the total marks for the current term
                        $total_term_marks[$terms->term_id] += $ob_main_mark;
                        $total_sub_marks[$terms->term_id] += $total_mark;
                        // $grade_arr = $this->getGradeScale($standard_id, '');
                    } 
                    // get percentage 
                    if( ($standard_id >= 3296 && $standard_id <=3307) || ($standard_id >= 3313 && $standard_id <=3316) && $academic_type=="upper"){   
                        $total_obt_mark =  round($obtained_mark_formatted,0);
                        if($total_obt_mark==0){
                            $grade_std = $this->getGrade($grade_arr, 0,$total_obt_mark);
                        }else{
                            $grade_std = $this->getGrade($grade_arr,  $overall_total,$total_obt_mark);
                        }
                        // $grade_std = $this->getGrade($grade_arr, $overall_total,round($both_term_ob_mark, 0));   
                        if($grade_std=="E"){
                            $pass_fail[]='Failed';
                        }   
                    
                    $underline = ($grade_std == "E") ? 'style="text-decoration: underline red 2px;"' : ''; // added by rajesh 22_02_2024
                    
                    $table .= '<td class="data_center tot_of_both" ' . $underline . '>' . round($both_term_ob_mark, 0) . '</td><td class="data_center grade_of_both">' . $grade_std . '</td>';
                    }
                    $get_all_ob_mark += $both_term_ob_mark;
                    $get_all_tot_mark += $overall_total;

                    $table .= '</tr>';
                }
                // exit;
            $table .= '<tr>';
    
        $table .= '</tr></tbody></table>';
    
                $res['scholastic'] = $table;
                $ret_data = DB::table('result_student_attendance_master as atd')
                    ->join('result_working_day_master as wrkd', function ($join) use ($standard_id, $sub_institute_id) {
                        $join->on('wrkd.standard', '=', 'atd.standard')
                            ->on('wrkd.sub_institute_id', '=', 'atd.sub_institute_id');
                    })
                    ->selectRaw("atd.student_id,wrkd.total_working_day,atd.attendance,GROUP_CONCAT(Distinct atd.teacher_remark SEPARATOR '|') as teacher_remark")
                    ->where('atd.standard', $standard_id)
                    ->where('atd.sub_institute_id', $sub_institute_id)
                    ->where('atd.student_id', $student_id)
                    ->where('atd.syear', $syear)
                    ->whereRaw($att_term)
                    ->groupBy('student_id')
                    ->first();
                    
                $res['teacher_remark'] = '';
                if (!empty($ret_data)) {
                    $student_Remark = str_replace('|','',$ret_data->teacher_remark);
                    $res['teacher_remark'] = $student_Remark;
                }

                    $get_remark = DB::table('result_remarks')
                    ->selectRaw("student_id,GROUP_CONCAT(Distinct term_id SEPARATOR '|') as term_id,GROUP_CONCAT(Distinct result_remarks SEPARATOR '|') as remark")
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('student_id', $student_id)
                    ->where('syear', $syear)
                    ->groupBy('student_id')
                    ->first();
                    
                    if(isset($get_remark->remark)){
                        $pass_or_fail = str_replace('|','',$get_remark->remark);
                    }else if(in_array('Failed',$pass_fail)){
                        $pass_or_fail='Failed';
                    }else{
                        $get_next_std = DB::table('standard')->where('id',$standard_id)->first();
                        if(isset($get_next_std->next_standard_id)){
                        $next_std_name = DB::table('standard')->where('id',$get_next_std->next_standard_id)->first();
                            $next_std =$next_std_name->short_name;
                        }else{
                            $next_std ='';
                        }
                        $pass_or_fail ='Passed & Promoted to class : '.$next_std;
                    }

                $res['pass_or_fail'] = ($format != "yearly") ? '' : $pass_or_fail;
 
        return $res;
    }
   
    public function get_scholastic_hills_t1_9($standard_id, $student_id, $format, $academic_type)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term  = $extra_exam = "1=1";
        $att_term="atd.term_id = 1";
        if ($format != "yearly"){
            $extra_term = "term_id = 2";
            $extra_exam = "rce.term_id = 2";
            $att_term = "atd.term_id = 1";
        }

    // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        // get subject
        $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
        // get exam master name
        $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
        // get exam title 
        $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
        //get exam marks
        $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id,'best_of_2');
        // echo "<pre>";print_r($exam_name);
        
        // scholastic table started 
        $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style="background:black;color:white"><b>Scholastic Areas:</b></th>';
                    $col = 1;
                    $total_term_marks = $total_sub_marks = [];
                    $total_weightage = $overall_total  = $all_colspan =$last_col = 0;
                    $colspan = 2;
                    // get both term name and total marks per subject 
                    foreach ($term_name as $keys => $terms) {
                        $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, &$total_weightage) {
                            $total_weightage += $title->weightage;
                            return $title->term_id == $terms->term_id;
                        });
                        $colspan = 1;
                        // Initialize the total marks for each term to zero
                        $total_term_marks[$terms->term_id] = 0;
                        $total_sub_marks[$terms->term_id] = 0;
                        $all_colspan += count($term_exam_titles);
                    }
                    // 9 to 12 
                    if($academic_type=="upper"){
                        $table .= '<th colspan="' . (count($term_exam_titles) + $colspan + 2) . '" style="text-align:center;background:black;color:white">Progress Report Card</th>';//<b>ACADEMIC YEAR (100)</b> remove from rajesh 22_02_2024
                    }
                    $table .= '</tr><tr><th><b>Subject</b></th>';
                    $weigthage = '';
                    // get exam names heading like PA,SA,NB
                    foreach ($term_name as $keys => $terms) {
                        $total_mark = 0;
                        foreach ($exam_title as $key => $title) {
                            $weigthage = '(' . $title->weightage . ')';

                            $exam_head = $title->ExamTitle;
                            if($exam_head == 'Periodic Test'){
                                $i = 0;
                                $pts = ($title->standard_id == 3299 || $title->standard_id == 3965) ? 3 : 2;
                                $printed_titles = []; // Array to keep track of printed titles
                                foreach ($exam_name as $key => $value) {
                                    if ($i < $pts && !in_array($value->title, $printed_titles) && $value->ExamTitle == 'Periodic Test') {
                                        $table .= '<th class="data_center"><b>' . $value->title . '<br>(' . $value->points . ')</b></th>';
                                        $printed_titles[] = $value->title; // Add the title to printed_titles array
                                        $i++;
                                    }
                                }
                            }else{
                                if ($terms->term_id == $title->term_id) {
                                    $table .= '<th class="data_center"><b>' . $exam_head . '<br>' . $weigthage . '</b></th>';
                                    $total_mark += $title->weightage;
                                }
                            }
                        }
                        $mark_tot = '(' . $total_mark . ')';
                        $overall_total += $total_mark;
                    }
                    $table .= '</tr>
                    </thead>
                    <tbody>';
                    $tot_ob_mark = $tot_sub_mark = $get_all_ob_mark = $get_all_tot_mark = 0;

                    // get all subject name 
                    foreach ($get_subject as $val) {
                        $both_term_ob_mark = 0;
                        $table .= '<tr>
                        <td>' . $val->subject_name . '</td>';
                        // get term wise eam and marks 
                        foreach ($term_name as $keys => $terms) {
                            $obtained_marks = $to_marks = $to_weight = $title_exam = []; 
                                // get marks by exam id wise
                            foreach ($exam_name as $key => $title) {
                                if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                                    $foundMarks = false;
                                    $ob_mark = 0;
                                    // By Rajesh - Display periodic test exam and other diaply type 06-08-2024
                                    if($title->ExamTitle=='Periodic Test'){
                                        $arr = $title->id;
                                        $title_exam[$arr][] = $title->title;
                                        $weightage = $title->points;
                                    }else{
                                        $arr = $title->exam_id;
                                        $title_exam[$arr][] = $title->ExamTitle;
                                        $weightage = $title->weightage;
                                    }
                                    // all exam marks 
                                    foreach ($exam_marks as $index => $marks) {
                                        if ($title->id == $marks->exam_id) {

                                            // for AB,NA,EX
                                            if ($marks->is_absent != "") {
                                                $ab_ex_na = $marks->is_absent;
                                                $obtained_marks[$arr][] = $ab_ex_na;

                                                if($ab_ex_na=="AB"){
                                                    $to_marks[$arr][] = $title->points;
                                                    $to_weight[$arr] = $weightage;
                                                }
                                            } else {
                                                $to_marks[$arr][] = $title->points;
                                                $to_weight[$arr] = $weightage;
                                                $ob_mark = $marks->points;
                                                // store marks in array to get best of 2 
                                                $obtained_marks[$arr][] = $ob_mark;
                                                $foundMarks = true;
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            $ob_main_mark = $ab_ex_na = $total_marks = 0;
                            // for best of 2 exam wise 
                            if (!empty($title_exam)) {
                                foreach ($title_exam as $exam_id => $marksArray) {                                
                                    $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                                    $t_m = array_sum(array_intersect_key($to_marks[$exam_id] ?? [], $marksArray));
                                    $obtained_mark_arr = $obtained_marks[$exam_id] ?? [];
                                    $obtained_mark_sum = array_sum($obtained_mark_arr);
                                    
                                    // get mark for total mark 
                                    $ob_main_mark += ($t_m !== 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                                    // convert marks if best of 2
                                    if($standard_id==3299 || $standard_id==3965){
                                        if(count($obtained_mark_arr) > 1){
                                            $convert_mark = max($obtained_mark_arr); // get greatest max
                                        }else{
                                            $convert_mark = $obtained_mark_sum; // for PT
                                        }
                                    }else{
                                        $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                                    }
                                    
                                    $pt_per = ($convert_mark !== '0.00' && $w_m != 0) ? round(($convert_mark / $w_m) * 100, 0) : 0;
                            
                                    // $underline = ($pt_per < 33 && $academic_type == "upper" && $standard_id!=3299 && $standard_id!=3965) ? 'style="text-decoration: underline red 2px;"' : '';
                                    $underline = ($pt_per < 33 && $academic_type == "upper") ? 'style="text-decoration: underline red 2px;"' : '';
                                    if(count($obtained_mark_arr) > 1) {
                                        $total_marks += $w_m;
                                        $table .= '<td class="data_center" ' . $underline . ' ' . $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . number_format($convert_mark, 2) . '</td>';
                                    }else {
                                        if (!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX"])) {
                                            $total_marks += $w_m;
                                        }

                                        if(!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX","AB"])){
                                            $tdVal = number_format($convert_mark, 2);
                                        }else{
                                            $tdVal = $obtained_mark_arr[0] ?? "0.00"; // print AB,NA,EX
                                        }
                                        $table .= '<td class="data_center else" ' . $underline . ' ' . $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . $tdVal . '</td>';
                                    }
                                }
                            } else {
                                // If marks not found
                                foreach ($title_exam as $exam_id => $marksArray) {
                                    $table .= '<td class="data_center no_mark ' . $exam_id . '">0.00</td>';
                                }
                            }            
                        } 
                        $table .= '</tr>';
                    }
                    // exit;
                $table .= '<tr>';
        
            $table .= '</tr></tbody></table>';
    
                $res['scholastic'] = $table;
                $ret_data = DB::table('result_student_attendance_master as atd')
                    ->join('result_working_day_master as wrkd', function ($join) use ($standard_id, $sub_institute_id) {
                        $join->on('wrkd.standard', '=', 'atd.standard')
                            ->on('wrkd.sub_institute_id', '=', 'atd.sub_institute_id');
                    })
                    ->selectRaw("atd.student_id,wrkd.total_working_day,atd.attendance,GROUP_CONCAT(Distinct atd.teacher_remark SEPARATOR '|') as teacher_remark")
                    ->where('atd.standard', $standard_id)
                    ->where('atd.sub_institute_id', $sub_institute_id)
                    ->where('atd.student_id', $student_id)
                    ->where('atd.syear', $syear)
                    ->whereRaw($att_term)
                    ->groupBy('student_id')
                    ->first();
                    
                $res['teacher_remark'] = '';
                if (!empty($ret_data)) {
                    $student_Remark = str_replace('|','',$ret_data->teacher_remark);
                    $res['teacher_remark'] = $student_Remark;
                }
        return $res;
    }

    public function get_scholastic_hills_10_11($standard_id, $student_id, $format, $academic_type){
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        // Initialize variables
        $extra_term = $extra_exam = "1=1";
        $att_term = "atd.term_id = 2";
        if ($format != "yearly") {
            $extra_term = "term_id = 2";
            $extra_exam = "rce.term_id = 2";
            $att_term = "atd.term_id = 1";
        }

        // Retrieve data from database
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        $get_subject = $this->get_subject($sub_institute_id, $syear, $student_id, $standard_id);
        $exam_marks = $this->get_exam_marks($sub_institute_id, $student_id, 'best_of_2');
        $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
        // get exam title 
        $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);

        $heads =DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->where('rce.report_card_status','Y')->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])
        ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id')
        ->orderBy('rce.sort_order')
        ->get()->toArray();//->whereRaw($extra_exam)

        // Initialize table
        $table = '<style>.data_center{text-align:center !important;}</style>';
        $table .= '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
            <thead>
                <tr>
                    <th style="background:black;color:white"><b>Scholastic Areas:</b></th>';

        $colspan = $academic_type == "primary" ? 1 : 2;
        $total_weightage = $overall_total = $all_colspan = 0;

        $all_colspan += count($heads);

        $table .= '<td colspan='.$all_colspan.' class="data_center" style="background:black;color:white"><b>Progress Report Card</b></td></tr>
                    <tr><th><b>Subject</b></th>';
        
        // Generate exam headers
            $total_mark = 0;
            foreach ($term_name as $keys => $terms) {
                $total_mark = 0;
                foreach ($exam_title as $key => $title) {
                    $weigthage = '(' . $title->con_point . ')';

                    $exam_head = $title->ExamTitle;
                    if($exam_head == 'Periodic Test'){
                        $i = 0;
                        $printed_titles = []; // Array to keep track of printed titles
                        foreach ($exam_name as $key => $value) {
                            if (!in_array($value->title, $printed_titles) && $value->ExamTitle == 'Periodic Test') {
                                //$table .= '<th class="data_center"><b>' . $value->title . '<br>(' . $value->points . ')</b></th>';
                                $table .= '<th class="data_center"><b>' . $value->title . '<br>(' . $value->con_point . ')</b></th>';
                                $printed_titles[] = $value->title; // Add the title to printed_titles array
                                $i++;
                            }
                        }
                    }else{
                        if ($terms->term_id == $title->term_id) {
                            $table .= '<th class="data_center"><b>' . $exam_head . '<br>' . $weigthage . '</b></th>';
                            $total_mark += $title->weightage;
                        }
                    }
                }
                $mark_tot = '(' . $total_mark . ')';
                $overall_total += $total_mark;
            }
      
        $table .= '</tr></thead><tbody>';

        $grade_arr = $this->getGradeScale($standard_id, '');
        $pass_fail = [];
        // echo "<pre>";print_r($newHead);exit;
        // Process each subject
         foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr>
            <td>' . $val->subject_name . '</td>';
            // get term wise eam and marks 
            foreach ($term_name as $keys => $terms) {
                $obtained_marks = $to_marks = $to_weight = $title_exam = []; 
                    // get marks by exam id wise
                foreach ($exam_name as $key => $title) {
                    if ($title->subject_id == $val->subject_id) {
                       
                        $ob_mark = 0;
                        // By Rajesh - Display periodic test exam and other diaply type 06-08-2024
                        if($title->ExamTitle=='Periodic Test'){
                            $arr = $title->id;
                            $title_exam[$arr][] = $title->title;
                            //$weightage = $title->points;
                            $weightage = $title->con_point;
                        }else{
                            $arr = $title->exam_id;
                            $title_exam[$arr][] = $title->ExamTitle;
                            $weightage = $title->con_point;
                        }
                        // all exam marks 
                        foreach ($exam_marks as $index => $marks) {
                            if ($title->id == $marks->exam_id) {
                                // for AB,NA,EX
                                if ($marks->is_absent != "") {
                                    $ab_ex_na = $marks->is_absent;
                                    $obtained_marks[$arr][] = $ab_ex_na;

                                    if($ab_ex_na=="AB"){
                                        $to_marks[$arr][] = $title->points;
                                        $to_weight[$arr] = $weightage;
                                    }
                                } else {
                                    $to_marks[$arr][] = $title->points;
                                    $to_weight[$arr] = $weightage;

                                    $ob_mark = $marks->points;
                                    // store marks in array to get best of 2 
                                    $obtained_marks[$arr][] = $ob_mark;
                                }
                                // break;
                            }
                        }
                    }
                }
                // echo "<pre>";print_r($obtained_marks);
                // echo "<pre>";print_r($to_marks);
                $ob_main_mark = $ab_ex_na = $total_marks = 0;

                // for best of 2 exam wise 
                if (!empty($title_exam)) {
                    foreach ($title_exam as $exam_id => $marksArray) {                                
                        $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                        $obtained_mark_arr = $obtained_marks[$exam_id] ?? [];
                        // best of 2
                        arsort($obtained_mark_arr); // sort array Desc
                        $obtained_mark_arr = array_slice($obtained_mark_arr, 0, 2, true); // Get the top 2 obtained_marks

                        // Get the corresponding to_marks using the keys of best_obtained
                        $best_to_marks = [];
                        $t_m=0; // get total_marks as per best of 2 arrays
                        foreach ($obtained_mark_arr as $key => $mark) {
                            $t_m += $to_marks[$exam_id][$key] ?? 0;
                        }
                        // sum of best of 2
                        $obtained_mark_sum = array_sum($obtained_mark_arr);

                        // Convert marks
                        $ob_main_mark += ($t_m !== 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                        $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                        $pt_per = ($convert_mark !== '0.00' && $w_m != 0) ? round(($convert_mark / $w_m) * 100, 0) : 0;
                        $underline = ($pt_per < 33) ? 'style="text-decoration: underline red 2px;"' : '';
                        // for other exams
                        if(count($obtained_mark_arr) > 1) {
                            $total_marks += $w_m;
                            $tdVal = number_format($convert_mark, 2);
                        }
                        //  for PT 
                        else {
                            if (!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX"])) {
                                $total_marks += $w_m;
                            }

                            if(!empty($obtained_mark_arr) && !in_array($obtained_mark_arr[0],["N.A.","EX","AB"])){
                                $tdVal = number_format($convert_mark, 2);
                            }else{
                                $tdVal = $obtained_mark_arr[0] ?? "0.00"; // print AB,NA,EX
                            }
                           
                        }
                        $table .= '<td class="data_center else" ' . $underline . ' ' . $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . $tdVal . '</td>';
                    }
                } else {
                    // If marks not found
                    foreach ($title_exam as $exam_id => $marksArray) {
                        $table .= '<td class="data_center no_mark ' . $exam_id . '">0.00</td>';
                    }
                }            
            } 
            $table .= '</tr>';
        }
        // exit;
        $table .= '<tr></tr></tbody></table>';
        $res['scholastic'] = $table;

        // Fetch attendance and remarks
        $ret_data = DB::table('result_student_attendance_master as atd')
            ->join('result_working_day_master as wrkd', function ($join) use ($standard_id, $sub_institute_id) {
                $join->on('wrkd.standard', '=', 'atd.standard')
                    ->on('wrkd.sub_institute_id', '=', 'atd.sub_institute_id');
            })
            ->selectRaw("atd.student_id, wrkd.total_working_day, atd.attendance, GROUP_CONCAT(Distinct atd.teacher_remark SEPARATOR '|') as teacher_remark")
            ->where('atd.standard', $standard_id)
            ->where('atd.sub_institute_id', $sub_institute_id)
            ->where('atd.student_id', $student_id)
            ->where('atd.syear', $syear)
            ->whereRaw($att_term)
            ->groupBy('student_id')
            ->first();

        $res['teacher_remark'] = !empty($ret_data) ? str_replace('|', '', $ret_data->teacher_remark) : '';

        // Determine pass/fail status
        $get_remark = DB::table('result_remarks')
            ->selectRaw("student_id, GROUP_CONCAT(Distinct term_id SEPARATOR '|') as term_id, GROUP_CONCAT(Distinct result_remarks SEPARATOR '|') as remark")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('student_id', $student_id)
            ->where('syear', $syear)
            ->groupBy('student_id')
            ->first();

        if (isset($get_remark->remark)) {
            $pass_or_fail = str_replace('|', '', $get_remark->remark);
        } else if (in_array('Failed', $pass_fail)) {
            $pass_or_fail = 'Failed';
        } else {
            $get_next_std = DB::table('standard')->where('id', $standard_id)->first();
            $next_std_name = $get_next_std->next_standard_id ? DB::table('standard')->where('id', $get_next_std->next_standard_id)->first()->short_name : '';
            $pass_or_fail = 'Passed & Promoted to class : ' . $next_std_name;
        }

        $res['pass_or_fail'] = ($format != "yearly") ? '' : $pass_or_fail;

        return $res;
    }

    public function get_scholastic_lions($standard_id, $student_id, $format, $academic_type)
    {  // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term =$extra_exam = "1=1";
        if ($format != "yearly"){
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

       // get subject
       $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
       // get exam name
       $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
       // get exam title 
       $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
       //get exam marks
       $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id,$syear,$standard_id,$extra_exam,'lions');

        $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th><b>Scholastic Areas:</b></th>';
                $col = 1;
                $total_term_marks = $total_sub_marks = [];
                $total_weightage = $overall_total =  $all_colspan = 0;
                $colspan = 2;
                // print term title like term-1 or term-2
                foreach ($term_name as $keys => $terms) {
                    $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
                        return $title->term_id == $terms->term_id;
                    });
                    $total_weightage = array_reduce(
                        array_filter($exam_title, function ($title) use ($terms) {
                            return $title->term_id == $terms->term_id;
                        }),
                        function ($carry, $title) {
                            return $carry + $title->weightage;
                        },
                        0
                    );
                
                    $table .= '<th colspan="' . (count($term_exam_titles) + $colspan) . '" style="text-align:center;"><b>' . $terms->title.'(' . $total_weightage . ')</b></th>';
                    // Initialize the total marks for each term to zero
                    $total_term_marks[$terms->term_id] = 0;
                    $total_sub_marks[$terms->term_id] = 0;
                    $all_colspan += count($term_exam_titles);
                }

        $table .= '</tr>
        <tr>
            <th><b>Subject</b></th>';
                // exam names like periodic test,portfolio etc
                foreach ($term_name as $keys => $terms) {
                    $total_mark = 0;
                    foreach ($exam_title as $key => $title) {
                        if ($terms->term_id == $title->term_id) {
                            $table .= '<th class="data_center"><b>' . $title->ExamTitle . '<br>(' . $title->weightage . ')</b></th>';
                                $total_mark += $title->weightage;
                        }
                    }
                    // Store the total marks for each term
                    $table .= '<th class="data_center"><b>Marks Obtained <br>('.$total_mark.') </b></th>';
                    $overall_total += $total_mark;
                    $table .= '<th class="data_center"><b>Grade (' . $terms->title . ')</b></th>';
                }
       
        $table .= '</tr>
        </thead>
        <tbody>';
        // grade array to get grade according to marks
        $grade_arr = $this->getGradeScale($standard_id, '');

        $tot_ob_mark =  $tot_sub_mark =   $get_all_ob_mark =  $get_all_tot_mark =$pass_or_fail = 0;
        // get all subject name 
        foreach ($get_subject as $val) {
            $both_term_ob_mark =0;
            $subject_total=[];
            $table .= '<tr>
            <td>' . $val->subject_name . '</td>';
            // get term wise eam and marks 
            foreach ($term_name as $keys => $terms) {
                $obtained_marks =$to_marks = $to_weight = $title_exam = []; 
                // get marks by exam id wise
                foreach ($exam_name as $key => $title) {
                    if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                        $foundMarks = false;
                        $title_exam[$title->exam_id][] = $title->ExamTitle;
                        // all exam marks 
                        foreach ($exam_marks as $index => $marks) {
                            if ($title->id == $marks->exam_id) {
                                $to_marks[$title->exam_id][] = $title->points;
                                $to_weight[$title->exam_id] = $title->weightage;
                                // for AB,NA,EX
                                if ($marks->is_absent != "") {
                                    $ab_ex_na = $marks->is_absent;
                                    $obtained_marks[$title->exam_id][] = $ab_ex_na;
                                } else {
                                    // store marks in array to get best of 2 
                                    $obtained_marks[$title->exam_id][] = $marks->points;
                                    $foundMarks = true;
                                }
                                break;
                            }
                        }
                    }
                }
                //    echo "<pre>";print_r($obtained_marks);
                $ob_main_mark = 0;
                // for best of 2 exam wise 
                if (!empty($title_exam)) {
                    foreach ($title_exam as $exam_id => $marksArray) {                                
                        $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                        $t_m = isset($to_marks[$exam_id]) ? array_values($to_marks[$exam_id]) : [0=>0];
                        $obtained_mark_arr = isset($obtained_marks[$exam_id]) ? array_values($obtained_marks[$exam_id]) : [0=>0];
                        
                        $obtained_mark_sum = end($obtained_mark_arr);
                        $t_m = end($t_m);                                                                        
                        if(!isset($subject_total[$terms->term_id])){
                            $subject_total[$terms->term_id] = 0;
                        }
                    //    echo "<pre>";print_r($obtained_mark_arr); echo '-'.$exam_id.'-';
                        if($obtained_mark_sum=="AB" || $obtained_mark_sum=="EX" || $obtained_mark_sum=="N.A." || $obtained_mark_sum=="A.B"){
                            $convert_mark = $obtained_mark_sum;
                            if($obtained_mark_sum=="A.B" || $obtained_mark_sum=="AB"){
                                $subject_total[$terms->term_id] += $w_m;    
                            }                        
                        }else{
                            $ob_main_mark += ($obtained_mark_sum !=0 ) ? number_format(($obtained_mark_sum / $t_m) * $w_m,0) : 0;
                            $convert_mark = ($obtained_mark_sum !=0 ) ?  number_format(($obtained_mark_sum / $t_m) * $w_m,0) : '0';
                            $subject_total[$terms->term_id] += $w_m;                            
                        }
                        
                        $table .= '<td class="data_center" '.$exam_id.' '.$val->subject_id.'>' . $convert_mark .'</td>';
                       
                        // if($convert_mark!=0){
                        // }
                    }
                } else {
                    // If marks not found
                    foreach ($title_exam as $exam_id => $marksArray) {
                        $table .= '<td class="data_center no_mark ' . $exam_id . '">0</td>';
                    }
                }            
                $obtained_mark_formatted = number_format($ob_main_mark, 0);
                if(isset($subject_total[$terms->term_id]) && $subject_total[$terms->term_id]!=0){
                    $tot_mark = ($obtained_mark_formatted * 100)/$subject_total[$terms->term_id];
                    if($tot_mark < 33){
                        $pass_or_fail++;
                    }
                }
                
                $table .= '<td class="data_center all_mark">' . $obtained_mark_formatted .'</td><td class="data_center grade_of_both">'. $this->getGrade($grade_arr, isset($subject_total[$terms->term_id]) ? $subject_total[$terms->term_id] : 0, $obtained_mark_formatted) . '</td>';
                                
                $both_term_ob_mark += $obtained_mark_formatted;
                // Update the total marks for the current term
                $total_term_marks[$terms->term_id] += $ob_main_mark;
                $total_sub_marks[$terms->term_id] += isset($subject_total[$terms->term_id]) ? $subject_total[$terms->term_id] : 0;
            } 
            
            $get_all_ob_mark += $both_term_ob_mark;
            $get_all_tot_mark += array_sum($subject_total);
            $table .= '</tr>';
        }
        // exit;
        $table .= '<tr>';
        $table_per = $rep_val =$table_all = '';
        $all_ob_mark = $all_sub_mark =$overall_per = $finalPer= 0;
        $result = "Pass";
        
        // Calculate the total marks for each term
            foreach ($term_name as $keys => $terms) {
                $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
                    return $title->term_id == $terms->term_id;
                });
                $tot_ob_mark = $total_term_marks[$terms->term_id];
                $tot_sub_mark = $total_sub_marks[$terms->term_id];
                if ($keys == 0) {
                    $cols = 1;
                    $val = "Overall Percentage";
                } else {
                    $cols = 0;
                    $val = "Overall Grade";
                }
                $all_ob_mark += $total_term_marks[$terms->term_id];
                $all_sub_mark += $total_sub_marks[$terms->term_id];
                // get percentage   
                $finalPer = $this->getPer($tot_ob_mark, $tot_sub_mark);  
                // get overall percentage  
                $overall_per += $finalPer;  
                $main_per = number_format(($overall_per / 200) * 100,2);  
                // get overall grade  
                $grade_arr = $this->getGradeScale($standard_id, '');

                $all_grade = \App\Helpers\getGrade($grade_arr, 100, $main_per);
                $all_per = $main_per . "%";
                if ($keys == 0) {
                    $rep_val = "&lt;&lt;per&gt;&gt;";
                } else {
                    $rep_val = "&lt;&lt;grade&gt;&gt;";
                }
                $table .= '<td colspan="' . (count($term_exam_titles)) + $cols . '" style="text-align:right"><b>Total</b></td><td class="data_center"><b>' . round($tot_ob_mark,0) . '</b></td><td rowspan="2" class="data_center"><b>' . \App\Helpers\getGrade($grade_arr, $total_mark, $finalPer) . '</b></td>';

                $table_per .= '<td colspan="' . (count($term_exam_titles)) + $cols . '" style="text-align:right"><b>Percentage</b></td><td class="data_center"><b>' . $finalPer . '% </b></td>';

                $table_all .= '<td colspan="' . (count($term_exam_titles)) + $cols . '" style="text-align:right"><b>' . $val . '</b></td><td colspan="2" class="data_center"><b>' . $rep_val . '</b></td>';
            }
        // exit;
            $table_all = str_replace(htmlspecialchars("<<per>>"), $all_per, $table_all);
            $table_all = str_replace(htmlspecialchars("<<grade>>"), $all_grade, $table_all);
            $table .= '<tr>' . $table_per . '</tr>
                    <tr>' . $table_all . '</tr>
                    </tr>
                </tbody></table>';

        $result="Pass";
        if($pass_or_fail>0){
            $result="Promoted";
        }

        $res['remark'] = \App\Helpers\getGradeComment($grade_arr, 100, $main_per) ?? '-'; //$finalPer by rajesh 23-03-2024
        $res['result'] = $result;//.'-'.$main_per
        $res['table'] = $table;
        return $res;
    }

    public function get_scholastic_lions11($standard_id, $student_id, $format, $academic_type)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term = "term_id = 150";
        $extra_exam = "rce.term_id = 150";
    
        $term_name = DB::table('academic_year')
            ->whereRaw($extra_term)
            ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])
            ->get()->toArray();
    
        $get_subject = $this->get_subject($sub_institute_id, $syear, $student_id, $standard_id);
        $exam_name = $this->get_exam_name($sub_institute_id, $syear, $standard_id, $extra_exam);
        $exam_title = $this->get_exam_title($sub_institute_id, $syear, $standard_id, $extra_exam);
        $exam_marks = $this->get_exam_marks($sub_institute_id, $student_id, $syear, $standard_id, $extra_exam, 'lions');
    
        $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
            <thead>
                <tr>
                    <th><b>Scholastic Areas:</b></th>
                    <th colspan="13" style="text-align:center;"><b>ACADEMIC YEAR EXAM</b></th>
                </tr>
                <tr>
                    <th class="data_center" rowspan="4"><b>Subject</b></th>
                </tr>
                <tr>
                    <th class="data_center" colspan="2"><b>UNIT TEST</b></th>
                    <th class="data_center" rowspan="2" colspan="2"><b>HALF YEARLY <br> EXAM</b></th>
                    <th class="data_center" rowspan="2" colspan="2"><b>YEARLY <br> PRACTICAL/ASL/PROJECT</b></th>
                    <th class="data_center" rowspan="2" colspan="2"><b>YEARLY <br> EXAM</b></th>
                    <th class="data_center"  rowspan="3"><b>TOTAL</b></th>
                    <th class="data_center"  rowspan="3"><b>GRAND <br> TOTAL</b></th>            
                    <th class="data_center" rowspan="3"><b>AVERAGE</b></th>
                </tr>
                <tr>
                    <th class="data_center"><b>I</b></th>
                    <th class="data_center"><b>II</b></th>
                </tr>
            <tr>';
        $exam_names = $total_points = $unit_heads = [];
        $math_yearly = 0;
        // get exam names 
        foreach ($term_name as $keys => $terms) {
            $total_mark = 0;
            foreach ($exam_name as $key => $title) {
                $exam_head = $title->title;
                if ($terms->term_id == $title->term_id && !in_array($exam_head, $exam_names)) {
                    $exam_names[] =  $exam_head;
                    $total_points[] =  $title->exam_id;
                    if ($exam_head == "UT1" || $exam_head == "UT2") {
                        $table .= '<th class="data_center"><b>OUT OF (' . $title->points . ')</b></th>';
                    }
                }
                if ($exam_head == "Yearly" && $title->subject_id == 1076) {
                    $math_yearly = $title->points;
                }
            }
    
            $table .= '<th><b>MAX. MARKS</b></th>
                <th><b>OBT. MARKS</b></th>

                <th><b>MAX. MARKS</b></th>
                <th><b>OBT. MARKS</b></th>
    
                <th><b>MAX. MARKS</b></th>
                <th><b>OBT. MARKS</b></th>';
        }
    
        $table .= '</tr>
            </thead>
            <tbody>';
        
        $sort_exam = ['UT1', 'UT2', 'Half Yearly', 'Practical/ASL/Project', 'Yearly'];
    
        $tot_ob_mark =  $tot_sub_mark = $get_all_ob_mark = $get_all_tot_mark = $practical = $yearly_mark = $total_avg = $total_avg_max = $pass_or_fail=0;
        $math_arr = [];
        $grade_arr = $this->getGradeScale($standard_id, '');
    
        foreach ($get_subject as $val) {
            $table .= '<tr>
                <td>' . $val->subject_name .'</td>';
            
            $total_marks = $total_max = $total_practical = $avg_total = $avg_max = $cols  =  $prac_yearly= $prac_yearly_tot = 0;
            $ex_points = $na_points =[];
    
            foreach ($sort_exam as $key => $value) {
                $abFlag = $naFlag = $exFlag = 0;
                $cols++;
    
                $marks = DB::table('result_marks as rm')
                    ->join('result_create_exam as rce', 'rce.id', '=', 'rm.exam_id')
                    ->join('sub_std_map as s', function ($join) {
                        $join->on('s.subject_id', '=', 'rce.subject_id')->on('rce.standard_id', '=', 's.standard_id');
                    })
                    ->selectRaw('rm.id as marks_id,rm.points,rce.points as total_points,rm.is_absent,rce.exam_id,rce.id as create_id,rce.title,rce.subject_id,s.display_name,s.elective_subject')
                    ->where('rm.student_id', $student_id)
                    ->where('rce.syear', $syear)
                    ->where('rm.sub_institute_id', $sub_institute_id)
                    ->where('rce.subject_id', $val->subject_id)
                    ->whereRaw('rm.points IS NOT NULL')
                    ->where('rce.term_id', 150)
                    ->orderByRaw('s.display_name,rce.title')
                    ->groupByRaw('rce.title,s.display_name,rce.points')->get()->toArray();
    
                $ob_marks = $tot_points =  [];
    
                if (!empty($marks)) {
                    foreach ($marks as $markskey => $mark) {
                        if ($mark->title == $value && $val->subject_id == $mark->subject_id) {
                            $is_absent = $mark->is_absent;
                            if (isset($is_absent) && $is_absent == "N.A.") {
                                $naFlag = 1;
                                $tot_points[$value][] = $mark->total_points;
                                $na_points[$val->subject_name][]=$mark->total_points;                                
                            } elseif (isset($is_absent) && $is_absent == "EX") {
                                $exFlag = 1;
                                $tot_points[$value][] = $mark->total_points;
                                $ex_points[$val->subject_name][]=$mark->total_points;
                            } elseif (isset($is_absent) && $is_absent == "AB") {
                                $abFlag = 1;
                                $ob_marks[$value][] = $mark->points;
                                $tot_points[$value][] = $mark->total_points;
                                if ($mark->title == "Practical/ASL/Project" || $mark->title == "Yearly") {
                                    $total_practical += $mark->points;
                                }
                                $avg_total += $mark->points;
                                $avg_max += $mark->total_points;
                            } else {
                                if ($mark->title == "Yearly" && $mark->display_name == "MATHEMATICS") {
                                    $yearly_weight = $mark->total_points;
                                    $yearly_mark = $mark->points;
                                } elseif ($mark->display_name == "MATHEMATICS" && $mark->title != "Practical/ASL/Project") {
                                    $math_arr[] = round(10 * ($mark->points) / $mark->total_points, 0);
                                }
                                if ($mark->display_name == "MATHEMATICS" && $mark->title == "Practical/ASL/Project") {
                                    $practical += $mark->points;
                                }
    
                                if ($mark->display_name != "MATHEMATICS") {
                                    $ob_marks[$value][] = $mark->points;
                                    $tot_points[$value][] = $mark->total_points;
                                    if ($mark->title == "Practical/ASL/Project" || $mark->title == "Yearly") {
                                        $total_practical += $mark->points;
                                    }
                                    if($mark->display_name != "APP MATHS"){
                                        $avg_total += $mark->points;
                                        $avg_max += $mark->total_points;
                                    }
                                }
                            }
                        }
                    }
                }
                // echo $val->subject_name.'-'.$value.'-';echo "<pre>";print_r($ex_points);echo "<br>";
                if ($abFlag == 1) {
                    $total_marks += 0;
                    if (($value == "UT1" || $value == "UT2") && $val->subject_name != "MATHEMATICS") {
                        $table .=   '<td class="data_center">AB</td>';
                    } elseif ($val->subject_name != "MATHEMATICS") {
                        $table .= '<td class="data_center">' . array_sum($tot_points[$value]) . '</td><td class="data_center">AB</td>';
                    }
                    $prac_yearly_tot +=array_sum($tot_points[$value]);                            
                    
                } elseif ($naFlag == 1) {
                    $total_marks += 0;
                    if (($value == "UT1" || $value == "UT2") && $val->subject_name != "MATHEMATICS") {
                        $table .=   '<td class="data_center">N.A.</td>';
                    } elseif ($val->subject_name != "MATHEMATICS") {
                        $table .= '<td class="data_center">' . array_sum($tot_points[$value]) . '</td><td class="data_center">N.A.</td>';
                    }
                    if(count($na_points[$val->subject_name])>1){
                        $avg_max += $na_points[$val->subject_name][0];
                    }
                } elseif ($exFlag == 1) {
                    $total_marks += 0;
                    if (($value == "UT1" || $value == "UT2") && $val->subject_name != "MATHEMATICS") {
                        $table .=   '<td class="data_center">EX</td>';
                    } elseif ($val->subject_name != "MATHEMATICS") {
                        $table .= '<td class="data_center">' . array_sum($tot_points[$value]) . '</td><td class="data_center">EX</td>';
                    }
                    if(count($ex_points[$val->subject_name])>1){
                        $avg_max += $ex_points[$val->subject_name][0];
                    }
                } elseif (isset($ob_marks[$value])  && $val->subject_name != "MATHEMATICS") {
                    // app maths
                    if($val->subject_name == "APP MATHS"){
                        if ($value == "UT1" || $value == "UT2") {
                            $table .= '<td class="data_center">-</td>';
                        }else if($value == "Half Yearly"){
                            $table .= '<td class="data_center">-</td><td class="data_center">-</td>';                                                                                    
                        }else{
                            $obt_mark = number_format(array_sum($ob_marks[$value]), 0);
                            $table .= '<td class="data_center">' . array_sum($tot_points[$value]) . '</td>';
                            $table .= '<td ' . $val->subject_id . ' ' . $value . ' class="data_center">' . $obt_mark .'</td>';
                            $total_marks += $obt_mark;
                            $avg_total += $obt_mark;
                            $avg_max += array_sum($tot_points[$value]);                       
                            if($value!="Practical/ASL/Project"){
                                $prac_yearly +=$obt_mark;                            
                                $prac_yearly_tot +=array_sum($tot_points[$value]); 
                            }
                        }
                    }else{
                        if ($value == "UT1" || $value == "UT2") {
                            $obt_mark = number_format(array_sum($ob_marks[$value]), 0); 
                            $table .= '<td ' . $val->subject_id . ' ' . $value . ' class="data_center">' .  $obt_mark . '</td>';
                            $total_marks += $obt_mark;
                        } else{
                            $obt_mark = number_format(array_sum($ob_marks[$value]), 0);
                            $table .= '<td class="data_center">' . array_sum($tot_points[$value]) . '</td>';
                            $table .= '<td ' . $val->subject_id . ' ' . $value . ' class="data_center">' . $obt_mark .'</td>';
                            $total_marks += $obt_mark;
                        }
                        if($value!="Practical/ASL/Project"){
                            $prac_yearly +=$obt_mark;                            
                            $prac_yearly_tot +=array_sum($tot_points[$value]);                            
                        }
                    }
                  
                } else if ($val->subject_name != "MATHEMATICS") {
                    $get_points = DB::table('result_create_exam')
                        ->where('exam_id', $total_points[$key])
                        ->where('standard_id', $standard_id)
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('subject_id', $val->subject_id)
                        ->where('syear',$syear)
                        ->where('term_id', 150)
                        ->first();
    
                    if (($value == "UT1" || $value == "UT2") && $val->subject_name != "MATHEMATICS") {
                        $table .= '<td class="data_center">0</td>';
                    } elseif ($val->subject_name != "MATHEMATICS"  && ($abFlag != 1 || $exFlag != 1 || $naFlag != 1)) {
                        $avg_max += $get_points->points;
                        $table .= '<td ' . $value . ' class="data_center">' . $get_points->points . '</td>';
                        $table .= '<td class="data_center">0</td>';
                                                  
                    }
                }
            }
    
            $math_2 = \App\Helpers\getBestOf($math_arr);
            $math_tot = round((array_sum($math_2) / 2), 0) + $practical;
    
            if ($val->subject_name == "MATHEMATICS") {
                $table .= '<td class="data_center">-</td>
                    <td class="data_center">-</td>
                    <td class="data_center">-</td>
                    <td class="data_center">-</td>
                    <td class="data_center">20</td>
                    <td class="data_center">' . $math_tot . '</td>                
                    <td class="data_center">' . number_format($math_yearly, 0) . '</td>
                    <td class="data_center">' . number_format($yearly_mark, 0) . '</td>';
                $total_practical = $total_marks = $math_tot + $yearly_mark;
                $avg_total = $total_marks;
                $avg_max = $math_yearly + 20;
                if($yearly_mark!=0){
                    $prac_yearly += $yearly_mark;
                    $prac_yearly_tot += $math_yearly;
                }
            }
            // .'-'.$prac_yearly.'/'.$prac_yearly_tot.
            $avg_mark = round(($avg_total * 100 / $avg_max));
            $table .= "<td  class='data_center'>" . $total_practical."</td>";
            $table .= "<td  class='data_center'>" . $total_marks ."</td>";
            $table .= "<td  class='data_center'>" . $avg_mark. "</td>";

            $total_avg += $avg_mark;
            $total_avg_max += $avg_max;

            if($prac_yearly!=0){
                $get_mark = round(($prac_yearly * 100)/$prac_yearly_tot,0);
                if($get_mark < 33){
                    $pass_or_fail++;
                }
            }
        }
       
        if ($avg_mark != 0) {
            $subject_marks = count($get_subject) * 100;
            $per = number_format(100 * $total_avg / $subject_marks, 2);
        } else {
            $per = '-';
        }
        // exit;
        $table .= "</tr>";
        $table .= "<tr><td colspan=" . ($cols + $cols + 1) . " style='text-align:right'><b>GRAND TOTAL</b></td>
                    <td class='data_center'><b>" . $total_avg . "</b></td></tr>
                    <tr><td colspan=" . ($cols + $cols + 1) . " style='text-align:right'><b>PERCENTAGE</b></td>
                    <td class='data_center'><b>" . $per . "%</b></td>";
    
        $table .= '</tr></tbody></table>';
        $main_result="PASS";
        // pass or promoted
        if($pass_or_fail > 0){
            $main_result="Failed";
        }
        $res['table'] = $table;
        $res['remark'] = \App\Helpers\getGradeComment($grade_arr, 100, $per) ?? '-';
        $res['result'] =$main_result;
        
        return $res;
    }

    public function get_scholastic_lions9($standard_id, $student_id, $format, $academic_type){
         // echo "<pre>";print_r($student_id);exit;
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $extra_term = "term_id = 150";
            $extra_exam = "rce.term_id = 150";
            
            // get term_name 
            $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
    
           // get subject
           $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
           // get exam name
           $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
           // get exam title 
           $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
           //get exam marks
           $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id,$syear,$standard_id,$extra_exam,'lions');
          
            $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
            <thead>
                <tr>
                    <th><b> Scholastic Areas:	</b></th>
                    <th colspan="7" class="data_center"><b>Academic Year (100 Marks)</b></th>
            </tr>
            <tr>
                <th><b>Subject</b></th>';
                $total_weightage = $overall_total =  $all_colspan = 0;
                $exam_ids=[];
                foreach ($term_name as $keys => $terms) {
                    $total_mark = 0;
                    foreach ($exam_title as $key => $title) {
                        $exam_head = $title->ExamTitle;
                        $exam_ids[] = $title->exam_id;
                        if ($terms->term_id == $title->term_id) {
                            $table .= '<th class="data_center"><b>' . $exam_head . '<br>(' . $title->weightage . ')</b></th>';
                                $total_mark += $title->weightage;
                        }
                    }
                    $table .= '<th class="data_center"><b>Marks Obtained <br>('.$total_mark.')</b></th>';
                    $overall_total += $total_mark;
                    $table .= '<th class="data_center"><b>Grade</b></th>';
                }
            
            $grade_arr = $this->getGradeScale($standard_id, '');   
           
            $table .= '</tr>
            </thead>
            <tbody>';
            $tot_ob_mark =  $tot_sub_mark =   $get_all_ob_mark =  $get_all_tot_mark = $pass_or_fail = 0;
            // grade array to get grade according to marks
            $grade_arr = $this->getGradeScale($standard_id, '');
            // get all subject name 
            foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr>
            <td>' . $val->subject_name . '</td>';
            // get term wise eam and marks 
            foreach ($term_name as $keys => $terms) {
                $obtained_marks =$to_marks = $to_weight = $title_exam = []; 
                // get marks by exam id wise
                foreach ($exam_title as $key => $title) {
                
                    // echo  "<pre>";print_r($title);
                    $marks = DB::table('result_marks as rm')
                    ->join('result_create_exam as rce','rce.id','=','rm.exam_id')
                    ->join('result_exam_master as rem','rem.Id','=','rce.exam_id')                            
                    ->join('sub_std_map as s',function($join){
                        $join->on('s.subject_id','=','rce.subject_id')->on('rce.standard_id','=','s.standard_id');
                    })
                    ->selectRaw('rm.id as marks_id,rm.points,rce.points as total_points,rm.is_absent,rce.exam_id,rce.id as create_id,rce.title,rce.subject_id,s.display_name,rem.weightage,rem.ExamTitle')
                    ->where('rm.student_id',$student_id)
                    ->where('rce.syear',$syear)
                    ->where('rm.sub_institute_id',$sub_institute_id)
                    ->where('rce.subject_id',$val->subject_id)
                    ->whereRaw('rm.points IS NOT NULL')
                    ->where('rce.term_id',150)
                    ->orderByRaw('s.display_name,rce.title')
                    ->groupByRaw('rce.title,s.display_name,rce.points')->get()->toArray();
                
                    $value=$title->ExamTitle;
                    $ob_marks=$tot_points=$weigthage=[];
                        if(!empty($marks)){
                                foreach($marks as $markskey=>$mark){                       
                                if($mark->ExamTitle ==$title->ExamTitle && $val->subject_id == $mark->subject_id){
                                        if($mark->points!="0.00"){
                                            $ob_marks[$value][] = round(($mark->points * $mark->weightage)/$mark->total_points,0);
                                            $tot_points[$value][] = $mark->weightage; 
                                            $weigthage[$value] = $mark->weightage;
                                        }    
                                    }
                                }
                            }

                            if(isset($ob_marks[$value])){
                                rsort($ob_marks[$value]);
                                $best_two = array_sum(array_slice($ob_marks[$value], 0, 2));
                                $best_two_p = array_sum(array_slice($tot_points[$value], 0, 2));
                                $obtain_marks = round(($best_two/$best_two_p) * $weigthage[$value],0);
                                $both_term_ob_mark+=$obtain_marks;                                        
                                $table .= '<td  class="data_center">'.$obtain_marks.'</td>';
                            }else{
                                $table .= '<td  class="data_center">0</td>';
                            }
                        }
                    }
            $get_all_ob_mark +=$both_term_ob_mark; 
            $get_all_tot_mark +=$overall_total;                
            if($both_term_ob_mark < 33){
                $pass_or_fail++;
            }                                     
            $table.="<td class='data_center'><b>".$both_term_ob_mark."</b></td><td class='data_center'><b>".$this->getGrade($grade_arr, $overall_total, $both_term_ob_mark)."</b></td>";
        }
        $per = $this->getPer($get_all_ob_mark, $get_all_tot_mark) ?? '-';
    $table.="</tr><tr>
        <td style='text-align:right'  colspan=".(count($exam_ids)+1)."><b>Total</b></td>
        <td class='data_center'><b>".$get_all_ob_mark."</b></td>
        <td rowspan='2' class='data_center'><b>".$this->getGrade($grade_arr, $get_all_tot_mark, $get_all_ob_mark)."</b></td>
    </tr>
    <tr>
    <td  style='text-align:right' colspan=".(count($exam_ids)+1)."</td><b>Percentage</b>
    <td class='data_center'><b>".$per."%</b></td>";
    
    // pass or fail 
    $main_result = "Pass";
    if($pass_or_fail > 0){
        $main_result = "Promoted";
    }
    // exit;
    $table .= '</tr></tbody></table>';
    $res['table'] = $table;
    $res['remark'] = \App\Helpers\getGradeComment($grade_arr, 100, $per) ?? '-';
    $res['result'] = $main_result;
    
    return $res;
        
    }
    
    public function get_scholastic_hills_11($standard_id, $student_id, $format, $academic_type)
    {
       $syear = session()->get('syear');
       $sub_institute_id = session()->get('sub_institute_id');
       $extra_term  = $extra_exam = "1=1";
       $att_term="atd.term_id = 2";
       if ($format != "yearly"){
           $extra_term = "term_id = " . $format;
           $extra_exam = "rce.term_id = " . $format;
           $att_term = "atd.term_id = " . $format;
       }

       // get term_name 
       $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
       // get subject
       $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
       // get exam master name
       $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
       // get exam title 
       $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
       //get exam marks
       $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id,'best_of_2');
        
       // scholastic table started 
      $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
       <thead>
           <tr>
               <th style="background:black;color:white"><b>Scholastic Areas:</b></th>';
               $col = 1;
               $total_term_marks = $total_sub_marks = $titles_weight = [];
               $total_weightage = $overall_total  = $all_colspan = 0;
               $colspan = 2;

               // get both term name and total marks per subject 
               foreach ($term_name as $keys => $terms) {
                   $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, &$total_weightage, &$titles_weight) {
                       $total_weightage += $title->weightage;
                       $titles_weight[$title->ExamTitle] = $title->weightage;
                       return $title->term_id == $terms->term_id;
                   });
                   
                   // Initialize the total marks for each term to zero
                   $total_term_marks[$terms->term_id] = 0;
                   $total_sub_marks[$terms->term_id] = 0;
                   $all_colspan += count($term_exam_titles);
               }
               $periodic_test = isset($titles_weight['Periodic Test']) ? $titles_weight['Periodic Test'] : 0 ;
               $theory = isset($titles_weight['Theory']) ? $titles_weight['Theory'] : 0 ;
               $practical = isset($titles_weight['Practical']) ? $titles_weight['Practical'] : 0 ;
               $overall_total = $periodic_test + $theory + $practical;

       $table .= '<th colspan="' . (count($term_exam_titles) + $colspan + 2) . '" style="text-align:center;background:black;color:white">&nbsp;</th>';//<b>ACADEMIC YEAR ('.($periodic_test + $practical + $theory).')</b>
       $table.="</tr>
                   <tr>
                   <th rowspan=2>Subject</th>
                   <th rowspan=2  style='text-align:center'><b>Periodic Test <br> (". $periodic_test .")</b></th>
                   <th colspan='2'  style='text-align:center'><b>Yearly Exam</b></th>
                   <th rowspan=2 style='text-align:center'><b>Marks Obtained <br> (".($periodic_test + $practical + $theory).")</b></th>
                   <th rowspan=2 style='text-align:center'><b>Grade</b></th>    
               </tr>
               <tr>
                   <th style='text-align:center'><b>Theory <br> (". $theory .")</b></th>            
                   <th style='text-align:center'><b>Practical <br> (". $practical .")</b></th>
               </tr>";
               $weigthage = '';
               $exam_heads = [];
                // get exam names heading like PA,SA,NB
                foreach ($term_name as $keys => $terms) {
                    $total_mark = 0;
                    foreach ($exam_name as $key => $title) {
                        $weigthage = $title->weightage;
                        $exam_head = $title->title;
                        $pt_exams=["P.T.-1","P.T.-2","P.T.-3"];    
                            if(in_array($exam_head,$pt_exams) ){
                                $exam_head = "Periodic Test";
                            }           
                            if ($terms->term_id == $title->term_id && !in_array($exam_head,$exam_heads)) {  
                            $total_mark += $weigthage;
                            $exam_heads[]=$exam_head;
                        }
                    }
                    $mark_tot = '(' . $total_mark . ')';
                }
    //   echo "<pre>";print_r($overall_total);exit;
                $table .= '</tr></thead><tbody>';
                $tot_ob_mark =  $tot_sub_mark = $get_all_ob_mark =  $get_all_tot_mark = 0;
                // get grade array for grade 
                $grade_arr = $this->getGradeScale($standard_id, '');
                $pass_fail=[];
                // get all subject name 
                foreach ($get_subject as $val) {
                    $both_term_ob_mark = 0;
                    $table .= '<tr><td>' . $val->subject_name .  '</td>';
                    // get term wise eam and marks 
                    foreach ($term_name as $keys => $terms) {
                        $obtained_marks = $to_marks = $to_weight = $title_exam = []; 
                        // get marks by exam id wise
                        foreach ($exam_name as $key => $title) {
                            if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                                $foundMarks = false;
                                $ob_mark = 0;
                                $title_exam[$title->exam_id][] = $title->ExamTitle;
                                // all exam marks 
                                foreach ($exam_marks as $index => $marks) {
                                    if ($title->id == $marks->exam_id) {
                                        $to_marks[$title->exam_id][] = $title->weightage;
                                        // $to_weight[$title->exam_id] = $title->weightage;
                                        $to_weight[$title->exam_id] = $title->weightage;                                        
                                        // for AB,NA,EX
                                        if ($marks->is_absent != "") {
                                            $ab_ex_na = $marks->is_absent;
                                            if ($marks->is_absent == '') {
                                                $ab_ex_na = 0;
                                            }
                                            $obtained_marks[$title->exam_id][] = $ab_ex_na;
                                        } else {
                                            $ob_mark = $marks->points;
                                            // $ob_mark = ($ob_mark != 0) ? (($ob_mark / $title->points) * $title->weightage) : 0;
                                            // store marks in array to get best of 2 

                                            // convert marks according to weightage
                                            $ob_mark = ($ob_mark != 0) ? (($ob_mark / $title->points) * $title->weightage) : $ob_mark;

                                            $obtained_marks[$title->exam_id][] = $ob_mark;

                                            $foundMarks = true;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                        $ob_main_mark = $ab_ex_na = $total_marks = 0;
                        // echo "<pre>";print_r($obtained_marks);
                        // for best of 2 exam wise 
                        if (!empty($title_exam)) {
                            foreach ($title_exam as $exam_id => $marksArray) {                                
                                $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                                $t_m = array_sum($to_marks[$exam_id] ?? []);
                                $obtained_mark_arr = $obtained_marks[$exam_id] ?? [];
                                
                                $obtained_mark_sum = array_sum($obtained_mark_arr);
                                
                                // convert marks if best of 2
                                $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                                
                                $pt_per = ($convert_mark !== '0.00' && $w_m != 0) ? round(($convert_mark / $w_m) * 100, 0) : 0;
                        
                                $underline = ($pt_per < 33 && $academic_type == "upper") ? 'style="text-decoration: underline red 2px;"' : '';
                                if(count($obtained_mark_arr)>1){
                                    $total_marks = $w_m;                                    
                                    $table .= '<td class="data_center" ' . $underline . ' ' . $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' . number_format($convert_mark, 2) . '</td>';
                                    $ob_main_mark += ($t_m !== 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                                }else{
                                    if($obtained_mark_arr[0]!='N.A.' || $obtained_mark_arr[0]!='EX'){
                                        $total_marks = $w_m;
                                    }
                                    $table .= '<td class="data_center" ' . $underline . ' ' . $exam_id . '-'.$val->subject_id.'-'.$standard_id.'>' .number_format($obtained_mark_arr[0],2) . '</td>';
                                    $ob_main_mark += ($obtained_mark_arr[0] !== 0) ? $obtained_mark_arr[0] : 0;
                                }
                        // echo $exam_id;echo "<pre>";print_r($obtained_mark_sum);
                                
                            }
                        } else {
                            // If marks not found
                            foreach ($title_exam as $exam_id => $marksArray) {
                                $table .= '<td class="data_center no_mark ' . $exam_id . '">0.00</td>';
                            }
                        }           

                        $obtained_mark_formatted = number_format($ob_main_mark, 2);
                        $both_term_ob_mark += $obtained_mark_formatted;
                        // Update the total marks for the current term
                        $total_term_marks[$terms->term_id] += $ob_main_mark;
                        $total_sub_marks[$terms->term_id] += $total_mark;
                        // $grade_arr = $this->getGradeScale($standard_id, '');
                    } 
                    // get percentage 
                    $total_obt_mark =round($both_term_ob_mark,0);
                    if($total_obt_mark==0){
                        $grade_std=$this->getGrade($grade_arr,0, $total_obt_mark);
                    }else{
                        $grade_std=$this->getGrade($grade_arr, $overall_total, $total_obt_mark);                        
                    }
                    if($grade_std=="E"){
                        $pass_fail[]="Failed";
                    }

                    $underline = ($grade_std =="E") ? 'style="text-decoration: underline red 2px;"' : ''; // added by rajesh 22_02_2024

                    $table .= '<td class="data_center tot_of_both" ' . $underline . '>' . round($both_term_ob_mark, 0) . '</td><td class="data_center grade_of_both">' . $grade_std . '</td>';
                    $table .= '</tr>';
                }
                $table .= '</tbody></table>';
       
            $res['scholastic'] = $table;
            $ret_data = DB::table('result_student_attendance_master as atd')
            ->join('result_working_day_master as wrkd', function ($join) use ($standard_id, $sub_institute_id) {
                $join->on('wrkd.standard', '=', 'atd.standard')
                    ->on('wrkd.sub_institute_id', '=', 'atd.sub_institute_id');
            })
            ->selectRaw("atd.student_id,wrkd.total_working_day,atd.attendance,GROUP_CONCAT(Distinct atd.teacher_remark SEPARATOR '|') as teacher_remark")
            ->where('atd.standard', $standard_id)
            ->where('atd.sub_institute_id', $sub_institute_id)
            ->where('atd.student_id', $student_id)
            ->where('atd.syear', $syear)
            ->whereRaw($att_term)
            ->groupBy('student_id')
            ->first();
            
        $res['teacher_remark'] = '';
        if (!empty($ret_data)) {
            $student_Remark = str_replace('|','',$ret_data->teacher_remark);
            $res['teacher_remark'] = $student_Remark;
        }


        $get_remark = DB::table('result_remarks')
        ->selectRaw("student_id,GROUP_CONCAT(Distinct term_id SEPARATOR '|') as term_id,GROUP_CONCAT(Distinct result_remarks SEPARATOR '|') as remark")
        ->where('sub_institute_id', $sub_institute_id)
        ->where('student_id', $student_id)
        ->where('syear', $syear)
        ->groupBy('student_id')
        ->first();
        
        if(isset($get_remark->remark)){
            $pass_or_fail = str_replace('|','',$get_remark->remark);
        }else if(in_array('Failed',$pass_fail)){
            $pass_or_fail='Failed';
        }else{
            $get_next_std = DB::table('standard')->where('id',$standard_id)->first();
            if(isset($get_next_std->next_standard_id)){
            $next_std_name = DB::table('standard')->where('id',$get_next_std->next_standard_id)->first();
                $next_std =$next_std_name->short_name;
            }else{
                $next_std ='';
            }
            $pass_or_fail ='Passed & Promoted to class : '.$next_std;
        }

        $res['pass_or_fail'] = ($format != "yearly") ? '' : $pass_or_fail;
       return $res;
    }

public function get_co_scholastic_hills($standard_id, $student_id, $format, $academic_type)
{
    $syear = session()->get('syear');
    $sub_institute_id = session()->get('sub_institute_id');
    
    $extra_term = ($format == "yearly") ? 
        (($academic_type != "primary") ? "term_id = 2" : "1=1") : 
        "term_id = $format";
    $extra_term_co = ($format == "yearly" && $academic_type != "primary") ? "comark.term_id = 2" : "1=1";
    $extra_exam = ($format == "yearly") ? "1=1" : "comark.term_id = $format";

    $both_term = DB::table('academic_year')
        ->whereRaw($extra_term)
        ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])
        ->get()
        ->toArray();

    $ret_mark_grade = DB::select(DB::raw("SELECT * FROM result_co_scholastic WHERE sub_institute_id = $sub_institute_id AND $extra_term"));

    if (count($ret_mark_grade) > 0) {
        $ret_data = DB::table('result_co_scholastic_marks_entries as comark')
            ->selectRaw(
                'comark.student_id, comark.co_scholastic_id, comark.term_id, cop.id as parent_id, cop.title as parent_title, co.title as child_title, co.id as coid,
                IF(comark.grade = 0, comark.points, cograde.title) as obtain_grade, co.max_mark'
            )
            ->leftJoin('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
            ->join('result_co_scholastic as co', 'co.id', '=', 'comark.co_scholastic_id')
            ->join('result_co_scholastic_parent as cop', 'cop.id', '=', 'co.parent_id')
            ->where('comark.syear', $syear)
            ->whereRaw($extra_exam)
            ->whereRaw($extra_term_co)
            ->where([
                'comark.standard_id' => $standard_id,
                'co.standard_id' => $standard_id,
                'comark.student_id' => $student_id,
                'comark.sub_institute_id' => $sub_institute_id
            ])
            ->orderBy('comark.student_id')
            ->orderBy('cop.sort_order')
            ->orderBy('co.sort_order')
            ->orderBy('comark.term_id')
            ->get();
        
        $skill_data = $criteria_data = $decipline_data = $co_data = [];
        $get_grade = DB::table('result_co_scholatic_range')
            ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])
            ->get();
        
        foreach ($ret_data as $value) {
            $per = $value->obtain_grade;
            switch ($value->parent_title) {
                case "SKILL OBSERVATION":
                    $value->obtain_grade = ($value->obtain_grade != '0.00') ? $value->obtain_grade : '-';
                    $skill_data[] = $value;
                    break;
                case "CRITERIA":
                    $value->obtain_grade = ($value->obtain_grade != '0.00') ? $value->obtain_grade : '-';
                    $criteria_data[] = $value;
                    break;
                case "DISCIPLINE":
                    $grade = (!empty($get_grade) && $per != 0 && $per != '') ? $this->getGrade($get_grade, $value->max_mark, $per, "co_scholastic") : '-';
                    $value->obtain_grade = $grade;
                    $decipline_data[] = $value;
                    break;
                default:
                    $grade = (!empty($get_grade) && $per != 0 && $per != '') ? $this->getGrade($get_grade, $value->max_mark, $per, "co_scholastic") : '-';
                    $value->obtain_grade = $grade;
                    $co_data[] = $value;
            }
        }
        
        $term_name = ($academic_type == "upper") ? "Grade" : ($both_term[0]->title ?? 'Grade');
        $flex = 'display:flex;flex-wrap:wrap';

        $co_scholastic = $this->buildCoScholasticTable($both_term, $co_data, $term_name, $flex, $academic_type);
        $other_table = $this->buildOtherTables($both_term, $criteria_data, $skill_data, $decipline_data, $term_name, $academic_type);

        return [
            'co_scholastic' => (count($ret_data) > 0) ? $co_scholastic : '',
            'other_tags' => $other_table
        ];
    }

    return [
        'co_scholastic' => '',
        'other_tags' => ''
    ];
}

// private function buildCoScholasticTable($both_term, $co_data, $term_name, $flex, $academic_type)
// {
//     $co_scholastic = '<div style=' . $flex . ' class="co_scho_hills">
//     <div style="width:50%;">
//         <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
//             <thead>
//                 <tr>
//                     <th><b>CO SCHOLASTIC</b></th>';

//     foreach ($both_term as $terms) {
//         $co_scholastic .= '<th class="data_center"><b>' . ($academic_type == "primary" ? $terms->title : $term_name) . '</b></th>';
//     }

//     $co_scholastic .= '</tr></thead><tbody>';
//     $groupedData = [];

//     foreach ($co_data as $value) {
//         $groupedData[$value->child_title][$value->term_id] = $value->obtain_grade;
//     }

//     foreach ($groupedData as $childTitle => $termGrades) {
//         $co_scholastic .= '<tr><td>' . $childTitle . '</td>';
//         foreach ($both_term as $terms) {
//             $grade = $termGrades[$terms->term_id] ?? '-';
//             $co_scholastic .= '<td class="data_center">' . $grade . '</td>';
//         }
//         $co_scholastic .= '</tr>';
//     }

//     $co_scholastic .= '</tbody></table></div></div>';
//     return $co_scholastic;
// }
private function buildCoScholasticTable($both_term, $co_data, $term_name, $flex, $academic_type)
{
    $co_scholastic = '<div style=' . $flex . ' class="co_scho_hills">';

    // Group data
    $groupedData = [];
    foreach ($co_data as $value) {
        $groupedData[$value->child_title][$value->term_id] = $value->obtain_grade;
    }
    $break = 8;
    if($academic_type=="primary"){
        $break = 6;
    }
    // Split grouped data into two parts
    $groupedData1 = array_slice($groupedData, 0, $break, true);
    $groupedData2 = array_slice($groupedData, $break, null, true);

    // Build first table
    if(!empty($groupedData1)){
        $co_scholastic .= '<div style="width:50%;">
            <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                <thead>
                    <tr>
                        <th><b>CO SCHOLASTIC</b></th>';

        foreach ($both_term as $terms) {
            $co_scholastic .= '<th class="data_center"><b>' . ($academic_type == "primary" ? $terms->title : $term_name) . '</b></th>';
        }

        $co_scholastic .= '</tr></thead><tbody>';
        foreach ($groupedData1 as $childTitle => $termGrades) {
            $co_scholastic .= '<tr><td>' . $childTitle . '</td>';
            foreach ($both_term as $terms) {
                $grade = $termGrades[$terms->term_id] ?? '-';
                $co_scholastic .= '<td class="data_center">' . $grade . '</td>';
            }
            $co_scholastic .= '</tr>';
        }
        $co_scholastic .= '</tbody></table></div>';
    }
    // Build second table
    if(!empty($groupedData2)){
        $co_scholastic .= '<div style="width:50%;">
        <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
            <thead>
                <tr>
                    <th><b>CO SCHOLASTIC</b></th>';

        foreach ($both_term as $terms) {
            $co_scholastic .= '<th class="data_center"><b>' . ($academic_type == "primary" ? $terms->title : $term_name) . '</b></th>';
        }

        $co_scholastic .= '</tr></thead><tbody>';
        foreach ($groupedData2 as $childTitle => $termGrades) {
            $co_scholastic .= '<tr><td>' . $childTitle . '</td>';
            foreach ($both_term as $terms) {
                $grade = $termGrades[$terms->term_id] ?? '-';
                $co_scholastic .= '<td class="data_center">' . $grade . '</td>';
            }
            $co_scholastic .= '</tr>';
        }
        $co_scholastic .= '</tbody></table></div>';
    }
    $co_scholastic .= '</div>';
    
    return $co_scholastic;
}

private function buildOtherTables($both_term, $criteria_data, $skill_data, $decipline_data, $term_name, $academic_type)
{
    $other_table = '';
    $groupedCriteria = [];
    $groupedSkills = [];

    foreach ($criteria_data as $value) {
        $groupedCriteria[$value->child_title][$value->term_id] = $value->obtain_grade;
    }

    foreach ($skill_data as $value) {
        $groupedSkills[$value->child_title][$value->term_id] = $value->obtain_grade;
    }

    if ($academic_type == "primary" && !empty($criteria_data)) {
        $other_table .= "<div style='display:flex;felx-wrap:wrap;width:100%'>
           ".$this->buildSubTable('CRITERIA', $groupedCriteria, $both_term, $academic_type)."
            ".$this->buildSubTable('SKILL OBSERVATION', $groupedSkills, $both_term, $academic_type)."
        </div>";
    } elseif (!empty($decipline_data)) {
        $other_table .= $this->buildDisciplineTable($decipline_data);
    }

    return $other_table;
}

private function buildSubTable($title, $groupedData, $both_term, $academic_type)
{
    $sub_table = '<div style="width:50%;">
        <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
            <thead>
                <tr>
                    <th><b>' . $title . '</b></th>';

    foreach ($both_term as $terms) {
        $sub_table .= '<th class="data_center"><b>' . ($academic_type == "primary" ? $terms->title : 'Grade') . '</b></th>';
    }

    $sub_table .= '</tr></thead><tbody>';

    foreach ($groupedData as $childTitle => $termGrades) {
        $sub_table .= '<tr><td>' . $childTitle . '</td>';
        foreach ($both_term as $terms) {
            $grade = $termGrades[$terms->term_id] ?? '-';
            $sub_table .= '<td class="data_center">' . $grade . '</td>';
        }
        $sub_table .= '</tr>';
    }

    $sub_table .= '</tbody></table></div>';
    return $sub_table;
}

private function buildDisciplineTable($decipline_data)
{
    $discipline_table = '<div style="width:50%;">
        <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
            <thead>
                <tr>
                    <th><b>DISCIPLINE</b></th>
                    <th class="data_center"><b>Grade</b></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><b>Discipline</b></td>';

    foreach ($decipline_data as $value) {
        $discipline_table .= '<td class="data_center">' . $value->obtain_grade . '</td>';
    }

    $discipline_table .= '</tr></tbody></table></div>';
    return $discipline_table;
}


    //co_schalstic area for mmis
    public function get_co_scholastic_mmis($standard_id, $student_id, $format, $grade_type)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        if ($format == "yearly") {
            $extra_os = $extra_term = $extra_exam = "1=1";
        } else {
            $extra_os = "rce.term_id = " . $format;
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        // get grade if mark 
        $sql_mark_grade = "select * from result_co_scholastic where sub_institute_id = " . $sub_institute_id . " and " . $extra_term . " ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));

        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
                $ret_data = DB::table('result_co_scholastic_marks_entries as comark')
                    ->selectRaw(
                        'comark.student_id,comark.co_scholastic_id,co.parent_id,comark.term_id,cop.title as parent_title,co.title as child_title,
                  if(comark.grade=0,comark.points,cograde.title) obtain_grade,co.max_mark'
                    )
                    ->leftjoin('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
                    ->join('result_co_scholastic as co', 'co.id', '=', 'comark.co_scholastic_id')
                    ->join('result_co_scholastic_parent as cop', 'cop.id', '=', 'co.parent_id')
                    ->where('comark.syear', $syear)
                    ->whereRaw($extra_exam)
                    ->where('comark.standard_id', $standard_id)
                    ->where('co.standard_id', $standard_id)
                    ->where('co.parent_id','!=', '21')
                    ->where('comark.student_id', $student_id)
                    ->where('comark.sub_institute_id', $sub_institute_id)
                    ->orderBy('comark.student_id')
                    ->orderBy('cop.sort_order')
                    ->orderBy('co.sort_order')
                    ->orderBy('comark.term_id')
                    ->get();

            // for disiplins 
            $grade_scale=7;
                if($standard_id<=106){
                    $grade_scale=8;
                     $ret_data_disipline = DB::table('result_co_scholastic_marks_entries as comark')
                    ->selectRaw(
                        'comark.student_id,comark.co_scholastic_id,co.parent_id,comark.term_id,cop.title as parent_title,co.title as child_title,
                  if(comark.grade=0,comark.points,cograde.title) obtain_grade,co.max_mark'
                    )
                    ->leftjoin('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
                    ->join('result_co_scholastic as co', 'co.id', '=', 'comark.co_scholastic_id')
                    ->join('result_co_scholastic_parent as cop', 'cop.id', '=', 'co.parent_id')
                    ->where('comark.syear', $syear)
                    ->whereRaw($extra_exam)
                    ->where('comark.standard_id', $standard_id)
                    ->where('co.standard_id', $standard_id)
                    ->where('co.parent_id','=', '21')
                    ->where('comark.student_id', $student_id)
                    ->where('comark.sub_institute_id', $sub_institute_id)
                    ->orderBy('comark.student_id')
                    ->orderBy('cop.sort_order')
                    ->orderBy('co.sort_order')
                    ->orderBy('comark.term_id')
                    ->get();
                }
            }
        }
        $get_grade = DB::table('result_co_scholatic_range')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get();
        $co_data = [];
        $dis_data = [];
        $counter = 1;
        $width='';
        $width='';

        if (isset($ret_data)) {
            foreach ($ret_data as $item) {
                $obtainGrade = $item->obtain_grade;
            // Check if the obtain_grade contains digits
                if (preg_match('/\d/', $obtainGrade) && is_numeric($obtainGrade)) {
                    $item->obtain_grade = $obtainGrade;
                    $item->obtain_grade = $this->getGrade($get_grade, $item->max_mark, $obtainGrade, "co_scholastic");

                } else {
                    $item->obtain_grade = $obtainGrade;
                }
                $co_data[] = $item;
            }
        }
        // dicipline 
         if (isset($ret_data_disipline)) {
            foreach ($ret_data_disipline as $item) {
                $dis_data[] = $item;
            }
        }
        $co_scholastic = '<div style="display:block !important"><div style="width:100%">
            <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                <thead>
                    <tr>
                        <th style="width:80%"><b>Co-Scholastic Areas:</b></th>';// [on a 3-point (A-C) Grading Scale]
                        if(count($term_name) > 1){
                            foreach ($term_name as $keys => $terms) {
                                $co_scholastic .= '<th class="data_center '.$terms->term_id.'"><b>' . $terms->title . '</b></th>';
                                $term_ids[] = $terms->term_id;
                            }
                        }else{
                            $co_scholastic .= '<th class="data_center"><b>Grade</b></th>';
                        }
                    '</tr>  
                </thead>
                <tbody>';
        $counter = 0;
        
    if (!empty($co_data)) {
            $groupedData = [];
            foreach ($co_data as $key => $value) {
                $groupedData[$value->child_title][$value->term_id] = $value->obtain_grade;
            }

            foreach ($groupedData as $childTitle => $termGrades) {
                $co_scholastic .= '<tr>';
                $co_scholastic .= '<td class="'.$value->co_scholastic_id.'">' . $childTitle . '</td>';

                foreach ($term_name as $keys => $terms) {
                    $grade = $termGrades[$terms->term_id] ?? '-';
                    $co_scholastic .= '<td class="data_center co_term-' . $terms->term_id . ' mterm-' . $terms->term_id . ' ">' . $grade . '</td>';
                }

                $co_scholastic .= '</tr>';
            }
        }

        $co_scholastic .= '</tbody></table></div>';
        $disciplineTable='';
        // for disipline 
        if($standard_id<=106 && isset($dis_data)){
        $disciplineTable .= '<div style="width:100%">
            <table class="aca-year" style="width:100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                <thead>
                    <tr>
                        <th style="width:80%"><b>Discipline:</b></th>';// [on a 3-point (A-C) Grading Scale]
                        if(count($term_name) > 1){
                            foreach ($term_name as $keys => $terms) {
                                $disciplineTable .= '<th class="data_center '.$terms->term_id.'"><b>' . $terms->title . '</b></th>';
                                $term_ids[] = $terms->term_id;
                            }
                        }else{
                            $disciplineTable .= '<th class="data_center"><b>Grade</b></th>';
                        }
                    '</tr>  
                </thead>
                <tbody>';
        $counter = 0;
        // for dicipline 
    if (!empty($dis_data)) {
            $groupedData = [];
            foreach ($dis_data as $key => $value) {
                $groupedData[$value->child_title][$value->term_id] = $value->obtain_grade;
            }

            foreach ($groupedData as $childTitle => $termGrades) {
                $disciplineTable .= '<tr>';
                $disciplineTable .= '<td class="'.$value->co_scholastic_id.'">Discipline</td>';//: Term-1 [on a 3-point (A-C) Grading Scale]

                foreach ($term_name as $keys => $terms) {
                    $grade = $termGrades[$terms->term_id] ?? '-';
                    $disciplineTable .= '<td class="data_center co_term-' . $terms->term_id . ' mterm-' . $terms->term_id . ' ">' . $grade . '</td>';
                }

                $disciplineTable .= '</tr>';
            }
        }

        $disciplineTable .= '</tbody></table></div>';
        }
         $co_scholastic .= '</div>';

        if($standard_id<108 && isset($dis_data)){
        $get_grade = DB::table('grade_master_data')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear,'grade_id'=>$grade_scale])->get();

        // echo "<pre>";print_r($co_scholastic);exit;
     $check_optional_subject_with_student = DB::table('student_optional_subject as sos')
    ->select('ssm.display_name', 'sos.subject_id', 'sos.student_id', 'ssm.standard_id', 'rce.id as create_id', 'rce.title', 'rce.term_id', 'rce.standard_id', 'rem.weightage','rem.ExamTitle', 'rce.subject_id', 'rce.points as r_point', 'rce.con_point','rm.is_absent', 'rem.Id as ExamId', 'rce.exam_id', DB::raw('IFNULL(rm.points, 0) as points'))
    ->join('sub_std_map as ssm', 'sos.subject_id', '=', 'ssm.subject_id')
    ->leftJoin('result_create_exam as rce', function ($join) use($syear){
        $join->on('rce.subject_id', '=', 'sos.subject_id')
            ->on('rce.standard_id', '=', 'ssm.standard_id')
            ->where('rce.syear', '=', $syear);
    })
    ->leftJoin('result_exam_master as rem', 'rem.Id', '=', 'rce.exam_id')
    ->leftJoin('result_marks as rm', function ($join) use($student_id) {
        $join->on('rm.exam_id', '=', 'rce.id')
            ->on('rm.student_id', '=', 'sos.student_id');
    })
    ->where('sos.student_id', '=',  $student_id)
    ->where('sos.sub_institute_id', '=', $sub_institute_id)
    // ->where('ssm.standard_id', '=', $standard_id)
    ->where('sos.syear', '=', $syear)
    ->where('ssm.elective_subject', '=', 'YES')
    ->where('ssm.allow_grades', '=', 'YES')
    ->whereRaw('(ssm.optional_type != 1 OR ssm.optional_type IS NULL) AND ssm.sub_institute_id='.$sub_institute_id.' and ssm.standard_id='.$standard_id)
    ->groupBy('rem.Id', 'sos.subject_id', 'ssm.display_name')
    ->orderBy('ssm.sort_order', 'ASC')
    ->get();

            // echo "<pre>";print_r($check_optional_subject_with_student);exit;
        $scho_table = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
        <tr>
        <th colspan="3" width="15%" style="text-align: left;">
            <b>Part 1-B-Scholastic Areas:</b></th>
    </tr><tr>  <th width="50%" style="text-center: left;"><b>Optional
    Subject</b></th>';

            $grade_arr_mmis = $this->getGradeScale($standard_id, '');

        $col = 1;
        $total_term_marks = [];
        $total_sub_marks = [];
        $term_ids = [];
        foreach ($term_name as $keys => $terms) {
            $scho_table .= '<th style="text-align:center"><b>' . $terms->title . '</b></th>';
            $term_ids[] = $terms->term_id;
        }
        $scho_table .= '</tr>';

        $scho_table .= '</tr></thead><tbody>';
        if (!empty($check_optional_subject_with_student)) {

            foreach ($check_optional_subject_with_student as $record) {
                $subjectName = $record->display_name;
                $termId = $record->term_id;
                $points = $record->points;
                $weigthage = $record->weightage;
                $r_point = $record->r_point;
                $is_absent = $record->is_absent;

                if (!isset($subjectRows[$subjectName])) {
                    $subjectRows[$subjectName] = [];
                }
                if (in_array($termId, $term_ids)) {
                    if($is_absent!=''){
                        $subjectRows[$subjectName][$termId] = [$is_absent, $weigthage,$r_point];
                    }else{
                        $subjectRows[$subjectName][$termId] = [$points, $weigthage,$r_point];
                    }
                }
            }
                    // echo "<pre>";print_r($get_grade);
            if(isset($subjectRows)){
                    // echo "<pre>";print_r($subjectRows);exit;

            foreach ($subjectRows as $subjectName => $termPoints) {
                    // echo "<pre>";print_r($termPoints);

                $scho_table .= '<tr>';
                $scho_table .= '<td>' . $subjectName . '</td>';
                foreach ($term_ids as $term) {
                    $obt_points = isset($termPoints[$term][0]) ? $termPoints[$term][0] : 0;
                    if(in_array($obt_points,["N.A.","EX","AB"])){
                        $obt_grade = $obt_points;
                    }else{
                        $tot_mark = isset($termPoints[$term][2]) ? $termPoints[$term][2] : 0;
                        // echo "<pre>";print_r($obt_grade);

                        $max_weightage = isset($termPoints[$term][1]) ? $termPoints[$term][1] : 0; 
                        $obt_grade = $this->getGrade($grade_arr_mmis, $tot_mark,$obt_points);
                    }
                    $scho_table .= '<td class="data_center">' . $obt_grade . '</td>';
                }
                $scho_table .= '</tr>';
            }
        }
        }
        $scho_table .= '</tbody></table>';
    }

        $res['co_scholastic'] = $co_scholastic ?? '';
        $res['discipline'] = $disciplineTable ?? '';
        $res['optional'] = $scho_table ?? '';
        return $res;
    }
    // exam name common for all
    public function get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam,$best_of_2=''){
        $exam_name = DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->where('rce.report_card_status','Y')->whereRaw($extra_exam)->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])
        ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id');
        // get exam master name
        $exam_name->orderByRaw('rem.SortOrder,rce.sort_order');
        
        $exam_name = $exam_name->get()->toArray();
        
        return $exam_name;
    }

    public function get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam,$termwise=''){
        $exam_title = DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->whereRaw($extra_exam)->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])->where('rce.report_card_status','Y')
        ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id');
        // get created exam name 
        $exam_title->orderByRaw('rem.SortOrder,rce.sort_order');
        if($termwise==''){
            $exam_title->groupByRaw('exam_id,term_id');
        }
        $exam_title = $exam_title->get()->toArray();
        return $exam_title;
    }

    public function get_exam_marks($sub_institute_id,$student_id,$best_of_2=''){
        $exam_marks = DB::table('result_marks as rce')->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.student_id' => $student_id])->get()->toArray();
        // get all mark 
        return $exam_marks;
    }
    // subject common for all 
    public function get_subject($sub_institute_id,$syear,$student_id,$standard_id){
       
       $get_subject = DB::table("sub_std_map as ssm")->join('subject as sub', 'ssm.subject_id', '=', 'sub.id')
       ->selectRaw('ssm.id as map_id,sub.id as subject_id,ssm.display_name as subject_name,ssm.elective_subject,ssm.allow_grades,ssm.optional_type')
       ->where(['ssm.sub_institute_id' => $sub_institute_id, 'ssm.standard_id' => $standard_id, 'allow_grades' => "Yes"])->orderBy('ssm.sort_order')->get()->toArray();
        
        $getHillsOptional = [254];
        $getMMISOptional = [47];
        // only for hills
            if(in_array($sub_institute_id,$getHillsOptional)){
                $get_subject = array_filter($get_subject, function ($value) use ($student_id, $syear,$sub_institute_id) {
                    if ($value->elective_subject == 'Yes' && $value->allow_grades == 'Yes') {
                        $check_optional_subject_with_student = DB::table('student_optional_subject as sos')
                            ->join('result_marks as rm', 'sos.student_id', '=', 'rm.student_id')
                            ->join('result_create_exam as rc', function ($join) {
                                $join->on('rc.subject_id', '=', 'sos.subject_id')
                                    ->on('rc.id', '=', 'rm.exam_id');
                            })
                            ->where('sos.student_id', $student_id)
                            ->where('sos.subject_id', $value->subject_id)
                            ->where('sos.syear', $syear)
                            ->count();
                            return $check_optional_subject_with_student > 0;
                    }

                    return true;
                });
            }
            // only for MMIS
            else if(in_array($sub_institute_id,$getMMISOptional)){
                //Filter subjects based on elective and optional conditions
                $get_subject = array_filter($get_subject, function ($subject) use ($student_id, $syear, $sub_institute_id) {
                    $isElectiveSubject = $subject->elective_subject === 'Yes' && $subject->allow_grades === 'Yes' && $subject->optional_type != 1;
                    $check_optional_subject_with_student = DB::table('student_optional_subject as sos')
                        ->join('result_marks as rm', 'sos.student_id', '=', 'rm.student_id')
                        ->join('result_create_exam as rc', function ($join) {
                            $join->on('rc.subject_id', '=', 'sos.subject_id')
                                ->on('rc.id', '=', 'rm.exam_id');
                        })
                        ->where('sos.student_id', $student_id)
                        ->where('sos.subject_id', $subject->subject_id)
                        ->where('sos.syear', $syear)
                        ->count();

                    // Conditional checks based on sub_institute_id and subject type
                    if ($isElectiveSubject) {
                        return $sub_institute_id == 47 ? $check_optional_subject_with_student < 0 : $check_optional_subject_with_student > 0;
                    } elseif ($subject->optional_type == 1) {
                        return $check_optional_subject_with_student > 0;
                    }

                    return true;
                });

                $get_subject = array_values($get_subject);
            }
            // for other Institutes
            else{
                // Filter the elective subjects based on the condition
                $get_subject = array_filter($get_subject, function ($value) use ($student_id, $syear) {
                    if ($value->elective_subject == 'Yes') {
                        $check_optional_subject_with_student = DB::table('student_optional_subject')
                            ->where('student_id', $student_id)
                            ->where('subject_id', $value->subject_id)
                            ->where('syear', $syear)
                            ->count();
    
                        return $check_optional_subject_with_student > 0;
                    }
                    return true;
                });
            }
        return  $get_subject;
    }
    // store results in table result_html for mobile 
    public function save_result_html(Request $request)
    {
            // return $request;exit;        
        $student_array = explode(",", $request->get('student_arr'));
        $term_id = $request->get('term_id');
        $grade_id = $request->get('grade_id');
        $standard_id = $request->get('standard_id');
        $division_id = $request->get('division_id');
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        $result_type = $request->get('result_type');
        // echo "<pre>";print_r($request->all());exit;

        foreach ($student_array as $key => $val) {
            $result_data['student_id'] = $val;
            $result_data['term_id'] = $term_id;
            $result_data['grade_id'] = $grade_id;
            $result_data['standard_id'] = $standard_id;
            $result_data['division_id'] = $division_id;
            $result_data['syear'] = $syear;
            $result_data['sub_institute_id'] = $sub_institute_id;
            $result_data['created_by'] = $user_id;
            $result_data['result_type'] = $result_type;
            $result_data['html'] = $request->get('html_' . $val);
            // if($val==236243){
            //     echo "<pre>";print_r($result_data);
            // }
            // exit;
            $data = DB::select("SELECT * FROM result_html WHERE student_id = '" . $val . "' AND term_id = '" . $request->get('term_id') . "'
                    AND grade_id = '" . $request->get('grade_id') . "'  AND standard_id = '" . $request->get('standard_id') . "'
                     AND division_id = '" . $request->get('division_id') . "'  AND syear = '" . $request->get('syear') . "'
                     AND sub_institute_id = '" . session()->get('sub_institute_id') . "' AND result_type = '".$result_type."'
                    ");
            if (count($data) > 0) {
                // $html = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">'.htmlentities($request->get('html_' . $val));
                $html = $request->get('html_' . $val);
                
                $finalArray['html'] = $html;
                $finalArray['result_type'] = $result_type;
                $finalArray['updated_by'] = $user_id;
                $finalArray['updated_on'] = NOW();
                $data = DB::table('result_html')->where(['student_id' => $val, 'term_id' => $term_id, 'grade_id' => $grade_id, 'standard_id' => $standard_id, 'division_id' => $division_id, 'syear' => $syear,'result_type'=>$result_type])->update($finalArray);
                echo "Updated for std-".$standard_id.' term_id-'.$term_id.' student_id-'.$val;
            } else {
                DB::table("result_html")->insert($result_data);
            }
        }
        return 1;
    }

    public function get_activity_marks($standard_id, $student_id, $format, $digit)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $format_sub_different = [61, 195];

        if ($format == "yearly") {
            $extra_term =$extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }

        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        $get_result_skillsets = DB::table('result_skillset')->selectRaw('*,
        	group_concat(title order by sort_order SEPARATOR "|") as all_title,
        	GROUP_CONCAT(id ORDER BY sort_order ASC) as all_id,
        	group_concat(`group`) as all_group')
            ->where(['sub_institute_id' => $sub_institute_id,'standard'=>$standard_id])
            ->groupBy('main_title')
            ->orderByRaw('main_sort_order')
            ->get()->toArray();
        
        $table = '';
    //     <table width="100%" cellspacing="0" cellpadding="0">
    //     <tbody>
    //         <tr>
    //             <td align="center" width="50%"><b>&lt;&lt;class_teacher_name&gt;&gt;<br>Class Teacher</b></td>
    //             <td align="center" width="50%"><b>P.P.Jose<br>Principal</b></td>
    //         </tr>
    //     </tbody>
    // </table>
        foreach($get_result_skillsets as $key=>$get_result_skillset)
        {
            $margin_bottom='30px';

            if($sub_institute_id==254){
                // if($key==0){
                //     $margin_bottom ="60px;margin-top:30px !important";
                // }
                // if($key==1){
                //     $margin_bottom="40px";
                // }
                if($key==2){
                    $margin_bottom="50px";
                }
                // if($key==3 || $key==4 || $key==5){
                    // $margin_bottom="40px";
                // }
            }
            
            $table .= '<table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #000 !important; margin-bottom:'.$margin_bottom.';" cellspacing="0"  border="1">
            <tbody>
            <tr>';
            $style = '';
            $heading = $get_result_skillset->main_title;
            $sub_title_heading = $get_result_skillset->all_title;
            $skillset_ids = $get_result_skillset->all_id;
            $all_group = $get_result_skillset->all_group;

            $sub_title = explode('|', $sub_title_heading);
            $skill_ids = explode(',', $skillset_ids);
            $all_group = explode(',', $all_group);

            $table .= '<th style="text-align:left;font-size:large;background:white !important" colspan="5"><b>' . $heading . '</b></th></tr>';

            foreach($sub_title as $key => $value)
            {
                if(isset($all_group[$key])) 
				{
        		$sub_sub_title = DB::table('result_activity_group as rag')
            		->where('rag.sub_institute_id', $sub_institute_id)
            		->where('rag.group', $all_group[$key])
            		->get()
            		->toArray();
	    		} else {
	        		continue;
	    		}

                $table .= '<tr><th style="text-align:left;font-size:medium;background:white !important;"><b>' . $value . '</b></th>';
                $get_result_activity_marks = $get_sub_activity = $sub_sub_id = [];  

                foreach($sub_sub_title as $key1 => $value1)
                {
                    $table .= '<th style="text-align:center;font-size:medium;color:black;background:white !important"><b>' . $value1->title . '</b></th>';

                    $get_result_activity_masters = DB::table('result_activity_master')
                    ->selectRaw('*, 
                    	group_concat(title ORDER BY sort_order ASC SEPARATOR "|") as activity_master_title,
                    	GROUP_CONCAT(id ORDER BY sort_order ASC) as ids')
                    ->where(['sub_institute_id' => $sub_institute_id])
                    ->where('skill_id', $skill_ids[$key])
                    ->where('standard',$standard_id)
                    ->orderBy('sort_order')
                    ->get()->toArray();
                    
                    foreach($get_result_activity_masters as $get_result_activity_master)
                    {
                        $activity_master_id = explode(',', $get_result_activity_master->ids);
                        $activity_master_title = explode('|', $get_result_activity_master->activity_master_title);

                        foreach($activity_master_id as $key2 => $activity_id)
                        {
                            $get_result_activity_marks[$activity_master_title[$key2]][] = DB::table('result_activity_marks')
                            ->where(['sub_institute_id' => $sub_institute_id])
                            ->where('activity_id', $activity_id)
                            ->where('group_id', $value1->id)
                            ->where('student_id', $student_id)
                            ->where('syear',$syear)
                            ->get()->toArray();

                            if(!isset($get_sub_activity[$activity_id])){
                                $get_sub_activity[$activity_id]= DB::table('result_sub_activity')->where('sub_skill_id',$activity_id)->get()->toArray();
                            }
                        }
                    }
                }
                // echo "<pre>";print_r($get_sub_activity);
                $table .= '</tr>';
                if(isset($get_result_activity_masters) && !empty($get_result_activity_masters)){
                    foreach($get_result_activity_masters as $get_result_activity_master)
                    {
                        $activity_master_title = explode('|', $get_result_activity_master->activity_master_title);
                        $activity_master_id = explode(',', $get_result_activity_master->ids);

                        foreach ($activity_master_id as $ak => $activity_id) {
                            if(isset($get_sub_activity[$activity_id]) && !empty($get_sub_activity[$activity_id])){
                                $table.='<tr><td style="text-align:left;font-size:medium;width:60%;background:white !important;"><b>'.$activity_master_title[$ak].'</b></td>';
                                foreach($sub_sub_title as $key1 => $value1)
                                {
                                        $table .= '<td style="text-align:center;font-size:medium;background:white !important;color:black;width:10%;">N/A</td>';
                                }
                               
                                $table.=' </tr>';
                                foreach ($get_sub_activity[$activity_id] as $kr => $val) {
                                        // echo "<pre>";print_r($v->title);
                                      
                                    $table.='<tr><td style="background:white;color:black;width:10%;">'.$val->title.'</td>';
                                    $count = [];
                                    foreach($sub_sub_title as $ke => $va)
                                    {
                                        $marks = DB::table('result_activity_marks')
                                        ->where(['sub_institute_id' => $sub_institute_id])
                                        ->where('activity_id', $activity_id)
                                        ->where('sub_activity_id', $val->id)
                                        ->where('group_id', $va->id)
                                        ->where('student_id', $student_id)
                                        ->where('syear',$syear)
                                        ->first();

                                        if(!empty($marks)){
                                            if(isset($marks->group_id) && $marks->group_id==$va->id){
                                               
                                                $count[$activity_id][$ke]=1;
                                            }
                                        }
                                    }
                                    if(!isset($count[$activity_id])){
                                        foreach($sub_sub_title as $kk => $vv)
                                        {
                                            $table .= '<td style="text-align:center;font-size:medium;background:white !important;color:black;width:10%;">N/A</td>';
                                        }
                                    }else{
                                        foreach($sub_sub_title as $kk => $vv)
                                        {
                                            if(isset($count[$activity_id][$kk])){

                                                $table .= '<td style="text-align:center;font-size:medium;background:white !important;color:black;width:10%;">&#10004</td>';
                                            }else if(in_array($sub_institute_id,[202])){
                                                    $table .= '<td style="text-align:center;font-size:medium;background:white !important;color:black;width:10%;">-</td>';
                                            }else{
                                                $table .= '<td style="text-align:center;font-size:medium;background:white !important;color:black;width:10%;">-</td>';
                                            }
                                        }
                                    }
                                    $table.='</tr>';
                                    
                                }
                                // $table.='</tr>';
                            }else{
                                $table .= '<tr><td style="text-align:left;font-size:medium;width:60%;background:white !important;">' . $activity_master_title[$ak] .'</td>';
                                $checked = 0;
                                if(isset($get_result_activity_marks[$activity_master_title[$ak]]) && !empty($get_result_activity_marks[$activity_master_title[$ak]]))
                                {
                                    foreach($get_result_activity_marks[$activity_master_title[$ak]] as $get_result_activity_mark)
                                    {
                                        if(isset($get_result_activity_mark[0]) && $get_result_activity_mark[0]->activity_id==$activity_master_id[$ak]){
                                            $checked++;
                                        }                                  
                                    }
                                }
                                if($checked==0){
                                    foreach($get_result_activity_marks[$activity_master_title[$ak]] as $get_result_activity_mark)
                                    {
                                        $table .= '<td style="text-align:center;font-size:medium;background:white !important;color:black;width:10%;">NA</td>';
                                    }
                                }else{
                                    foreach($get_result_activity_marks[$activity_master_title[$ak]] as $get_result_activity_mark)
                                    {
                                        $table .= '<td style="text-align:center;font-size:medium;background:white !important;color:black;width:10%;">';
                                        if(isset($get_result_activity_mark[0]) && $get_result_activity_mark[0]->activity_id==$activity_master_id[$ak])
                                        {
                                            $table .= '&#10004';

                                        }else if(in_array($sub_institute_id,[202])){
                                            $table .= '-';
                                        }
                                        $table .= '</td>';
                                    
                                    }
                                }
                                $table .= '</tr>';
                            }

                        }
                    }
                }
                // if(isset($get_result_activity_masters) && !empty($get_result_activity_masters))
                // {
                //     foreach($get_result_activity_masters as $get_result_activity_master)
                //     {
                //         $activity_master_title = explode('|', $get_result_activity_master->activity_master_title);
                //         $activity_master_id = explode(',', $get_result_activity_master->ids);
                        
                //         foreach($activity_master_title as $key2 => $activity_master_titles)
                //         {
                //             $table .= '<tr><td style="text-align:left;font-size:medium !important;width:60%;background:white !important;">' . $activity_master_titles .'</td>';
                //             $checked = 0;
                //             if(isset($get_result_activity_marks[$activity_master_title[$key2]]) && !empty($get_result_activity_marks[$activity_master_title[$key2]]))
                //             {
                //                 foreach($get_result_activity_marks[$activity_master_title[$key2]] as $get_result_activity_mark)
                //                 {
                //                     if(isset($get_result_activity_mark[0]) && $get_result_activity_mark[0]->activity_id==$activity_master_id[$key2]){
                //                         $checked++;
                //                     }                                  
                //                 }
                //             }
                //             if($checked==0){
                //                 foreach($get_result_activity_marks[$activity_master_title[$key2]] as $get_result_activity_mark)
                //                 {
                //                     $table .= '<td style="text-align:center;font-size:medium !important;background:white !important;color:black;width:10%;">NA</td>';
                //                 }
                //             }else{
                //                 foreach($get_result_activity_marks[$activity_master_title[$key2]] as $get_result_activity_mark)
                //                 {
                //                     $table .= '<td style="text-align:center;font-size:medium !important;background:white !important;color:black;width:10%;">';
                //                     if(isset($get_result_activity_mark[0]) && $get_result_activity_mark[0]->activity_id==$activity_master_id[$key2])
                //                     {
                //                         $table .= '<img src="https://erp.triz.co.in/Images/check-hpc.png" alt="checkImg" style="width:10px;height:10px !important">   ';
                //                     }
                //                     $table .= '</td>';
                                  
                //                 }
                //             }
                //             $table .= '</tr>';
                //         }
                //     }
                // }
            }
            $table .= '</tbody></table>';
        }
        // exit;
        $res['table'] = $table;
        return $res;
    }

    public function get_hpc_hills_nursary($standard_id, $student_id, $format, $digit)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $format_sub_different = [61, 195];

        if ($format == "yearly") {
            $extra_term =$extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }

        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        $get_result_skillsets = DB::table('result_skillset')->selectRaw('*,
        	group_concat(title order by sort_order SEPARATOR "|") as all_title,
        	GROUP_CONCAT(id ORDER BY sort_order ASC) as all_id,
        	group_concat(`group`) as all_group')
            ->where(['sub_institute_id' => $sub_institute_id,'standard'=>$standard_id])
            ->groupBy('main_title')
            ->orderByRaw('main_sort_order')
            ->get()->toArray();
        
        $table = '';
  
        foreach($get_result_skillsets as $keym=>$get_result_skillset)
        {
            
            $table .= '<table  class="curricular_table" cellspacing="0"  border="1">
            <thead>
            <tr class="curricular_thead_tr_main">';
            $style = '';
            $heading = $get_result_skillset->main_title;
            $sub_title_heading = $get_result_skillset->all_title;
            $skillset_ids = $get_result_skillset->all_id;
            $all_group = $get_result_skillset->all_group;

            $sub_title = explode('|', $sub_title_heading);
            $skill_ids = explode(',', $skillset_ids);
            $all_group = explode(',', $all_group);

            $table .= '<th class="curricular_th" style="font-size:18px"><b>' . $heading . '</b></th></tr>';

            foreach($sub_title as $key => $value)
            {
                if(isset($all_group[$key])) 
				{
        		$sub_sub_title = DB::table('result_activity_group as rag')
            		->where('rag.sub_institute_id', $sub_institute_id)
            		->where('rag.group', $all_group[$key])
            		->get()
            		->toArray();
	    		} else {
	        		continue;
	    		}

                $table .= '<tr class="curricular_thead_tr"><th class="curricular_th"><b>' . $value . '</b></th>';
                $get_result_activity_marks = $get_sub_activity = $sub_sub_id = [];  

                $span_classes = ["span_0","span_1","span_2","span_3","span_4","span_5"];
                $first_char = [];
                foreach($sub_sub_title as $key1 => $value1)
                {
                    $firstCharacter = substr($value1->title, 0, 1);
                    $first_char[$key1] = $firstCharacter;
                    $table .= '<th  class="curricular_th"><span class="'.$span_classes[$key1].'">' . $value1->title . '</span></th>';

                    $get_result_activity_masters = DB::table('result_activity_master')
                    ->selectRaw('*, 
                    	group_concat(title ORDER BY sort_order ASC SEPARATOR "|") as activity_master_title,
                    	GROUP_CONCAT(id ORDER BY sort_order ASC) as ids')
                    ->where(['sub_institute_id' => $sub_institute_id])
                    ->where('skill_id', $skill_ids[$key])
                    ->where('standard',$standard_id)
                    ->orderBy('sort_order')
                    ->get()->toArray();
                    
                    foreach($get_result_activity_masters as $get_result_activity_master)
                    {
                        $activity_master_id = explode(',', $get_result_activity_master->ids);
                        $activity_master_title = explode('|', $get_result_activity_master->activity_master_title);

                        foreach($activity_master_id as $key2 => $activity_id)
                        {
                            if(isset($activity_master_title[$key2])){
                            $get_result_activity_marks[$activity_master_title[$key2]][] = DB::table('result_activity_marks')
                            ->where(['sub_institute_id' => $sub_institute_id])
                            ->where('activity_id', $activity_id)
                            ->where('group_id', $value1->id)
                            ->where('student_id', $student_id)
                            ->where('syear',$syear)
                            ->get()->toArray();

                            if(!isset($get_sub_activity[$activity_id])){
                                $get_sub_activity[$activity_id]= DB::table('result_sub_activity')->where('sub_skill_id',$activity_id)->get()->toArray();
                            }
                            }
                        }
                    }
                }
                // echo "<pre>";print_r($get_sub_activity);
                $table .= '</tr>';
                if(isset($get_result_activity_masters) && !empty($get_result_activity_masters)){
                    foreach($get_result_activity_masters as $get_result_activity_master)
                    {
                        $activity_master_title = explode('|', $get_result_activity_master->activity_master_title);
                        $activity_master_id = explode(',', $get_result_activity_master->ids);

                        foreach ($activity_master_id as $ak => $activity_id) {
                            if(isset($get_sub_activity[$activity_id]) && !empty($get_sub_activity[$activity_id])){
                                $table.='<tr class="curricular_tbody_tr"><td class="curricular_td"><span class="'.$span_classes[$key1].'">'.$activity_master_title[$ak].'</span></td>';
                                foreach($sub_sub_title as $key1 => $value1)
                                {
                                        $table .= '<td class="curricular_td""><span class="dash">N/A</span></td>';
                                }
                               
                                $table.=' </tr>';
                                foreach ($get_sub_activity[$activity_id] as $kr => $val) {
                                        // echo "<pre>";print_r($v->title);
                                      
                                    $table.='<tr class="curricular_tbody_tr"><td class="curricular_td">'.$val->title.'</td>';
                                    $count = [];
                                    foreach($sub_sub_title as $ke => $va)
                                    {
                                        $marks = DB::table('result_activity_marks')
                                        ->where(['sub_institute_id' => $sub_institute_id])
                                        ->where('activity_id', $activity_id)
                                        ->where('sub_activity_id', $val->id)
                                        ->where('group_id', $va->id)
                                        ->where('student_id', $student_id)
                                        ->where('syear',$syear)
                                        ->first();

                                        if(!empty($marks)){
                                            if(isset($marks->group_id) && $marks->group_id==$va->id){
                                               
                                                $count[$activity_id][$ke]=1;
                                            }
                                        }
                                    }
                                    if(!isset($count[$activity_id])){
                                        foreach($sub_sub_title as $kk => $vv)
                                        {
                                            $table .= '<td class="curricular_td"><span class="dash">N/A</span></td>';
                                        }
                                    }else{
                                        foreach($sub_sub_title as $kk => $vv)
                                        {
                                            if(isset($count[$activity_id][$kk])){

                                                $table .= '<td class="curricular_td"><psan class="'.$span_classes[$key1].'">&#10004</span></td>';
                                            }else if(in_array($sub_institute_id,[202])){
                                                    $table .= '<td class="curricular_td"><span class="dash">-</span></td>';
                                            }else{
                                                $table .= '<td class="curricular_td"><span class="dash">-</span></td>';
                                            }
                                        }
                                    }
                                    $table.='</tr>';
                                    
                                }
                                // $table.='</tr>';
                            }else{
                                if(isset($activity_master_title[$ak])){
                                   $printClass = '';

                                    if(in_array($standard_id,[2277,2293,2287,2300,2291,2304])){
                                        $printClass = 'nursey_'.$key.'_'.$ak;
                                    }
                                    if(in_array($standard_id,[2281,2295,2285,2298,2288,2301])){
                                        $printClass = 'junior_'.$key.'_'.$ak;
                                    }
                                    if(in_array($standard_id,[2282,2296,2286,2299,2290,2303])){
                                        $printClass = 'senior_'.$key.'_'.$ak;
                                    }
                                
                                    // $printClass = 'class_'.$key.'_'.$ak;

                                    $table .= '<tr class="curricular_tbody_tr '.$printClass.'"><td  class="curricular_td">' . $activity_master_title[$ak].'</td>'; // .'-'.$key.'('.$ak.')'
                                    $checked = 0;
                                    if(isset($get_result_activity_marks[$activity_master_title[$ak]]) && !empty($get_result_activity_marks[$activity_master_title[$ak]]))
                                    {
                                        foreach($get_result_activity_marks[$activity_master_title[$ak]] as $get_result_activity_mark)
                                        {
                                            if(isset($get_result_activity_mark[0]) && $get_result_activity_mark[0]->activity_id==$activity_master_id[$ak]){
                                                $checked++;
                                            }                                  
                                        }
                                    }
                                    if($checked==0){
                                        foreach($get_result_activity_marks[$activity_master_title[$ak]] as $get_result_activity_mark)
                                        {
                                            $table .= '<td class="curricular_td"><span class="dash">NA</span></td>';
                                        }
                                    }else{
                                        foreach($get_result_activity_marks[$activity_master_title[$ak]] as $kPoint => $get_result_activity_mark)
                                        {
                                            $table .= '<td class="curricular_td">';
                                            if(isset($get_result_activity_mark[0]) && $get_result_activity_mark[0]->activity_id==$activity_master_id[$ak])
                                            {
                                                $table .= '<span class="'.$span_classes[$kPoint].'">'.$first_char[$kPoint].'</span>';
                                            }else if(in_array($sub_institute_id,[202])){
                                                $table .= '-';
                                            }
                                            $table .= '</td>';
                                        
                                        }
                                    }
                                }
                                $table .= '</tr>';
                            }

                        }
                    }
                }
              
            }
            $table .= '</tbody></table>';
        }
        // toddleer


        if(in_array($standard_id,[2277,2293,2287,2300,2291,2304])){
            $table.='<style>@media print{
                .nursey_1_5,
                .nursey_3_1{
                    margin-bottom:114px;
                }
                
            }</style>';
        }
        // juniors
        if(in_array($standard_id,[2281,2295,2285,2298,2288,2301])){
            $table.='<style>@media print{
                .junior_0_12{
                    margin-bottom:120px;
                }
                .junior_3_6,
                .junior_5_1{
                    margin-bottom:150px;
                }
                .junior_2_2{
                    margin-bottom:180px;
                }
                
            }</style>';
        }

// seniors
        if(in_array($standard_id,[2282,2296,2286,2299,2290,2303])){
            $table.='<style>@media print{
                .senior_0_12,
                .senior_2_2,
                .senior_3_7
                {
                    margin-bottom:140px;
                } 
                .senior_5_2{
                    margin-bottom:130px;
                }
            }</style>';
        }
      
        // exit;
        $res['table'] = $table;
        return $res;
    }

        public function getTermAttendance($standard_id, $student_id, $format, $type)
        {
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            $get_term = DB::table('academic_year')
                    ->selectRaw('group_concat(id) as id,group_concat(term_id) as term_id,group_concat(title) as title,group_concat(start_date) as start_date,group_concat(end_date) as end_date,group_concat(post_start_date) as post_start_date,group_concat(post_end_date) as post_end_date')
                    ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])
                    ->when($format!=='yearly',function($q) use($format){
                        $q->where('term_id',$format);
                    })
                    ->groupBy('syear')
                    ->first();
            // echo "<pre>";print_r($student_id);exit;
            $post_start_date_ex = explode(',',$get_term->post_start_date);
            $post_end_date_ex = explode(',',$get_term->post_end_date);
            $post_start_date_final_ex = explode(',',$get_term->post_start_date);
            $post_end_date_final_ex = explode(',',$get_term->post_end_date);

            $post_start_date = $post_start_date_ex[0];
            $post_end_date = isset($post_end_date_ex[1]) ? $post_end_date_ex[1] : $post_end_date_ex[0];
            $post_start_date_final =  $post_start_date_final_ex[0];
            $post_end_date_final = isset($post_end_date_final_ex[1]) ? $post_end_date_final_ex[1] : $post_end_date_final_ex[0];

            $cal_event = DB::table('calendar_events as ce')
            ->join('academic_year as ay', 'ce.syear', '=', 'ay.syear')
            ->where(['ce.sub_institute_id' => $sub_institute_id, 'ce.syear' => $syear])
            ->WhereRaw("FIND_IN_SET('$standard_id', ce.standard) AND ce.event_type in ('holiday','vacation')")
            ->whereBetween('ce.school_date', [$post_start_date, $post_end_date])
            ->when($format!=='yearly',function($q) use($format){
                $q->where('ay.term_id',$format);
            })
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
//echo $attTotDays." - ".count($calArr);
//exit();            
            $attTotDays = $attTotDays - count($calArr);

            //db::enableQueryLog();
            $attarray = DB::table('attendance_student as ap')
            ->join('tblstudent as s', 'ap.student_id', '=', 's.id')
            ->join('tblstudent_enrollment as se', function ($join) {
            $join->on('s.id', '=', 'se.student_id')
            ->on('se.syear', '=', 'ap.syear')
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
            //dd(db::getQueryLog($attarray));
            $attarray = $attarray->pluck('present_day', 'id')->all();
//echo "<pre>";
//print_r($attarray);
//            exit();
            if (isset($attarray[$student_id])) {
                $attendance = $attarray[$student_id] . '/' . $attTotDays;
            } else {
                $attendance = '-/' . $attTotDays; 
            }
         
            return $attendance;
        }

    public function get_cma_scholatic($standard_id, $student_id, $format){
          // echo "<pre>";print_r($student_id);exit;
          $syear = session()->get('syear');
          $sub_institute_id = session()->get('sub_institute_id');
          $extra_term =$extra_exam = "1=1";
          if ($format != "yearly"){
              $extra_term = "term_id = " . $format;
              $extra_exam = "rce.term_id = " . $format;
          }
          // get term_name 
          $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
  
          // get subject
         $get_subject = $this->get_subject($sub_institute_id,$syear,$student_id,$standard_id);
         $exam_name = $this->get_exam_name($sub_institute_id,$syear,$standard_id,$extra_exam);
         // get exam title 
         $exam_title = $this->get_exam_title($sub_institute_id,$syear,$standard_id,$extra_exam);
         //get exam marks
         $exam_marks = $this->get_exam_marks($sub_institute_id,$student_id);
         // get standard gardes
         $grade_arr = $this->getGradeScale($standard_id, '');

        // echo "<pre>";print_r($exam_marks);exit;

        $ExamMasters =$existsMaster = [];

        foreach ($exam_name as $key => $value) {
            if(!in_array($value->ExamTitle,$existsMaster)){
                $existsMaster[] = $value->ExamTitle;
                $ExamMasters[] = $value;
            }
        }
        // echo "<pre>";print_r($ExamMasters);exit;

       $table = '<table class="aca-year" cellspacing="0" cellpadding="0" border="1" width="100%">
        <tr>
            <td rowspan="2" align="center">
                <b>MAIN SUBJECTS</b>
            </td>';
            foreach ($term_name as $key => $value) {
                $term_start_month = strtoupper(Carbon::parse($value->start_date)->format('F'));
                $term_end_month = strtoupper(Carbon::parse($value->end_date)->format('F'));
                $table .= '<td colspan="'. (count($ExamMasters) + 2) .'" align="center"><b>
                    '.$value->title.' ('.$term_start_month.' - '.$term_end_month.')
                    <b></td>';
                    $total_term_marks[$value->term_id] = 0;
                    $total_sub_marks[$value->term_id] = 0;
            }
        $table.='</tr><tr>';
        $total_mark = 0;
        foreach ($ExamMasters as $key => $val) {
            $table .= '<td class="data_center" align="center"><b>'.$val->ExamTitle.'<br>('.$val->weightage.')<b></td>';
            $total_mark += $val->weightage;
        }
        $table.='<td class="data_center" align="center"><b>TOTAL</b></td>
        <td class="data_center" align="center"><b>GRADE</b></td>
        </tr>';

        $tot_ob_mark =$tot_sub_mark =$get_all_ob_mark=$get_all_tot_mark = 0;
            foreach ($get_subject as $key => $val) {
                // print subject name
                $table .= '<tr><td><b>'.$val->subject_name.'</b></td>';
                $both_term_ob_mark = 0;
                foreach ($term_name as $keys => $terms) {
                    $obtained_marks = $to_marks = $to_weight = [];
                    $title_exam = []; 
                     // get marks by exam id wise
                    foreach ($exam_name as $key => $title) {
                        if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                            $foundMarks = false;
                            $ob_mark = 0;
                            $title_exam[$title->exam_id][] = $title->ExamTitle;
                            // all exam marks 
                            foreach ($exam_marks as $index => $marks) {
                                $to_marks[$title->exam_id][] = $title->points;
                                $to_weight[$title->exam_id] = $title->weightage;
                                if ($title->id == $marks->exam_id ) {
                                    // for AB,NA,EX
                                    if ($marks->points == "0.00" || $marks->points == "") {
                                        $ab_ex_na = $marks->is_absent;
                                        if ($marks->is_absent == '') {
                                            $ab_ex_na = 0;
                                        }
                                        $obtained_marks[$title->exam_id][] = $ab_ex_na ?? 0;
                                    } else {
                                        $ob_mark = $marks->points;
                                        // store marks in array to get best of 2 
                                        $obtained_marks[$title->exam_id][] = $ob_mark;
                                      
                                        $foundMarks = true;
                                    }
                                    break;
                                }
                            }
                        }
                    }
    
                    // echo "<pre>";print_r($obtained_marks);
                    $ob_main_mark = $ab_ex_na = $total_marks = 0;
                    // for best of 2 exam wise 
                    if (!empty($title_exam)) {
                        foreach ($title_exam as $exam_id => $marksArray) {                                
                            $w_m = $to_weight[$exam_id] ?? 0; // Check if the key exists
                            $t_m = array_sum(array_intersect_key($to_marks[$exam_id] ?? [], $marksArray));
                            $obtained_mark_arr = $obtained_marks[$exam_id] ?? [];
                            $obtained_mark_sum = array_sum($obtained_mark_arr);

                            // get mark for total mark 
                            $ob_main_mark += ($t_m !== 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                            // convert marks if best of 2
                            $convert_mark = ($obtained_mark_sum != 0) ? (($obtained_mark_sum / $t_m) * $w_m) : 0;
                            
                            $pt_per = ($convert_mark !== '0.00' && $w_m != 0) ? round(($convert_mark / $w_m) * 100, 0) : 0;
                            // print marks
                            if(count($obtained_mark_arr) > 1){
                                $total_marks += $w_m;
                                $table .= '<td class="data_center" align="center">' . number_format($convert_mark, 2) . '</td>';
                            }else{
                                if(isset($obtained_mark_arr[0]) && ($obtained_mark_arr[0]!='N.A.' || $obtained_mark_arr[0]!='EX')){
                                    $total_marks += $w_m;
                                }
                                $tdVal = number_format($convert_mark, 2);
                                if(isset($obtained_mark_arr[0]) && $obtained_mark_arr[0]=="AB"){
                                    $tdVal = "AB";
                                }
                                $table .= '<td class="data_center else" align="center">' . $tdVal . '</td>';
                            }
                        }
                    } else {
                        // If marks not found
                        foreach ($title_exam as $exam_id => $marksArray) {
                            $table .= '<td class="data_center no_mark ' . $exam_id . '" align="center">0.00</td>';
                        }
                    }    
                    $obtained_mark_formatted = number_format($ob_main_mark, 2);
                    // print total marks and grade
                    $table .= '<td class="data_center all_mark" align="center">' . $obtained_mark_formatted . '</td><td class="data_center grade_of_both"  align="center">' . $this->getGrade($grade_arr, $total_mark, $obtained_mark_formatted) . '</td>';
                    $both_term_ob_mark += $obtained_mark_formatted;
                    // Update the total marks for the current term
                    $total_term_marks[$terms->term_id] += $obtained_mark_formatted;
                    $total_sub_marks[$terms->term_id] += $total_mark;
                }
                
            }
            $get_all_ob_mark = array_sum($total_term_marks);
            $get_all_tot_mark = array_sum($total_sub_marks);
            $allGrade = $this->getGrade($grade_arr, $get_all_tot_mark, $get_all_ob_mark);
            $per = $this->getPer($get_all_ob_mark, $get_all_tot_mark);
            
        $table .= '</tr>
        <tr>
            <td colspan="2" align="left"><b>GRAND TOTAL: &nbsp;'.round($get_all_ob_mark).'<b></td>
            <td colspan="2" align="left"><b>PERCENTAGE : &nbsp;'.round($per).'%<b></td>
            <td colspan="2" align="left"><b>OVERALL GRADE : &nbsp;'.$allGrade.'<b></td>
        </tr>
        </table>';
    $res['table'] = $table;
        return $res;
    }
    public function get_cma_detailAssm($standard_id, $student_id, $format){
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $extra_term =$extra_exam = "1=1";
        if ($format != "yearly"){
            $extra_term = "term_id = " . $format;
            $extra_exam = "comark.term_id = " . $format;
        }
        $table='<div  width="100%" style="display:flex">';
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        $responce_arr = array();

        $sql_mark_grade = "select * from result_co_scholastic where sub_institute_id = " . $sub_institute_id . " and " . $extra_term . " ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));
        $extra_where="1=1";

        $ret_data = $this->co_scholaticQuery($sub_institute_id,$syear,$student_id,$standard_id,$extra_exam,'skill_competence');
            // echo "<pre>";print_r($ret_data);exit;
            $part1Head=$part2Head=$coData1=$coData2=[];
            foreach ($ret_data as $id => $arr) {
                if($arr->part_no==1){
                    $part1Head[$arr->parent_id] = $arr->parent_title;
                    $coData1[$arr->child_title][$arr->parent_id]=$arr->marks;
                }
                if($arr->part_no==2){
                    $part2Head[$arr->parent_id] = $arr->parent_title;
                    $coData2[$arr->child_title][$arr->parent_id]=$arr->marks;
                }
            }
          // table 1
          if(!empty($coData1)){
            $table.='<table class="aca-year" cellspacing="0" cellpadding="0" border="1" width="100%">
            <tr><th><b>SKILLS/ COMPETENCES</td></th>';
                foreach ($part1Head as $key => $value) {
                    $table.='<th align="center"><b>'.$value.'</b></th>';
                }
             $table.='</tr>';
                    foreach ($coData1 as $k => $val) {
                        $table.='<tr><td><b>'.$k.'</b></td>';
                        foreach ($part1Head as $key => $value) {
                        if(isset($val[$key])){
                            $table.='<td align="center">'.$val[$key].'</td>';
                        }else{
                            $table.='<td align="center">0.00</td>';
                        }
                    }
                    $table.='</tr>';
                }
          }
          // table 2
        if(!empty($coData2)){
                $table.='<table class="aca-year" cellspacing="0" cellpadding="0" border="1" width="100%">
                <tr><th><b>SKILLS/ COMPETENCES</td></th>';  
                foreach ($part2Head as $key => $value) {
                    $table.='<th align="center"><b>'.$value.'</b></th>';
                }
                $table.='</tr>'; 
                    foreach ($coData2 as $k => $val) {
                        $table.='<tr><td><b>'.$k.'</b></td>';
                        foreach ($part2Head as $key => $value) {
                            if(isset($val[$key])){
                                $table.='<td align="center">'.$val[$key].'</td>';
                            }else{
                                $table.='<td align="center">0.00</td>';
                            }
                        }
                        $table.='</tr>';
                }    
                $table.='</table>';
          }
          $table.='</div>';
            // echo "<pre>";print_r($table);exit;
        $res['table'] = $table;
        return $res;
    }
    public function get_cma_co_scholatic($standard_id, $student_id, $format){
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        // co scholoastic like lions
        $extra_term =  $extra_exam = "1=1";
        if ($format != "yearly"){
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        $responce_arr = array();

        $sql_mark_grade = "select * from result_co_scholastic where sub_institute_id = " . $sub_institute_id . " and " . $extra_term . " ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));
        $extra_where="1=1";

        $co_grade_range = DB::table('result_co_scholatic_range')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereRaw($extra_where)->get()->toArray();

        $data_arr = array();
        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
              // Define your query using the query builder
                $ret_data = $this->co_scholaticQuery($sub_institute_id,$syear,$student_id,$standard_id,$extra_exam,'co_scholastic');

                foreach ($ret_data as $id => $arr) {
                    $data_arr[$arr->parent_title][$arr->term_id][$arr->co_scholastic_id]['student_id'] = $arr->student_id;
                    $data_arr[$arr->parent_title][$arr->term_id][$arr->co_scholastic_id]['co_scholastic_id'] = $arr->co_scholastic_id;
                    $data_arr[$arr->parent_title][$arr->term_id][$arr->co_scholastic_id]['term_id'] = $arr->term_id;
                    $data_arr[$arr->parent_title][$arr->term_id][$arr->co_scholastic_id]['parent_title'] = $arr->parent_title;
                    $data_arr[$arr->parent_title][$arr->term_id][$arr->co_scholastic_id]['child_title'] = $arr->child_title;
                    $data_arr[$arr->parent_title][$arr->term_id][$arr->co_scholastic_id]['obtain_grade'] = $arr->obtain_grade;
                }               
            }
        }
        $table = '';
        if(!empty($data_arr)){
            $table .= '<table class="aca-year" cellspacing="0" cellpadding="0" border="1" width="100%">
            <tr>';
            foreach ($data_arr as $key => $value) {
                $table .= '<th align="center">'.$key.'</th>';
                foreach ($term_name as $key2 => $value2) {
                    $table .= '<th align="center"><b>'.$value2->title.'</b></th>';
                }
            }
            $table.='<tr>';
            foreach ($data_arr as $key => $value) {
                // $table .= '<th align="center">'.$key.'</th>';
                foreach ($value as $key2 => $value2) {
                    $table.='<tr>';
                    foreach ($value2 as $key3 => $value3) {
                        $table .= '<td align="center">'.$value3['child_title'].'</td>';
                        $table .= '<td align="center">'.$value3['obtain_grade'].'</td>';
                    }
                    $table.='</tr>';
                }
            }
            $table.='</table>';
        }
        // echo "<pre>";print_r($table);exit;
        $res['table'] = $table;
        return $res;
    }
    
    public function co_scholaticQuery($sub_institute_id,$syear,$student_id,$standard_id,$extra_exam,$partname=''){
        $select = 'comark.student_id,comark.co_scholastic_id,comark.term_id,cop.title as parent_title,co.title as child_title,cop.part_no,cop.part_name,cop.part_no,cop.part_name,cop.id as parent_id';

        if($partname=='skill_competence'){
            $select .= ',IFNULL(comark.points,"0") as marks';
        }else{
            $select .=',IFNULL(cograde.title,"-") as obtain_grade';
        }
       $query = DB::table('result_co_scholastic_marks_entries as comark')
            ->selectRaw($select)
            ->when($partname=='co_scholastic',function($q){
                $q->join('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade');
            })
            ->join('result_co_scholastic as co', 'co.id', '=', 'comark.co_scholastic_id')
            ->join('result_co_scholastic_parent as cop', 'cop.id', '=', 'co.parent_id')
            ->where('comark.syear', $syear)
            ->whereRaw($extra_exam)
            ->where('comark.standard_id', $standard_id)
            ->where('co.standard_id', $standard_id)
            ->where('comark.student_id', $student_id)
            ->where('comark.sub_institute_id', $sub_institute_id)
            ->when($partname=='co_scholastic',function($q){
                $q->where('cop.part_name','=','co_scholastic');
            })
            ->when($partname=='skill_competence',function($q){
                $q->where('cop.part_name','=','skill_competence');
            })
            ->orderBy('comark.student_id')
            ->orderBy('cop.sort_order')
            ->orderBy('co.sort_order')
            ->orderBy('comark.term_id')
            ->get();
            return $query;
    }
}