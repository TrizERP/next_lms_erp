<?php

namespace App\Helpers;


use App\Models\fees\fees_title\fees_title;
use App\Models\fees\map_year\map_year;
use App\Models\student\tblstudentModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\student\appNotificationModel;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use Illuminate\Support\Facades\URL;

if (!function_exists('is_mobile')) {

    function is_mobile($type, $url = null, $data = null, $redirect_type = "redirect")
    {
        if ($type == "API") {
            if(isset($data["status_code"])){
                $data["status"] = strtoupper($data["status_code"]);
                unset($data["status_code"]);
            }
            return json_encode($data);
        } else {
            if ($redirect_type == 'redirect') {
                //                return redirect($url)->with(['data' => $data]);
                return redirect()->route($url)->with(['data' => $data]);
                //                return redirect()->route( 'clients.show' )->with( [ 'id' => $id ] );
            } else if ($redirect_type == 'route_with_message') {
                return route($url)->with(['data' => $data]);
            } else if ($redirect_type == 'view') {
                return view($url, ['data' => $data]);
            }
        }
    }
}
if (!function_exists('ValidateInsertData')) {

    function ValidateInsertData($table, $type = 'insert')
    {

        $files_arr = array(
            "Logo", "Image",
        );

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
            } else {
                if ($obj->Null == 'NO') {
                    $required_fields["$obj->Field"] = 'required';
                }
            }
        }

        if ($validation_status == true) {
            $validator = Validator::make($_REQUEST, $required_fields);
            if ($validator->fails()) {
                $failedRules = $validator->failed();
                dd($failedRules);
                echo "validation fails. Parameter Missing";
                exit;
            }
        } else {
            echo "Validation Fails. File Not Found.";
            exit;
        }
    }
}
if (!function_exists('SearchChain')) {

    function SearchChain($col, $multiple, $listed_drop, $grade_val = "", $std_val = "", $div_val = "")
    {
        // echo "<pre>"; print_r(session()->all()); exit;
        
        $path = URL::current();
        preg_match("/[^\/]+$/", $path, $matches);
        $module_name = $matches[0];

        $module_array=array(
            '1' => 'student_homework'
        );

        // START 07/09/2021 code for getting standard , grade , division according to timetable wise for homework module
        if(session()->get('user_profile_name') == 'Teacher')
        {
            $teacher_id = session()->get('user_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            $subject_teacher_sql = "SELECT s.id,s.subject_name,t.*
                                    FROM subject s 
                                    INNER JOIN timetable t ON t.subject_id = s.id AND t.sub_institute_id = s.sub_institute_id
                                    WHERE t.teacher_id = '".$teacher_id."' AND t.syear = '".$syear."' AND t.sub_institute_id = '".$sub_institute_id."'
                                    GROUP BY s.id,t.standard_id,t.academic_section_id
                                    ORDER BY s.subject_name";
        // DB::enableQueryLog();
        $subject_teacher   = DB::select($subject_teacher_sql);       
        // dd(DB::getQueryLog());
                         
            $subjectTeacherGrdArr = $subjectTeacherStdArr = $subjectTeacherDivArr = array(); 
            if(count($subject_teacher) > 0)
            {
                foreach($subject_teacher as $k => $v)
                {
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

        // dd(session()->all());    

        $explod_list = explode(',', $listed_drop);
        $grade_name = 'grade';
        $std_name = 'standard';
        $div_name = 'division';

        if ($multiple == 'multiple') {
            $multiple = 'multiple="multiple"';
            $grade_name = 'grade[]';
            $std_name = 'standard[]';
            $div_name = 'division[]';
        } else if ($multiple == 'single') {
            $multiple = '';
        }

        $option = "<option value=''>Select</option>";
                
        $query = DB::table("academic_section");
        $query->where("sub_institute_id", session()->get('sub_institute_id'));
        //START Check for class teacher assigned standards
        $classTeacherGrdArr = session()->get('classTeacherGrdArr');
        if (isset($classTeacherGrdArr) && !in_array($module_name, $module_array))
        {
            if(count($classTeacherGrdArr) > 0)
            {
                $query->whereIn('id',$classTeacherGrdArr);          
            }
            else{
                $query->where('id',null);           
            }
        }
        //END Check for class teacher assigned standards

        //START Check for subject teacher assigned
        $subjectTeacherGrdArr = session()->get('subjectTeacherGrdArr');
        // dd($subjectTeacherGrdArr);
        if (isset($subjectTeacherGrdArr) && (!isset($classTeacherGrdArr) || in_array($module_name, $module_array)))
        {
            if(count($subjectTeacherGrdArr) > 0)
            {
                $query->whereIn('id',$subjectTeacherGrdArr);          
            }
            else{
               
                $query->where('id',null);           
            }
        }
        //END Check for subject teacher assigned

        $academic_section = $query->pluck("title", "id");        

        // $academic_section = DB::table("academic_section")
            // ->where("sub_institute_id", session()->get('sub_institute_id'))
            // ->pluck("title", "id");        

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
                $query  = DB::table('standard');
                $query->whereIn("grade_id", $grade_val);
                
                //START Check for class teacher assigned standards
                $classTeacherStdArr = session()->get('classTeacherStdArr');
                if (isset($classTeacherStdArr) && !in_array($module_name, $module_array))
                {
                    if (count($classTeacherStdArr) > 0 )
                    {
                        $query->whereIn('id',$classTeacherStdArr);          
                    }
                    else{
                        $query->where('id',null);           
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherStdArr = session()->get('subjectTeacherStdArr');
                if (isset($subjectTeacherStdArr) && (!isset($classTeacherStdArr) || in_array($module_name, $module_array)))
                {
                    if (count($subjectTeacherStdArr) > 0 )
                    {
                        $query->orwhereIn('id',$subjectTeacherStdArr);          
                    }
                    else{
                        $query->orwhere('id',null);           
                    }
                }
                //END Check for subject teacher assigned

                $standard = $query->pluck("name", "id");
                
                // $standard = DB::table("standard")                    
                    // ->whereIn("grade_id", $grade_val)
                    // ->pluck("name", "id");
            } else {
                $query  = DB::table('standard');
                $query->where("grade_id", $grade_val);
                
                //START Check for class teacher assigned standards
                $classTeacherStdArr = session()->get('classTeacherStdArr');
                if (isset($classTeacherStdArr) && !in_array($module_name, $module_array))
                {
                    if (count($classTeacherStdArr) > 0)
                    {
                        $query->whereIn('id',$classTeacherStdArr);          
                    }
                    else{
                        $query->where('id',null);           
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherStdArr = session()->get('subjectTeacherStdArr');
                if (isset($subjectTeacherStdArr) && (!isset($classTeacherStdArr) || in_array($module_name, $module_array)))
                {
                    if (count($subjectTeacherStdArr) > 0)
                    {
                        // $query->orwhereIn('id',$subjectTeacherStdArr);          
                        $query->whereIn('id',$subjectTeacherStdArr);          
                    }
                    else{
                        // $query->orwhere('id',null);           
                        $query->where('id',null);           
                    }
                }
                //END Check for subject teacher assigned

                $standard = $query->pluck("name", "id");
                
                // $standard = DB::table("standard")
                    // ->where("grade_id", $grade_val)
                    // ->pluck("name", "id");
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

        //        $sub_option = "";

        if ($std_val != "") {
            if (is_array($std_val)) {
                $query = DB::table('std_div_map');
                $query->join('division', 'division.id', '=', 'std_div_map.division_id');
                $query->where("std_div_map.standard_id", $std_val);
                //START Check for class teacher assigned standards
                $classTeacherDivArr = session()->get('classTeacherDivArr');
                if(isset($classTeacherDivArr) && !in_array($module_name, $module_array))
                {
                    if (count($classTeacherDivArr) > 0)
                    {
                        $query->whereIn('division.id',$classTeacherDivArr);         
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherDivArr = session()->get('subjectTeacherDivArr');
                if(isset($subjectTeacherDivArr) && (!isset($subjectTeacherDivArr) || in_array($module_name, $module_array)))
                {
                    if (count($subjectTeacherDivArr) > 0)
                    {
                        $query->orwhereIn('division.id',$subjectTeacherDivArr);         
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
                if(isset($classTeacherDivArr) && !in_array($module_name, $module_array)){
                    if ( count($classTeacherDivArr) > 0)
                    {
                        $query->whereIn('division.id',$classTeacherDivArr);         
                    }
                }
                //END Check for class teacher assigned standards

                //START Check for subject teacher assigned
                $subjectTeacherDivArr = session()->get('subjectTeacherDivArr');
                // if(isset($subjectTeacherDivArr) && (!isset($subjectTeacherDivArr) || in_array($module_name, $module_array)))
                if ($subjectTeacherDivArr != "" && ($classTeacherDivArr == "" || in_array($module_name, $module_array)))
                {
                    // print_r($subjectTeacherDivArr); exit('here');
                    if ( count($subjectTeacherDivArr) > 0)
                    {
                        // $query->orwhereIn('division.id',$subjectTeacherDivArr);         
                        $query->whereIn('division.id',$subjectTeacherDivArr);         
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

        $grade = '<div class="col-md-' . $col . '">
                    <div class="form-group">                        
                        <label>Select Section:</label> 
                        <select name="' . $grade_name . '" id="grade" class="form-control" ' . $multiple . '>
                            ' . $option . '
                        </select>

                    </div>
                </div>';
                //<h4 class="box-title after-none mb-0">Select Section:</h4>

        $std = '<div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label>Select Standard:</label>                     
                        <select name="' . $std_name . '" id="standard" class="form-control" ' . $multiple . '>
                            ' . $std_option . '
                        </select>

                    </div>
                </div>';
                //<h4 class="box-title after-none mb-0">Select Standard:</h4>

        $div = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label>Select Division:</label>
                        <select name="' . $div_name . '" id="division" class="form-control" ' . $multiple . '>
                            ' . $div_option . '
                        </select>

                    </div>
                </div>';
                //<h4 class="box-title after-none mb-0">Select Division:</h4>

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
if (!function_exists('SearchChainSubject')) {

    function SearchChainSubject($col, $multiple, $listed_drop, $grade_val = "", $std_val = "", $sub_val = "")
    {

        //        echo $grade_val,',';
        //        echo $std_val,',';
        //        echo $sub_val,',';

        $explod_list = explode(',', $listed_drop);
        $grade_name = 'grade';
        $std_name = 'standard';
        $sub_name = 'subject';

        if ($multiple == 'multiple') {
            $multiple = 'multiple="multiple"';
            $grade_name = 'grade[]';
            $std_name = 'standard[]';
            $sub_name = 'subject[]';
        } else if ($multiple == 'single') {
            $multiple = '';
        } else {
            echo "Chain Option Error : Must Provide First Prameter As Single Dropdown Or Multiple.";
        }

        $option = "<option value=''>--Select Grade--</option>";

        $academic_section = DB::table("academic_section")
            ->where("sub_institute_id", session()->get('sub_institute_id'))
            ->pluck("title", "id");

        //        echo "<pre>";
        //        print_r($academic_section);
        //        exit;

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
            //            echo "here";
            //            echo "<pre>";
            //            print_r($subjects);
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

        //echo $selected_val;
        //exit;
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

        $term = '
                    <div class="col-md-' . $col . ' form-group">
                        <label for="title">Select Term:</label>
                        <select name="term" id="term" class="form-control">
                            ' . $option . '
                        </select>
                    </div>
                ';

        //$html = '<div class="row">';
        $html = $term;
        //$html .= '</div>';
        echo $html;
    }
}
if (!function_exists('SearchStudent')) {

    function SearchStudent($grade, $standard = "", $div = "", $sub_institute_id = "", $syear = "",$roll_no = "")
    {
        // if ($grade == "" && $standard == "") {
        //     echo "Must Provide 1 Value";
        //     exit;
        // }

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

        $select_fields = "ts.*,se.syear,se.student_id,se.grade_id,
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
        $columns = explode(',', $select_fields);
        $columns[] = "s.name as standard_name";
        $columns[] = "s.medium as medium";
        $columns[] = "d.name as division_name";
        //        echo "<pre>";
        //        print_r($columns );
        //        exit;

        $query->join('tblstudent_enrollment as se', $enrollment_join);
        $query->where($where);

        $query->join('academic_section as acs', $grade_join);
        if (count($grade_arr)) {
            $query->WhereIn('acs.id', $grade_arr);
        }
               // echo "<pre>";
               // print_r($standard_arr);
               // print_r($std_join);
               // exit;
        $query->join('standard as s', $std_join);
        // if(!isset($classTeacherStdArr))
        // {
            if (count($standard_arr)) {
                $query->WhereIn('s.id', $standard_arr);
            }
        // }
        //        echo "asd";
        //        exit;
        //        echo "<pre>";
        //        print_r($div_arr);
        //        exit;
        $query->join('division as d', $div_join);
        // if(!isset($classTeacherDivArr))
        // {
            if (count($div_arr)) {
                $query->WhereIn('d.id', $div_arr);
            }
        // }
        
        //START Check for class teacher assigned standards
        $extraRaw = " 1 = 1 ";
        // $classTeacherStdArr = session()->get('classTeacherStdArr');
        // echo '<pre>';
        // print_r($classTeacherStdArr);
        if(isset($classTeacherStdArr) && count($standard_arr) < 0)
        {
            if (count($classTeacherStdArr) > 0)
            {
                $extraRaw = "s.id IN (".implode(",",$classTeacherStdArr).")";           
            }
            else
            {
                $extraRaw = "s.id IN (' ')";
            }
        }
        // $classTeacherDivArr = session()->get('classTeacherDivArr');
        if(isset($classTeacherDivArr))
        {
            if (count($classTeacherDivArr) > 0 && count($div_arr) < 0)
            {
                $extraRaw .= " and d.id IN (".implode(",",$classTeacherDivArr).")";             
            }
        }
        //END Check for class teacher assigned standards


        if($roll_no != '')
        {
            $extraRaw .= " AND ts.roll_no = '".$roll_no."' ";
        }
        
        $query->whereraw($extraRaw);
        
        $query->orderBy('ts.roll_no');
        //        $query->select('se.syear');
        $records = $query->get($columns)->toArray();
               // echo "<pre>";
               // print_r($records);
               // exit;

        return $records;
    }
}
if (!function_exists('FeeMonthId')) {

    function FeeMonthId()
    {
        $data = map_year::where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
            ])->get()->toArray();
            if(count($data) == 0){
                return array();
                exit;
            }
        // echo ('<pre>');
        // print_r($data);
        // print_r(session()->all());
        // exit;
        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
        $months_arr = array();
        $syear = session()->get('syear');

        for ($i = 1; $i <= 12; $i++) {
            $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
            if ($start_month == 12) {
                $start_month = 0;
                $syear = $syear + 1;
            }
            $start_month = $start_month + 1;
        }
        // echo ('<pre>');print_r($months_arr);exit;
        return $months_arr;
    }
}
if (!function_exists('FeeBreackoff')) {

    function FeeBreackoff($student_ids)
    {
        // echo '<pre>';echo "asd"; print_r(session()->get('syear')); exit;

        $sub_institute_id = session()->get('sub_institute_id');            
        $syear = session()->get('syear'); 

        if($sub_institute_id != '' && $syear != '')
        {
            $sub_institute_id = $sub_institute_id;
            $syear = $syear;
        }
        else
        {
            $sub_institute_id = request()->get('sub_institute_id');            
            $syear = request()->get('syear');            
        }

        $stud_arr = implode(',', $student_ids);
        $extra_where = " AND s.id in (" . $stud_arr . ")";

        $sql = "SELECT s.*,se.syear,se.student_id,se.grade_id,
                se.standard_id,se.section_id,se.student_quota,sq.title AS stu_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number,sum(fb.amount) bkoff,st.name standard_name,
                d.name as division_name,fb.month_id,RIGHT(fb.month_id, 4) as sort_year,CAST(SUBSTRING(fb.month_id,1,CHAR_LENGTH(fb.month_id)-4) as int) as sort_month
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                LEFT JOIN division d ON  d.id = se.section_id
                LEFT JOIN student_quota sq ON sq.id = se.student_quota AND sq.sub_institute_id = se.sub_institute_id
                INNER JOIN fees_breackoff fb ON
                        (fb.syear = '".$syear."' AND
                         fb.admission_year = s.admission_year AND
                         fb.quota = se.student_quota AND
                         fb.grade_id = se.grade_id AND
                         fb.standard_id = se.standard_id AND

                         fb.sub_institute_id = '".$sub_institute_id."'
                         )
                WHERE s.sub_institute_id = '".$sub_institute_id."'
                AND se.syear = '".$syear."'
                $extra_where
                GROUP BY s.id,fb.month_id
                 ORDER BY sort_year,sort_month 
                ";
        //fb.section_id = se.section_id AND
        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql);
        return $result;
    }
}

if (!function_exists('FeeBreakoffHeadWise')) {

    function FeeBreakoffHeadWise($student_ids,$from_date = null,$to_date = null)
    {  
        $stud_arr = implode(',', $student_ids);
        $extra_where = " AND s.id in (" . $stud_arr . ")";
        

        $sql = "SELECT s.*,se.syear,se.student_id,se.grade_id,
                se.standard_id,se.section_id,se.student_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number,fb.amount,st.name standard_name,
                d.name as division_name,fb.month_id,ft.display_name,ft.fees_title,'' as breakoff,s.father_name,s.mother_name,
                RIGHT(fb.month_id, 4) as sort_year,CAST(SUBSTRING(fb.month_id,1,CHAR_LENGTH(fb.month_id)-4) as int) as sort_month,
                ae.fees_circular_form_no
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                LEFT JOIN division d ON  d.id = se.section_id
                INNER JOIN fees_breackoff fb ON
                        (fb.syear = '" . session()->get('syear') . "' AND
                         fb.admission_year = s.admission_year AND
                         fb.quota = se.student_quota AND
                         fb.grade_id = se.grade_id AND
                         fb.standard_id = se.standard_id AND

                         fb.sub_institute_id = '" . session()->get('sub_institute_id') . "'
                         )
                INNER JOIN fees_title ft ON (fb.fee_type_id = ft.id)
                LEFT JOIN admission_registration ar ON ar.enrollment_no = s.enrollment_no AND ar.sub_institute_id = s.sub_institute_id
                LEFT JOIN admission_enquiry ae ON ae.id = ar.enquiry_id AND ar.sub_institute_id = ae.sub_institute_id
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "'
                AND se.syear = '" . session()->get('syear') . "'
                $extra_where
                GROUP BY s.id,fb.month_id,fb.fee_type_id
                ORDER BY sort_year,sort_month
                ";
        //fb.section_id = se.section_id AND

        $sql = preg_replace('/\n+/', '', $sql);        
        $result = DB::select($sql);

        //START for fees over all headwise report
        $extra_condition = '';

        if(isset($_REQUEST['from_date']) && isset($_REQUEST['to_date']))
        {   
             $extra_condition .= " AND fees_collect.receiptdate <= '".$_REQUEST['to_date']."' "; //AND fees_collect.receiptdate >= '".$_REQUEST['from_date']."'
        }
        //END for fees over all headwise report

        $data = array();
        $student_data = array();
        foreach ($result as $key => $value) {
            $fees_title = $value->fees_title;
            $month_id = $value->month_id;
            $sub_institute_id = session()->get('sub_institute_id');
            $sql = "SELECT sum(ifnull($fees_title,0)) total_paid,receiptdate
                    FROM fees_collect
                    WHERE term_id = $month_id
                    AND sub_institute_id = $sub_institute_id
                    AND is_deleted = 'N'
                    and student_id = '".$value->id."' $extra_condition
                    ";
                    //and student_id in ($stud_arr)            
            $paid_fees = DB::select($sql);
            
            //if ($value->amount != 0) {
                $data[$value->id][$value->month_id][$value->fees_title]['amount'] = $value->amount - $paid_fees[0]->total_paid;

                // Start Added by 18/05/2021 for getting paid amount in Overall Fees Head Wise report
                if(isset($paid_fees[0]->total_paid) && $paid_fees[0]->total_paid != '')
                {
                    $data[$value->id][$value->month_id][$value->fees_title]['paid_amount'] = $paid_fees[0]->total_paid;
                }else{
                    $data[$value->id][$value->month_id][$value->fees_title]['paid_amount'] = 0;                    
                }
                // End Added by 18/05/2021 for getting paid amount in Overall Fees Head Wise report

            //}

            $data[$value->id][$value->month_id][$value->fees_title]['title'] = $value->display_name;
            // $data[$value->id][$value->month_id][$value->fees_title]['receiptdate'] = $paid_fees[0]->receiptdate;
        }
       
        // echo ('<pre>');print_r($data);exit;
        //        foreach ($result as $key => $arr) {
        //            $fees_title = $arr->fees_title;
        //            $month_id = $arr->month_id;
        //            $sub_institute_id = session()->get('sub_institute_id');
        //            $sql = "SELECT sum(ifnull($fees_title,0)) total_paid
        //                    FROM fees_collect
        //                    WHERE term_id = $month_id AND sub_institute_id = $sub_institute_id
        //                    ";
        //            echo $sql;
        //            $paid_fees = DB::select($sql);
        //            echo "<pre>";
        //            print_r($paid_fees);
        //            exit;
        //            $result[$key][$arr->amount] = $paid_fees[0]->total_paid;
        //        }
        //        exit;
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
        //dd($student_data);        

        return $student_data;
    }
}

if (!function_exists('getStringOfAmount')) {

    function getStringOfAmount($number)
    {
        $no = round($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            '0' => '', '1' => 'one', '2' => 'two',
            '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
            '7' => 'seven', '8' => 'eight', '9' => 'nine',
            '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
            '13' => 'thirteen', '14' => 'fourteen',
            '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
            '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
            '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
            '60' => 'sixty', '70' => 'seventy',
            '80' => 'eighty', '90' => 'ninety'
        );
        $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
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
        // echo $result . "Rupees  " . $points . " Paise";
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

        $extrasql = "";
        if (session()->get('user_profile_name') == 'Teacher') //Get Class teacher standard and division
        {
            $extrasql = " AND ct.teacher_id = '" . session()->get('user_id') . "'";
        } else if (strtoupper(session()->get('user_profile_name')) != 'SCHOOL ADMIN' && strtoupper(session()->get('user_profile_name')) != 'ADMIN') {
            $extrasql = " AND 1 != 1"; //False Condition to stop
        }
        $standardDiv = "SELECT ct.standard_id,ct.division_id,s.name as standard_name,d.name as division_name
        FROM class_teacher ct
        INNER JOIN standard s ON ct.standard_id = s.id AND ct.sub_institute_id = s.sub_institute_id
        INNER JOIN division d ON d.id = ct.division_id AND d.sub_institute_id = ct.sub_institute_id
        WHERE ct.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND syear = '" . session()->get('syear') . "' 
        " . $extrasql;

        $result = DB::select($standardDiv);
        $returnHtml = '<select name="standard_division" class="form-control" required>';
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
if (!function_exists('OtherBreackOff')) {

    function OtherBreackOff($student_id_arr, $month_arr, $other_bf_amount='',$from_date = null,$to_date = null)
    {

        $student_id = $student_id_arr[0];
        $moth_ids = implode(',', $month_arr);

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $query = "SELECT *,sum(amount) tot_amount
                FROM fees_breakoff_other
                WHERE
                sub_institute_id = '".$sub_institute_id."' and
                syear = '".$syear."' and
                student_id = '".$student_id."' and
                month_id in($moth_ids)
                group by fee_type_id,month_id
                ";
        $query = preg_replace('/\n+/', '', $query);
        //        echo $query;
        $fees_breckoff = DB::select(DB::raw($query));
        //echo "<pre>";
        //exit;

        //START for fees over all headwise report
        $extra_condition = '';

        if(isset($_REQUEST['from_date']) && isset($_REQUEST['to_date']))
        {   
             $extra_condition .= " AND receiptdate <= '".$_REQUEST['to_date']."' "; //AND receiptdate >= '".$_REQUEST['from_date']."' 
        }
        //END for fees over all headwise report

        $final_bk = $other_fees_final_bk = array();
        foreach ($fees_breckoff as $id => $arr) {
            $fees_title = $arr->fee_type_id;
            $month_id = $arr->month_id;
            $sql = "SELECT sum(ifnull(fpo.$fees_title,0)) total_paid
                    FROM fees_paid_other fpo
                    WHERE fpo.month_id = '".$month_id."'
                    AND fpo.syear = '".$syear."'
                    AND fpo.sub_institute_id = '".$sub_institute_id."'
                        and fpo.student_id = '".$student_id."'
                        AND fpo.is_deleted = 'N' $extra_condition
                    ";
                       // echo $sql;
                       // echo '<br>';
            $paid_fees = DB::select($sql);

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
        // dd($other_fees_final_bk);
        $fees_title = fees_title::select('id', 'display_name', 'fees_title', 'mandatory', 'syear', 'other_fee_id')
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
                'fees_title_id' => 1,
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
        if($other_bf_amount == 'Yes')
        {
            return $other_fees_final_bk;
        }else
        {
            return $bk_off_with_name;
        }
        // start 27-07-2021 Added by divya for getting other_fees break off amount for fees overallhead wise report

        // return $bk_off_with_name;

    }
}
if (!function_exists('OtherBreackOffHead')) {

    function OtherBreackOffHead()
    {

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $query = "select *
                from fees_title
                where sub_institute_id = $sub_institute_id
                and syear = $syear
                and fees_title_id = 1
                ";
        $query = preg_replace('/\n+/', '', $query);
        $fees_title = DB::select(DB::raw($query));

        return $fees_title;
    }
}
if (!function_exists('OtherBreackOfMonth')) {

    function OtherBreackOfMonth($student_id_arr)
    {

        $student_id = $student_id_arr[0];
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $query = "select sum(amount) tot_amount ,month_id
                from fees_breakoff_other
                where sub_institute_id = $sub_institute_id
                and syear = $syear and student_id = $student_id
                group by month_id
                ";
        $query = preg_replace('/\n+/', '', $query);
        $fees_title = DB::select(DB::raw($query));
        //echo "<pre>";
        //print_r($fees_title);
        //exit;

        $responce_arr = array();
        foreach ($fees_title as $id => $arr) {
            $responce_arr[$arr->month_id] = $arr->tot_amount;
        }

        return $responce_arr;
    }
}
if (!function_exists('OtherBreackOfMonthHead')) {

    function OtherBreackOfMonthHead($student_id_arr, $month_arr)
    {

        $student_id = $student_id_arr[0];
        $moth_ids = implode(',', $month_arr);

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $query = "SELECT *,sum(amount) tot_amount
                FROM fees_breakoff_other
                WHERE
                sub_institute_id = '$sub_institute_id' and
                syear = '$syear' and
                student_id = '$student_id' and
                month_id in($moth_ids)
                group by fee_type_id,month_id
                ";
        $query = preg_replace('/\n+/', '', $query);
        //        echo $query;
        $fees_breckoff = DB::select(DB::raw($query));
        //echo "<pre>";
        //print_r($fees_breckoff);
        //exit;

        $final_bk = array();

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
        // $sql = "select date_format(school_date,'%Y-%m-%d') as school_date from CALENDAR_EVENTS where school_date between '$from_date' and '$to_date'";
        // $holiday_RET = DBGet(DBQuery($sql));
        // foreach($holiday_RET as $value)
        // {
        //     $holidays[] = $value['SCHOOL_DATE'];
        // }
        foreach ($days as $key => $day) {
            $i = 0;
            $from_date1 = $from_date;
            while (strtotime($from_date1) <= strtotime($to_date)) {
                // if(!in_array($from_date1,$holidays))
                // {
                if (date("N", strtotime($from_date1)) == $day) {
                    $i++;
                    $counter[$key][] = $from_date1;
                }
                // }
                $from_date1 = date("Y-m-d", strtotime("+1 day", strtotime($from_date1)));
            }
            //$counter[$key][TOTAL] = $i;
        }
        return $counter;
    }
}

if (!function_exists('getStudents')) {

    function getStudents($student_ids,$sub_institute_id = '',$syear = '')
    {
        //START 23-11-2021 Added FOR Add Homework API
        if($sub_institute_id != '' && $syear != '')
        {
            $sub_institute_id = $sub_institute_id;
            $syear = $syear;
        }
        else
        {
            $sub_institute_id = session()->get('sub_institute_id');            
            $syear = session()->get('syear');            
        }
        //END 23-11-2021 Added FOR Add Homework API

        $stud_arr = implode(',', $student_ids);
        $extra_where = " AND s.id in (" . $stud_arr . ")";

        $sql = "SELECT tc.*,s.*,se.syear,se.student_id,se.grade_id,
                se.standard_id,se.section_id,se.student_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number,st.name standard_name,s.city,
                se.standard_id,se.section_id,se.grade_id,
                d.name as division_name,s.father_name,s.mother_name,ss.SchoolName as school_name,ss.Mobile as school_mobile,ss.Logo as school_image,ss.ReceiptAddress as school_address,(CASE WHEN s.gender = 'M' then 'male' else 'female' end) as gender,
                r.religion_name,c.caste_name,s.subcast,s.affiliation_no,s.school_code,s.admission_date,
                td.first_name AS driver_name,td.mobile AS driver_mobile,td.icard_icon,s.mother_mobile,
                CONCAT_WS(' ',s.first_name,CONCAT(SUBSTRING(s.father_name,1,1),'.'),s.last_name) as short_student_name,
                tv.vehicle_type,tkr.id as distance_from_school_id,tkr.distance_from_school,tkr.from_distance,
                IF(tv.vehicle_type = 'Van',tkr.van_new,tkr.rick_new) AS distance_rate
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                INNER JOIN division d ON  d.id = se.section_id
                INNER JOIN school_setup ss on s.sub_institute_id = ss.Id
                LEFT JOIN tblstudent_tc_details tc on tc.sub_institute_id = s.sub_institute_id AND tc.student_id = s.id
                LEFT JOIN religion r ON r.id = s.religion
                LEFT JOIN caste c ON c.id = s.cast
                LEFT JOIN transport_map_student tms ON tms.student_id = s.id AND tms.sub_institute_id = s.sub_institute_id
                LEFT JOIN transport_vehicle tv ON tv.id = tms.from_bus_id AND tms.sub_institute_id = tv.sub_institute_id 
                LEFT JOIN transport_driver_detail td ON td.id = tv.driver AND td.sub_institute_id = tms.sub_institute_id
                LEFT JOIN transport_kilometer_rate tkr ON tkr.id = s.distance_from_school AND tkr.sub_institute_id = s.sub_institute_id
                WHERE s.sub_institute_id = '" .$sub_institute_id. "'
                AND se.syear = '" .$syear. "'
                $extra_where
                GROUP BY s.id
                ";
                // die;
        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql);

        $student_data = array();
        foreach ($result as $key => $value) {
            $student_data[$value->id]['id'] = $value->id;
            $student_data[$value->id]['enrollment_no'] = $value->enrollment_no;
            $student_data[$value->id]['student_name'] = $value->first_name . " " . $value->last_name;
            $student_data[$value->id]['student_full_name'] = $value->first_name." ".$value->middle_name." " .$value->last_name;
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

if (!function_exists('send_FCM_Notification')) {
    function send_FCM_Notification($to, $message)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        foreach ($to as $val) 
        {  
            $fields = array(
                     'registration_ids' => array($val),
                     'notification' => $message
                    );

            // $headers = array(
            //         'Authorization: key=' . "AAAApM0aBq0:APA91bEMbTNrawzSIm6Ra-IedYR4PmLZjznNGqmjep6-Opk7mSBha3UssNij8k7AhU4q1m2Y0fIh8bhFHgn3yfsGhS6GWFnKbiBQnICF9lYISJfX9t6cdYskBUyOeJVYW38aRKgg7VkK",
            //         'Content-Type: application/json'
            //         );

            $headers = array(
                    'Authorization: key=' . "AAAApM0aBq0:APA91bEMbTNrawzSIm6Ra-IedYR4PmLZjznNGqmjep6-Opk7mSBha3UssNij8k7AhU4q1m2Y0fIh8bhFHgn3yfsGhS6GWFnKbiBQnICF9lYISJfX9t6cdYskBUyOeJVYW38aRKgg7VkK",
                    'Content-Type: application/json'
                    );        

            //CHANGED ON 09-DEC-2021 -> AAAApM0aBq0:APA91bEMbTNrawzSIm6Ra-IedYR4PmLZjznNGqmjep6-Opk7mSBha3UssNij8k7AhU4q1m2Y0fIh8bhFHgn3yfsGhS6GWFnKbiBQnICF9lYISJfX9t6cdYskBUyOeJVYW38aRKgg7VkK

            $ch = curl_init ();
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

            $result = curl_exec($ch);
            // echo '$result : '.$result;
            // echo '<pre>';
            // print_r(curl_getinfo($ch));
            // die();
            curl_close ($ch);
        }
    }
}

if (!function_exists('sendNotification')) {    
    function sendNotification($notification_arr)
    {
        //  $app_notification_content = array(
        //     'NOTIFICATION_TYPE' => 'Virtual Classroom',
        //     'NOTIFICATION_DATE' => date('Y-m-d'),
        //     'NOTIFICATION_DESCRIPTION' => $request->get('room_name'),
        //     'STATUS' => 0,
        //     'SUB_INSTITUTE_ID' => $sub_institute_id,                  
        //     'SYEAR' => $syear,
        //     'CREATED_BY' => $user_id,        
        //     'CREATED_IP' => $created_ip          
        // );                        
        appNotificationModel::insert($notification_arr);

    }
}

if(!function_exists('htmlToPDF')){
    function htmlToPDF($htmlPath, $pdfPath) 
    {
        $command = '/usr/local/bin/wkhtmltopdf '; 
        // $command = '/usr/local/bin/wkhtmltopdf '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if(!function_exists('htmlToPDFPortrait')){
    function htmlToPDFPortrait($htmlPath, $pdfPath) 
    {
        // $command = '/usr/local/bin/wkhtmltopdf '; 
        $command = '/usr/local/bin/wkhtmltopdf -L 0 -R 0 -B 0 -T 0.5 -s A4 '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if(!function_exists('htmlToPDFLandscape')){
    function htmlToPDFLandscape($htmlPath, $pdfPath) 
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 0 -R 0 -B 0 -T 0.5 --page-height 250mm --page-width 300mm '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if(!function_exists('htmlToPDFLandscapeCertificate')){
    function htmlToPDFLandscapeCertificate($htmlPath, $pdfPath) 
    {
        $command = '/usr/local/bin/wkhtmltopdf -L 5 -R 5 -B 5 -T 5 -s A5 --orientation "Landscape" '; // --page-height 297mm //-L 0 -R 0 -B 0 -T 0 -s A4 
        $command .= " $htmlPath ";
        $command .= " $pdfPath ";

        return exec($command);
    }
}

if(!function_exists('sendSMS')){
function sendSMS($mobile, $text, $sub_institute_id)
    {
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();
        $isError = 0;

        if ($data) 
        {
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
            // print "<pre>";
            // print_r(curl_getinfo($ch));
            // echo 'out put '.$output ;
            // exit;

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

    function LMSSearchChain($col, $multiple,$prefix,$standard_id, $listed_drop, $std_val = "", $sub_val = "" , $chapter_val = "" ,$topic_val = "" )
    {        
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
        } else if ($multiple == 'single') {
            $multiple = '';
        } else {
            echo "Chain Option Error : Must Provide First Prameter As Single Dropdown Or Multiple.";
        }       

        $std_option = "";
        if($prefix == "pre")
        {
            $extra = " id < $standard_id";
        }
        elseif($prefix == "post")
        {
            $extra = " id > $standard_id";
        }
        elseif($prefix == "cross-curriculum")
        {
            $extra = " 1 = 1";   
        }

        $standard = DB::table("standard")
            ->where("sub_institute_id", $sub_institute_id)
            ->whereraw($extra)
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
                ->where(['sub_institute_id'=>session()->get('sub_institute_id'),'subject_id'=>$sub_val,"standard_id"=>$std_val])
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
                ->where(['sub_institute_id'=>session()->get('sub_institute_id'),'chapter_id'=>$chapter_val])                
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
                        <select name="' . $prefix.$std_name . '" id="'.$prefix.'standard" class="form-control" ' . $multiple . '>
                            ' . $std_option . '
                        </select>
                    </div>
                </div>';

        $sub = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Subject</label>
                        <select name="' . $prefix.$sub_name . '" id="'.$prefix.'subject" class="form-control" ' . $multiple . '>
                            ' . $sub_option . '
                        </select>
                    </div>
                </div>';
        
        $chapter = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Chapter</label>
                        <select name="' . $prefix.$chapter_name . '" id="'.$prefix.'chapter" class="form-control" ' . $multiple . '>
                            ' . $chapter_option . '
                        </select>
                    </div>
                </div>';
        
        $topic = ' <div class="col-md-' . $col . '">
                    <div class="form-group">
                        <label for="title">Select Topic</label>
                        <select name="' . $prefix.$topic_name . '" id="'.$prefix.'topic" class="form-control" ' . $multiple . '>
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
