<?php

namespace App\Helpers;


use App\Models\easy_com\manage_sms_api\manage_sms_api;
use App\Models\fees\fees_title\fees_title;
use App\Models\fees\map_year\map_year;
use App\Models\student\appNotificationModel;
use App\Models\student\tblstudentModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

if (! function_exists('is_mobile')) {

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
if (! function_exists('ValidateInsertData')) {

    function ValidateInsertData($table, $type = 'insert')
    {

        $files_arr = ["Logo", "Image"];

        $columns = DB::select("SHOW COLUMNS FROM ".$table);

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
if (! function_exists('SearchChain')) {

    function SearchChain($col, $multiple, $listed_drop, $grade_val = "", $std_val = "", $div_val = "")
    {

        $path = URL::current();
        preg_match("/[^\/]+$/", $path, $matches);
        $module_name = $matches[0];

        $module_array = array(
            '1' => 'student_homework',
        );

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

        if ($multiple == 'multiple') {
            $multiple = 'multiple="multiple"';
            $grade_name = 'grade[]';
            $std_name = 'standard[]';
            $div_name = 'division[]';
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
        if (isset($classTeacherGrdArr) && ! in_array($module_name, $module_array)) {
            if (count($classTeacherGrdArr) > 0) {
                $query->whereIn('id', $classTeacherGrdArr);
            } else {
                $query->where('id', null);
            }
        }
        //END Check for class teacher assigned standards

        //START Check for subject teacher assigned
        $subjectTeacherGrdArr = session()->get('subjectTeacherGrdArr');
        // dd($subjectTeacherGrdArr);
        if (isset($subjectTeacherGrdArr) && (! isset($classTeacherGrdArr) || in_array($module_name, $module_array))) {
            if (count($subjectTeacherGrdArr) > 0) {
                $query->whereIn('id', $subjectTeacherGrdArr);
            } else {

                $query->where('id', null);
            }
        }
        //END Check for subject teacher assigned

        $academic_section = $query->pluck("title", "id");

        foreach ($academic_section as $id => $val) {
            $selected = '';
            if (is_array($grade_val)) {
                if (in_array($id, $grade_val)) {
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
                $query->whereIn("grade_id", $grade_val);

                //START Check for class teacher assigned standards
                $classTeacherStdArr = session()->get('classTeacherStdArr');
                if (isset($classTeacherStdArr) && ! in_array($module_name, $module_array)) {
                    if (count($classTeacherStdArr) > 0) {
                        $query->whereIn('id', $classTeacherStdArr);
                    } else {
                        $query->where('id', null);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherStdArr = session()->get('subjectTeacherStdArr');
                if (isset($subjectTeacherStdArr) && (! isset($classTeacherStdArr)
                        || in_array($module_name, $module_array))) {
                    if (count($subjectTeacherStdArr) > 0) {
                        $query->orwhereIn('id', $subjectTeacherStdArr);
                    } else {
                        $query->orwhere('id', null);
                    }
                }
                //END Check for subject teacher assigned

                $standard = $query->pluck("name", "id");

            } else {
                $query = DB::table('standard');
                $query->where("grade_id", $grade_val);

                //START Check for class teacher assigned standards
                $classTeacherStdArr = session()->get('classTeacherStdArr');
                if (isset($classTeacherStdArr) && ! in_array($module_name, $module_array)) {
                    if (count($classTeacherStdArr) > 0) {
                        $query->whereIn('id', $classTeacherStdArr);
                    } else {
                        $query->where('id', null);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherStdArr = session()->get('subjectTeacherStdArr');
                if (isset($subjectTeacherStdArr) && (! isset($classTeacherStdArr)
                        || in_array($module_name, $module_array))) {
                    if (count($subjectTeacherStdArr) > 0) {
                        // $query->orwhereIn('id',$subjectTeacherStdArr);          
                        $query->whereIn('id', $subjectTeacherStdArr);
                    } else {
                        // $query->orwhere('id',null);           
                        $query->where('id', null);
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
                if (isset($classTeacherDivArr) && ! in_array($module_name, $module_array)) {
                    if (count($classTeacherDivArr) > 0) {
                        $query->whereIn('division.id', $classTeacherDivArr);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherDivArr = session()->get('subjectTeacherDivArr');
                if (isset($subjectTeacherDivArr) && (! isset($subjectTeacherDivArr)
                        || in_array($module_name, $module_array))) {
                    if (count($subjectTeacherDivArr) > 0) {
                        $query->orwhereIn('division.id', $subjectTeacherDivArr);
                    }
                }
                //END Check for subject teacher assigned

                $division = $query->pluck('division.name', 'division.id');

            } else {
                $query = DB::table('std_div_map');
                $query->join('division', 'division.id', '=', 'std_div_map.division_id');
                $query->where("std_div_map.standard_id", $std_val);
                //START Check for class teacher assigned standards
                $classTeacherDivArr = session()->get('classTeacherDivArr');
                if (isset($classTeacherDivArr) && ! in_array($module_name, $module_array)) {
                    if (count($classTeacherDivArr) > 0) {
                        $query->whereIn('division.id', $classTeacherDivArr);
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherDivArr = session()->get('subjectTeacherDivArr');

                if ($subjectTeacherDivArr != "" && ($classTeacherDivArr == ""
                        || in_array($module_name, $module_array))) {
                    if (count($subjectTeacherDivArr) > 0) {
                        $query->whereIn('division.id', $subjectTeacherDivArr);
                    }
                }
                //END Check for subject teacher assigned

                $division = $query->pluck('division.name', 'division.id');
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

        $grade = '<div class="col-md-'.$col.'">
                    <div class="form-group">                        
                        <label>Select Section:</label> 
                        <select name="'.$grade_name.'" id="grade" class="form-control" '.$multiple.'>
                            '.$option.'
                        </select>

                    </div>
                </div>';

        $std = '<div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label>Select Standard:</label>                     
                        <select name="'.$std_name.'" id="standard" class="form-control" '.$multiple.'>
                            '.$std_option.'
                        </select>

                    </div>
                </div>';

        $div = ' <div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label>Select Division:</label>
                        <select name="'.$div_name.'" id="division" class="form-control" '.$multiple.'>
                            '.$div_option.'
                        </select>

                    </div>
                </div>';

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
        $html .= '';
        echo $html;
    }
}
if (! function_exists('SearchChainSubject')) {

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

        $grade = '<div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label for="title">Select Grade:</label>
                        <select name="'.$grade_name.'" id="gradeS" class="form-control" '.$multiple.'>
                            '.$option.'
                        </select>
                    </div>
                </div>';

        $std = '<div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label for="title">Select Standard:</label>
                        <select name="'.$std_name.'" id="standardS" class="form-control" '.$multiple.'>
                            '.$std_option.'
                        </select>
                    </div>
                </div>';

        $sub = ' <div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label for="title">Select Subject:</label>
                        <select name="'.$sub_name.'" id="subject" class="form-control" '.$multiple.'>
                            '.$sub_option.'
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
if (! function_exists('TermDD')) {

    function TermDD($selected_val = "", $col = 4)
    {
        $option = "<option value=''>Select Term</option>";

        $academic_year = DB::table("academic_year")
            ->where([
                "sub_institute_id" => session()->get('sub_institute_id'),
                "syear"            => session()->get('syear'),
            ])
            ->pluck("title", "term_id");

        foreach ($academic_year as $id => $val) {
            $selected = "";
            if ($selected_val == $id) {
                $selected = 'selected="selected"';
            }

            $option .= "<option $selected value=$id>$val</option>";
        }

        $term = '<div class="col-md-'.$col.' form-group">
                    <label for="title">Select Term:</label>
                    <select name="term" id="term" class="form-control">
                        '.$option.'
                    </select>
                </div>';

        $html = $term;

        echo $html;
    }
}
if (! function_exists('SearchStudent')) {

    function SearchStudent($grade, $standard = "", $div = "", $sub_institute_id = "", $syear = "", $roll_no = "")
    {
        if ($sub_institute_id == '') {
            $sub_institute_id = session()->get('sub_institute_id');
        }

        if ($syear == '') {
            $syear = session()->get('syear');
        }

        $grade_arr = array();
        $standard_arr = array();
        $div_arr = array();
        $classTeacherStdArr = session()->get('classTeacherStdArr');
        $classTeacherDivArr = session()->get('classTeacherDivArr');

        if ($grade != '') {
            $grade_arr = (array) $grade;
        }
        if ($standard != '') {
            $standard_arr = (array) $standard;
        }
        if ($div != '') {
            $div_arr = (array) $div;
        }

        $enrollment_join = array(
            'se.student_id'       => 'ts.id',
            'se.sub_institute_id' => 'ts.sub_institute_id',
        );
        $grade_join = array(
            'acs.id'               => 'se.grade_id',
            'acs.sub_institute_id' => 'se.sub_institute_id',
        );
        $std_join = array(
            's.id'               => 'se.standard_id',
            's.sub_institute_id' => 'se.sub_institute_id',
        );
        $div_join = array(
            'd.id'               => 'se.section_id',
            'd.sub_institute_id' => 'se.sub_institute_id',
        );

        $select_fields = "ts.*,se.syear,se.student_id,se.grade_id,
                se.standard_id,se.section_id,se.student_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number";
        $select_fields = preg_replace('/\s+/', '', $select_fields);
        $where = array(
            'se.syear'            => $syear,
            'ts.sub_institute_id' => $sub_institute_id,
            'se.end_date'         => null,
        );

        $query = tblstudentModel::from('tblstudent as ts');
        $columns = explode(',', $select_fields);
        $columns[] = "s.name as standard_name";
        $columns[] = "s.medium as medium";
        $columns[] = "d.name as division_name";

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

        //START Check for class teacher assigned standards
        $extraRaw = " 1 = 1 ";

        if (isset($classTeacherStdArr) && count($standard_arr) < 0) {
            if (count($classTeacherStdArr) > 0) {
                $extraRaw = "s.id IN (".implode(",", $classTeacherStdArr).")";
            } else {
                $extraRaw = "s.id IN (' ')";
            }
        }

        if (isset($classTeacherDivArr)) {
            if (count($classTeacherDivArr) > 0 && count($div_arr) < 0) {
                $extraRaw .= " and d.id IN (".implode(",", $classTeacherDivArr).")";
            }
        }
        //END Check for class teacher assigned standards


        if ($roll_no != '') {
            $extraRaw .= " AND ts.roll_no = '".$roll_no."' ";
        }

        $query->whereraw($extraRaw);

        $query->orderBy('ts.roll_no');

        return $query->get($columns)->toArray();
    }
}
if (! function_exists('FeeMonthId')) {

    function FeeMonthId()
    {
        $data = map_year::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ])->get()->toArray();
        if (count($data) == 0) {
            return array();
            exit;
        }

        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = [
            1  => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $months_arr = [];
        $syear = session()->get('syear');

        for ($i = 1; $i <= 12; $i++) {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
            if ($start_month == 12) {
                $start_month = 0;
                ++$syear;
            }
            ++$start_month;
        }

        return $months_arr;
    }
}
if (! function_exists('FeeBreackoff')) {

    function FeeBreackoff($student_ids)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if ($sub_institute_id != '' && $syear != '') {
            $sub_institute_id = $sub_institute_id;
            $syear = $syear;
        } else {
            $sub_institute_id = request()->get('sub_institute_id');
            $syear = request()->get('syear');
        }

        return DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id');
            })->join('academic_section as g', function ($join) {
                $join->whereRaw('g.id = se.grade_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = se.standard_id');
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw(' d.id = se.section_id');
            })->leftJoin('student_quota as sq', function ($join) {
                $join->whereRaw('sq.id = se.student_quota AND sq.sub_institute_id = se.sub_institute_id');
            })->join('fees_breackoff as fb', function ($join) use ($syear, $sub_institute_id) {
                $join->whereRaw("fb.syear = '".$syear."' AND fb.admission_year = s.admission_year AND fb.quota = se.student_quota
                 AND fb.grade_id = se.grade_id AND fb.standard_id = se.standard_id AND fb.sub_institute_id = '".$sub_institute_id."'");
            })->selectRaw("s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,
                sq.title AS stu_quota,se.start_date,se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,se.drop_remarks,
                se.term_id,se.remarks,se.admission_fees, se.house_id,se.lc_number,sum(fb.amount) bkoff,st.name standard_name,
                d.name as division_name,fb.month_id,RIGHT(fb.month_id, 4) as sort_year,
                CAST(SUBSTRING(fb.month_id,1,CHAR_LENGTH(fb.month_id)-4) as int) as sort_month")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->whereIn('s.id', $student_ids)
            ->groupByRaw('s.id,fb.month_id')
            ->orderByRaw('sort_year,sort_month')->get()->toArray();
    }
}

if (! function_exists('FeeBreakoffHeadWise')) {

    function FeeBreakoffHeadWise($student_ids, $from_date = null, $to_date = null)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $stud_arr = implode(',', $student_ids);
        $extra_where = " AND s.id in (".$stud_arr.")";

        $result = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id');
            })->join('academic_section as g', function ($join) {
                $join->whereRaw('g.id = se.grade_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = se.standard_id');
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw(' d.id = se.section_id');
            })->join('fees_breackoff as fb', function ($join) use ($syear, $sub_institute_id) {
                $join->whereRaw("fb.syear = '".$syear."' AND fb.admission_year = s.admission_year AND fb.quota = se.student_quota
                 AND fb.grade_id = se.grade_id AND fb.standard_id = se.standard_id AND fb.sub_institute_id = '".$sub_institute_id."'");
            })->join('fees_title as ft', function ($join) {
                $join->whereRaw('fb.fee_type_id = ft.id');
            })->leftJoin('admission_registration as ar', function ($join) {
                $join->whereRaw('ar.enrollment_no = s.enrollment_no AND ar.sub_institute_id = s.sub_institute_id');
            })->leftJoin('admission_enquiry as ae', function ($join) {
                $join->whereRaw('ae.id = ar.enquiry_id AND ar.sub_institute_id = ae.sub_institute_id');
            })->selectRaw("s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,
                se.start_date,se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,se.drop_remarks,se.term_id,
                se.remarks,se.admission_fees,se.house_id,se.lc_number,fb.amount,st.name standard_name,d.name as division_name,
                fb.month_id,ft.display_name,ft.fees_title,'' as breakoff,s.father_name,s.mother_name,
                RIGHT(fb.month_id, 4) as sort_year,CAST(SUBSTRING(fb.month_id,1,CHAR_LENGTH(fb.month_id)-4) as int) as sort_month,
                ae.fees_circular_form_no")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->whereIn('s.id', $student_ids)
            ->groupByRaw('s.id,fb.month_id,fb.fee_type_id')
            ->orderByRaw('sort_year,sort_month')->get()->toArray();


        $data = array();
        $student_data = array();
        foreach ($result as $key => $value) {
            $fees_title = $value->fees_title;
            $month_id = $value->month_id;
            $sub_institute_id = session()->get('sub_institute_id');

            $request = $_REQUEST;


            $paid_fees = $paid_fees = DB::table('fees_collect')
                ->selectRaw("sum(ifnull($fees_title,0)) total_paid,receiptdate")
                ->where([
                    'term_id'          => $month_id,
                    'sub_institute_id' => $sub_institute_id,
                    'is_deleted'       => 'N',
                    'student_id'       => $value->id,
                ])->when(isset($request['from_date'], $request['to_date']), function ($q) use ($request) {
                    $q->where('fees_collect.receiptdate', '<=', $request['to_date']);
                })->get()->toArray();

            $data[$value->id][$value->month_id][$value->fees_title]['amount'] = $value->amount - $paid_fees[0]->total_paid;

            // Start Added by 18/05/2021 for getting paid amount in Overall Fees Head Wise report
            if (isset($paid_fees[0]->total_paid) && $paid_fees[0]->total_paid != '') {
                $data[$value->id][$value->month_id][$value->fees_title]['paid_amount'] = $paid_fees[0]->total_paid;
            } else {
                $data[$value->id][$value->month_id][$value->fees_title]['paid_amount'] = 0;
            }
            // End Added by 18/05/2021 for getting paid amount in Overall Fees Head Wise report


            $data[$value->id][$value->month_id][$value->fees_title]['title'] = $value->display_name;
        }

        foreach ($result as $key => $value) {

            $student_data[$value->id]['id'] = $value->id;
            $student_data[$value->id]['enrollment_no'] = $value->enrollment_no;
            $student_data[$value->id]['surname'] = $value->last_name;
            $student_data[$value->id]['student_name'] = $value->first_name." ".$value->middle_name;
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

if (! function_exists('getStringOfAmount')) {

    function getStringOfAmount($number)
    {
        $no = round($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = [];
        $words = [
            '0'  => '', '1' => 'one', '2' => 'two',
            '3'  => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
            '7'  => 'seven', '8' => 'eight', '9' => 'nine',
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
                $str[] = ($number < 21) ? $words[$number].
                    " ".$digits[$counter].$plural." ".$hundred : $words[floor($number / 10) * 10]
                    ." ".$words[$number % 10]." "
                    .$digits[$counter].$plural." ".$hundred;
            } else {
                $str[] = null;
            }
        }

        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ?
            ".".$words[$point / 10]." ".
            $words[$point = $point % 10] : '';

        $returnValue = $result;
        if ($points != '') {
            $returnValue .= ".".$points;
        }

        return ucwords($returnValue)." Only";
    }
}

if (! function_exists('ClassTeacherSearch')) {

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
                    if (strtoupper(session()->get('user_profile_name')) != 'SCHOOL ADMIN'
                        && strtoupper(session()->get('user_profile_name')) != 'ADMIN') {
                        $q->whereRaw('1 != 1');
                    }
                }
            })->get()->toArray();

        $returnHtml = '<select name="standard_division" class="form-control" required>';
        $returnHtml .= '<option value=""> Select Standard Division </option>';

        foreach ($result as $key => $value) {
            $newValue = $value->standard_id."||".$value->division_id;

            $selected = '';
            if ($newValue == $stdiv) {
                $selected = 'selected="selected"';
            }
            $returnHtml .= "<option value='".$newValue."' ".$selected.">".$value->standard_name." - ".$value->division_name."</option>";
        }

        $returnHtml .= "</select>";

        echo $returnHtml;
    }
}
if (! function_exists('OtherBreackOff')) {

    function OtherBreackOff($student_id_arr, $month_arr, $other_bf_amount = '', $from_date = null, $to_date = null)
    {

        $student_id = $student_id_arr[0];
        $moth_ids = implode(',', $month_arr);

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $fees_breckoff = DB::table('fees_breakoff_other')
            ->whereRaw('*,sum(amount) tot_amount')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('student_id', $student_id)
            ->where('month_id', $month_arr)
            ->groupByRaw('fee_type_id,month_id')->get()->toArray();

        //START for fees over all headwise report
        $extra_condition = '';

        if (isset($_REQUEST['from_date']) && isset($_REQUEST['to_date'])) {
            $extra_condition .= " AND receiptdate <= '".$_REQUEST['to_date']."' "; //AND receiptdate >= '".$_REQUEST['from_date']."' 
        }
        //END for fees over all headwise report

        $final_bk = $other_fees_final_bk = array();
        foreach ($fees_breckoff as $id => $arr) {
            $fees_title = $arr->fee_type_id;
            $month_id = $arr->month_id;

            $request = $_REQUEST;

            $paid_fees = DB::table('fees_paid_other')
                ->selectRaw("sum(ifnull($fees_title,0)) total_paid")
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
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear'            => session()->get('syear'),
                'fees_title_id'    => 1,
            ])->get()->toArray();

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

if (! function_exists('OtherBreackOffHead')) {

    function OtherBreackOffHead()
    {

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        return DB::table('fees_title')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('fees_title_id', 1)->get()->toArray();
    }
}
if (! function_exists('OtherBreackOfMonth')) {

    function OtherBreackOfMonth($student_id_arr)
    {

        $student_id = $student_id_arr[0];
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $fees_title = DB::table('fees_breakoff_other')
            ->selectRaw('sum(amount) as tot_amount,month_id')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->where('student_id', $syear)
            ->groupBy('month_id')->get()->toArray();

        $responce_arr = array();
        foreach ($fees_title as $id => $arr) {
            $responce_arr[$arr->month_id] = $arr->tot_amount;
        }

        return $responce_arr;
    }
}

if (! function_exists('OtherBreackOfMonthHead')) {

    function OtherBreackOfMonthHead($student_id_arr, $month_arr)
    {
        $student_id = $student_id_arr[0];

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

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

if (! function_exists('getCountDays')) {

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

if (! function_exists('getStudents')) {

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
        $extra_where = " AND s.id in (".$stud_arr.")";

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
            })->leftJoin('tblstudent_tc_details as tc', function ($join) {
                $join->whereRaw('tc.sub_institute_id = s.sub_institute_id AND tc.student_id = s.id');
            })->leftJoin('religion as r', function ($join) {
                $join->whereRaw('r.id = s.religion');
            })->leftJoin('caste as c', function ($join) {
                $join->whereRaw('c.id = s.cast');
            })->leftJoin('transport_map_student as tms', function ($join) {
                $join->whereRaw('tms.student_id = s.id AND tms.sub_institute_id = s.sub_institute_id');
            })->leftJoin('transport_vehicle as tv', function ($join) {
                $join->whereRaw('tv.id = tms.from_bus_id AND tms.sub_institute_id = tv.sub_institute_id');
            })->leftJoin('transport_driver_detail as td', function ($join) {
                $join->whereRaw('td.id = tv.driver AND td.sub_institute_id = tms.sub_institute_id');
            })->leftJoin('transport_kilometer_rate as tkr', function ($join) {
                $join->whereRaw('tkr.id = s.distance_from_school AND tkr.sub_institute_id = s.sub_institute_id');
            })
            ->selectRaw("tc.*,s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,
                se.start_date,se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,se.drop_remarks,se.term_id,
                se.remarks,se.admission_fees,se.house_id,se.lc_number,st.name standard_name,s.city,se.standard_id,se.section_id,
                se.grade_id,d.name as division_name,s.father_name,s.mother_name,ss.SchoolName as school_name,ss.Mobile as school_mobile,
                ss.Logo as school_image,ss.ReceiptAddress as school_address,(CASE WHEN s.gender = 'M' then 'male' else 'female' end) as gender,
                r.religion_name,c.caste_name,s.subcast,s.affiliation_no,s.school_code,s.admission_date,td.first_name AS driver_name,
                td.mobile AS driver_mobile,td.icard_icon,s.mother_mobile,CONCAT_WS(' ',s.first_name,CONCAT(SUBSTRING(s.father_name,1,1),'.'),
                s.last_name) as short_student_name,tv.vehicle_type,tkr.id as distance_from_school_id,tkr.distance_from_school,
                tkr.from_distance,IF(tv.vehicle_type = 'Van',tkr.van_new,tkr.rick_new) AS distance_rate")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->whereIn('s.id', $stud_arr)
            ->groupBy('s.id')->get()->toArray();

        $student_data = array();
        foreach ($result as $key => $value) {
            $student_data[$value->id]['id'] = $value->id;
            $student_data[$value->id]['enrollment_no'] = $value->enrollment_no;
            $student_data[$value->id]['student_name'] = $value->first_name." ".$value->last_name;
            $student_data[$value->id]['student_full_name'] = $value->first_name." ".$value->middle_name." ".$value->last_name;
            $student_data[$value->id]['gender'] = $value->gender;
            $student_data[$value->id]['mobile'] = $value->mobile;
            $student_data[$value->id]['dob'] = $value->dob;
            $student_data[$value->id]['admission_year'] = $value->admission_year;
            $student_data[$value->id]['address'] = $value->address;
            $student_data[$value->id]['standard_name'] = $value->standard_name;
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
            $student_data[$value->id]['if_to_which_class'] = $value->if_to_which_class;
            $student_data[$value->id]['month_up_paid_school_dues'] = $value->month_up_paid_school_dues;
            $student_data[$value->id]['admission_under'] = $value->admission_under;
            $student_data[$value->id]['total_working_days'] = $value->total_working_days;
            $student_data[$value->id]['total_working_days_present'] = $value->total_working_days_present;
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

if (! function_exists('send_FCM_Notification')) {
    function send_FCM_Notification($to, $message)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        foreach ($to as $val) {
            $fields = [
                'registration_ids' => array($val),
                'notification'     => $message,
            ];


            $headers = array(
                'Authorization: key='."AAAApM0aBq0:APA91bEMbTNrawzSIm6Ra-IedYR4PmLZjznNGqmjep6-Opk7mSBha3UssNij8k7AhU4q1m2Y0fIh8bhFHgn3yfsGhS6GWFnKbiBQnICF9lYISJfX9t6cdYskBUyOeJVYW38aRKgg7VkK",
                'Content-Type: application/json',
            );

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

if (! function_exists('sendNotification')) {
    function sendNotification($notification_arr)
    {
        appNotificationModel::insert($notification_arr);
    }
}

if (! function_exists('htmlToPDF')) {
    function htmlToPDF($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf ';
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if (! function_exists('htmlToPDFPortrait')) {
    function htmlToPDFPortrait($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 0 -R 0 -B 0 -T 0.5 -s A4 '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if (! function_exists('htmlToPDFLandscape')) {
    function htmlToPDFLandscape($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 0 -R 0 -B 0 -T 0.5 --page-height 250mm --page-width 300mm '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if (! function_exists('htmlToPDFLandscapeCertificate')) {
    function htmlToPDFLandscapeCertificate($htmlPath, $pdfPath)
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 5 -R 5 -B 5 -T 5 -s A5 --orientation "Landscape" '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if (! function_exists('sendSMS')) {
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
            $url = $data['url'].$data['pram'].$data['mobile_var'].$mobile.$data['text_var'].$text.$data['last_var'];
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

if (! function_exists('LMSSearchChain')) {

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
                    "standard_id"      => $std_val,
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

        $std = '<div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label for="title">Select Standard</label>
                        <select name="'.$prefix.$std_name.'" id="'.$prefix.'standard" class="form-control" '.$multiple.'>
                            '.$std_option.'
                        </select>
                    </div>
                </div>';

        $sub = ' <div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label for="title">Select Subject</label>
                        <select name="'.$prefix.$sub_name.'" id="'.$prefix.'subject" class="form-control" '.$multiple.'>
                            '.$sub_option.'
                        </select>
                    </div>
                </div>';

        $chapter = ' <div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label for="title">Select Chapter</label>
                        <select name="'.$prefix.$chapter_name.'" id="'.$prefix.'chapter" class="form-control" '.$multiple.'>
                            '.$chapter_option.'
                        </select>
                    </div>
                </div>';

        $topic = ' <div class="col-md-'.$col.'">
                    <div class="form-group">
                        <label for="title">Select Topic</label>
                        <select name="'.$prefix.$topic_name.'" id="'.$prefix.'topic" class="form-control" '.$multiple.'>
                            '.$topic_option.'
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

if (! function_exists('getGrade')) {
    function getGrade($grade_arr, $total_mark, $total_gain_mark)
    {
        $per = (100 * $total_gain_mark) / $total_mark;
        foreach ($grade_arr as $id => $data) {
            if (! isset($grade)) {
                if ($per >= $data['breakoff']) {
                    $grade = $data['title'];
                }
            }
        }
        if (! isset($grade)) {
            $grade = "-";
        }

        return $grade;
    }
}


if (! function_exists('getGradeScale')) {
    function getGradeScale()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $standard_id = session()->get('standard');

        $sql_grade = "SELECT dt.* 
                    FROM result_std_grd_maping  sgm
                    INNER JOIN grade_master_data dt on dt.grade_id = sgm.grade_scale AND dt.syear = ".$syear."
                    WHERE sgm.standard = ".$standard_id." AND 
                    sgm.sub_institute_id = ".$sub_institute_id."
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
}

DEFINE('BEST_OF', 2);

if (! function_exists('getGradeScale')) {
    function getBestOf($elemArr)
    {
        $newArr = [];

        rsort($elemArr);
        $srNo = 0;

        foreach ($elemArr as $value) {
            $srNo++;
            if ($srNo <= BEST_OF) {
                $newArr[] = $value;
            }
        }

        return $newArr;
    }
}
