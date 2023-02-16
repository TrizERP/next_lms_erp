<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setupModel;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\student\tblstudentPastEducationModel;
use App\Models\student\tblstudentFamilyHistoryModel;
use App\Models\student\studentInfirmaryModel;
use App\Models\student\studentVaccinationModel;
use App\Models\student\studentHWModel;
use App\Models\student\studentHealthModel;
use App\Models\student\tblstudentDocumentModel;
use App\Models\transportation\map_student\map_student;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class rollOverController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) 
	{
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = session()->get('sub_institute_id');
		$syear = session()->get('syear');
        $to_next_syear = $syear + 1;

		$from_institute_details = school_setupModel::where(['id' => $sub_institute_id])->get()->toArray();
        $from_institute_name = '';
        if(count($from_institute_details) > 0)
        {
            $from_institute_name = $from_institute_details[0]['SchoolName'];
        }

        $table_array = array(
            "academic_year" => "Academic Year",
            "batch" => "Batch",
            "class_teacher" => "Class Teacher",
            "division_capacity_master" => "Division Capacity Master",
            "fees_map_years" => "Fees Map Years",
            "fees_title" => "Fees Title",
            "fees_breackoff" => "Fees Breakoff",
            "student_optional_subject" => "Student Optional Subject",
            "timetable" => "Timetable",
            "transport_map_student" => "Transport Map Student",
            "tblstudent_enrollment" => "Student Enrollment",
        );

        $table_array_check = array();
        $academic_year = DB::SELECT("SELECT COUNT(*) AS total_data FROM academic_year 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $batch = DB::SELECT("SELECT COUNT(*) AS total_data FROM batch 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $class_teacher = DB::SELECT("SELECT COUNT(*) AS total_data FROM class_teacher 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $division_capacity_master = DB::SELECT("SELECT COUNT(*) AS total_data FROM division_capacity_master 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $fees_map_years = DB::SELECT("SELECT COUNT(*) AS total_data FROM fees_map_years 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $fees_title = DB::SELECT("SELECT COUNT(*) AS total_data FROM fees_title 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $fees_breackoff = DB::SELECT("SELECT COUNT(*) AS total_data FROM fees_breackoff 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $student_optional_subject = DB::SELECT("SELECT COUNT(*) AS total_data FROM student_optional_subject 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $timetable = DB::SELECT("SELECT COUNT(*) AS total_data FROM timetable 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");

        $transport_map_student = DB::SELECT("SELECT COUNT(*) AS total_data FROM transport_map_student 
                                    WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
        
        $current_year_students = DB::SELECT("SELECT count(*) as old_year_students FROM tblstudent_enrollment
                                WHERE syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."' AND end_date IS NULL");

        $next_year_students = DB::SELECT("SELECT count(*) as new_year_students FROM tblstudent_enrollment
                                WHERE syear = '".$to_next_syear."' AND sub_institute_id = '".$sub_institute_id."' 
                                AND end_date IS NULL");
        $remaining_rollover_students = ($current_year_students[0]->old_year_students - $next_year_students[0]->new_year_students);

        $table_array_check['academic_year'] = $academic_year[0]->total_data;
        $table_array_check['batch'] = $batch[0]->total_data;
        $table_array_check['class_teacher'] = $class_teacher[0]->total_data;
        $table_array_check['division_capacity_master'] = $division_capacity_master[0]->total_data;
        $table_array_check['fees_map_years'] = $fees_map_years[0]->total_data;
        $table_array_check['fees_title'] = $fees_title[0]->total_data;
        $table_array_check['fees_breackoff'] = $fees_breackoff[0]->total_data;
        $table_array_check['student_optional_subject'] = $student_optional_subject[0]->total_data;
        $table_array_check['timetable'] = $timetable[0]->total_data;
        $table_array_check['transport_map_student'] = $transport_map_student[0]->total_data;
        $table_array_check['tblstudent_enrollment'] = $current_year_students[0]->old_year_students.'/'.$next_year_students[0]->new_year_students.'/'.$remaining_rollover_students;
        
        // dd($table_array_check);
        $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status'] = 1;
        $res['message'] = "Success";
        $res['from_institute_name'] = $from_institute_name;
        $res['table_array'] = $table_array;
        $res['table_array_check'] = $table_array_check;
		$res['to_academic_sections'] = $to_academic_sections;

		return is_mobile($type, "student/show_rollover", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) 
	{
        // dd($request);
        $sub_institute_id = session()->get('sub_institute_id');
        $to_next_syear = (session()->get('syear') + 1);
        $from_sub_institute_id = session()->get('sub_institute_id');
        $from_sub_institute_name = $request->input('from_institute_name');
		$tblstudent_enrollment_value = $request->input('tblstudent_enrollment');
		$from_current_syear = session()->get('syear');
		$from_grade = $request->input('grade');
		$from_standard = $request->input('standard');
		$from_division = $request->input('division');
        $to_sub_institute_id = session()->get('sub_institute_id');
        $next_session_year = $request->input('to_next_syear');
        $to_academic_section = $request->input('to_academic_section');
        $to_standard = $request->input('to_standard');
		$to_division = $request->input('to_division');
		$type = $request->input('type');

        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        //START FOR ROLLOVER ALL DATA INCLUDING ALL STUDENTS
        if($request->has('tables'))
        {
            $tables = $request->get('tables');
            $i = 1;
            //START ROLLOVER OTHER TABLES DATA
            foreach ($tables as $key => $table_name)
            {
                switch ($table_name) {
                    case 'academic_year':
                        $check_academic_year = DB::select("SELECT * FROM academic_year WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_academic_year) == 0)
                        {
                            DB::INSERT("INSERT INTO academic_year (term_id,syear,sub_institute_id,title,short_name,sort_order,start_date,end_date,post_start_date,post_end_date,does_grades,does_exams,created_at)
                                SELECT term_id,'".$to_next_syear."',sub_institute_id,title,short_name,sort_order,date_add(start_date,INTERVAL 365 DAY),date_add(end_date,INTERVAL 365 DAY),
                                date_add(post_start_date,INTERVAL 365 DAY),date_add(post_end_date,INTERVAL 365 DAY),does_grades,does_exams,Now() 
                                FROM academic_year 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'batch':
                        $check_batch = DB::select("SELECT * FROM batch WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_batch) == 0)
                        {
                            DB::INSERT("INSERT INTO batch (title,standard_id,division_id,sub_institute_id,syear,created_at,rollover_id)
                                SELECT title,standard_id,division_id,sub_institute_id,'".$to_next_syear."',Now(),id
                                FROM batch 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'class_teacher':
                        $check_class_teacher = DB::select("SELECT * FROM class_teacher WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_class_teacher) == 0)
                        {
                            DB::INSERT("INSERT INTO class_teacher (syear,sub_institute_id,grade_id,standard_id,division_id,teacher_id,created_at)
                                SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,teacher_id,Now()
                                FROM class_teacher 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'division_capacity_master':
                        $check_division_capacity_master = DB::select("SELECT * FROM division_capacity_master WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_division_capacity_master) == 0)
                        {
                            DB::INSERT("INSERT INTO division_capacity_master (syear,sub_institute_id,grade_id,standard_id,division_id,capacity,created_on,created_by,created_ip)
                                SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,capacity,Now(),'".$created_by."','".$created_ip."'
                                FROM division_capacity_master 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'fees_map_years':
                        $check_fees_map_years = DB::select("SELECT * FROM fees_map_years WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_fees_map_years) == 0)
                        {
                            DB::INSERT("INSERT INTO fees_map_years (from_month,to_month,syear,sub_institute_id,created_at)
                                SELECT from_month,to_month,'".$to_next_syear."',sub_institute_id,Now()
                                FROM fees_map_years 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'fees_title':
                        $check_fees_title = DB::select("SELECT * FROM fees_title WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_fees_title) == 0)
                        {
                            DB::INSERT("INSERT INTO fees_title (fees_title_id,fees_title,display_name,cumulative_name,append_name,mandatory,syear,sub_institute_id,other_fee_id,created_at,rollover_id)
                                SELECT fees_title_id,fees_title,display_name,cumulative_name,append_name,mandatory,'".$to_next_syear."',sub_institute_id,other_fee_id,Now(),id
                                FROM fees_title 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;    
                    case 'fees_breackoff':
                        $check_fees_breackoff = DB::select("SELECT * FROM fees_breackoff WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_fees_breackoff) == 0)
                        {
                            DB::INSERT("INSERT INTO fees_breackoff (syear,admission_year,fee_type_id,quota,grade_id,standard_id,section_id,month_id,amount,sub_institute_id,created_at)
                                    SELECT '".$to_next_syear."',fb.admission_year,ft.id,fb.quota,fb.grade_id,fb.standard_id,fb.section_id,CONCAT(LEFT(fb.month_id,LENGTH(fb.month_id)-4),
                                        CASE
                                            WHEN RIGHT(fb.month_id,4) = '".$from_current_syear."' THEN '".$to_next_syear."'
                                            WHEN RIGHT(fb.month_id,4) = '".$to_next_syear."' THEN '".($to_next_syear + 1)."'
                                        END),fb.amount,fb.sub_institute_id,Now()
                                    FROM fees_breackoff fb
                                    LEFT JOIN fees_title ft ON ft.rollover_id = fb.fee_type_id AND ft.sub_institute_id = fb.sub_institute_id AND ft.syear = '".$to_next_syear."'
                                    WHERE fb.syear = '".$from_current_syear."' AND fb.sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'student_optional_subject':
                        $check_student_optional_subject = DB::select("SELECT * FROM student_optional_subject WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_student_optional_subject) == 0)
                        {
                            DB::INSERT("INSERT INTO student_optional_subject (syear,sub_institute_id,subject_id,student_id)
                                SELECT '".$to_next_syear."',sub_institute_id,subject_id,student_id
                                FROM student_optional_subject 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;    
                    case 'timetable':
                        $check_timetable = DB::select("SELECT * FROM timetable WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_timetable) == 0)
                        {
                            DB::INSERT("INSERT INTO timetable (sub_institute_id,syear,academic_section_id,standard_id,division_id,batch_id,period_id,subject_id,teacher_id,week_day,created_at)
                                SELECT t.sub_institute_id,'".$to_next_syear."',t.academic_section_id,t.standard_id,t.division_id,b.id,period_id,subject_id,teacher_id,week_day,Now()
                                FROM timetable t
                                LEFT JOIN batch b ON b.rollover_id = t.batch_id AND b.sub_institute_id = t.sub_institute_id AND b.syear = '".$to_next_syear."'
                                WHERE t.syear = '".$from_current_syear."' AND t.sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'transport_map_student':
                        $check_transport_map_student = DB::select("SELECT * FROM transport_map_student WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if(count($check_transport_map_student) == 0)
                        {
                            DB::INSERT(" INSERT INTO transport_map_student (syear,student_id,from_shift_id,from_bus_id,from_stop,to_shift_id,to_bus_id,to_stop,sub_institute_id,created_at)
                             SELECT '".$to_next_syear."',student_id,from_shift_id,from_bus_id,from_stop,to_shift_id,to_bus_id,to_stop,sub_institute_id,Now()
                             FROM transport_map_student 
                             WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;                          
                    default:    
                        break;
                }
            }
            //END ROLLOVER OTHER TABLES DATA

            //START ROLLOVER ALL STUDENT DATA
            if($request->has('tblstudent_enrollment'))
            {
                $tblstudent_enrollment = $request->get('tblstudent_enrollment');

                $get_all_student_data = DB::SELECT("SELECT * FROM tblstudent_enrollment
                                        WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' 
                                        AND end_date IS NULL");
                $students = json_decode(json_encode($get_all_student_data),true);
                
                foreach ($students as $key => $student_data) 
                {
                    $student_id = $student_data['student_id'];

                    // START Check student is already exist in next year 
                    $check_student = DB::select("SELECT count(se.id) as total_student
                                                FROM tblstudent_enrollment se
                                                WHERE se.student_id = '".$student_id."' AND se.syear = '".$to_next_syear."' 
                                                AND se.sub_institute_id = '".$sub_institute_id."' AND se.end_date IS NULL ");
                    
                    if($check_student[0]->total_student > 0)
                    {
                        $i++;
                    }

                    if($check_student[0]->total_student == 0)
                    {
                        // START UPDATE in tblstudent 
                        DB::INSERT("INSERT INTO tblstudent_enrollment (syear,student_id,grade_id,standard_id,section_id,
                                    student_quota,start_date,end_date,
                                    enrollment_code,drop_code,drop_remarks,remarks,admission_fees,house_id,lc_number,adhar,sub_institute_id,created_on)
                                    SELECT '".$to_next_syear."',se.student_id,st.next_grade_id,st.next_standard_id,se.section_id,se.student_quota,se.start_date,se.end_date,
                                    se.enrollment_code,se.drop_code,
                                    se.drop_remarks,se.remarks,se.admission_fees,se.house_id,se.lc_number,se.adhar,se.sub_institute_id,Now()
                                    FROM tblstudent_enrollment se
                                    INNER JOIN standard st ON st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id
                                    WHERE se.student_id = '".$student_id."' AND se.syear = '".$from_current_syear."' 
                                    AND se.sub_institute_id = '".$sub_institute_id."' ");
                        // END UPDATE in tblstudent 
                    }   
                    // END Check student is already exist in next year 
                }            
            }
            //END ROLLOVER ALL STUDENT DATA 

            if($i > 1)
            {
                $res['status'] = "0";
                $res['message'] = $i." students is already exist in next year.";
            }else{
                $res['status'] = "1";
                $res['message'] = "Student Data Rollover Successfully.";
            }            
            return \App\Helpers\is_mobile($type, "rollover.index", $res, "redirect");   
        }
        //END FOR ROLLOVER ALL DATA INCLUDING ALL STUDENTS   
        
        //START FOR ROLLOVER ONLY ALL STUDENT DATA
        if($request->has('tblstudent_enrollment') && !($request->has('new_tables')))
        {
            $tblstudent_enrollment = $request->get('tblstudent_enrollment');

            $get_all_student_data = DB::SELECT("SELECT * FROM tblstudent_enrollment
                                    WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' 
                                    AND end_date IS NULL");
            $students = json_decode(json_encode($get_all_student_data),true);
            $i = 1;
            foreach ($students as $key => $student_data) 
            {
                $student_id = $student_data['student_id'];

                // START Check student is already exist in next year 
                $check_student = DB::select("SELECT count(se.id) as total_student
                                            FROM tblstudent_enrollment se
                                            WHERE se.student_id = '".$student_id."' AND se.syear = '".$to_next_syear."' 
                                            AND se.sub_institute_id = '".$sub_institute_id."' AND se.end_date IS NULL ");
                
                if($check_student[0]->total_student > 0)
                {
                    $i++;
                }

                if($check_student[0]->total_student == 0)
                {
                    // START UPDATE in tblstudent 
                    DB::INSERT("INSERT INTO tblstudent_enrollment (syear,student_id,grade_id,standard_id,section_id,
                                student_quota,start_date,end_date,
                                enrollment_code,drop_code,drop_remarks,remarks,admission_fees,house_id,lc_number,adhar,sub_institute_id,created_on)
                                SELECT '".$to_next_syear."',se.student_id,st.next_grade_id,st.next_standard_id,se.section_id,se.student_quota,se.start_date,se.end_date,
                                se.enrollment_code,se.drop_code,
                                se.drop_remarks,se.remarks,se.admission_fees,se.house_id,se.lc_number,se.adhar,se.sub_institute_id,Now()
                                FROM tblstudent_enrollment se
                                INNER JOIN standard st ON st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id
                                WHERE se.student_id = '".$student_id."' AND se.syear = '".$from_current_syear."' 
                                AND se.sub_institute_id = '".$sub_institute_id."' ");
                    // END UPDATE in tblstudent 
                }   
                // END Check student is already exist in next year 
            }
            if($i > 1){
                $res['status'] = "0";
                $res['message'] = $i." students is already exist in next year11.";
            }else{
                $res['status'] = "1";
                $res['message'] = "Student Data Rollover Successfully.";
            }            
            return \App\Helpers\is_mobile($type, "rollover.index", $res, "redirect");               
        }
        //END FOR ROLLOVER ONLY ALL STUDENT DATA         
        
        //START FOR ROLLOVER SELECTED STUDENTS
        if($request->has('new_tables'))
        {
            $new_tables = $request->get('new_tables');
            $studentData = SearchStudent($from_grade, $from_standard, $from_division, $from_sub_institute_id, $from_current_syear);

            $table_array = array(
                "academic_year" => "Academic Year",
                "batch" => "Batch",
                "class_teacher" => "Class Teacher",
                "division_capacity_master" => "Division Capacity Master",
                "fees_breackoff" => "Fees Breakoff",
                "fees_map_years" => "Fees Map Years",
                "fees_title" => "Fees Title",
                "student_optional_subject" => "Student Optional Subject",
                "timetable" => "Timetable",
                "transport_map_student" => "Transport Map Student",
                "tblstudent_enrollment" => "Student Enrollment",
            );

            $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $to_sub_institute_id])->get()->toArray();
            $to_standards = standardModel::where(['grade_id' => $to_academic_section,'sub_institute_id' => $to_sub_institute_id])->get()->toArray();
            $to_divisions = std_div_mappingModel::select('division.*')
                        ->join("division",function($join){
                            $join->on("division.id","=","std_div_map.division_id")
                                ->on("division.sub_institute_id","=","std_div_map.sub_institute_id");
                            })
                        ->where(['std_div_map.standard_id' => $to_standard,'std_div_map.sub_institute_id' => $to_sub_institute_id])
                        ->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['student_data'] = $studentData;
            $res['table_array'] = $table_array;
            $res['tables'] = $new_tables;
            $res['tblstudent_enrollment_value'] = $tblstudent_enrollment_value;
            $res['from_institute_name'] = $from_sub_institute_name;
            $res['from_current_syear'] = $from_current_syear;
            $res['grade'] = $from_grade;
            $res['standard'] = $from_standard;
            $res['division'] = $from_division;
            $res['to_next_syear'] = $next_session_year;
            $res['to_academic_section'] = $to_academic_section;
            $res['to_standard'] = $to_standard;
            $res['to_division'] = $to_division;
            $res['to_academic_sections'] = $to_academic_sections;
            $res['to_standards'] = $to_standards;
            $res['to_divisions'] = $to_divisions;

            return is_mobile($type, "student/show_rollover_selected_students", $res, "view");

        }
        //END FOR ROLLOVER SELECTED STUDENTS
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) 
	{
        $sub_institute_id = session()->get('sub_institute_id');
        $from_sub_institute_name = $request->get('from_institute_name');
        $tblstudent_enrollment_value = $request->get('tblstudent_enrollment');
        $from_current_syear = $request->get('from_current_syear');
        $from_grade = $request->get('grade');
        $from_standard = $request->get('standard');
        $from_division = $request->get('division');
        $to_sub_institute_id = session()->get('sub_institute_id');
        $to_next_syear = $request->get('to_next_syear');
        $to_academic_section = $request->get('to_academic_section');
        $to_standard = $request->get('to_standard');
        $to_division = $request->get('to_division');
		$students = $request->get('students');
        $new_tables = $request->get('new_tables');
		$type = $request->get('type');

        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        $tables = explode(',', $new_tables);

        //START Rollover Other Tables Data
        foreach ($tables as $key => $table_name)
        {
            switch ($table_name) {
                case 'academic_year':
                    $check_academic_year = DB::select("SELECT * FROM academic_year WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_academic_year) == 0)
                    {
                        DB::INSERT("INSERT INTO academic_year (term_id,syear,sub_institute_id,title,short_name,sort_order,start_date,end_date,post_start_date,post_end_date,does_grades,does_exams,created_at)
                            SELECT term_id,'".$to_next_syear."',sub_institute_id,title,short_name,sort_order,date_add(start_date,INTERVAL 365 DAY),date_add(end_date,INTERVAL 365 DAY),
                            date_add(post_start_date,INTERVAL 365 DAY),date_add(post_end_date,INTERVAL 365 DAY),does_grades,does_exams,Now() 
                            FROM academic_year 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'batch':
                    $check_batch = DB::select("SELECT * FROM batch WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_batch) == 0)
                    {
                        DB::INSERT("INSERT INTO batch (title,standard_id,division_id,sub_institute_id,syear,created_at,rollover_id)
                            SELECT title,standard_id,division_id,sub_institute_id,'".$to_next_syear."',Now(),id
                            FROM batch 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'class_teacher':
                    $check_class_teacher = DB::select("SELECT * FROM class_teacher WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_class_teacher) == 0)
                    {
                        DB::INSERT("INSERT INTO class_teacher (syear,sub_institute_id,grade_id,standard_id,division_id,teacher_id,created_at)
                            SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,teacher_id,Now()
                            FROM class_teacher 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'division_capacity_master':
                    $check_division_capacity_master = DB::select("SELECT * FROM division_capacity_master WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_division_capacity_master) == 0)
                    {
                        DB::INSERT("INSERT INTO division_capacity_master (syear,sub_institute_id,grade_id,standard_id,division_id,capacity,created_on,created_by,created_ip)
                            SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,capacity,Now(),'".$created_by."','".$created_ip."'
                            FROM division_capacity_master 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'fees_map_years':
                    $check_fees_map_years = DB::select("SELECT * FROM fees_map_years WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_fees_map_years) == 0)
                    {
                        DB::INSERT("INSERT INTO fees_map_years (from_month,to_month,syear,sub_institute_id,created_at)
                            SELECT from_month,to_month,'".$to_next_syear."',sub_institute_id,Now()
                            FROM fees_map_years 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'fees_title':
                    $check_fees_title = DB::select("SELECT * FROM fees_title WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_fees_title) == 0)
                    {
                        DB::INSERT("INSERT INTO fees_title (fees_title_id,fees_title,display_name,cumulative_name,append_name,mandatory,syear,sub_institute_id,other_fee_id,created_at,rollover_id)
                            SELECT fees_title_id,fees_title,display_name,cumulative_name,append_name,mandatory,'".$to_next_syear."',sub_institute_id,other_fee_id,Now(),id
                            FROM fees_title 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;    
                case 'fees_breackoff':
                    $check_fees_breackoff = DB::select("SELECT * FROM fees_breackoff WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_fees_breackoff) == 0)
                    {
                        DB::INSERT("INSERT INTO fees_breackoff (syear,admission_year,fee_type_id,quota,grade_id,standard_id,section_id,month_id,amount,sub_institute_id,created_at)
                                SELECT '".$to_next_syear."',fb.admission_year,ft.id,fb.quota,fb.grade_id,fb.standard_id,fb.section_id,CONCAT(LEFT(fb.month_id,LENGTH(fb.month_id)-4),
                                    CASE
                                        WHEN RIGHT(fb.month_id,4) = '".$from_current_syear."' THEN '".$to_next_syear."'
                                        WHEN RIGHT(fb.month_id,4) = '".$to_next_syear."' THEN '".($to_next_syear + 1)."'
                                    END),fb.amount,fb.sub_institute_id,Now()
                                FROM fees_breackoff fb
                                LEFT JOIN fees_title ft ON ft.rollover_id = fb.fee_type_id AND ft.sub_institute_id = fb.sub_institute_id AND ft.syear = '".$to_next_syear."'
                                WHERE fb.syear = '".$from_current_syear."' AND fb.sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'student_optional_subject':
                    $check_student_optional_subject = DB::select("SELECT * FROM student_optional_subject WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_student_optional_subject) == 0)
                    {
                        DB::INSERT("INSERT INTO student_optional_subject (syear,sub_institute_id,subject_id,student_id)
                            SELECT '".$to_next_syear."',sub_institute_id,subject_id,student_id
                            FROM student_optional_subject 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;    
                case 'timetable':
                    $check_timetable = DB::select("SELECT * FROM timetable WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_timetable) == 0)
                    {
                        DB::INSERT("INSERT INTO timetable (sub_institute_id,syear,academic_section_id,standard_id,division_id,batch_id,period_id,subject_id,teacher_id,week_day,created_at)
                            SELECT t.sub_institute_id,'".$to_next_syear."',t.academic_section_id,t.standard_id,t.division_id,b.id,period_id,subject_id,teacher_id,week_day,Now()
                            FROM timetable t
                            LEFT JOIN batch b ON b.rollover_id = t.batch_id AND b.sub_institute_id = t.sub_institute_id AND b.syear = '".$to_next_syear."'
                            WHERE t.syear = '".$from_current_syear."' AND t.sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'transport_map_student':
                    $check_transport_map_student = DB::select("SELECT * FROM transport_map_student WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                    if(count($check_transport_map_student) == 0)
                    {
                        DB::INSERT(" INSERT INTO transport_map_student (syear,student_id,from_shift_id,from_bus_id,from_stop,to_shift_id,to_bus_id,to_stop,sub_institute_id,created_at)
                         SELECT '".$to_next_syear."',student_id,from_shift_id,from_bus_id,from_stop,to_shift_id,to_bus_id,to_stop,sub_institute_id,Now()
                         FROM transport_map_student 
                         WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;                          
                default:    
                    break;
            }
        }
        //END Rollover Other Tables Data

        //START Rollover Student Data
		foreach ($students as $key => $student_id) 
		{
            // START Check student is already exist in next year 
            $check_student = DB::select("SELECT count(se.id) as total_student
                                        FROM tblstudent_enrollment se
                                        WHERE se.student_id = '".$student_id."' AND se.syear = '".$to_next_syear."' 
                                        AND se.sub_institute_id = '".$sub_institute_id."'
                                        AND se.grade_id = '".$to_academic_section."' 
                                        AND se.standard_id = '".$to_standard."' 
                                        AND se.section_id = '".$to_division."'  ");

            if($check_student[0]->total_student != 0)
            {
                $res['status'] = 0;
                $res['message'] = "Student is already exist in next year.";
                return \App\Helpers\is_mobile($type, "rollover.index", $res, "redirect");
            }   
            // END Check student is already exist in next year

            // START UPDATE in tblstudent 
                DB::INSERT("INSERT INTO tblstudent_enrollment (syear,student_id,grade_id,standard_id,section_id,
                            student_quota,start_date,end_date,
                            enrollment_code,drop_code,drop_remarks,remarks,admission_fees,house_id,lc_number,adhar,sub_institute_id,created_on)
                            SELECT '".$to_next_syear."',se.student_id,".$to_academic_section.",".$to_standard.",".$to_division.",se.student_quota,se.start_date,se.end_date,
                            se.enrollment_code,se.drop_code,
                            se.drop_remarks,se.remarks,se.admission_fees,se.house_id,se.lc_number,se.adhar,se.sub_institute_id,Now()
                            FROM tblstudent_enrollment se
                            WHERE se.student_id = '".$student_id."' AND se.syear = '".$from_current_syear."' 
                            AND se.sub_institute_id = '".$sub_institute_id."' ");
            // END UPDATE in tblstudent 
		}
        //END Rollover Student Data

        $res['status'] = "1";
        $res['message'] = "Data Rollover Successfully.";
        return \App\Helpers\is_mobile($type, "rollover.index", $res, "redirect");
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id) {
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit($id) {
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {
		//
	}

    public function ajax_toAcademicSections(Request $request)
    {
        $to_sub_institute_id = $request->input("to_sub_institute_id");   
        $academic_section_data = academic_sectionModel::where(['sub_institute_id' => $to_sub_institute_id])->get()->toArray();       
        return $academic_section_data; 
    }

    public function ajax_toStandards(Request $request)
    {
        $to_academic_section = $request->input("to_academic_section");   
        $standard_data = standardModel::where(['grade_id' => $to_academic_section])->get()->toArray();       
        return $standard_data; 
    }

    public function ajax_toDivisions(Request $request)
    {
        $to_standard = $request->input("to_standard");   
        $div_data = std_div_mappingModel::select('division.*')
                    ->join("division",function($join){
                        $join->on("division.id","=","std_div_map.division_id")
                            ->on("division.sub_institute_id","=","std_div_map.sub_institute_id");
                        })
                    ->where(['std_div_map.standard_id' => $to_standard])
                    ->get()->toArray();
        return $div_data; 
    }

    public function selected_student_view()
    {                
        $sub_institute_id = session()->get('sub_institute_id');
        $from_institute_details = school_setupModel::where(['id' => $sub_institute_id])->get()->toArray();
        $from_institute_name = '';
        if(count($from_institute_details) > 0)
        {
            $from_institute_name = $from_institute_details[0]['SchoolName'];
        }
        $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status'] = 1;
        $res['message'] = "Success"; 
        $res['to_academic_sections'] = $to_academic_sections;
        $res['from_institute_name'] = $from_institute_name;        

        return view('student/show_rollover_selected_students',$res);      
    } 

}
