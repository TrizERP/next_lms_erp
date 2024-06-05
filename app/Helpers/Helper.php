<?php

namespace App\Helpers;


use App\Models\easy_com\manage_sms_api\manage_sms_api;
use App\Models\fees\fees_title\fees_title;
use App\Models\normClature;
use App\Models\user\tbluserModel;
use App\Models\fees\map_year\map_year;
use App\Models\student\appNotificationModel;
use App\Models\student\tblstudentModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

if (!defined('BEST_OF')) {
    define('BEST_OF', 2);
}

if (!function_exists('is_mobile')) {

    function is_mobile($type, $url = null, $data = null, $redirect_type = "redirect")
    {
        if ($type == "API") {
            if (isset($data["status_code"])) {
                $data["status"] = strtoupper($data["status_code"]);
                unset($data["status_code"]);
            }

            return json_encode($data);
        } else {
            if ($redirect_type == 'redirect') {

                return redirect()->route($url)->with(['data' => $data]);
            } else {
                if ($redirect_type == 'route_with_message') {

                    return route($url)->with(['data' => $data]);
                } else {
                    if ($redirect_type == 'view') {

                        return view($url, ['data' => $data]);
                    }
                }
            }
        }
    }
}
if (!function_exists('ValidateInsertData')) {

    function ValidateInsertData($table, $type = 'insert')
    {

        $files_arr = ["Logo", "Image"];

        $columns = DB::select("SHOW COLUMNS FROM " . $table);

        $required_fields = array();
        $validation_status = true;
        foreach ($columns as $id => $obj) {
            if ($obj->Field == 'Id' || $obj->Field == 'id') {
                continue;
            }
            if (in_array($obj->Field, $files_arr)) {
                if ($type == 'insert') {
                    if ($_FILES[$obj->Field]['error'] != 0) {
                        $validation_status = false;
                    }
                }
            } elseif ($obj->Null == 'NO') {
                $required_fields["$obj->Field"] = 'required';
            }
        }

        if ($validation_status == true) {
            $validator = Validator::make($_REQUEST, $required_fields);
            if ($validator->fails()) {
                $failedRules = $validator->failed();
                echo "validation fails. Parameter Missing";
                exit;
            }
        } else {
            echo "Validation Fails. File Not Found.";
            exit;
        }
    }
}


