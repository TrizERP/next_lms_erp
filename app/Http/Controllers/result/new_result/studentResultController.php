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
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $data['data'] = result_template::where('sub_institute_id', $sub_institute_id)->orderBy('sort_order')->get()->toArray();
        if (empty($data['data'])) {
            $data['data'] = result_template::where('sub_institute_id', 0)->orderBy('sort_order')->get()->toArray();
        }
        $data['terms'] = DB::table('academic_year')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        
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
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        // get students
        $studentData = SearchStudent($grade, $standard, $division);
        if (!isset($studentData[0]['enrollment_no']) || empty($studentData)) {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";
            return is_mobile($type, "student-result.index", $res);
        }
        $res['data'] = result_template::where('sub_institute_id', $sub_institute_id)->orderBy('sort_order')->get()->toArray();
        if (empty($res['data'])) {
            $res['data'] = result_template::where('sub_institute_id', 0)->orderBy('sort_order')->get()->toArray();
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

        return is_mobile($type, "result/new_result/student_results/show", $res, "view");
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $template = $request->input('template_id') ?? 0;
        $student_ids = $request->input('students');
        $format = $request->input('format');

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
            ->where('rbm.standard', $request->standard_id)
            ->select('rbm.*', 'rtm.*') // You can specify the columns you want to select
            ->first();

        $last_insert_ids = '';
        $new_html = '';
        $all_stud_html = array();
        foreach ($data as $key => $value) {
            $html_content = $tData[0]['html_content'];
            $class = '';
            if ($sub_institute_id == 254) {
                $class = 'class="report-card-bg"';
            }
            $new_html_content = '<div id="' . $value['id'] . '" ' . $class . ' style="page-break:always !important;">' . $this->create_html_content($syear, $sub_institute_id, $html_content, $value, $template, $result_trust, $format) . '</div>';
            $new_html .= $new_html_content;
            $all_stud_html[$value['id']] = $new_html_content;
        }
        // echo "<pre>";print_r($all_stud_html);exit;
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
        $data['all_stud_html'] = $all_stud_html;
        $data['students_ids'] = $request->students;

        return is_mobile($type, "result/new_result/student_results/result_view", $data, "view");
    }

    public function create_html_content($syear, $sub_institute_id, $html_content, $value, $template, $result_trust, $format)
    {
        // echo "<pre>";print_r($value);exit;
        $logo_height = "50px !important";
        if ($sub_institute_id == 47) {
            $logo_height = "120px !important";
        }
        if (isset($result_trust->left_logo)) {
            $image_path1 = "/storage/result/left_logo/" . $result_trust->left_logo ?? '';
            $image_path_1 = '<img src="' . $image_path1 . '" alt="SCHOOL LEFT LOGO" style="height: ' . $logo_height . ';">';
            $html_content = str_replace(htmlspecialchars("<<result_left_logo>>"), $image_path_1, $html_content);
        }
        if (isset($result_trust->right_logo)) {
            $image_path2 = "/storage/result/right_logo/" . $result_trust->right_logo ?? '';
            $image_path_2 = '<img src="' . $image_path2 . '" alt="SCHOOL RIGHT LOGO" style="height: 50px !important;">';
            $html_content = str_replace(htmlspecialchars("<<result_right_logo>>"), $image_path_2, $html_content);
        }
        $display_year = $syear . "-" . ($syear + 1);

        $student_image_path1 = "/storage/student/" . $value['image'];
        $student_image_path = '<img class="logo" src="' . $student_image_path1 . '" alt="Student Logo" style="height: 50px !important;">';


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

        $teacher_name = DB::table('class_teacher as ct')->join('tbluser as us', 'ct.teacher_id', '=', 'us.id')->selectRaw('ct.standard_id,ct.division_id,ct.teacher_id,concat_ws(" ",us.first_name,us.last_name) as teacher_name')->where(['ct.syear' => $syear, 'ct.sub_institute_id' => $sub_institute_id, 'ct.standard_id' => $value['standard_id'], 'ct.division_id' => $value['section_id']])->first();
           // for teachers signature standard_wise
        $result_teacher = $this->getExamMasterSettigs($standard_id);
        if (!empty($result_teacher)) {
            $teacher_sign = '';
            if (strpos($html_content, htmlspecialchars('<<scholastic_marks_hills>>')) !== false || strpos($html_content, htmlspecialchars('<<scholastic_marks_hills_upper>>')) !== false) {
                $teacher_sign = $teacher_name->teacher_name;
            } else {
                if (isset($result_teacher['teacher_sign'])) {
                    $teacher_sign = '<img src="/storage/result/teacher_sign/' . $result_teacher['teacher_sign'] . '" alt="teacher_sign" style="height: 50px !important;">';
                }
            }
            $principal_sign = '<img src="/storage/result/principle_sign/' . $result_teacher['principal_sign'] . '" alt="principal_sign" style="height: 50px !important;">';
            $director_signatiure = '<img src="/storage/result/director_sign/' . $result_teacher['director_signatiure'] . '" alt="director_signatiure" style="height: 50px !important;">';

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
        //Start Bonafide certificate Tags
        $html_content = str_replace(htmlspecialchars("<<academic_years>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_image_value>>"), $student_image_path, $html_content);
        $html_content = str_replace(
            htmlspecialchars("<<student_id>>"),
            strtoupper($value['id']),
            $html_content
        );
        $html_content = str_replace(htmlspecialchars("<<student_name_value>>"), strtoupper($value['student_full_name']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<short_standard_name>>"), strtoupper($value['short_standard_name']), $html_content);
        $html_content = str_replace(
            htmlspecialchars("<<student_enrollment_value>>"),
            $value['enrollment_no'],
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<student_roll_no_value>>"),
            $value['roll_no'],
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<student_standard_value>>"),
            $value['standard_name'],
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<student_division_value>>"),
            $value['division_name'],
            $html_content
        );
        $html_content = str_replace(htmlspecialchars("<<student_year_value>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_mobile_value>>"), $value['mobile'], $html_content);
        $html_content = str_replace(
            htmlspecialchars("<<student_dob_value>>"),
            date('d-m-Y', strtotime($value['dob'])),
            $html_content
        );
        $html_content = str_replace(htmlspecialchars("<<current_date>>"), date('d-M-Y'), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_word_value>>"), $date_in_word, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dise_uid_value>>"), $value['dise_uid'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<his_her_value>>"), $his_her, $html_content);
        $html_content = str_replace(htmlspecialchars("<<he_she_value>>"), $he_she, $html_content);

        $html_content = str_replace(
            htmlspecialchars("<<place_of_birth_value>>"),
            strtoupper($value['place_of_birth']),
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<father_name_value>>"),
            strtoupper($value['father_name']),
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<mother_name_value>>"),
            strtoupper($value['mother_name']),
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<religion_name_value>>"),
            strtoupper($value['religion_name']),
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<caste_name_value>>"),
            strtoupper($value['caste_name']),
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<subcast_value>>"),
            strtoupper($value['subcast']),
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<date_of_first_admission_value>>"),
            strtoupper($value['date_of_first_admission']),
            $html_content
        );
        $html_content = str_replace(
            htmlspecialchars("<<student_uniqueid_value>>"),
            strtoupper($value['unique_id']),
            $html_content
        );
         
        // for hills high tags
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_hills>>')) !== false) {
            $main_result = $this->get_scholastic_hills($standard_id, $value['id'], $format, 'primary');
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_hills>>"), $main_result['scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['teacher_remark'], $html_content);
        } else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_hills_upper>>')) !== false) {
            $main_result = $this->get_scholastic_hills($standard_id, $value['id'], $format, 'upper');
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_hills_upper>>"), $main_result['scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $main_result['teacher_remark'], $html_content);
        }

        if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks_mmis>>')) !== false) {
            $co_result = $this->get_co_scholastic_mmis($standard_id, $value['id'], $format, "primary");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_mmis>>"), $co_result['co_scholastic'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<optional_marks_mmiss>>')) !== false) {
            $co_result = $this->get_co_scholastic_mmis($standard_id, $value['id'], $format, "primary");
            $html_content = str_replace(htmlspecialchars("<<optional_marks_mmiss>>"), $co_result['optional'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks_hills>>')) !== false) {
            $co_result = $this->get_co_scholastic_hills($standard_id, $value['id'], $format, "primary");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_hills>>"), $co_result['co_scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<other_tags_hills>>"), $co_result['other_tags'], $html_content);
        } else if (strpos($html_content, htmlspecialchars('<<co_scholastic_marks_hills_upper>>')) !== false) {
            $co_result = $this->get_co_scholastic_hills($standard_id, $value['id'], $format, "upper");
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks_hills_upper>>"), $co_result['co_scholastic'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<other_tags_hills>>"), $co_result['other_tags'], $html_content);
        }
        // student Result
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_no_zero>>')) !== false) {
            $main_result = $this->get_scholastic($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_no_zero>>"), $main_result['table'], $html_content);
        } else if (strpos($html_content, htmlspecialchars('<<scholastic_marks_single_zero>>')) !== false) {
            $main_result = $this->get_scholastic($standard_id, $value['id'], $format, "signle_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_single_zero>>"), $main_result['table'], $html_content);
        } else {
            $main_result = $this->get_scholastic($standard_id, $value['id'], $format, "double_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks>>"), $main_result['table'], $html_content);
        }
        if (strpos($html_content, htmlspecialchars('<<scholastic_marks_mmis>>')) !== false) {
            $main_result = $this->get_scholastic_mmis($standard_id, $value['id'], $format, "no_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_marks_mmis>>"), $main_result['table'], $html_content);
        }

        if (strpos($html_content, htmlspecialchars('<<scholastic_grade_marks>>')) !== false) {
            $co_result = $this->get_co_scholastic($standard_id, $value['id'], $format, "double_zero");
            $html_content = str_replace(htmlspecialchars("<<scholastic_grade_marks>>"), $co_result['grade_range'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<co_scholastic_grade_marks>>"), $co_result['co_scholastic'], $html_content);
        }
        $co_result = $this->get_co_scholastic($standard_id, $value['id'], $format, "double_zero");
        $html_content = str_replace(htmlspecialchars("<<co_scholastic_marks>>"), $co_result['scholastic'], $html_content); 
        // attendance
        if (strpos($html_content, htmlspecialchars('<<total_attendance_simple>>')) !== false) {
            $atten = $this->get_attendance($standard_id, $value['id'], $format, "simple");
            $html_content = str_replace(htmlspecialchars("<<total_attendance_simple>>"), $atten['attendance'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<class_teacher_remark_simple>>"), $atten['remark'], $html_content);
        }
        $atten = $this->get_attendance($standard_id, $value['id'], $format, "total_attendance");
        $html_content = str_replace(htmlspecialchars("<<total_attendance>>"), $atten['table'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_teacher_remark>>"), $atten['remark'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_teacher_remark_anual>>"), $atten['anual'], $html_content);
        if (strpos($html_content, htmlspecialchars('<<total_attendance_manual>>')) !== false) {
            $atten = $this->get_attendance($standard_id, $value['id'], $format, "total_attendance_manual");
            $html_content = str_replace(htmlspecialchars("<<total_attendance_manual>>"), $atten['table'], $html_content);
        } else if (strpos($html_content, htmlspecialchars('<<attendance_hills>>')) !== false) {
            $atten = $this->get_attendance($standard_id, $value['id'], $format, "attendance_hills");
            $html_content = str_replace(htmlspecialchars("<<attendance_hills>>"), $atten['table'], $html_content);
        }

        $html_content = str_replace(htmlspecialchars("<<result>>"), strtoupper($main_result['result']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<school_open_date>>"), $reopen_date, $html_content);

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

    public function get_scholastic_mmis($standard_id, $student_id, $format, $digit)
    {
        // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        if ($format == "yearly") {
            $extra_term = "1=1";
            $extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        // get subject
        $get_subject = DB::table("sub_std_map as ssm")->join('subject as sub', 'ssm.subject_id', '=', 'sub.id')->selectRaw('ssm.id as map_id,sub.id as subject_id,ssm.display_name as subject_name,ssm.elective_subject,ssm.allow_grades')->where(['ssm.sub_institute_id' => $sub_institute_id, 'ssm.standard_id' => $standard_id, 'allow_grades' => "Yes"])->get()->toArray();
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
         
        // get exam name
        $exam_name = DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->whereRaw($extra_exam)->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])
            ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id')->get()->toArray();

        $exam_title = DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->whereRaw($extra_exam)->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])
            ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id')->groupByRaw('exam_id,term_id')->get()->toArray();
        // get all mark 
        $exam_marks = DB::table('result_marks as rce')->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.student_id' => $student_id])->get()->toArray();

        // scholastic table started 
       $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style="background:black;color:white"><b>Scholastic Areas:</b></th>';
        $col = 1;
        $total_term_marks = [];
        $total_sub_marks = [];
        $total_weightage = $overall_total  = $all_colspan = 0;
        $colspan = 2;
        // get both term name and total marks per subject 
        foreach ($term_name as $keys => $terms) {
            $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, $total_weightage) {
                $total_weightage += $title->weightage;
                return $title->term_id == $terms->term_id;
            });
            $colspan = 1;
            $table .= '<th colspan="' . (count($term_exam_titles) + $colspan) . '" style="text-align:center;background:black;color:white"><b>' . $terms->title . '</b></th>';
            // Initialize the total marks for each term to zero
            $total_term_marks[$terms->term_id] = 0;
            $total_sub_marks[$terms->term_id] = 0;
            $all_colspan += count($term_exam_titles);
        }
        $table .= '<th colspan="2"  class="data_center" style="background:black;color:white"><b>Total</b></th>';
        $table .= '</tr>
        <tr>
            <th><b>Subject</b></th>';
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
        // Store the total marks for each term
            $table .= '<th class="data_center"><b>Marks Obtained <br>' . $mark_tot . ' </b></th>';
            $overall_total += $total_mark;
        }
        //total marks of both term headings     
        $table .= '<th class="data_center"><b>Total Marks <br>Obtained (' . $overall_total . ')</b></th><th><b>Grade</b></th>';
        $table .= '</tr>
        </thead>
        <tbody>';
        $tot_ob_mark = 0;
        $tot_sub_mark = 0;
        $get_all_ob_mark = 0;
        $get_all_tot_mark = 0;
        // get all subject name 
        foreach ($get_subject as $val) {
            $both_term_ob_mark = 0;
            $table .= '<tr>
            <td>' . $val->subject_name . '</td>';
            // get term wise eam and marks 
            foreach ($term_name as $keys => $terms) {
                $obtained_marks = [];
                $to_marks = [];
                $to_weight = [];
                $title_exam = []; 
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
                                if ($marks->points == "0.00" || $marks->points == "") {
                                    $ab_ex_na = $marks->is_absent;
                                    if ($marks->is_absent == '') {
                                        $ab_ex_na = 0;
                                    }
                                    $obtained_marks[$title->exam_id][] = $ab_ex_na;
                                     $to_marks[$title->exam_id][] = $title->points;
                                    $to_weight[$title->exam_id] = $title->weightage;

                                } else {
                                    $ob_mark = $marks->points;
                                          // store marks in array to get best of 2 
                                    $obtained_marks[$title->exam_id][] = $ob_mark;
                                    $to_marks[$title->exam_id][] = $title->points;
                                    $to_weight[$title->exam_id] = $title->weightage;

                                    $foundMarks = true;
                                }
                                break;
                            }
                        }
                    }
                }

                $ob_main_mark = 0;
                // echo "<pre>";print_r($obtained_marks);
                // for best of 2 exam wise 
                if (!empty($obtained_marks)) {
                    foreach ($obtained_marks as $exam_id => $marksArray) {
                        $t_m = 0;
                        $w_m = isset($to_weight[$exam_id]) ? $to_weight[$exam_id] : 0; // Check if the key exists

                        foreach ($marksArray as $index => $value) {
                            if (isset($to_marks[$exam_id][$index])) {
                                $t_m += $to_marks[$exam_id][$index];
                            }
                        }
                        $obtained_mark_sum = array_sum($marksArray);
                        if ($t_m !== 0) {
                            $ob_main_mark += (($obtained_mark_sum / $t_m) * $w_m);
                        } else {
                            $ob_main_mark += 0;
                        }
                        $convert_mark = (($obtained_mark_sum / $t_m) * $w_m);
                        $table .= '<td class="data_center ' . $exam_id . '">' . number_format($convert_mark,2) . '</td>';
                    }
                } else {
                    // if marks not found 
                    foreach ($title_exam as $exam_id => $marksArray) {
                        $table .= '<td class="data_center no_mark">0.00</td>';
                    }

                }

                   $obtained_mark_formatted = number_format($ob_main_mark, 2);
                
                $table .= '<td  class="data_center all_mark">' . $obtained_mark_formatted . '</td>';
                $both_term_ob_mark += $obtained_mark_formatted;
            // Update the total marks for the current term
                $total_term_marks[$terms->term_id] += $ob_main_mark;
                $total_sub_marks[$terms->term_id] += $total_mark;
                $grade_arr = $this->getGradeScale($standard_id, '');

            }
            $grade_arr_mmis = $this->getGradeScale($standard_id, '');
            $table .= '<td  class="data_center tot_of_both">' . number_format($both_term_ob_mark, 2) . '</td><td  class="data_center grade_of_both">' . $this->getGrade($grade_arr_mmis, $overall_total, $both_term_ob_mark) . '</td>';
            $get_all_ob_mark += $both_term_ob_mark;
            $get_all_tot_mark += $overall_total;

            $table .= '</tr>';
        }
        $table .= '<tr>';
        $table_per = $rep_val = '';
        $table_all = '';
        $ov_ob_mark = $ov_sub_mark = 0;
        $ov_ob_mark2 = $ov_sub_mark2 = 0;
        $result = "Pass";
        $table .= '<tr><td  class="data_center"><b>Percentage</b></td><td colspan=' . ($all_colspan + 4) . '><b>' . $per = $this->getPer($get_all_ob_mark, $get_all_tot_mark) . '%</b></td></tr>';
        $curr_std = DB::table('standard')->where('id', $standard_id)->first();
        $next_std = DB::table('standard')->where('id', $curr_std->next_standard_id)->first();
        if ($per > 33) {
            $result = 'Passed & Promoted to ' . $next_std->name;
        } else {
            $result = "fail";
        }
        
    // Calculate the total marks for each term
        $res['result'] = $result;
        $table .= '</tr></tbody></table>';
        $res['table'] = $table;
        return $res;

    }
    public function get_scholastic($standard_id, $student_id, $format, $digit)
    {

        // echo "<pre>";print_r($student_id);exit;
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        // sub_institute want foramt like lions 
        $format_sub_different = [61, 195];

        if ($format == "yearly") {
            $extra_term = "1=1";
            $extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $extra_exam = "rce.term_id = " . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        // get subject
        $get_subject = DB::table("sub_std_map as ssm")->join('subject as sub', 'ssm.subject_id', '=', 'sub.id')->selectRaw('ssm.id as map_id,sub.id as subject_id,ssm.display_name as subject_name,ssm.elective_subject,ssm.allow_grades')->where(['ssm.sub_institute_id' => $sub_institute_id, 'ssm.standard_id' => $standard_id, 'allow_grades' => "Yes"])->get()->toArray();
       // Filter the elective subjects based on the condition
        if ($sub_institute_id != 47) {
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
        } else {
            $get_subject = array_filter($get_subject, function ($value) use ($student_id, $syear) {
                if ($value->elective_subject == 'Yes') {
                    $check_optional_subject_with_student = DB::table('student_optional_subject')
                        ->where('student_id', $student_id)
                        ->where('subject_id', $value->subject_id)
                        ->where('syear', $syear)
                        ->count();

                    return $check_optional_subject_with_student < 0;
                }
                return true;
            });
        }
  
        // get exam name
        $exam_name = DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->whereRaw($extra_exam)->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])
            ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id')->get()->toArray();

        $exam_title = DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->whereRaw($extra_exam)->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])
            ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id')->groupByRaw('exam_id,term_id')->get()->toArray();

        $exam_marks = DB::table('result_marks as rce')->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.student_id' => $student_id])->get()->toArray();
        $style = '';
        $heading = 'Scholastic Areas:';
        //only for mmis
        if ($sub_institute_id == 47) {
            $style = "background:black;color:white";
            $heading = 'Part <br> 1-AScholastic<br>Areas:';
        }
        $table = '<style>.data_center{text-align:center !important;}</style><table class="aca-year"  style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
        <thead>
            <tr>
                <th style=' . $style . '><b>' . $heading . '</b></th>';
        $col = 1;
        $total_term_marks = [];
        $total_sub_marks = [];
        $total_weightage = $overall_total = $total_weightage_main = $all_colspan = 0;
        $colspan = 2;
        foreach ($term_name as $keys => $terms) {
            $term_exam_titles = array_filter($exam_title, function ($title) use ($terms, $total_weightage) {
                $total_weightage += $title->weightage;
                return $title->term_id == $terms->term_id;
            });
            //only for mmis
            if ($sub_institute_id == 47) {
                $total_weightage_main = '(50)';
                $colspan = 1;
            }
            $table .= '<th colspan="' . (count($term_exam_titles) + $colspan) . '" style="text-align:center;' . $style . '"><b>' . $terms->title . $total_weightage_main . '</b></th>';
            // Initialize the total marks for each term to zero
            $total_term_marks[$terms->term_id] = 0;
            $total_sub_marks[$terms->term_id] = 0;
            $all_colspan += count($term_exam_titles);
        }
        //only for mmis        
        if ($sub_institute_id != 61) {
            $table .= '<th colspan="2" style=' . $style . '><b>Total</b></th>';
        }
        $table .= '</tr>
        <tr>
            <th><b>Subject</b></th>';
        $weigthage = '';
        foreach ($term_name as $keys => $terms) {
            $total_mark = 0;
            foreach ($exam_title as $key => $title) {
                if ($sub_institute_id != 61) {
                    $weigthage = '(' . $title->weightage . ')';
                }
                $exam_head = $title->title;
                if ($sub_institute_id == 47) {
                    $exam_head = $title->ExamTitle;
                }
                if ($terms->term_id == $title->term_id) {
                    $table .= '<th class="data_center"><b>' . $exam_head . '<br>' . $weigthage . '</b></th>';
                    if ($sub_institute_id != 61) {
                        $total_mark += $title->weightage;
                    } else {
                        $total_mark += $title->points;
                    }
                }
            }
            $mark_tot = '';
            if ($sub_institute_id != 61) {
                $mark_tot = '(' . $total_mark . ')';
            }
        // Store the total marks for each term
            $table .= '<th class="data_center"><b>Marks Obtained <br>' . $mark_tot . ' </b></th>';
            $overall_total += $total_mark;
            if ($sub_institute_id != 47) {
                $table .= '<th class="data_center"><b>Grade (' . $terms->title . ')</b></th>';
            }
        }
        //only for mmis        
        if ($sub_institute_id != 61) {
            $table .= '<th class="data_center"><b>Total Marks <br>Obtained (' . $overall_total . ')</b></th><th><b>Grade</b></th>';
        }
        $table .= '</tr>
        </thead>
        <tbody>';
        $tot_ob_mark = 0;
        $tot_sub_mark = 0;
        $get_all_ob_mark = 0;
        $get_all_tot_mark = 0;
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
                                    $table .= '<td  class="data_center">' . $ab_ex_na . '</td>';
                                } else {
                                    if ($digit == "no_zero") {
                                        $ob_mark = intval($marks->points);
                                    } else if ($digit == "signle_zero") {
                                        $ob_mark = number_format(round($marks->points, 1), 1);
                                    } else {
                                        $ob_mark = $marks->points;
                                        // connvert marks from weightage
                                        if ($sub_institute_id != 61) {
                                            $ob_mark = number_format((($ob_mark / $title->points) * $title->weightage), 2);
                                        }
                                    }
                        // $arr[$title->exam_id] = $marks->points;
                        // $sum_mark=0;

                                    $obtained_mark += $ob_mark;
                                 // echo "<pre>";print_r($arr);
                                    $table .= '<td  class="data_center ' . $title->exam_id . '">' . $ob_mark . '</td>';
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
                    $table .= '<td  class="data_center">' . $obtained_mark_formatted . '</td>';
                }
                $both_term_ob_mark += $obtained_mark_formatted;
            // Update the total marks for the current term
                $total_term_marks[$terms->term_id] += $obtained_mark;
                $total_sub_marks[$terms->term_id] += $total_mark;
                $grade_arr = $this->getGradeScale($standard_id, '');
                if ($sub_institute_id != 47) {
                    $table .= '<td  class="data_center">' . $this->getGrade($grade_arr, $total_mark, $obtained_mark_formatted) . '</td>';
                }
            }
            //only for mmis            
            if ($sub_institute_id != 61) {
                $grade_arr_mmis = $this->getGradeScale($standard_id, '');
                $table .= '<td  class="data_center">' . number_format($both_term_ob_mark, 2) . '</td><td  class="data_center">' . $this->getGrade($grade_arr_mmis, $overall_total, $both_term_ob_mark) . '</td>';
                $get_all_ob_mark += $both_term_ob_mark;
                $get_all_tot_mark += $overall_total;
            }
            $table .= '</tr>';
        }
        $table .= '<tr>';
        $table_per = $rep_val = '';
        $table_all = '';
        $ov_ob_mark = $ov_sub_mark = 0;
        $ov_ob_mark2 = $ov_sub_mark2 = 0;
        $result = "Pass";
        //only for mmis        
        if ($sub_institute_id == 47) {
            $table .= '<tr><td  class="data_center"><b>Percentage</b></td><td colspan=' . ($all_colspan + 4) . '><b>' . $per = $this->getPer($get_all_ob_mark, $get_all_tot_mark) . '%</b></td></tr>';
            $curr_std = DB::table('standard')->where('id', $standard_id)->first();
            $next_std = DB::table('standard')->where('id', $curr_std->next_standard_id)->first();
            if ($per > 33) {
                $result = 'Passed & Promoted to ' . $next_std->name;
            } else {
                $result = "fail";
            }
        }
        
    // Calculate the total marks for each term
        if ($sub_institute_id != 47) {
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
            if ($sub_institute_id == 195) {
                $table .= '<td rowspan="3" class="data_center">' . $all_per . '</td><td rowspan="3" class="data_center">' . $all_grade . '</td>';
            }
    
    // exit;
            $table_all = str_replace(htmlspecialchars("<<per>>"), $all_per, $table_all);
            $table_all = str_replace(htmlspecialchars("<<grade>>"), $all_grade, $table_all);
            $table .= '<tr>' . $table_per . '</tr>';
            if ($sub_institute_id != 195) {
                $table .= '<tr>' . $table_all . '</tr>';
            }

            $res['remark'] = \App\Helpers\getGradeComment($grade_arr, 100, $overall_per) ?? '';
        }
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

        if ($format == "yearly") {
            $extra_term = "1=1";
            $extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        $responce_arr = array();

        $sql_mark_grade = "select * from result_co_scholastic where sub_institute_id = " . $sub_institute_id . " and " . $extra_term . " ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));

        $co_grade_range = DB::table('result_co_scholatic_range')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
              // Define your query using the query builder
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
            }
        }
        if (in_array($sub_institute_id, $format_sub_different)) {
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
                $table .= '<th style="text-align:center"><b>' . $terms->title . '</b></th>';
            }
            $table .= '</tr>';
            $table .= '</tr></thead><tbody>';
            $val_grade = ["N.A.", "NA", "E.X.", "EX", "A.B.", "AB"];
            $maxCount = 0;
            foreach ($responce_arr as $sub => $term_data) {
                $table .= '<tr><td>' . $sub . '</td>';
                foreach ($term_name as $key => $terms) {
                    if (isset($term_data[$terms->term_id])) {
                        $table .= '<td>' . $term_data[$terms->term_id] . '</td>';
                    } else {
                        $table .= '<td>-</td>';
                    }
                }
                $table .= '</tr>';
            }
            $table .= '</tbody>
    </table>';
        } 
        // scholastic grade range 
        $get_grade_ranges = $this->getGradeRange($standard_id);
        $table_range = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
        <tr>
        <th class="data_center"  style="width:312px"><b>MARKS RANGE</b></th>';
        if (!empty($get_grade_ranges)) {
            foreach ($get_grade_ranges['mark_range']['SCHOLASTIC_MARKS_RANGE'] as $key => $value) {
                $table_range .= '<td class="data_center">' . $value . '</td>';
            }
        }
        $table_range .= '</tr>
        <tr>
        <th class="data_center" style="width:312px"><b>GRADE</b></th>';
        if (!empty($get_grade_ranges)) {
            foreach ($get_grade_ranges['mark_range']['GRADE'] as $key => $value) {
                $table_range .= '<td class="data_center">' . $value . '</td>';
            }
        }
        $table_range .= '<tr>
        </thead></table>';

        //co grade range
        if (!empty($co_grade_range)) {
            $co_table = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
            <thead>
            <tr>
            <th class="data_center"  style="width:312px"><b>MARKS RANGE</b></th>';
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
        $res['scholastic'] = $table ?? 'No Co-Scholastic for scholastic Found';
        $res['grade_range'] = $table_range ?? 'No Co-Scholastic for grade Found';
        $res['co_scholastic'] = $co_table ?? 'No Co-Scholastic Found';
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

    public static function getGrade($grade_arr, $total_mark, $total_gain_mark, $type_co = '')
    {

        if ($total_mark == 0) {
            return "-";
        }
        //echo $total_gain_mark."/".$total_mark."<br/>";
        $per = round((100 * $total_gain_mark) / $total_mark, 0);

        foreach ($grade_arr as $id => $data) {
            if (!isset($grade) && $type_co != '') {
                if ($per >= $data->breakoff) {
                    $grade = $data->title;
                }
            } else if (!isset($grade)) {
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
        // dd($student_id);
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        if ($format == "yearly") {
            $extra_term = "1=1";
        } else {
            $extra_term = "atd.term_id = " . $format;
        }
        $ret_data = DB::table('result_student_attendance_master as atd')
            ->join('result_working_day_master as wrkd', function ($join) use ($standard_id, $sub_institute_id) {
                $join->on('wrkd.standard', '=', 'atd.standard')
                    ->on('wrkd.sub_institute_id', '=', 'atd.sub_institute_id');
            })
            ->select('student_id', DB::raw('SUM(total_working_day) as total_working_day'), DB::raw('SUM(attendance) as attendance'), DB::raw('group_concat("",teacher_remark) as teacher_remark'))
            ->where('atd.standard', $standard_id)
            ->where('atd.sub_institute_id', $sub_institute_id)
            ->where('atd.student_id', $student_id)
            ->where('atd.syear', $syear)
            ->whereRaw($extra_term)
            ->groupBy('atd.student_id')
            ->first();
    // echo "<pre>";print_r($ret_data);exit;
        $sim_tr = '';
        $sim_att = 0;
        $sim_twd = 0;
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


        $remark = explode(',', $ret_data->teacher_remark ?? '');
        $res['remark'] = $remark[0] ?? '';
        $res['anual'] = $remark[1] ?? '';

        if ($type == "attendance_hills") {
            $get_term = DB::table('academic_year')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'sort_order' => '1'])->first();
            $post_start_date = $get_term->post_start_date;
            $post_end_date = $get_term->post_end_date;
            $post_start_date_final = $get_term->post_start_date;
            $post_end_date_final = $get_term->post_end_date;

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
    // Convert the result into an associative array
            $attarray = $attarray->pluck('present_day', 'id')->all();

            if (isset($attarray[$student_id])) {
                $table = $attarray[$student_id] . '/' . $attTotDays;
            } else {
            // Handle the case where $attarray[$student_id] is not set or not a string
                $table = '-/' . $attTotDays; // Replace with your desired handling
            }
    //  echo "<pre>";print_r($format);exit;
        } else {
            $table = '<table class="aca-year" style="width: 100%;height:fit-content;margin-top:8%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <tbody>
        <tr>
            <th colspan="2" style="text-align: left;">
                <b>Total Attendance</b></th>
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
    // dd($student_id);
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        if ($format == "yearly") {
            $extra_term = "1=1";
            $att_term = "1=1";
            $extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $att_term = "atd.term_id = 1";//" . $format; added by rajesh 28-09-2023 for Term-1 only classteacher remarks display
            $extra_exam = "rce.term_id = " . $format;
        }

        if ($academic_type == "primary") {
            $sort_order = "rem.SortOrder";
        } else {
            $sort_order = "rce.sort_order";
        }
            // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        $get_subject = DB::table("sub_std_map as ssm")
            ->join('subject as sub', 'ssm.subject_id', '=', 'sub.id')
            ->selectRaw('ssm.id as map_id, sub.id as subject_id, sub.subject_name, ssm.elective_subject')
            ->where(['ssm.sub_institute_id' => $sub_institute_id, 'ssm.standard_id' => $standard_id, 'allow_grades' => "Yes"])
            ->orderBy('ssm.sort_order')
            ->get()
            ->toArray();
    
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
    
            //get exam name termwise
        $exam_title = DB::table('result_create_exam as rce')->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')->whereRaw($extra_exam)->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.syear' => $syear, 'rce.standard_id' => $standard_id])
            ->selectRaw('rce.id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point')->orderBy($sort_order, 'ASC')->get()->toArray();

        $exam_marks = DB::table('result_marks as rce')->where(['rce.sub_institute_id' => $sub_institute_id, 'rce.student_id' => $student_id])->get()->toArray();
            // dd($exam_marks);
        $head = count($exam_title);

        $table = '<style>.data_center{text-align:center !important}</style>
        <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0"  border="1">
            <thead>
                <tr>
                    <th style="background:black;color:#fff"><b>Scholastic Areas:</b></th>';
        $col = 1;
        $total_term_marks = [];
        $total_sub_marks = [];
        $printedExamTitles = []; // Keep track of printed exam titles for each term

        foreach ($term_name as $keys => $terms) {
            $term_exam_titles = array_filter($exam_title, function ($title) use ($terms) {
                return $title->term_id == $terms->term_id;
            });
            if ($academic_type == "upper") {
                $table .= '<th colspan="' . (count($term_exam_titles) + 2) . '" style="text-align:center;background:black;color:#fff"><b>Progress Report Card</b></th>';
            } else {
                $table .= '<th colspan="' . (count($term_exam_titles) + 2) . '" style="text-align:center;background:black;color:#fff"><b>' . $terms->title . ' (50)</b></th>';
            }
           
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
                    $currentTermExamTitles[] = $title;
                }
            }
            if (!empty($currentTermExamTitles)) {
                foreach ($currentTermExamTitles as $title) {
                    if ($academic_type == "primary") {
                        $main_title = $title->ExamTitle;
                        $all_points = $title->weightage;
                    } else {
                        $main_title = $title->title;
                        $all_points = $title->con_point; //update $title->points to $title->con_point by rajesh
                    }
                    if (!in_array($main_title, $printedExamTitles)) {
                        $table .= '<th class="data_center"><b>' . $main_title . '<br>(' . $all_points . ')</b></th>';
                        $printedExamTitles[] = $main_title;
                        $total_mark += $all_points;
                    }
                }
            }
            if ($academic_type == "primary") {
                $table .= '<th class="data_center"><b>Marks Obtained <br> (' . $total_mark . ')</b></th>';
                $table .= '<th class="data_center"><b>Grade</b></th>';
            }
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
                $obtained_mark = number_format(0, 2);
                $ob_mark = 0;
                // Create an array to store the maximum marks for each subject in this term
                $maxMarks = [];
                $weigthage = [];
                $outof = [];

                foreach ($exam_title as $key => $title) {
                    if ($title->subject_id == $val->subject_id && $terms->term_id == $title->term_id) {
                        $foundMarks = false;
                        foreach ($exam_marks as $index => $marks) {
                            if ($title->id == $marks->exam_id) {
                                if ($marks->points == "0.00" || $marks->points == "") {
                                    $ab_ex_na = $marks->is_absent;
                                    //echo $ab_ex_na;die();
                                    if ($marks->is_absent == '') {
                                        $ab_ex_na = number_format(0, 2);
                                    }
                                    $table .= '<td class="data_center">' . $ab_ex_na . '</td>';
                                } else {
                                    $ob_mark = $marks->points;
                                    if ($academic_type == "primary") {
                                        $ob_mark = number_format(($ob_mark / $title->points) * $title->weightage, 2);
                                        $table .= '<td class="data_center">' . $ob_mark . '</td>';
                                        $obtained_mark += $ob_mark;
                                    } else {
                                        $obtained_mark += $ob_mark;
                                        $ob_mark = number_format((($ob_mark / $title->points) * $title->con_point), 2); //added by rajesh
                                        $underline = "";
                                        $title_arr = ['P.T.-1', 'P.T.-2'];
                                        if (in_array($title->title, $title_arr)) {
                                            if ($ob_mark !== '0.00') {
                                                $pt_per = round((($ob_mark / $title->con_point) * 100), 0);
                                            } else {
                                                $pt_per = $ob_mark;
                                            }//update $title->points to $title->con_point by rajesh
                                            // $pt_per = $ob_mark;
                                            if ($pt_per < 33) {
                                                $underline = 'style="text-decoration: underline red 2px;"';
                                            }
                                        }
                                        $table .= '<td ' . $underline . ' class="data_center">' . $ob_mark . '</td>';
                                    }
                                }
                                $foundMarks = true;
                                break;
                            }
                        }
                        if (!$foundMarks) {
                            $table .= '<td class="data_center">0.00</td>';
                        }
                    }
                }

                if ($academic_type == "primary") {
                    $obtained_mark_formatted = $obtained_mark;
                    $table .= '<td class="data_center">' . number_format($obtained_mark_formatted, 2) . '</td>';
                    $grade_arr = $this->getGradeScale($standard_id, '');
                    $table .= '<td class="data_center">' . $this->getGrade($grade_arr, $total_mark, $obtained_mark_formatted) . '</td>';
                }
            }
            $table .= '</tr>';
        }
        // exit;

        $table .= '</tr></tbody>
        </table>';
        $res['scholastic'] = $table;
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
            ->whereRaw($att_term)
            ->first();
        $res['teacher_remark'] = '';
        if (!empty($ret_data)) {
            $res['teacher_remark'] = $ret_data->teacher_remark;
        }
        return $res;
    }

    public function get_co_scholastic_hills($standard_id, $student_id, $format, $academic_type)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        if ($format == "yearly" || $academic_type == "upper") {
            $extra_term = "1=1";
            $extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        $sql_mark_grade = "select * from result_co_scholastic where sub_institute_id = " . $sub_institute_id . " and " . $extra_term . " ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));

        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
                $ret_data = DB::table('result_co_scholastic_marks_entries as comark')
                    ->selectRaw(
                        'comark.student_id,comark.co_scholastic_id,comark.term_id,cop.title as parent_title,co.title as child_title,
                  if(comark.grade=0,comark.points,cograde.title) obtain_grade,co.max_mark'
                    )
                    ->leftjoin('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
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
            }
            $skill_data = [];
            $criteria_data = [];
            $co_data = [];
            $get_grade = DB::table('result_co_scholatic_range')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get();
            // echo "<pre>";print_r($ret_data);exit;
            foreach ($ret_data as $key => $value) {
                if ($value->parent_title == "SKILL OBSERVATION") {
                    $skill_data[] = $value;
                }
                if ($value->parent_title == "CRITERIA") {
                    $criteria_data[] = $value;
                }
                if ($value->parent_title != "CRITERIA" && $value->parent_title != "SKILL OBSERVATION") {
                    $per = $value->obtain_grade;
                    if (!empty($get_grade) && $per != 0 && $per != '') {
                        $value->obtain_grade = $this->getGrade($get_grade, $value->max_mark, $per, "co_scholastic");
                    } else {
                        $value->obtain_grade = '-';
                    }
                    $co_data[] = $value;
                }
            }
        }

        if ($academic_type == "upper") {
            $term_name = "Grade";
            $flex = '';
        } else {
            $term_name = $term_name[0]->title ?? 'Grade';
            $flex = 'display:flex;flex-wrap:wrap';
        }
        // get other tag data
        $co_scholastic = '<div style=' . $flex . ' class="co_scho_hills">
        <div style="width:50%;">
            <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                <thead>
                    <tr>
                        <th><b>CO SCHOLASTIC</b></th>
                        <th style="text-align:center"><b>' . $term_name . '</b></th>               
                    </tr>
                </thead>
                <tbody>';
        $counter = 0;
        if (!empty($co_data)) {
            foreach ($co_data as $key => $value) {
                if ($counter < 6) {
                    $co_scholastic .= '<tr><td>' . $value->child_title . '</td><td class="data_center">' . $value->obtain_grade . '</td></tr>';
                } else {
                    if ($counter === 6) {
                        $co_scholastic .= '</tbody></table></div>';
                        $co_scholastic .= '<div style="width:50%;">
                        <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                            <thead>
                                <tr>
                                    <th><b>CO SCHOLASTIC</b></th>
                                    <th class="data_center"><b>' . $term_name . '</b></th>               
                                </tr>
                            </thead>
                            <tbody>';
                    }
                    $co_scholastic .= '<tr><td>' . $value->child_title . '</td><td class="data_center">' . $value->obtain_grade . '</td></tr>';
                }
                $counter++;
            }
        }

        $co_scholastic .= '</tbody></table></div></div>';
        // get other tag data
        $other_table = '<div style="display:flex;flex-wrap:wrap"  class="co_scho_hills">
            <div style="width:50%;">
            <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
            <thead>
                <tr>
                <th><b>CRITERIA</b></th>
                <th class="data_center"><b>' . $term_name . '</b></th>               
                </tr>
            </thead>';
        if (!empty($criteria_data)) {
            foreach ($criteria_data as $key => $value) {
                $other_table .= '<tr><td>' . $value->child_title . '</td><td class="data_center">' . $value->obtain_grade . '</td></tr>';
            }
        }
        $other_table .= '<tboady></tboady>
            </table></div>
            <div style="width:50%;">
            <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
            <thead>
                <tr>
                <th><b>SKILL OBSERVATION</b></th>
                <th class="data_center"><b>' . $term_name . '</b></th>               
                </tr>
            </thead>';
        if (!empty($skill_data)) {
            foreach ($skill_data as $key => $value) {
                $other_table .= '<tr><td>' . $value->child_title . '</td><td class="data_center">' . $value->obtain_grade . '</td></tr>';
            }
        }
        $other_table .= '<tboady></tboady>
            </table>
            </div>            
        </div>';
        $res['co_scholastic'] = $co_scholastic;
        $res['other_tags'] = $other_table;
        return $res;
    }

    //co_schalstic area for mmis
    public function get_co_scholastic_mmis($standard_id, $student_id, $format, $grade_type)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        if ($format == "yearly" || $grade_type == "upper") {
            $extra_term = "1=1";
            $extra_exam = "1=1";
        } else {
            $extra_term = "term_id = " . $format;
            $extra_exam = 'comark.term_id=' . $format;
        }
        // get term_name 
        $term_name = DB::table('academic_year')->whereRaw($extra_term)->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();
        $sql_mark_grade = "select * from result_co_scholastic where sub_institute_id = " . $sub_institute_id . " and " . $extra_term . " ";
        $ret_mark_grade = DB::select(DB::raw($sql_mark_grade));

        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
                $ret_data = DB::table('result_co_scholastic_marks_entries as comark')
                    ->selectRaw(
                        'comark.student_id,comark.co_scholastic_id,comark.term_id,cop.title as parent_title,co.title as child_title,
                  if(comark.grade=0,comark.points,cograde.title) obtain_grade,co.max_mark'
                    )
                    ->leftjoin('result_co_scholastic_grades as cograde', 'cograde.id', '=', 'comark.grade')
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
            }
        }
        $get_grade = DB::table('result_co_scholatic_range')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get();
        $co_data = [];
        $counter = 1;
        if (isset($ret_data)) {
            foreach ($ret_data as $item) {
                $obtainGrade = $item->obtain_grade;
            // Check if the obtain_grade contains digits
                if (preg_match('/\d/', $obtainGrade)) {
                    $item->obtain_grade = $obtainGrade;
                    $item->obtain_grade = $this->getGrade($get_grade, $item->max_mark, $obtainGrade, "co_scholastic");

                } else {
                    $item->obtain_grade = $obtainGrade;
                }
                $co_data[] = $item;
            }
        }
        $co_scholastic = '
            <table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
                <thead>
                    <tr>
                        <th  class="data_center"><b>Co-Scholastic Areas: [on a 3-point (A-C) Grading
                        Scale]
                        </b></th>';
        foreach ($term_name as $keys => $terms) {
            $co_scholastic .= '<th style="text-align:center"><b>' . $terms->title . '</b></th>';
            $term_ids[] = $terms->term_id;
        }
        '</tr>
                </thead>
                <tbody>';
        $counter = 0;
        if (!empty($co_data)) {

            foreach ($co_data as $key => $value) {
                $co_scholastic .= '<tr>';
                foreach ($term_name as $keys => $terms) {
                    $found = false;
                    if ($terms->term_id == $value->term_id) {
                        $co_scholastic .= '<td>' . $value->child_title . '</td><td class="data_center">' . $value->obtain_grade . '</td>';
                        $found = true;
                    }
                }
                if (!$found) {
                    $co_scholastic .= '<td>-</td>';
                }
                $co_scholastic .= '</tr>';
            }
        }

        $co_scholastic .= '</tbody></table>';

        // echo "<pre>";print_r($co_scholastic);exit;
        $check_optional_subject_with_student = DB::table('student_optional_subject as sos')->join('sub_std_map as ssm', 'sos.subject_id', '=', 'ssm.subject_id')->leftjoin('result_exam_master as rem', 'rem.standard_id', '=', 'ssm.standard_id')
            ->leftjoin('result_create_exam as rce', 'rem.id', '=', 'rce.exam_id')
            ->leftjoin('result_marks as rm', 'rm.exam_id', '=', 'rce.exam_id')
            ->selectRaw('ssm.display_name,sos.subject_id,sos.student_id,ssm.standard_id,rce.id as create_id,rce.title,rce.term_id,rce.standard_id,rem.weightage,rem.ExamTitle,rce.subject_id,rce.points,rce.con_point,rem.Id as ExamId,rce.exam_id,rm.points')
            ->where('sos.student_id', $student_id)
            ->where('sos.sub_institute_id', $sub_institute_id)
            ->where('ssm.standard_id', $standard_id)
            ->where('sos.syear', $syear)
            ->where('ssm.elective_subject', 'Yes')
            ->where('ssm.allow_grades', 'Yes')
            ->groupByRaw('rem.Id,sos.subject_id')
            ->get()->toArray();
            // echo "<pre>";print_r($check_optional_subject_with_student);exit;
        $scho_table = '<table class="aca-year" style="width: 100%;border-collapse:collapse; border:1px solid #e68023;" cellspacing="0" cellpadding="0" border="1">
        <thead>
        <tr>
        <th colspan="3" width="15%" style="text-align: center;">
            <b>Part 1-B-Scholastic Areas::</b></th>
    </tr><tr>  <th width="50%" style="text-center: left;"><b>Optional
    Subject</b></th>';

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
                if (!isset($subjectRows[$subjectName])) {
                    $subjectRows[$subjectName] = [];
                }
                if (in_array($termId, $term_ids)) {
                    $subjectRows[$subjectName][$termId] = [$points, $weigthage];
                }
            }

            foreach ($subjectRows as $subjectName => $termPoints) {
                $scho_table .= '<tr>';
                $scho_table .= '<td>' . $subjectName . '</td>';
                foreach ($term_ids as $term) {
                    $obt_grade = isset($termPoints[$term][0]) ? $termPoints[$term][0] : 0;
                    $max_weightage = isset($termPoints[$term][1]) ? $termPoints[$term][1] : 0;
                    $obt_grade = $this->getGrade($get_grade, $obt_grade, $max_weightage, "co_scholastic");
                    $scho_table .= '<td  class="data_center">' . $obt_grade . '</td>';
                }
                $scho_table .= '</tr>';
            }
        }
        $scho_table .= '</tbody>
</table>';

        $res['co_scholastic'] = $co_scholastic ?? '';
        $res['optional'] = $scho_table ?? '';
        return $res;
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

        foreach ($student_array as $key => $val) {
            $result_data['student_id'] = $val;
            $result_data['term_id'] = $term_id;
            $result_data['grade_id'] = $grade_id;
            $result_data['standard_id'] = $standard_id;
            $result_data['division_id'] = $division_id;
            $result_data['syear'] = $syear;
            $result_data['sub_institute_id'] = $sub_institute_id;
            $result_data['html'] = $request->get('html_' . $val);

            $data = DB::select("SELECT * FROM result_html WHERE student_id = '" . $val . "' AND term_id = '" . $request->get('term_id') . "'
                    AND grade_id = '" . $request->get('grade_id') . "'  AND standard_id = '" . $request->get('standard_id') . "'
                     AND division_id = '" . $request->get('division_id') . "'  AND syear = '" . $request->get('syear') . "'
                     AND sub_institute_id = '" . session()->get('sub_institute_id') . "'
                    ");
            if (count($data) > 0) {
                $html = $request->get('html_' . $val);
                $finalArray['html'] = $html;
                $data = DB::table('result_html')->where(['student_id' => $val, 'term_id' => $term_id, 'grade_id' => $grade_id, 'standard_id' => $standard_id, 'division_id' => $division_id, 'syear' => $syear])->update($finalArray);

            } else {
                DB::table("result_html")->insert($result_data);
            }
        }
        return 1;
    }

}