if (!function_exists('encrypt_url')) {
    function encrypt_url($action, $string)
    {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        //pls set your unique hashing key
        $secret_key = 'muni';
        $secret_iv = 'muni123';
        // hash
        $key = hash('sha256', $secret_key);
        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        //do the encyption given text/string/number
        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if ($action == 'decrypt') {
            //decrypt the given text/string/number
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }
}


if (!function_exists('SearchChain')) {

    function SearchChain($col, $multiple, $listed_drop, $grade_val = "", $std_val = "", $div_val = "")
    {

        // $path = URL::current();
        // preg_match("/[^\/]+$/", $path, $matches);
        // $module_name = $matches[0];

        // $module_array = array(
        //     '1' => 'student_homework',
        //     '2' => 'marks_entry',
        //     '3' => 'dicipline',
        //     '4' => 'lmsExamwise_progress_report',
        //     '5' => 'questionReport',
        //     '6' => 'parent_communication',
        // );

        $path = $_SERVER['HTTP_REFERER'] ?? URL::current();

        if ($path) {
            $parsedUrl = parse_url($path);
            
            if (isset($parsedUrl['path'])) {
                $pathParts = pathinfo($parsedUrl['path']);
                
                if (isset($pathParts['filename'])) {
                    $module_name = $pathParts['filename'];
                }
                if($parsedUrl['path'] == '/lms/question_paper/create' || $parsedUrl['path'] == '/lms/question_paper/search'){
                    $module_name = 'question_paper';
                }
                
                $path = "/student/student_homework/create";
                $keyword = "student_homework";
                
                if (strpos($path, $keyword) !== false) {
                    $module_name = "student_homework";
                }
            }
        }
        $module_array = [
            '1' => 'student_homework',
            '2' => 'marks_entry',
            '3' => 'dicipline',
            '4' => 'lmsExamwise_progress_report',
            '5' => 'questionReport',
            '6' => 'parent_communication',
            '7' => 'question_paper',
            '8' => 'co_scholastic_marks_entry',                                    
        ];

        // menu_ids to get class teacher class only
        if(session()->get('sub_institute_id')==195){
            $menu_ids = [80,102];
        }else{
            // $menu_ids = [80,102,156];
            $menu_ids=[];
        }

        $getClass=DB::table('class_teacher')->whereRaw('sub_institute_id='.session()->get('sub_institute_id').' and teacher_id ='.session()->get('user_id').' and syear="'.session()->get('syear').'"')->groupBy('teacher_id')->first();

        // START 07/09/2021 code for getting standard , grade , division according to timetable wise for homework module
        if (session()->get('user_profile_name') == 'Teacher') {
            $teacher_id = session()->get('user_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            $subject_teacher = DB::table('subject as s')
                ->join('timetable as t', function ($join) {
                    $join->whereRaw('t.subject_id = s.id AND t.sub_institute_id = s.sub_institute_id');
                })->selectRaw('s.id,s.subject_name,t.*')
                ->where('t.teacher_id', $teacher_id)
                ->where('t.syear', $syear)
                ->where('t.sub_institute_id', $sub_institute_id)
                ->groupByRaw('s.id,t.standard_id,t.academic_section_id')
                ->orderBy('s.subject_name')->get()->toArray();

            $subjectTeacherGrdArr = $subjectTeacherStdArr = $subjectTeacherDivArr = array();
            if (count($subject_teacher) > 0) {
                foreach ($subject_teacher as $k => $v) {
                    $subjectTeacherGrdArr[] = $v->academic_section_id;
                    $subjectTeacherStdArr[] = $v->standard_id;
                    $subjectTeacherDivArr[] = $v->division_id;
                }
            }
            Session::put('subjectTeacherGrdArr', $subjectTeacherGrdArr);
            Session::put('subjectTeacherStdArr', $subjectTeacherStdArr);
            Session::put('subjectTeacherDivArr', $subjectTeacherDivArr);
        }
        // END 07/09/2021 code for getting standard , grade , division according to timetable wise for homework module

        $explod_list = explode(',', $listed_drop);
        $grade_name = 'grade';
        $std_name = 'standard';
        $div_name = 'division';
        $batch_section = 'batchsection';

        if ($multiple == 'multiple') {
            $multiple = 'multiple="multiple"';
            $grade_name = 'grade[]';
            $std_name = 'standard[]';
            $div_name = 'division[]';
            $batch_section = 'batchsection[]';
        } else if ($multiple == 'required') {
            $multiple = 'required="required"';
        } else {
            if ($multiple == 'single') {
                $multiple = '';
            }
        }

        $option = "<option value=''>Select</option>";

        $query = DB::table("academic_section");
        $query->where("sub_institute_id", session()->get('sub_institute_id'));
        //START Check for class teacher assigned standards
        $classTeacherGrdArr = session()->get('classTeacherGrdArr');
        if (isset($classTeacherGrdArr) && !in_array($module_name, $module_array)) {
            if (count($classTeacherGrdArr) > 0) {
                $query->whereIn('id', $classTeacherGrdArr);
            } else {
                $query->oRwhere('id', null);
            }
        }
        //  END Check for class teacher assigned standards      

        // added on 17-04-24 by uma, when menu where class teacher can see only their class students
        if(in_array(session()->get('right_menu_id'),$menu_ids) && session()->get('user_profile_name') == 'Teacher'){
            $query->whereIn('id', [$getClass->grade_id ?? 0 ]);
        }else{
        //START Check for subject teacher assigned
            $subjectTeacherGrdArr = session()->get('subjectTeacherGrdArr');
            if (isset($subjectTeacherGrdArr) && (!isset($classTeacherGrdArr) || in_array($module_name, $module_array))) {
                if (count($subjectTeacherGrdArr) > 0) {
                    $query->whereIn('id', $subjectTeacherGrdArr);
                } else {

                    $query->oRwhere('id', null);
                }
            }
        }
        //END Check for subject teacher assigned

        $academic_section = $query->pluck("title", "id");

        $g_id=[];
        foreach ($academic_section as $id => $val) {
            $selected = '';
            if (is_array($grade_val)) {
                if (in_array($id, $grade_val)) {
                    $g_id[]=$id;                    
                    $selected = 'selected="selected"';
                }
            } else {
                if ($grade_val == $id) {
                    $selected = 'selected="selected"';
                }
            }
            $option .= "<option $selected value=$id>$val</option>";
        }

        $std_option = "<option value=''>Select</option>";
        if ($grade_val != "") {
            if (is_array($grade_val)) {

                $query = DB::table('standard');
                $classTeacherStdArr = session()->get('classTeacherStdArr');
                if($std_val!='' && is_array($std_val) && !empty($classTeacherStdArr)){
                    $query->whereIn("id", $std_val)->whereIn('grade_id',$g_id);                    
                }else{
                    $query->whereIn("grade_id", $grade_val);
                }
                
                //START Check for class teacher assigned standards
                if (isset($classTeacherStdArr) && !in_array($module_name, $module_array)) {
                    if (count($classTeacherStdArr) > 0 && $std_val=='') {
                        $query->whereIn('id', $classTeacherStdArr);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherStdArr = session()->get('subjectTeacherStdArr');
                if (isset($subjectTeacherStdArr) && (!isset($classTeacherStdArr) || in_array($module_name, $module_array))) {
                    if (count($subjectTeacherStdArr) > 0 && $std_val=='') {
                        $query->orwhereIn('id', $subjectTeacherStdArr);
                    } else {
                        $query->oRwhere('id', null);
                    }
                }
                $standard = $query->pluck("name", "id");

            } else {
                $query = DB::table('standard');
                $query->where("grade_id", $grade_val);

                //START Check for class teacher assigned standards
                $classTeacherStdArr = session()->get('classTeacherStdArr');
                if (isset($classTeacherStdArr) && !in_array($module_name, $module_array)) {
                    if (count($classTeacherStdArr) > 0) {
                        $query->whereIn('id', $classTeacherStdArr);
                    } else {
                        $query->oRwhere('id', null);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherStdArr = session()->get('subjectTeacherStdArr');
                if (isset($subjectTeacherStdArr) && (!isset($classTeacherStdArr) || in_array($module_name, $module_array))) {
                    if (count($subjectTeacherStdArr) > 0) {
                        // $query->orwhereIn('id',$subjectTeacherStdArr);
                        $query->whereIn('id', $subjectTeacherStdArr);
                    } else {
                        // $query->orwhere('id',null);
                        $query->oRwhere('id', null);
                    }
                }
                //END Check for subject teacher assigned

                $standard = $query->pluck("name", "id");
            }

            foreach ($standard as $id => $val) {
                $selected = '';
                if (is_array($std_val)) {
                    if (in_array($id, $std_val)) {
                        $selected = 'selected="selected"';
                    }
                } else {
                    if ($std_val == $id) {
                        $selected = 'selected="selected"';
                    }
                }

                $std_option .= "<option $selected value=$id>$val</option>";
            }
        }

        $div_option = "<option value=''>Select</option>";
        if ($std_val != "") {
            if (is_array($std_val)) {
                $query = DB::table('std_div_map');
                $query->join('division', 'division.id', '=', 'std_div_map.division_id');
                $query->where("std_div_map.standard_id", $std_val);
                //START Check for class teacher assigned standards
                $classTeacherDivArr = session()->get('classTeacherDivArr');
                if (isset($classTeacherDivArr) && !in_array($module_name, $module_array)) {
                    if (count($classTeacherDivArr) > 0) {
                        $query->whereIn('division.id', $classTeacherDivArr);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherDivArr = session()->get('subjectTeacherDivArr');
                if (isset($subjectTeacherDivArr) && (!isset($subjectTeacherDivArr) || in_array($module_name, $module_array))) {
                    if (count($subjectTeacherDivArr) > 0) {
                        $query->orwhereIn('division.id', $subjectTeacherDivArr);
                    }
                }
                //END Check for subject teacher assigned

                $division = $query->pluck('division.name', 'division.id');

                // $division = DB::table('std_div_map')
                // ->join('division', 'division.id', '=', 'std_div_map.division_id')
                // //                        ->where("std_div_map.standard_id", implode(',', $std_val))
                // ->where("std_div_map.standard_id", $std_val)
                // ->pluck('division.name', 'division.id');
            } else {
                // die('here');
                $query = DB::table('std_div_map');
                $query->join('division', 'division.id', '=', 'std_div_map.division_id');
                $query->where("std_div_map.standard_id", $std_val);
                //START Check for class teacher assigned standards
                $classTeacherDivArr = session()->get('classTeacherDivArr');
                if (isset($classTeacherDivArr) && !in_array($module_name, $module_array)) {
                    if (count($classTeacherDivArr) > 0) {
                        $query->whereIn('division.id', $classTeacherDivArr);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherDivArr = session()->get('subjectTeacherDivArr');
                // if(isset($subjectTeacherDivArr) && (!isset($subjectTeacherDivArr) || in_array($module_name, $module_array)))
                if ($subjectTeacherDivArr != "" && ($classTeacherDivArr == "" || in_array($module_name, $module_array))) {
                    // print_r($subjectTeacherDivArr); exit('here');
                    if (count($subjectTeacherDivArr) > 0) {
                        // $query->orwhereIn('division.id',$subjectTeacherDivArr);
                        $query->whereIn('division.id', $subjectTeacherDivArr);
                    }
                }
                //END Check for subject teacher assigned

                $division = $query->pluck('division.name', 'division.id');
                // $division = DB::table('std_div_map')
                // ->join('division', 'division.id', '=', 'std_div_map.division_id')
                // ->where("std_div_map.standard_id", $std_val)
                // ->pluck('division.name', 'division.id');
                // $query = DB::table('std_div_map');
                // $query->join('division', 'division.id', '=', 'std_div_map.division_id');
                // $query->where("std_div_map.standard_id", $std_val);
                // //START Check for class teacher assigned standards
                // $classTeacherDivArr = session()->get('classTeacherDivArr');
                // if ($classTeacherDivArr != "" && !in_array($module_name, $module_array))
                // {
                //     $query->whereIn('division.id',$classTeacherDivArr);
                // }
                // //END Check for class teacher assigned standards

                // //START Check for class teacher assigned standards
                // $subjectTeacherDivArr = session()->get('subjectTeacherDivArr');
                // if ($subjectTeacherDivArr != "" && ($classTeacherDivArr == "" || in_array($module_name, $module_array)))
                // {
                //     $query->whereIn('division.id',$subjectTeacherDivArr);
                // }
                // //END Check for class teacher assigned standards

                // $division = $query->pluck('division.name', 'division.id');
            }

            foreach ($division as $id => $val) {
                $selected = '';
                if (is_array($div_val)) {
                    if (in_array($id, $div_val)) {
                        $selected = 'selected="selected"';
                    }
                } else {
                    if ($div_val == $id) {
                        $selected = 'selected="selected"';
                    }
                }

                $div_option .= "<option $selected value=$id>$val</option>";
            }

        }

        //  //  batch val  //  //
        $batch_option = "<option value=''>Select</option>";
        $searchsection = 'Search Section';
        $grade = '<div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label>' . get_string('searchsection', 'request') . ': </label>
                        <select name="' . $grade_name . '" id="grade" class="form-control" ' . $multiple . '>
                            ' . $option . '
                        </select>

                    </div>
                </div>';
        //<h4 class="box-title after-none mb-0">Select Section:</h4>

        $std = '<div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label>' . get_string('searchstandard', 'request') . ': </label>
                        <select name="' . $std_name . '" id="standard" class="form-control" ' . $multiple . '>
                            ' . $std_option . '
                        </select>
                    </div>
                </div>';
        //<h4 class="box-title after-none mb-0">Select Standard:</h4>

        $div = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label>' . get_string('searchdivision', 'request') . ': </label>
                        <select name="' . $div_name . '" id="division" class="form-control" ' . $multiple . '>
                            ' . $div_option . '
                        </select>

                    </div>
                </div>';
        //<h4 class="box-title after-none mb-0">Select Division:</h4>

        //  //  batch val  //  //
        $batch = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label>Select Batch:</label>
                        <select name="' . $batch_section . '" id="stdBatch" class="form-control" ' . $multiple . '>
                            ' . $batch_option . '
                        </select>

                    </div>
                </div>';
        // <h4 class="box-title after-none mb-0">Select Division:</h4>

        $html = '';

        if (in_array('grade', $explod_list)) {
            $html .= $grade;
        }

        if (in_array('std', $explod_list)) {
            $html .= $std;
        }

        if (in_array('div', $explod_list)) {
            $html .= $div;
        }

        if (in_array('batch', $explod_list)) {
            $html .= $batch;
        }
        $html .= '';
        echo $html;
    }
}

if (!function_exists('SearchChainSubject')) {

    function SearchChainSubject($col, $multiple, $listed_drop, $grade_val = "", $std_val = "", $sub_val = "")
    {

        $explod_list = explode(',', $listed_drop);
        $grade_name = 'grade';
        $std_name = 'standard';
        $sub_name = 'subject';

        if ($multiple == 'multiple') {
            $multiple = 'multiple="multiple"';
            $grade_name = 'grade[]';
            $std_name = 'standard[]';
            $sub_name = 'subject[]';
        } else {
            if ($multiple == 'single') {
                $multiple = '';
            } else {
                echo "Chain Option Error : Must Provide First Prameter As Single Dropdown Or Multiple.";
            }
        }

        $option = "<option value=''>--Select Grade--</option>";

        $academic_section = DB::table("academic_section")
            ->where("sub_institute_id", session()->get('sub_institute_id'))
            ->pluck("title", "id");

        foreach ($academic_section as $id => $val) {
            $selected = '';
            if ($grade_val == $id) {
                $selected = 'selected="selected"';
            }

            $option .= "<option $selected value=$id>$val</option>";
        }

        $std_option = "";
        if ($grade_val != "") {
            $standard = DB::table("standard")
                ->where("grade_id", $grade_val)
                ->pluck("name", "id");
            foreach ($standard as $id => $val) {
                $selected = '';
                if ($std_val == $id) {
                    $selected = 'selected="selected"';
                }

                $std_option .= "<option $selected value=$id>$val</option>";
            }
        }

        $div_option = "";
        $sub_option = "";

        if ($std_val != "") {
            $subjects = DB::table('sub_std_map')
                ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
                ->where("sub_std_map.standard_id", $std_val)
                ->pluck('subject.subject_name', 'subject.id');

            foreach ($subjects as $id => $val) {
                $selected = '';
                if ($sub_val == $id) {
                    $selected = 'selected="selected"';
                }

                $sub_option .= "<option $selected value=$id>$val</option>";
            }
        }

        $grade = '<div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Grade:</label>
                        <select name="' . $grade_name . '" id="gradeS" class="form-control" ' . $multiple . '>
                            ' . $option . '
                        </select>
                    </div>
                </div>';

        $std = '<div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Standard:</label>
                        <select name="' . $std_name . '" id="standardS" class="form-control" ' . $multiple . '>
                            ' . $std_option . '
                        </select>
                    </div>
                </div>';

        $sub = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Subject:</label>
                        <select name="' . $sub_name . '" id="subject" class="form-control" ' . $multiple . '>
                            ' . $sub_option . '
                        </select>
                    </div>
                </div>';
        $html = '<div class="row">';

        if (in_array('grade', $explod_list)) {
            $html .= $grade;
        }

        if (in_array('std', $explod_list)) {
            $html .= $std;
        }

        if (in_array('sub', $explod_list)) {
            $html .= $sub;
        }
        $html .= '</div>';
        echo $html;
    }
}
if (!function_exists('TermDD')) {

    function TermDD($selected_val = "", $col = 4)
    {
        $option = "<option value=''>Select Term</option>";

        $academic_year = DB::table("academic_year")
            ->where([
                "sub_institute_id" => session()->get('sub_institute_id'),
                "syear" => session()->get('syear'),
            ])
            ->pluck("title", "term_id");

        foreach ($academic_year as $id => $val) {
            $selected = "";
            if ($selected_val == $id) {
                $selected = 'selected="selected"';
            }

            $option .= "<option $selected value=$id>$val</option>";
        }

        $term = '<div class="col-md-' . $col . ' form-group">
                    <label for="title">Select Term:</label>
                    <select name="term" id="term" class="form-control">
                        ' . $option . '
                    </select>
                </div>';

        $html = $term;

        echo $html;
    }
}
if (!function_exists('SearchStudent')) {

    function SearchStudent($grade = "", $standard = "", $div = "", $sub_institute_id = "", $syear = "", $roll_no = "", $stu_name = "", $uniqueid = "", $mobile = "", $grno = "", $stud_id = "", $batch = "")
    {
        if ($sub_institute_id == '') {
            $sub_institute_id = session()->get('sub_institute_id');
        }

        if ($syear == '') {
            $syear = session()->get('syear');
        }
        $marking_period_id = session()->get('term_id');

        $grade_arr = array();
        $standard_arr = array();
        $div_arr = array();
        $classTeacherStdArr = session()->get('classTeacherStdArr');
        $classTeacherDivArr = session()->get('classTeacherDivArr');

        if ($grade != '') {
            $grade_arr = (array)$grade;
        }
        if ($standard != '') {
            $standard_arr = (array)$standard;
        }
        if ($div != '') {
            $div_arr = (array)$div;
        }
        if ($stud_id != '') {
            $stud_id = $stud_id;
        }

        $enrollment_join = array(
            'se.student_id' => 'ts.id',
            'se.sub_institute_id' => 'ts.sub_institute_id',
        );
        $grade_join = array(
            'acs.id' => 'se.grade_id',
            'acs.sub_institute_id' => 'se.sub_institute_id',
        );
        $std_join = array(
            's.id' => 'se.standard_id',
            's.sub_institute_id' => 'se.sub_institute_id',
        );
        $div_join = array(
            'd.id' => 'se.section_id',
            'd.sub_institute_id' => 'se.sub_institute_id',
        );
        $batch_join = array(
            'b.id' => 'ts.studentbatch',
            'b.sub_institute_id' => 'se.sub_institute_id',
        );


        $select_fields = "ts.*,se.syear,se.student_id,se.grade_id,se.roll_no,
                se.standard_id,se.section_id,se.student_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number";
        $select_fields = preg_replace('/\s+/', '', $select_fields);
        $where = array(
            'se.syear' => $syear,
            'ts.sub_institute_id' => $sub_institute_id,
            'se.end_date' => null,
        );

        $query = tblstudentModel::from('tblstudent as ts');

        // $query->when($marking_period_id, function ($join) use ($marking_period_id) {
        //     $join->where('ts.marking_period_id', $marking_period_id);
        // });
        if ($batch != "") {
            $query->where('ts.studentbatch', $batch);
        }

        if ($mobile != '') {
            $query->where('ts.mobile', $mobile);
        }
        if ($grno != '') {
            $query->where('ts.enrollment_no', $grno);
        }
        if ($uniqueid != '') {
            $query->where('ts.uniqueid', $uniqueid);
        }
        if ($stu_name != '') {
            $query->where(function ($query) use ($stu_name) {
                $query->where('ts.first_name', 'like', '%' . $stu_name . '%')
                    ->orWhere('ts.middle_name', 'like', '%' . $stu_name . '%')
                    ->orWhere('ts.last_name', 'like', '%' . $stu_name . '%');
            });
        }
        if (!empty($stud_id)) {
            // Check if $stud_id is already an array, if not, convert it to an array
            if (!is_array($stud_id)) {
                $stud_id = [$stud_id];
            }
            // Now, you can safely use the whereIn function with $stud_id
            $query->whereIn('ts.id', $stud_id);
        }

        $columns = explode(',', $select_fields);
        $columns[] = "s.name as standard_name";
        $columns[] = "s.medium as medium";
        $columns[] = "d.name as division_name";
        $columns[] = "b.title as batch_title";

        $query->join('tblstudent_enrollment as se', $enrollment_join);
        $query->where($where);

        $query->join('academic_section as acs', $grade_join);
        if (count($grade_arr)) {
            $query->WhereIn('acs.id', $grade_arr);
        }

        $query->join('standard as s', $std_join);
        if (count($standard_arr)) {
            $query->WhereIn('s.id', $standard_arr);
        }

        $query->join('division as d', $div_join);
        if (count($div_arr)) {
            $query->WhereIn('d.id', $div_arr);
        }
        $query->leftJoin('batch as b', $batch_join);
  
        //START Check for class teacher assigned standards
        $extraRaw = " 1 = 1 ";

        if (isset($classTeacherStdArr) && count($standard_arr) < 0) {
            if (count($classTeacherStdArr) > 0) {
                $extraRaw = "s.id IN (" . implode(",", $classTeacherStdArr) . ")";
            } else {
                $extraRaw = "s.id IN (' ')";
            }
        }

        if (isset($classTeacherDivArr)) {
            if (count($classTeacherDivArr) > 0 && count($div_arr) < 0) {
                $extraRaw .= " and d.id IN (" . implode(",", $classTeacherDivArr) . ")";
            }
        }
        //END Check for class teacher assigned standards


        if ($roll_no != '') {
            $extraRaw .= " AND se.roll_no = '" . $roll_no . "' ";
        }

        $query->whereraw($extraRaw);

        $query->orderByRaw('s.sort_order, d.id, se.roll_no');

        return $query->get($columns)->toArray();
    }
}
if (!function_exists('FeeMonthId')) {

    function FeeMonthId($syear = '',$sub_institute_id='')
    {
        if($sub_institute_id==''){
            $sub_institute_id=session()->get('sub_institute_id');
        }
        if($syear==''){
            $syear = session()->get('syear');
        }
        $data = map_year::where([
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
        ])->get()->toArray();
        if (count($data) == 0) {
            return array();
            exit;
        }

        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $months_arr = [];
        if ($syear == '') {
            $syear = session()->get('syear');
        }

        for ($i = 1; $i <= 12; $i++) {
            $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
            if ($start_month == 12) {
                $start_month = 0;
                ++$syear;
            }
            ++$start_month;
        }

        return $months_arr;
    }
}
// function is used in fees_collect_controller and online_fees_collect_controller and all fee_reports
if (!function_exists('FeeBreackoff')) {

    function FeeBreackoff($student_ids, $standard = null, $syear = '',$sub_institute_id='')
    {

        if ($sub_institute_id == '') {
            $sub_institute_id = session()->get('sub_institute_id');
        } 
        if($syear == '') {
            $syear = session()->get('syear');
        }

        $data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
            ->join('academic_section as g', 'g.id', '=', 'se.grade_id')
            ->join('standard as st', function ($join) use ($standard) {
                $join->on('st.id', '=', 'se.standard_id');
                if (!$standard) {
                    $join->whereRaw('st.id = se.standard_id');
                }
            })
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->leftJoin('student_quota as sq', 'sq.id', '=', 'se.student_quota')
            ->join('fees_breackoff as fb', function ($join) use ($syear, $sub_institute_id, $standard) {
                $join->on('fb.syear', '=', DB::raw("'" . $syear . "'"))
                    ->on('fb.admission_year', '=', 's.admission_year')
                    ->on('fb.quota', '=', 'se.student_quota')
                    ->on('fb.grade_id', '=', 'se.grade_id')
                    ->on('fb.sub_institute_id', '=', DB::raw("'" . $sub_institute_id . "'"));

                if (!$standard) {
                    $join->on('fb.standard_id', '=', 'se.standard_id');
                } else {
                    $join->on('fb.standard_id', '=', DB::raw("'" . $standard . "'"));
                }
            })
            ->selectRaw("s.*, se.syear, se.student_id, se.grade_id, se.standard_id, se.section_id, se.student_quota, g.medium, sq.title AS stu_quota,se.roll_no,
                    se.start_date, se.end_date, se.enrollment_code, se.drop_code, se.drop_remarks, se.drop_remarks,
                    se.term_id, se.remarks, se.admission_fees, se.house_id, se.lc_number, sum(fb.amount) as bkoff,
                    st.name as standard_name, d.name as division_name, fb.month_id,
                    RIGHT(fb.month_id, 4) as sort_year,
                    CAST(SUBSTRING(fb.month_id, 1, CHAR_LENGTH(fb.month_id) - 4) as signed) as sort_month")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->whereIn('s.id', $student_ids)
            ->groupBy('s.id', 'fb.month_id')
            ->orderByRaw('sort_year, sort_month')
            ->get()->toArray();

        return $data;
    }
}

// function is used in fees_collect_controller and online_fees_collect_controller and all fee_reports

if (!function_exists('FeeBreakoffHeadWise')) {

    function FeeBreakoffHeadWise($student_ids, $from_date = null, $to_date = null, $fees_head = null, $syear = '',$months='',$sub_institute_id='')
    {
        if($sub_institute_id==''){
        $sub_institute_id = session()->get('sub_institute_id');
        }
        if ($syear == '') {
            $syear = session()->get('syear');
        }
        $extra_where = "1=1";

        $stud_arr = implode(',', $student_ids);
        // $extra_where = " AND s.id in (" . $stud_arr . ")";

       if (!empty($months) && is_array($months)) {
            $month_arr = implode(',', $months);
            $extra_where .= " AND fb.month_id in (" . $month_arr . ")";
        } 

        $result = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
            ->join('academic_section as g', 'g.id', '=', 'se.grade_id')
            ->join('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->join('fees_breackoff as fb', function ($join) use ($syear, $sub_institute_id) {
                $join->on('fb.syear', '=', DB::raw("'" . $syear . "'"))
                    ->on('fb.admission_year', '=', 's.admission_year')
                    ->on('fb.quota', '=', 'se.student_quota')
                    ->on('fb.grade_id', '=', 'se.grade_id')
                    ->on('fb.standard_id', '=', 'se.standard_id')
                    ->on('fb.sub_institute_id', '=', DB::raw("'" . $sub_institute_id . "'"));
            })
            ->join('fees_title as ft', 'fb.fee_type_id', '=', 'ft.id')
            ->leftJoin('admission_registration as ar', function ($join) {
                $join->on('ar.enrollment_no', '=', 's.enrollment_no')
                    ->on('ar.sub_institute_id', '=', 's.sub_institute_id');
            })
            ->leftJoin('admission_enquiry as ae', function ($join) {
                $join->on('ae.id', '=', 'ar.enquiry_id')
                    ->on('ar.sub_institute_id', '=', 'ae.sub_institute_id');
            })
            ->selectRaw("s.*, se.syear, se.student_id, se.roll_no, se.grade_id, se.standard_id, se.section_id, se.student_quota,
                    se.start_date, se.end_date, se.enrollment_code, se.drop_code, se.drop_remarks, se.drop_remarks, se.term_id,
                    se.remarks, se.admission_fees, se.house_id, se.lc_number, fb.amount, st.name as standard_name, d.name as division_name,
                    fb.month_id, ft.display_name, ft.fees_title, ft.mandatory, '' as breakoff, s.father_name, s.mother_name,
                    RIGHT(fb.month_id, 4) as sort_year, CAST(SUBSTRING(fb.month_id, 1, CHAR_LENGTH(fb.month_id) - 4) as signed) as sort_month,
                    ae.fees_circular_form_no")
            ->havingRaw("sum(fb.amount) != 0")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->whereRaw($extra_where)            
            ->whereIn('s.id', $student_ids)
            ->groupBy('s.id', 'fb.month_id', 'fb.fee_type_id')
            ->orderByRaw('sort_year, sort_month, ft.sort_order ASC')
            ->get()->toArray();

        $data = array();
        $student_data = array();
        foreach ($result as $key => $value) {
            $fees_title = $value->fees_title;
            $month_id = $value->month_id;
            $sub_institute_id = $sub_institute_id;

            $request = $_REQUEST;

            $paid_fees = $paid_fees = DB::table('fees_collect') // 03-06-24 by uma
                ->selectRaw("sum(ifnull($fees_title,0)) total_paid,SUM(fees_discount) AS total_disc,receiptdate")
                ->where([
                    'term_id' => $month_id,
                    'sub_institute_id' => $sub_institute_id,
                    'is_deleted' => 'N',
                    'student_id' => $value->id,
                ])->when(isset($request['from_date'], $request['to_date']), function ($q) use ($request) {
                    $q->where('fees_collect.receiptdate', '<=', $request['to_date']);
                })->get()->toArray();

            $data[$value->id][$value->month_id][$value->fees_title]['amount'] = $value->amount - $paid_fees[0]->total_paid;

            // Start Added by 18/05/2021 for getting paid amount in Overall Fees Head Wise report
            if (isset($paid_fees[0]->total_paid) && $paid_fees[0]->total_paid != '') {
                $data[$value->id][$value->month_id][$value->fees_title]['paid_amount'] = $paid_fees[0]->total_paid;
                $data[$value->id][$value->month_id][$value->fees_title]['disc_amount'] = $paid_fees[0]->total_disc; // 03-06-24 by uma
            } else {
                $data[$value->id][$value->month_id][$value->fees_title]['paid_amount'] = 0;
            }
            // End Added by 18/05/2021 for getting paid amount in Overall Fees Head Wise report


            $data[$value->id][$value->month_id][$value->fees_title]['title'] = $value->display_name;
            $data[$value->id][$value->month_id][$value->fees_title]['mandatory'] = $value->mandatory;
        }

        foreach ($result as $key => $value) {

            $student_data[$value->id]['id'] = $value->id;
            $student_data[$value->id]['enrollment_no'] = $value->enrollment_no;
            $student_data[$value->id]['surname'] = $value->last_name;
            $student_data[$value->id]['student_name'] = $value->first_name . " " . $value->middle_name;
            $student_data[$value->id]['gender'] = $value->gender;
            $student_data[$value->id]['mobile'] = $value->mobile;
            $student_data[$value->id]['dob'] = $value->dob;
            $student_data[$value->id]['admission_year'] = $value->admission_year;
            $student_data[$value->id]['address'] = $value->address;
            $student_data[$value->id]['standard_name'] = $value->standard_name;
            $student_data[$value->id]['division_name'] = $value->division_name;
            $student_data[$value->id]['father_name'] = $value->father_name;
            $student_data[$value->id]['mother_name'] = $value->mother_name;
            $student_data[$value->id]['fees_circular_form_no'] = $value->fees_circular_form_no;
            $student_data[$value->id]['breakoff'] = $data[$value->id];
        }

        return $student_data;
    }
}

// last byear end
if (!function_exists('getStringOfAmount')) {

    function getStringOfAmount($number)
    {
        $no = round($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = [];
        $words = [
            '0' => '', '1' => 'one', '2' => 'two',
            '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
            '7' => 'seven', '8' => 'eight', '9' => 'nine',
            '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
            '13' => 'thirteen', '14' => 'fourteen',
            '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
            '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
            '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
            '60' => 'sixty', '70' => 'seventy',
            '80' => 'eighty', '90' => 'ninety',
        ];
        $digits = ['', 'hundred', 'thousand', 'lakh', 'crore'];
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred : $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else {
                $str[] = null;
            }
        }

        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ?
            "." . $words[$point / 10] . " " .
            $words[$point = $point % 10] : '';

        $returnValue = $result;
        if ($points != '') {
            $returnValue .= "." . $points;
        }

        return ucwords($returnValue) . " Only";
    }
}

if (!function_exists('ClassTeacherSearch')) {

    function ClassTeacherSearch($stdiv = null)
    {

        $result = DB::table('class_teacher as ct')
            ->join('standard as s', function ($join) {
                $join->whereRaw('ct.standard_id = s.id AND ct.sub_institute_id = s.sub_institute_id');
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = ct.division_id AND d.sub_institute_id = ct.sub_institute_id');
            })->selectRaw('ct.standard_id,ct.division_id,s.name as standard_name,d.name as division_name')
            ->where('ct.sub_institute_id', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))
            ->where(function ($q) {
                if (session()->get('user_profile_name') == 'Teacher') {
                    $q->where('ct.teacher_id', session()->get('user_id'));
                } else {
                    if (session()->get('profile_parent_id') != '1') {
                        $q->whereRaw('1 != 1');
                    }
                }
            })->get()->toArray();

        $returnHtml = '<select name="standard_division" class="form-control" id="standard_division" required>';
        $returnHtml .= '<option value=""> Select Standard Division </option>';

        foreach ($result as $key => $value) {
            $newValue = $value->standard_id . "||" . $value->division_id;

            $selected = '';
            if ($newValue == $stdiv) {
                $selected = 'selected="selected"';
            }
            $returnHtml .= "<option value='" . $newValue . "' " . $selected . ">" . $value->standard_name . " - " . $value->division_name . "</option>";
        }

        $returnHtml .= "</select>";

        echo $returnHtml;
    }
}
// function is used in fees_collect_controller and online_fees_collect_controller and all fee_reports

if (!function_exists('OtherBreackOff')) {

    function OtherBreackOff($student_id_arr, $month_arr, $other_bf_amount = '', $from_date = null, $to_date = null, $syear = '',$sub_institute_id='')
    {

        $student_id = $student_id_arr[0];
        $moth_ids = implode(',', $month_arr);
        if ($sub_institute_id == '') {        
        $sub_institute_id = session()->get('sub_institute_id');
        }
        if ($syear == '') {
            $syear = session()->get('syear');
        }

        $fees_breckoff = DB::table('fees_breakoff_other')
            ->selectRaw('*, sum(amount) as tot_amount')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('student_id', $student_id)
            ->whereIn('month_id', $month_arr)
            ->groupByRaw('fee_type_id,month_id')->get()->toArray();
        //START for fees over all headwise report
        $extra_condition = '';

        if (isset($_REQUEST['from_date']) && isset($_REQUEST['to_date'])) {
            $extra_condition .= " AND receiptdate <= '" . $_REQUEST['to_date'] . "' ";
        }
        //END for fees over all headwise report

        $final_bk = $other_fees_final_bk = array();
        foreach ($fees_breckoff as $id => $arr) {
            $fees_title = $arr->fee_type_id;
            $month_id = $arr->month_id;

            $request = $_REQUEST;

            $paid_fees = DB::table('fees_paid_other as fpo')
                ->selectRaw("sum(ifnull(fpo.$fees_title,0)) total_paid")
                ->where('month_id', $month_id)
                ->where('syear', $syear)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('student_id', $student_id)
                ->where('is_deleted', 'N')
                ->when(isset($request['from_date'], $request['to_date']), function ($q) use ($request) {
                    $q->where('receiptdate', '<=', $request['to_date']);
                })->get()->toArray();

            if (isset($final_bk[$arr->fee_type_id])) {
                $final_bk[$arr->fee_type_id] = $final_bk[$arr->fee_type_id] + ($arr->tot_amount - $paid_fees[0]->total_paid);
            } else {
                $final_bk[$arr->fee_type_id] = ($arr->tot_amount - $paid_fees[0]->total_paid);
            }

            // start 27-07-2021 Added by divya for getting other_fees break off amount for fees overallhead wise report
            $other_fees_final_bk[$student_id][$arr->fee_type_id][$month_id]['bf_amount'] = $arr->tot_amount;
            $other_fees_final_bk[$student_id][$arr->fee_type_id][$month_id]['paid_amount'] = $paid_fees[0]->total_paid;
            // end 27-07-2021 Added by divya for getting other_fees break off amount for fees overallhead wise report
        }

        $fees_title = fees_title::select('id', 'display_name', 'fees_title', 'mandatory', 'syear', 'other_fee_id')
            ->where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
                'fees_title_id' => 1,
            ])->orderBy('sort_order', 'ASC')->get()->toArray();

        $bk_off_with_name = array();
        foreach ($fees_title as $id => $arr) {
            foreach ($final_bk as $bk_id => $amount) {
                if ($arr['fees_title'] == $bk_id) {
                    $bk_off_with_name[$arr['display_name']] = $amount;
                }
            }
        }

        // start 27-07-2021 Added by divya for getting other_fees break off amount for fees overallhead wise report
        if ($other_bf_amount == 'Yes') {
            return $other_fees_final_bk;
        }
        return $bk_off_with_name;
        // start 27-07-2021 Added by divya for getting other_fees break off amount for fees overallhead wise report
    }
}
// function is used in fees_collect_controller and online_fees_collect_controller and all fee_reports

if (!function_exists('OtherBreackOffHead')) {

    function OtherBreackOffHead($sub_institute_id='',$syear='')
    { 

        if($sub_institute_id==''){
        $sub_institute_id = session()->get('sub_institute_id');
        }
        if($syear==''){
        $syear = session()->get('syear');
        }
       
        return DB::table('fees_title')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('fees_title_id', 1)->orderBy('sort_order')->get()->toArray();
    }
}
// function is used in fees_collect_controller and online_fees_collect_controller and all fee_reports

if (!function_exists('OtherBreackOfMonth')) {

    function OtherBreackOfMonth($student_id_arr, $syear = '',$sub_institute_id='')
    {

        $student_id = $student_id_arr[0];
        if($sub_institute_id==''){        
        $sub_institute_id = session()->get('sub_institute_id');
        }
        if ($syear == '') {
            $syear = session()->get('syear');
        }
        $fees_title = DB::table('fees_breakoff_other')
            ->selectRaw('sum(amount) as tot_amount,month_id')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('student_id', $student_id)
            ->groupBy('month_id')->get()->toArray();

        $responce_arr = array();
        foreach ($fees_title as $id => $arr) {
            $responce_arr[$arr->month_id] = $arr->tot_amount;
        }

        return $responce_arr;
    }
}
// function is used in fees_collect_controller and online_fees_collect_controller and all fee_reports

if (!function_exists('OtherBreackOfMonthHead')) {

    function OtherBreackOfMonthHead($student_id_arr, $month_arr, $syear = '',$sub_institute_id='')
    {
        $student_id = $student_id_arr[0];
        if ($sub_institute_id == '') {
        $sub_institute_id = session()->get('sub_institute_id');
        }
        if ($syear == '') {
            $syear = session()->get('syear');
        }
        uksort($month_arr, function($a, $b) {
            $last4A = substr($a, -4);
            $last4B = substr($b, -4);
        
            if ($last4A != $last4B) {
                return $last4A - $last4B; // Sort by last 4 digits
            } else {
                return $a - $b; // If last 4 digits are the same, sort by the entire value
            }
        });
        $fees_breckoff = DB::table('fees_breakoff_other')
            ->selectRaw('*,sum(amount) as tot_amount')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('student_id', $student_id)
            ->whereIn('month_id', $month_arr)
            ->groupByRaw('fee_type_id,month_id')->get()->toArray();

        $final_bk = [];

        foreach ($fees_breckoff as $id => $arr) {
            $fees_title= $arr->fee_type_id;
            // db::enableQueryLog();
            $fees_paid = DB::table('fees_paid_other')
            ->selectRaw('*,sum(`'.$fees_title.'`) as tot_amount')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('student_id', $student_id)
            ->where('month_id', $arr->month_id)
            ->where('is_deleted','N')
            ->where($fees_title,'!=','0')            
            ->groupByRaw($fees_title.',month_id')->first();
            // dd(db::getQueryLog($fees_paid));

            if(isset($fees_paid->tot_amount)){
                $final_bk[$arr->month_id][$arr->fee_type_id] = ($arr->tot_amount - $fees_paid->tot_amount);
            }else{
                $final_bk[$arr->month_id][$arr->fee_type_id] = $arr->tot_amount;
            }
        }

        return $final_bk;
    }
}
// function is used in fees_collect_controller and online_fees_collect_controller and all fee_reports

if (!function_exists('OtherBreackOfMonthHeadlast')) {

    function OtherBreackOfMonthHeadlast($student_id_arr, $month_arr)
    {
        $student_id = $student_id_arr[0];

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = (session()->get('syear') - 1);

        $fees_breckoff = DB::table('fees_breakoff_other')
            ->selectRaw('*,sum(amount) as tot_amount')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('student_id', $student_id)
            ->whereIn('month_id', $month_arr)
            ->groupByRaw('fee_type_id,month_id')->get()->toArray();

        $final_bk = [];

        foreach ($fees_breckoff as $id => $arr) {
            $final_bk[$arr->month_id][$arr->fee_type_id] = $arr->tot_amount;
        }

        return $final_bk;
    }
}
if (!function_exists('getCountDays')) {

    function getCountDays($from_date, $to_date)
    {
        //5 for count Friday, 6 for Saturday , 7 for Sunday
        $days = array('S' => '7');
        $counter = [];

        foreach ($days as $key => $day) {
            $from_date1 = $from_date;
            while (strtotime($from_date1) <= strtotime($to_date)) {
                if (date("N", strtotime($from_date1)) == $day) {
                    $counter[$key][] = $from_date1;
                }
                $from_date1 = date("Y-m-d", strtotime("+1 day", strtotime($from_date1)));
            }
        }

        return $counter;
    }
}

if (!function_exists('getStudents')) {

    function getStudents($student_ids, $sub_institute_id = '', $syear = '')
    {
        //START 23-11-2021 Added FOR Add Homework API
        if ($sub_institute_id != '' && $syear != '') {
            $sub_institute_id = $sub_institute_id;
            $syear = $syear;
        } else {
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }
        
        //END 23-11-2021 Added FOR Add Homework API

        $stud_arr = implode(',', $student_ids);
        
        $extra_where = "s.id in (" . $stud_arr . ")";
        // whenever add new table, make join with function and all where condition within function not outside
        // db::enableQueryLog();
        $result = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id');
            })->join('academic_section as g', function ($join) {
                $join->whereRaw('g.id = se.grade_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = se.standard_id');
            })->join('division as d', function ($join) {
                $join->whereRaw(' d.id = se.section_id');
            })->join('school_setup as ss', function ($join) {
                $join->whereRaw('s.sub_institute_id = ss.Id');
            })->leftJoin('blood_group as bg', function ($join) {
                $join->whereRaw('s.bloodgroup = bg.id');
            })->leftJoin('tblstudent_tc_details as tc', function ($join) {
                $join->whereRaw('tc.sub_institute_id = s.sub_institute_id AND tc.student_id = s.id');
            })->leftJoin('religion as r', function ($join) {
                $join->whereRaw('r.id = s.religion');
            })->leftJoin('caste as c', function ($join) {
                $join->whereRaw('c.id = s.cast');
            })->leftJoin('transport_map_student as tms', function ($join) {
                $join->whereRaw('tms.student_id = s.id');
            })->leftJoin('transport_vehicle as tv', function ($join) {
                $join->whereRaw('tv.id = tms.from_bus_id');
            })->leftJoin('transport_driver_detail as td', function ($join) {
                $join->whereRaw('td.id = tv.driver')->where('td.status', 'Active');
            })->leftJoin('transport_kilometer_rate as tkr', function ($join) {
                $join->whereRaw('tkr.id = s.distance_from_school');
            })->leftJoin('result_student_attendance_master as rsam', function ($join) {
                $join->on('rsam.student_id','=', 's.id')->where('rsam.term_id', '2');
            })->leftJoin('attendance_student as ats', function ($join) {
                $join->whereRaw('ats.student_id = s.id AND ats.sub_institute_id = s.sub_institute_id');
            })->leftJoin('fees_collect as fc', function ($join) {
                $join->on('fc.student_id', '=', 's.id')->on('fc.sub_institute_id','=','s.sub_institute_id')->on('se.syear','=','fc.syear')->where('fc.is_deleted', 'N')->groupBy('fc.term_id');
            })
            ->selectRaw("tc.*,s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,se.roll_no,
                se.start_date,se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,se.drop_remarks,se.term_id,
                se.remarks,se.admission_fees,se.house_id,se.lc_number,st.name standard_name,st.short_name as short_standard_name,st.school_stream,s.city,se.standard_id,se.section_id,se.roll_no,
                se.grade_id,d.name as division_name,s.father_name,s.mother_name,ss.SchoolName as school_name,ss.Mobile as school_mobile,
                ss.Logo as school_image,ss.ReceiptAddress as school_address,(CASE WHEN s.gender = 'M' then 'male' else 'female' end) as gender,
                r.religion_name,c.caste_name,s.subcast,s.affiliation_no,s.school_code,s.admission_date,td.first_name AS driver_name,
                td.mobile AS driver_mobile,td.icard_icon,s.mother_mobile,CONCAT_WS(' ',s.first_name,CONCAT(SUBSTRING(s.father_name,1,1),'.'),
                s.last_name) as short_student_name,tv.vehicle_type,tkr.id as distance_from_school_id,tkr.distance_from_school,
                tkr.from_distance,IF(tv.vehicle_type = 'Van',tkr.van_new,tkr.rick_new) AS distance_rate,s.first_name as student_first_name,s.middle_name as student_middle_name,s.last_name as student_last_name,rsam.teacher_remark,COUNT(ats.id) as total_att_days,sum(CASE WHEN ats.attendance_code = 'P' THEN 1 ELSE 0 END) as present_att_days, group_concat(DISTINCT  fc.term_id) as month_name, bg.bloodgroup as blood_group_name")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                // ->groupBy('fc.id')
                // ->orderBy('fc.id', 'desc')->limit(1)
                ->whereIn('s.id', $student_ids)
                ->orderBy('se.roll_no', 'ASC')
                ->groupBy('s.id')->get()->toArray();
                // dd(db::getQueryLog($result));
        $student_data = array();
        foreach ($result as $key => $value) {
            $student_data[$value->id]['id'] = $value->id;
            $student_data[$value->id]['enrollment_no'] = $value->enrollment_no;
            $student_data[$value->id]['roll_no'] = $value->roll_no;
            $student_data[$value->id]['student_name'] = $value->first_name . " " . $value->last_name;
            $student_data[$value->id]['student_first_name'] = $value->first_name;
            $student_data[$value->id]['student_middle_name'] = $value->middle_name;
            $student_data[$value->id]['student_last_name'] = $value->last_name;
            $student_data[$value->id]['student_full_name'] = $value->first_name . " " . $value->middle_name . " " . $value->last_name;
            $student_data[$value->id]['gender'] = $value->gender;
            $student_data[$value->id]['mobile'] = $value->mobile;
            $student_data[$value->id]['adharnumber'] = $value->adharnumber;
            $student_data[$value->id]['blood_group_name'] = $value->blood_group_name;
            $student_data[$value->id]['dob'] = $value->dob;
            $student_data[$value->id]['admission_year'] = $value->admission_year;
            $student_data[$value->id]['admission_date'] = $value->admission_date;
            $student_data[$value->id]['address'] = $value->address;
            $student_data[$value->id]['standard_name'] = $value->standard_name;
            $student_data[$value->id]['short_standard_name'] = $value->short_standard_name;
            $student_data[$value->id]['school_stream'] = $value->school_stream;
            $student_data[$value->id]['division_name'] = $value->division_name;
            $student_data[$value->id]['father_name'] = $value->father_name;
            $student_data[$value->id]['mother_name'] = $value->mother_name;
            $student_data[$value->id]['image'] = $value->image;
            $student_data[$value->id]['address'] = $value->address;
            $student_data[$value->id]['city'] = $value->city;
            $student_data[$value->id]['school_name'] = $value->school_name;
            $student_data[$value->id]['school_mobile'] = $value->school_mobile;
            $student_data[$value->id]['school_image'] = $value->school_image;
            $student_data[$value->id]['school_address'] = $value->school_address;
            $student_data[$value->id]['standard_id'] = $value->standard_id;
            $student_data[$value->id]['section_id'] = $value->section_id;
            $student_data[$value->id]['grade_id'] = $value->grade_id;
            $student_data[$value->id]['dise_uid'] = $value->dise_uid;
            $student_data[$value->id]['unique_id'] = $value->uniqueid;
            $student_data[$value->id]['religion_name'] = $value->religion_name;
            $student_data[$value->id]['caste_name'] = $value->caste_name;
            $student_data[$value->id]['subcast'] = $value->subcast;
            $student_data[$value->id]['nationality'] = $value->nationality;
            $student_data[$value->id]['place_of_birth'] = $value->place_of_birth;
            $student_data[$value->id]['candidate_belongs_to'] = $value->candidate_belongs_to;
            $student_data[$value->id]['date_of_first_admission'] = $value->date_of_first_admission;
            $student_data[$value->id]['class_in_which_pupil_last_studied'] = $value->class_in_which_pupil_last_studied;
            $student_data[$value->id]['last_school_board'] = $value->last_school_board;
            $student_data[$value->id]['whether_failed'] = $value->whether_failed;
            $student_data[$value->id]['subjects_studied'] = $value->subjects_studied;
            $student_data[$value->id]['whether_qualified'] = $value->whether_qualified;
            $student_data[$value->id]['teacher_remark'] = $value->teacher_remark;
            $student_data[$value->id]['if_to_which_class'] = $value->if_to_which_class;
            $student_data[$value->id]['month_up_paid_school_dues'] = $value->month_up_paid_school_dues;
            $student_data[$value->id]['month_name'] = $value->month_name;
            $student_data[$value->id]['admission_under'] = $value->admission_under;
            $student_data[$value->id]['total_working_days'] = $value->total_working_days;
            $student_data[$value->id]['total_working_days_present'] = $value->total_working_days_present;
            $student_data[$value->id]['total_working_days_system'] = $value->total_att_days;
            $student_data[$value->id]['total_working_days_present_system'] = $value->present_att_days;
            $student_data[$value->id]['games_played'] = $value->games_played;
            $student_data[$value->id]['general_conduct'] = $value->general_conduct;
            $student_data[$value->id]['date_of_application_for_certificate'] = $value->date_of_application_for_certificate;
            $student_data[$value->id]['date_of_issue_of_certificate'] = $value->date_of_issue_of_certificate;
            $student_data[$value->id]['reason_leaving_school'] = $value->reason_leaving_school;
            $student_data[$value->id]['proof_for_dob'] = $value->proof_for_dob;
            $student_data[$value->id]['whether_school_is_under_goverment'] = $value->whether_school_is_under_goverment;
            $student_data[$value->id]['date_on_which_pupil_name_was_struck'] = $value->date_on_which_pupil_name_was_struck;
            $student_data[$value->id]['any_fees_concession'] = $value->any_fees_concession;
            $student_data[$value->id]['whether_ncc_cadet'] = $value->whether_ncc_cadet;
            $student_data[$value->id]['any_other_remarks'] = $value->any_other_remarks;
            $student_data[$value->id]['affiliation_no'] = $value->affiliation_no;
            $student_data[$value->id]['school_code'] = $value->school_code;
            $student_data[$value->id]['admission_date'] = $value->admission_date;
            $student_data[$value->id]['driver_name'] = $value->driver_name;
            $student_data[$value->id]['driver_mobile'] = $value->driver_mobile;
            $student_data[$value->id]['icard_icon'] = $value->icard_icon;
            $student_data[$value->id]['vehicle_type'] = $value->vehicle_type;
            $student_data[$value->id]['distance_from_school_id'] = $value->distance_from_school_id;
            $student_data[$value->id]['distance_from_school'] = $value->distance_from_school;
            $student_data[$value->id]['from_distance'] = $value->from_distance;
            $student_data[$value->id]['distance_rate'] = $value->distance_rate;
            $student_data[$value->id]['mother_mobile'] = $value->mother_mobile;
            $student_data[$value->id]['short_student_name'] = $value->short_student_name;
        }

        return $student_data;
    }
}

if (!function_exists('send_FCM_Notification')) {
    function send_FCM_Notification($to, $message, $sub_institute_id)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        foreach ($to as $val) {
            $fields = [
                'registration_ids' => array($val),
                'notification' => $message,
            ];

            if ($sub_institute_id == 254) {
                $headers = array(
                    'Authorization: key=' . "AAAAIbBYYCQ:APA91bElNhyJBqYr7hVMqFyH5kT3hO7EtiOQIoEN656ZzabihtIQ64PA72mpCuKv59XuMoq1-EDq-oiel1J9zvazDm4Mb2eKdA6k23_IC9cVAfuE5lQDx1jn4wkhst5Heyw0vVVlvN3J",
                    'Content-Type: application/json',
                );
            } else {
                $headers = array(
                    'Authorization: key=' . "AAAApM0aBq0:APA91bEMbTNrawzSIm6Ra-IedYR4PmLZjznNGqmjep6-Opk7mSBha3UssNij8k7AhU4q1m2Y0fIh8bhFHgn3yfsGhS6GWFnKbiBQnICF9lYISJfX9t6cdYskBUyOeJVYW38aRKgg7VkK",
                    'Content-Type: application/json',
                );
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

            $result = curl_exec($ch);

            curl_close($ch);
        }
    }
}

if (!function_exists('sendNotification')) {
    function sendNotification($notification_arr)
    {
        appNotificationModel::insert($notification_arr);
    }
}

if (!function_exists('htmlToPDF')) {
    function htmlToPDF($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf ';
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if (!function_exists('htmlToPDFPortraitLetter')) {
    function htmlToPDFPortraitLetter($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 5 -R 10 -B 5 -T 5 -s letter ';
        // $command = '/usr/local/bin/wkhtmltopdf -L 0 -R 0 -B 0 -T 0.5 --page-height 350mm --page-width 250mm ';
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";
        return exec($command);
    }
}

if (!function_exists('htmlToPDFPortrait')) {
    function htmlToPDFPortrait($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 0 -R 0 -B 0 -T 0.5 -s A4 '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}


if (!function_exists('htmlToPDFLandscape')) {
    function htmlToPDFLandscape($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 0 -R 0 -B 0 -T 0.5 --page-height 250mm --page-width 300mm '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if (!function_exists('htmlToPDFLandscapeCertificate')) {
    function htmlToPDFLandscapeCertificate($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 5 -R 5 -B 5 -T 5 -s A5 --orientation "Landscape" '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if (!function_exists('sendSMS')) {
    function sendSMS($mobile, $text, $sub_institute_id)
    {
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();
        $isError = 0;

        if ($data) {
            $data = $data->toArray();
            $isError = 0;
            $errorMessage = true;
            $text = urlencode($text);
            $data['last_var'] = urlencode($data['last_var']);
            $url = $data['url'] . $data['pram'] . $data['mobile_var'] . $mobile . $data['text_var'] . $text . $data['last_var'];
            $ch = curl_init();

            // Ignore SSL certificate verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $output = curl_exec($ch);

            //Print error if any
            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch);
            }
            curl_close($ch);
        } else {
            $isError = 1;
            $errorMessage = "Please add api details first.";
        }
        $responce = array();
        if ($isError) {
            $responce = array('error' => 1, 'message' => $errorMessage);
        } else {
            $responce = array('error' => 0);
        }

        return $responce;
    }
}

if (!function_exists('LMSSearchChain')) {

    function LMSSearchChain(
        $col,
        $multiple,
        $prefix,
        $standard_id,
        $listed_drop,
        $std_val = "",
        $sub_val = "",
        $chapter_val = "",
        $topic_val = ""
    ) {
        $sub_institute_id = session()->get('sub_institute_id');
        $explod_list = explode(',', $listed_drop);
        $std_name = 'standard';
        $sub_name = 'subject';
        $chapter_name = 'chapter';
        $topic_name = 'topic';

        if ($multiple == 'multiple') {
            $multiple = 'multiple="multiple"';
            $std_name = 'standard[]';
            $sub_name = 'subject[]';
            $chapter_name = 'chapter[]';
            $topic_name = 'topic[]';
        } else {
            if ($multiple == 'single') {
                $multiple = '';
            } else {
                echo "Chain Option Error : Must Provide First Prameter As Single Dropdown Or Multiple.";
            }
        }

        $std_option = "";
        $extra = '';
        if ($prefix == "pre") {
            $extra = " id < $standard_id";
        } elseif ($prefix == "post") {
            $extra = " id > $standard_id";
        } elseif ($prefix == "cross-curriculum") {
            $extra = " 1 = 1";
        }

        $standard = DB::table("standard")
            ->where("sub_institute_id", $sub_institute_id)
            ->whereRaw($extra)
            ->pluck("name", "id");
        $std_option .= "<option value=''>Select Standard</option>";
        foreach ($standard as $id => $val) {
            $selected = '';
            if ($std_val == $id) {
                $selected = 'selected="selected"';
            }

            $std_option .= "<option $selected value=$id>$val</option>";
        }


        $div_option = "";
        $sub_option = "";
        $chapter_option = "";
        $topic_option = "";

        if ($std_val != "") {
            $subjects = DB::table('sub_std_map')
                ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
                ->where("sub_std_map.standard_id", $std_val)
                ->pluck('subject.subject_name', 'subject.id');

            $sub_option = "<option value=''>Select Subject</option>";
            foreach ($subjects as $id => $val) {
                $selected = '';
                if ($sub_val == $id) {
                    $selected = 'selected="selected"';
                }

                $sub_option .= "<option $selected value=$id>$val</option>";
            }
        }

        if ($sub_val != "") {
            $chapters = DB::table('chapter_master')
                ->where([
                    'sub_institute_id' => session()->get('sub_institute_id'), 'subject_id' => $sub_val,
                    "standard_id" => $std_val,
                ])
                ->pluck('chapter_name', 'id');

            $chapter_option = "<option value=''>Select Chapter</option>";
            foreach ($chapters as $id => $val) {
                $selected = '';
                if ($chapter_val == $id) {
                    $selected = 'selected="selected"';
                }
                $chapter_option .= "<option $selected value=$id>$val</option>";
            }
        }

        if ($chapter_val != "") {
            $topic_list = DB::table('topic_master')
                ->where(['sub_institute_id' => session()->get('sub_institute_id'), 'chapter_id' => $chapter_val])
                ->pluck('name', 'id');

            $topic_option = "<option value=''>Select Topic</option>";
            foreach ($topic_list as $id => $val) {
                $selected = '';
                if ($topic_val == $id) {
                    $selected = 'selected="selected"';
                }
                $topic_option .= "<option $selected value=$id>$val</option>";
            }
        }

        $std = '<div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Standard</label>
                        <select name="' . $prefix . $std_name . '" id="' . $prefix . 'standard" class="form-control" ' . $multiple . '>
                            ' . $std_option . '
                        </select>
                    </div>
                </div>';

        $sub = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Subject</label>
                        <select name="' . $prefix . $sub_name . '" id="' . $prefix . 'subject" class="form-control" ' . $multiple . '>
                            ' . $sub_option . '
                        </select>
                    </div>
                </div>';

        $chapter = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Chapter</label>
                        <select name="' . $prefix . $chapter_name . '" id="' . $prefix . 'chapter" class="form-control" ' . $multiple . '>
                            ' . $chapter_option . '
                        </select>
                    </div>
                </div>';

        $topic = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Topic</label>
                        <select name="' . $prefix . $topic_name . '" id="' . $prefix . 'topic" class="form-control" ' . $multiple . '>
                            ' . $topic_option . '
                        </select>
                    </div>
                </div>';

        $html = '<div class="row">';

        if (in_array('std', $explod_list)) {
            $html .= $std;
        }

        if (in_array('sub', $explod_list)) {
            $html .= $sub;
        }

        if (in_array('chapter', $explod_list)) {
            $html .= $chapter;
        }

        if (in_array('topic', $explod_list)) {
            $html .= $topic;
        }

        $html .= '</div>';
        echo $html;
    }
}

if (!function_exists('getGrade')) {
    function getGrade($grade_arr, $total_mark, $total_gain_mark)
    {
        if ($total_mark == 0) {
            return "-";
        }

        $per = round((100 * $total_gain_mark) / $total_mark, 0);

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
}

if (!function_exists('getGradeComment')) {
    function getGradeComment($grade_arr, $total_mark, $total_gain_mark)
    {
        if (!is_numeric($total_mark) || !is_numeric($total_gain_mark)) {
            return 0;
        }
        $per = round((100 * $total_gain_mark) / $total_mark, 0);
        foreach ($grade_arr as $id => $data) {
            if (!isset($comment)) {
                if ($per >= $data['breakoff']) {
                    $comment = $data['comment'];
                }
            }
        }
        if (!isset($comment)) {
            $comment = "-";
        }

        return $comment;
    }

}


if (!function_exists('getGradeScale')) {
    function getGradeScale()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $standard_id = session()->get('standard');

        $ret_grade = DB::table('result_std_grd_maping as sgm')
            ->join('grade_master_data as dt', 'dt.grade_id', '=', 'sgm.grade_scale')
            ->select('dt.*')
            ->where('sgm.standard', $standard_id)
            ->where('sgm.sub_institute_id', $sub_institute_id)
            ->where('dt.syear', $syear)
            ->orderBy('dt.breakoff', 'DESC')
            ->get()->toArray();

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
}

if (!function_exists('getBestOf')) {
    function getBestOf($elemArr)
    {
        $newArr = array();
        rsort($elemArr);
        $srNo = 0;
        foreach ($elemArr as $value) {
            $srNo++;
            if ($srNo <= 2) {
                $newArr[] = $value;
            }
        }
        return $newArr;
    }
}

if (!function_exists('get_string')) {

    function get_string($arg,$type='', $sub_institute_id = '')
    {
        if($sub_institute_id==''){
        $sub_institute_id = session()->get('sub_institute_id');
        }
        $strings = DB::table('app_language')->whereRaw('sub_institute_id = 0 and string = "' . $arg . '"')->value('value');
        $strings_id = DB::table('app_language')->whereRaw('sub_institute_id = 0 and string = "' . $arg . '"')->groupBy('menu_id')->value('menu_id');        
       
        // dd($strings);
        if ($type === 'menu_id') {
            $menu_id = $arg;
            $normClature = normClature::whereRaw('sub_institute_id=' . $sub_institute_id . ' and status=1')
                ->where('menu_id', $menu_id)
                ->first();
        } else {
            $requestValue = $arg;
            $normClature = normClature::whereRaw('sub_institute_id=' . $sub_institute_id . ' and status=1')
                ->where('string', $requestValue)
                ->first();
        }

        if ($normClature) {
            if (!empty($normClature->value)) {
                return $normClature->value;
            } else {
                return $strings ?? '';
            }
        } else {
            return $strings ?? '';
        }
    }

    function get_school_details($grade = '', $std = '', $div = '')
    {
        $marking_period_id = session()->get('term_id');
        $get_name_data = DB::table('academic_section as ac')
            ->join('standard as s', function ($join) use ($marking_period_id) {
                $join->whereRaw('s.grade_id = ac.id AND ac.sub_institute_id = s.sub_institute_id');
                    // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                    //     $query->where('s.marking_period_id', $marking_period_id);
                    // });
            })
            ->selectRaw("ac.title AS academic_name, s.name AS std_name")
            ->where('ac.sub_institute_id', session()->get('sub_institute_id'))
            ->when($grade, function ($q) use ($grade) {
                $q->where('ac.id', $grade);
            })
            ->when($std, function ($q) use ($std) {
                $q->where('s.id', $std);
            });

        if ($div) {
            $get_name_data->join('std_div_map as sd', function ($join) {
                $join->whereRaw('sd.standard_id = s.id AND sd.sub_institute_id = s.sub_institute_id');
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = sd.division_id');
            })->when($div, function ($q) use ($div) {
                $q->where('d.id', $div);
            })->selectRaw("d.name AS div_name");
        }

        $get_name_data = $get_name_data->get()->toArray();

        $result = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('syear', session()->get('syear'))
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->groupByRaw("receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number")
            ->get()->toArray();

        $receipt_book_arr = [];
        $html = '';
        foreach ($result as $temp_id => $receipt_detail) {
            $receipt_book_arr = $receipt_detail;
        }

        // $image_path = "http://" . $_SERVER['HTTP_HOST'] . "/storage/fees/" . $receipt_book_arr->receipt_logo;
        if (count($result) > 0) {
            $html .= '<table style="margin:0 auto;" width="80%">
        <tbody>
            <tr>';
                // <td style=" width: 165px;text-align: center;" align="left">';

        // $html .= '    <img style="width: 100px;height: 90px;margin: 0;" src="' . $image_path . '" alt="SCHOOL LOGO">';
        // $html .= '</td>';
            $html .= '<td colspan="3" style="text-align:center !important;" align="center"> ';
            if ($receipt_book_arr->receipt_line_1 != '') {
                $html .= '<span style=" font-size: 26px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important;">' . $receipt_book_arr->receipt_line_1 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_2 != '') {
                $html .= '<span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">' . $receipt_book_arr->receipt_line_2 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $html .= '<span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">' . $receipt_book_arr->receipt_line_3 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_4 != '') {
                $html .= '<span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important;">' . $receipt_book_arr->receipt_line_4 . '</span><br>';
            }
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '<tr>
    <td>&nbsp;</td>
  </tr>';
            $html .= '<tr>';
            if (isset($get_name_data) && !$std == '') {

                if (isset($get_name_data[0]->academic_name)) {
                    $html .= '
         <td colspan="3" style="text-align:center !important;" align="center">
        <span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important;">Academic Section : ' . $get_name_data[0]->academic_name . '</span>';
                }

                if (isset($get_name_data[0]->std_name)) {
                    $html .= ' | <span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">Standard : ' . $get_name_data[0]->std_name . '</span>';
                }

                if (isset($get_name_data[0]->div_name)) {
                    $html .= ' | <span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">Division : ' . $get_name_data[0]->div_name . '</span>';
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
        }
        return $html;
    }

    function fees_config()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $fees_config = DB::table('fees_config_master')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->first();
        return $fees_config;
    }

    function get_map_month()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $data = map_year::where([
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
        ])->get()->toArray();

        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $months_arr = [];

        if ($data[0]['type'] == "yearly_fees") {
            $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
        } else if ($data[0]['type'] == "half_year_fees") {
            $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
            $sixmonths = ($start_month + 6);
            $months_arr[$sixmonths . $syear] = $months[$sixmonths] . '/' . $syear;

        } else if ($data[0]['type'] == "quarterly_fees") {
            for ($i = $start_month; $i <= 12; $i++) {
                if ($start_month <= 12) {
                    $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
                    $start_month = ($start_month + 3);
                } else {
                    $start_month = 1;
                    ++$syear;
                    $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
                    break;
                }
            }
        } else {
            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
                if ($start_month == 12) {
                    $start_month = 0;
                    ++$syear;
                }
                ++$start_month;
            }
        }
        $get_month_head = [];
        if (!empty($months_arr)) {
            foreach ($months_arr as $key => $val) {
                $get_month = DB::table('fees_month_header')->where(['sub_institute_id' => $sub_institute_id, 'month_id' => $key])->first();
                $get_month_head[$key] = $get_month->header;
                $numericValues = $alphabeticValues = [];

                foreach ($get_month_head as $key => $value) {
                    if (is_numeric($value)) {
                        $numericValues[$key] = $value;
                    } else {
                        $alphabeticValues[$key] = $value;
                    }
                }

                asort($numericValues, SORT_NUMERIC);
                asort($alphabeticValues, SORT_NATURAL);

                $get_month_head = $numericValues + $alphabeticValues;

            }
        } else {
            $get_month_head = $months_arr;
        }
        return $get_month_head;
    }

    if (!function_exists('MappedStdDiv')) {

        function MappedStdDiv($syear = '',$sub_institute_id='')
        {
            return DB::table('std_div_map as sdm')
            ->join('standard as std','std.id','=','sdm.standard_id')
            ->join('division as d','d.id','=','sdm.division_id')
            ->selectRaw('std.id as std_id,std.name as standard_name,d.id as div_id,d.name as division_name,std.grade_id')   
            ->where('sdm.sub_institute_id',$sub_institute_id)         
            ->get()->toArray();
        }
    }

    if (!function_exists('employeeDetails')) {

        function employeeDetails($sub_institute_id='',$employee_id='',$status='',$department_id='')
        {
            // return $status;exit;
            $empData= tbluserModel::join('tbluserprofilemaster as upm', 'upm.id', '=', 'tbluser.user_profile_id')
            ->selectRaw('tbluser.*,IfNULL(tbluser.first_name, "-") as first_name, IFNULL(tbluser.last_name, "-") as last_name,IFNULL(tbluser.middle_name, "-") as middle_name, tbluser.employee_no,tbluser.sub_institute_id, IfNULL(upm.name,"-") as user_profile,tbluser.department_id as department_id')
            ->where('tbluser.sub_institute_id', $sub_institute_id);

            if($status!==0){
                $empData->where('tbluser.status', 1);
            }
           
            $empData = $empData->when($employee_id!='',function($query) use($employee_id){
                $query->whereRaw('tbluser.id IN ('.$employee_id.')');
            })
            ->when($department_id!='',function($query) use($department_id){
                $query->whereRaw('tbluser.department_id IN ('.$department_id.')');
            })
            ->orderBy('tbluser.first_name')
            // ->take(20)  
            ->groupBy('tbluser.id')
            ->get()
            ->toArray();

            $empDatas=[];
            foreach($empData as $key => $value){
                $dep = DB::table('hrms_departments')->where('id',$value['department_id'])->where('status',1)->first();
                $empDatas[$key] = $value;
                $empDatas[$key]['department'] = (isset($dep->department)) ? $dep->department : '-';
            }
            return $empDatas;
        }
    }
    
    // hrms department with employees
    if (!function_exists('HrmsDepartments')) {

        function HrmsDepartments($col="",$depMultiple="",$dep_ids="",$empMultiple="",$emp_ids="",$inactive="")
        {
            $sub_institute_id= session()->get('sub_institute_id');
            if($col==""){
                $col=3;
            }
            $depname = "department_id";
            $dep_idsArr = $emp_idsArr= [];
            if($depMultiple!=""){
                $depname = "department_id[]";
                if($dep_ids!=''){
                 $dep_idsArr = $dep_ids;
                }
            }
            // dd($dep_idsArr);
            //get all department Lists
            $depLists =DB::table('hrms_departments')->where('status',1)->whereNull('deleted_at')->pluck('department','id');
            // make select for department
            $SelectDepartment ="<div class='col-md-".$col." form-group'>
                <label>Select Department</label>
                <select class='form-control' name='".$depname."' id='department_ids' ".$depMultiple.">
                <option value='0'>Select</option>";
                foreach ($depLists as $dep_id => $dep_name) {
                    $selected = "";
                    if($depMultiple!="" && $dep_idsArr!=''){
                        if(in_array($dep_id,$dep_idsArr)){
                            $selected="selected";
                        }
                    } 
                    if(isset($dep_ids) && $dep_id == $dep_ids){
                        $selected="selected";
                    }
                    $SelectDepartment .= "<option value=".$dep_id." ".$selected.">".$dep_name."</option>";
                }
            $SelectDepartment .="</select>
            </div>";
            
            // for employee list
            $empname = "emp_id";
            if($empMultiple!=""){
                $empname = "emp_id[]";
                if($emp_ids!=''){
                    $emp_idsArr = $emp_ids;
                    $dep_idsArr = [$dep_ids];
                }
            }else if($depMultiple=="" && isset($dep_ids)){
                $dep_idsArr = [$dep_ids];
            }
            $empData = [];

            if(isset($dep_ids) && $dep_ids!=0){
                $empData =DB::table('tbluser')->join('tbluserprofilemaster as upm', 'upm.id', '=', 'tbluser.user_profile_id')
                ->selectRaw('tbluser.id,CONCAT_WS(" ",COALESCE(tbluser.first_name, "-"),COALESCE(tbluser.last_name, "-")) as full_name,tbluser.sub_institute_id, IfNULL(upm.name,"-") as user_profile')
                ->where('tbluser.sub_institute_id', $sub_institute_id)
                ->whereIn('tbluser.department_id', $dep_idsArr)
                ->where('tbluser.department_id','!=',0)
                ->where('tbluser.status', 1)
                ->orderBy('tbluser.first_name')
                ->groupBy('tbluser.id')
                ->get()
                ->toArray(); 
            }
            if($empMultiple!="none"){
                $SelectDepartment .= "<div class='col-md-".$col." form-group'>
                <label>Select Employee</label>
                <select name='".$empname."' id='emp_id' class='form-control' ".$empMultiple.">
                <option value=0>select employee</option>";
                if(!empty($empData)){
                    foreach ($empData as $key => $value) {
                        $selected = "";
                        if($emp_idsArr!=''){
                            if(in_array($value->id,$emp_idsArr)){
                                $selected="selected";
                            }
                        } 
                        if(isset($emp_ids) && $value->id == $emp_ids){
                            $selected="selected";
                        }
                        $SelectDepartment .= "<option value=".$value->id."  ".$selected.">".$value->full_name." (".$value->user_profile.")</option>";
                    }
                }
                $SelectDepartment .= "</select>
            </div>";
            }
            return $SelectDepartment;
        }
    }
}
