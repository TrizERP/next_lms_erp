<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setupModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Str;

class rollOverController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $to_next_syear = $syear + 1;
        $marking_period_id = session()->get('term_id');

        $from_institute_details = school_setupModel::where(['id' => $sub_institute_id])->get()->toArray();
        $from_institute_name = '';
        if (count($from_institute_details) > 0) {
            $from_institute_name = $from_institute_details[0]['SchoolName'];
        }

        $table_array = [
            "academic_year"            => "Academic Year",
            "batch"                    => "Batch",
            "class_teacher"            => "Class Teacher",
            "division_capacity_master" => "Division Capacity Master",
            "fees_map_years"           => "Fees Map Years",
            "fees_title"               => "Fees Title",
            "fees_breackoff"           => "Fees Breakoff",
            "student_optional_subject" => "Student Optional Subject",
            "timetable"                => "Timetable",
            "transport_map_student"    => "Transport Map Student",
            "tblstudent_enrollment"    => "Student Enrollment",
            "advance_fees"             => "Advance Fees",

        ];

        $table_array_check = [];
        $academic_year = DB::table('academic_year')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $batch = DB::table('batch')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->when($marking_period_id,function($query) use ($marking_period_id){
                $query->where('marking_period_id',$marking_period_id);
            })
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $class_teacher = DB::table('class_teacher')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $division_capacity_master = DB::table('division_capacity_master')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $fees_map_years = DB::table('fees_map_years')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $fees_title = DB::table('fees_title')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $fees_breackoff = DB::table('fees_breackoff')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $advance_fees = DB::table('fees_collect')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $student_optional_subject = DB::table('student_optional_subject')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $timetable = DB::table('timetable')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->when($marking_period_id,function($query) use ($marking_period_id){
                $query->where('marking_period_id',$marking_period_id);
            })
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $transport_map_student = DB::table('transport_map_student')->selectRaw('COUNT(*) AS total_data')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $current_year_students = DB::table('tblstudent_enrollment')->selectRaw('COUNT(*) AS old_year_students')
            ->where('syear', $syear)
            ->where('sub_institute_id', $sub_institute_id)->whereNull('end_date')->get()->toArray();

        $next_year_students = DB::table('tblstudent_enrollment')->selectRaw('COUNT(*) AS new_year_students')
            ->where('syear', $to_next_syear)
            ->where('sub_institute_id', $sub_institute_id)->whereNull('end_date')->get()->toArray();

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
        $table_array_check['advance_fees'] = $advance_fees[0]->total_data;

        $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->when($marking_period_id,function($query) use ($marking_period_id){
            $query->where('marking_period_id',$marking_period_id);
        })->get()->toArray();

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
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        // print_r($request);
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
        $marking_period_id=session()->get('term_id');
        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        //START FOR ROLLOVER ALL DATA INCLUDING ALL STUDENTS
        if ($request->has('tables')) {
            $tables = $request->get('tables');
            $i = 1;
            //START ROLLOVER OTHER TABLES DATA
            foreach ($tables as $key => $table_name) {
                switch ($table_name) {
                    case 'academic_year':
                        $check_academic_year = DB::table('academic_year')->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();

                        if (count($check_academic_year) == 0) {
                            DB::INSERT("INSERT INTO academic_year (term_id,syear,sub_institute_id,title,short_name,sort_order,
                                start_date,end_date,post_start_date,post_end_date,does_grades,does_exams,created_at)
                                SELECT term_id,'".$to_next_syear."',sub_institute_id,title,short_name,sort_order,
                                date_add(start_date,INTERVAL 365 DAY),date_add(end_date,INTERVAL 365 DAY),
                                date_add(post_start_date,INTERVAL 365 DAY),date_add(post_end_date,INTERVAL 365 DAY),does_grades,does_exams,Now() 
                                FROM academic_year 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'batch':
                        $check_batch = DB::table('batch')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$to_next_syear])->when($marking_period_id,function($query) use($marking_period_id){
                            $query->where('marking_period_id',$marking_period_id);
                        })->get();
                        // DB::select("SELECT * FROM batch WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$to_next_syear."' ");
                        if (count($check_batch) == 0) {
                            DB::INSERT("INSERT INTO batch (title,standard_id,division_id,sub_institute_id,syear,created_at,rollover_id)
                                SELECT title,standard_id,division_id,sub_institute_id,'".$to_next_syear."',Now(),id
                                FROM batch 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;
                    case 'class_teacher':
                        $check_class_teacher = DB::table('class_teacher')->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();

                        if (count($check_class_teacher) == 0) {
                            DB::INSERT("INSERT INTO class_teacher (syear,sub_institute_id,grade_id,standard_id,division_id,
                                teacher_id,created_at)
                                SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,teacher_id,Now()
                                FROM class_teacher 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                            
                        }
                        break;
                    case 'division_capacity_master':
                        $check_division_capacity_master = DB::table('division_capacity_master')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();
                        if (count($check_division_capacity_master) == 0) {
                            DB::INSERT("INSERT INTO division_capacity_master (syear,sub_institute_id,grade_id,standard_id,division_id,
                                  capacity,created_on,created_by,created_ip)
                                  SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,capacity,Now(),
                                  '".$created_by."','".$created_ip."'
                                  FROM division_capacity_master 
                                  WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                           
                        }
                        break;
                    case 'fees_map_years':
                        $check_fees_map_years = DB::table('fees_map_years')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();

                        if (count($check_fees_map_years) == 0) {
                            DB::INSERT("INSERT INTO fees_map_years (from_month,to_month,syear,sub_institute_id,created_at)
                                SELECT from_month,to_month,'".$to_next_syear."',sub_institute_id,Now()
                                FROM fees_map_years 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                           
                        }
                        break;
                    case 'fees_title':
                        $check_fees_title = DB::table('fees_title')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();

                        if (count($check_fees_title) == 0) {
                            DB::INSERT("INSERT INTO fees_title (fees_title_id,fees_title,display_name,cumulative_name,append_name,
                                mandatory,syear,sub_institute_id,other_fee_id,created_at,rollover_id)
                                SELECT fees_title_id,fees_title,display_name,cumulative_name,append_name,mandatory,'".$to_next_syear."',
                                sub_institute_id,other_fee_id,Now(),id
                                FROM fees_title 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                            
                        }
                        break;
                    case 'fees_breackoff':
                        $check_fees_breackoff = DB::table('fees_breackoff')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();
                        if (count($check_fees_breackoff) == 0) {
                            DB::INSERT("INSERT INTO fees_breackoff (syear,admission_year,fee_type_id,quota,grade_id,standard_id,
                                section_id,month_id,amount,sub_institute_id,created_at)
                                SELECT '".$to_next_syear."',fb.admission_year,ft.id,fb.quota,fb.grade_id,fb.standard_id,fb.section_id,
                                CONCAT(LEFT(fb.month_id,LENGTH(fb.month_id)-4),
                                CASE
                                    WHEN RIGHT(fb.month_id,4) = '".$from_current_syear."' THEN '".$to_next_syear."'
                                    WHEN RIGHT(fb.month_id,4) = '".$to_next_syear."' THEN '".($to_next_syear + 1)."'
                                END),fb.amount,fb.sub_institute_id,Now()
                                FROM fees_breackoff fb
                                LEFT JOIN fees_title ft ON ft.rollover_id = fb.fee_type_id AND ft.sub_institute_id = fb.sub_institute_id 
                                AND ft.syear = '".$to_next_syear."'
                                WHERE fb.syear = '".$from_current_syear."' AND fb.sub_institute_id = '".$sub_institute_id."' ");
                        }
                        break;

                    case 'student_optional_subject':
                        $check_student_optional_subject = DB::table('student_optional_subject')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();

                        if (count($check_student_optional_subject) == 0) {
                            DB::INSERT("INSERT INTO student_optional_subject (syear,sub_institute_id,subject_id,student_id)
                                SELECT '".$to_next_syear."',sub_institute_id,subject_id,student_id
                                FROM student_optional_subject 
                                WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                         
                        }
                        break;
                    case 'timetable':
                        $check_timetable = DB::table('timetable')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->when($marking_period_id,function($query) use($marking_period_id){
                                $query->where('marking_period_id',$marking_period_id);
                            })->get()->toArray();

                        if (count($check_timetable) == 0) {
                            DB::INSERT("INSERT INTO timetable (sub_institute_id,syear,academic_section_id,standard_id,division_id,
                                batch_id,period_id,subject_id,teacher_id,week_day,created_at)
                                SELECT t.sub_institute_id,'".$to_next_syear."',t.academic_section_id,t.standard_id,t.division_id,b.id,
                                period_id,subject_id,teacher_id,week_day,Now()
                                FROM timetable t
                                LEFT JOIN batch b ON b.rollover_id = t.batch_id AND b.sub_institute_id = t.sub_institute_id 
                                AND b.syear = '".$to_next_syear."'
                                WHERE t.syear = '".$from_current_syear."' AND t.sub_institute_id = '".$sub_institute_id."' ");
                        
                        }
                        break;
                    case 'transport_map_student':
                        $check_transport_map_student = DB::table('transport_map_student')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $to_next_syear)->get()->toArray();

                        if (count($check_transport_map_student) == 0) {
                            DB::INSERT(" INSERT INTO transport_map_student (syear,student_id,from_shift_id,from_bus_id,from_stop,
                                to_shift_id,to_bus_id,to_stop,sub_institute_id,created_at)
                                SELECT '".$to_next_syear."',student_id,from_shift_id,from_bus_id,from_stop,to_shift_id,to_bus_id,
                                to_stop,sub_institute_id,Now()
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
    
            if ($request->has('tblstudent_enrollment')) {
                $tblstudent_enrollment = $request->get('tblstudent_enrollment');

                $get_all_student_data = DB::table('tblstudent_enrollment')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('syear', $from_current_syear)->whereNull('end_date')->get()->toArray();

                $students = json_decode(json_encode($get_all_student_data), true);

                foreach ($students as $key => $student_data) {
                    $student_id = $student_data['student_id'];

                    // START Check student is already exist in next year 
                    $check_student = DB::table('tblstudent_enrollment as se')
                        ->selectRaw('count(se.id) as total_student')
                        ->where('se.student_id', $student_id)
                        ->where('se.sub_institute_id', $sub_institute_id)
                        ->where('se.syear', $to_next_syear)->whereNull('end_date')->get()->toArray();

                    if ($check_student[0]->total_student > 0) {
                        $i++;
                    }

                    if ($check_student[0]->total_student == 0) {
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
            if($request->has('tables')=='fees_breackoff' && $request->has('tables')=='advance_fees'){
          
                //   $check_advance_fees = DB::table('fees_collect')
                //             ->where('sub_institute_id', $sub_institute_id)
                //             ->where('syear', $to_next_syear)->get()->toArray();
                // if (count($check_advance_fees) == 0) {
                $title = DB::table('fees_title')->where(['display_name'=>'Advance Fee','syear'=>$from_current_syear,'sub_institute_id'=>$sub_institute_id])->get()->toArray();
                
                if(isset($title) && count($title)>0 && !empty($title)){

                $advance_fees = "SELECT fb.*,fb.student_id, se.standard_id, SUM(fb.actual_amountpaid) AS sum_amount
                 FROM fees_paid_other fb
                 LEFT JOIN tblstudent_enrollment se ON fb.student_id = se.student_id
                 WHERE fb.".$title[0]->other_fee_id." !=0 AND fb.is_deleted ='N' AND fb.syear = '".$from_current_syear."'
                 AND fb.sub_institute_id = '".$sub_institute_id."'
                 AND se.syear = '".$to_next_syear."'
                 AND se.sub_institute_id = '".$sub_institute_id."'
                 GROUP BY fb.student_id, se.standard_id";
                $advance_fees_arr = DB::select($advance_fees);

                               // echo "<br> advance_fees  "; echo "<pre>";print_r($advance_fees_arr);exit;
                    $divided_advance_fees = [];
                    $paid_off = [];

            // Retrieve fees titles with amounts
            $query = "SELECT ft.id, ft.fees_title, fb.amount,ft.syear,fb.month_id,fb.standard_id,fb.grade_id,fb.section_id,se.student_id,se.standard_id
                      FROM fees_title ft
                      INNER JOIN tblstudent_enrollment se on se.syear = '".$to_next_syear."' AND se.sub_institute_id = '".$sub_institute_id."'
                      INNER JOIN fees_breackoff fb ON se.standard_id = fb.standard_id  AND  fb.syear = '".$to_next_syear."' AND fb.sub_institute_id = '".$sub_institute_id."' 
                      WHERE ft.syear = '".$to_next_syear."'
                      AND ft.sub_institute_id = '".$sub_institute_id."' group by fb.month_id order by fb.id";
            $fees_titles = DB::select($query);

   // echo "<pre>";print_r($month);exit;
            $sum_amt=0;

            foreach ($advance_fees_arr as $k => $fee) {
                $studentId = $fee->student_id;
                $monthId = $fee->month_id;
                $totalAmount = $fee->sum_amount;
                $remainingAmount = $totalAmount;
                $allocatedAmount = 0;
                $i = 4;
                foreach ($fees_titles as $key=>$title) {
                    $feesTitle = $title->fees_title;
                    $amount = $title->amount;
                  
                    // Calculate the amount to allocate for this fees title
                    $allocated = min($amount, $remainingAmount);
                    $remainingAmount -= $allocated;
                    $allocatedAmount += $allocated;
                    // $totalAllocatedAmount += $allocated;

                    if(isset($allocated) && $allocated !=0 ){
                        // $amounts +=$allocated;

                    $divided_advance_fees= [
                        'student_id'=>$studentId,
                        'standard_id'=>$title->standard_id,
                        'term_id' =>$title->month_id,
                        'syear' => $to_next_syear,
                        'sub_institute_id' => $fee->sub_institute_id,
                        'receipt_no' => $fee->reciept_id,
                        'fees_html'=>$fee->paid_fees_html,
                        'created_by'=>$fee->created_by,
                        'payment_mode'=>$fee->payment_mode,
                        'bank_name'=>$fee->bank_name,
                        'cheque_bank_name'=>$fee->bank_name,
                        'remark'=>$fee->remarks,
                        'fees_discount'=>$fee->fees_discount,
                        'fine'=>$fee->fine,
                        'bank_branch'=>$fee->bank_branch,
                        'receiptdate'=>$fee->receiptdate,
                        'cheque_no'=>$fee->cheque_dd_no,
                        'cheque_date'=>$fee->cheque_dd_date,
                        'amount'=> $allocated,
                        'receiptdate'=>$fee->receiptdate,
                        'is_deleted'=>'N',
                        'created_date'=>now(),
                         $feesTitle => $allocated,
                        // 'fees_title' => $feesTitle,
                    ];
                    $insert_fees = DB::table('fees_collect')->insert($divided_advance_fees);

                    //  $paid_off = [
                    //     // 'standard_id'=>$fee->std_id,
                    //     'syear' => $to_next_syear,
                    //     'student_id' => $fee->student_id,
                    //     'month_id' => $title->month_id,
                    //     'sub_institute_id' => $fee->sub_institute_id,
                    //     'is_deleted'=>'Y',
                    //      'actual_amountpaid'=> $allocated,
                    //     // 'fees_title' => $feesTitle,
                    // ];
                    // $insert_paidoff_fees = DB::table('fees_paid_other')->insert($paid_off);
            // $i++;
                // }
            }
                    // Break the loop if all the amount is allocated
                    if ($remainingAmount <= 0) {
                        break;
                    }

                }
            }

            $config_get = DB::table('fees_config_master')->where(['sub_institute_id'=>$sub_institute_id])->first();
            $config_check = DB::table('fees_config_master')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$to_next_syear])->get();
            if(count($config_check) > 0){
            $config_insert = DB::table('fees_config_master')->insert([
            "late_fees_amount"=>$config_get->late_fees_amount,
            "send_sms"=>$config_get->send_sms,
            "send_email"=>$config_get->send_email,
            "fees_receipt_template"=>$config_get->fees_receipt_template,
            "fees_bank_challan_template"=>$config_get->fees_bank_challan_template,
            "fees_receipt_note"=>$config_get->fees_receipt_note,
            "institute_name"=>$config_get->institute_name,
            "pan_no"=>$config_get->pan_no,
            "account_to_be_credited"=>$config_get->account_to_be_credited,
            "cms_client_code"=>$config_get->cms_client_code,
            "auto_head_counting"=>$config_get->auto_head_counting,
            "nach_account_type"=>$config_get->nach_account_type,
            "nach_registration_charge"=>$config_get->nach_registration_charge,
            "nach_transaction_charge"=>$config_get->nach_transaction_charge,
            "nach_failed_charge"=>$config_get->nach_failed_charge,
            "bank_logo"=>$config_get->bank_logo,
            "syear"=>$to_next_syear,
            "sub_institute_id"=>$sub_institute_id,
            ]);
        }
// echo "<br> divided_advance_fees  "; echo "<pre>";print_r($divided_advance_fees);

                        }
            
        }
            // exit;
            if ($i > 1) {
                $res['status'] = "0";
                $res['message'] = $i." students is already exist in next year.";
            } else {
                $res['status'] = "1";
                $res['message'] = "Student Data Rollover Successfully.";
            }

            return is_mobile($type, "rollover.index", $res, "redirect");
        }
        //END FOR ROLLOVER ALL DATA INCLUDING ALL STUDENTS   

        //START FOR ROLLOVER ONLY ALL STUDENT DATA
        if ($request->has('tblstudent_enrollment') && ! ($request->has('new_tables'))) {
            $tblstudent_enrollment = $request->get('tblstudent_enrollment');

            $get_all_student_data = DB::table('tblstudent_enrollment')
                ->where('syear', $from_current_syear)
                ->where('sub_institute_id', $sub_institute_id)
                ->whereNull('end_date')->get()->toArray();

            $students = json_decode(json_encode($get_all_student_data), true);
            $i = 1;
            foreach ($students as $key => $student_data) {
                $student_id = $student_data['student_id'];

                // START Check student is already exist in next year 
                $check_student = DB::table('tblstudent_enrollment as se')
                    ->selectRaw('count(se.id) as total_student')
                    ->where('se.student_id', $student_id)
                    ->where('se.syear', $from_current_syear)
                    ->where('se.sub_institute_id', $sub_institute_id)
                    ->whereNull('se.end_date')->get()->toArray();

                if ($check_student[0]->total_student > 0) {
                    $i++;
                }

                if ($check_student[0]->total_student == 0) {
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
            if ($i > 1) {
                $res['status'] = "0";
                $res['message'] = $i." students is already exist in next year11.";
            } else {
                $res['status'] = "1";
                $res['message'] = "Student Data Rollover Successfully.";
            }
            return is_mobile($type, "rollover.index", $res, "redirect");
        }
        //END FOR ROLLOVER ONLY ALL STUDENT DATA         

        //START FOR ROLLOVER SELECTED STUDENTS
        if ($request->has('new_tables')) {
            $new_tables = $request->get('new_tables');
            $studentData = SearchStudent($from_grade, $from_standard, $from_division, $from_sub_institute_id,
                $from_current_syear);

            $table_array = [
                "academic_year"            => "Academic Year",
                "batch"                    => "Batch",
                "class_teacher"            => "Class Teacher",
                "division_capacity_master" => "Division Capacity Master",
                "fees_breackoff"           => "Fees Breakoff",
                "fees_map_years"           => "Fees Map Years",
                "fees_title"               => "Fees Title",
                "student_optional_subject" => "Student Optional Subject",
                "timetable"                => "Timetable",
                "transport_map_student"    => "Transport Map Student",
                "tblstudent_enrollment"    => "Student Enrollment",
            ];

            $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $to_sub_institute_id])->get()->toArray();
            $to_standards = standardModel::where([
                'grade_id'         => $to_academic_section,
                'sub_institute_id' => $to_sub_institute_id,
            ])->get()->toArray();
            $to_divisions = std_div_mappingModel::select('division.*')
                ->join("division", function ($join) {
                    $join->on("division.id", "=", "std_div_map.division_id")
                        ->on("division.sub_institute_id", "=", "std_div_map.sub_institute_id");
                })
                ->where([
                    'std_div_map.standard_id'      => $to_standard,
                    'std_div_map.sub_institute_id' => $to_sub_institute_id,
                ])
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
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
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
        foreach ($tables as $key => $table_name) {
            switch ($table_name) {
                case 'academic_year':
                    $check_academic_year = DB::table('academic_year')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_academic_year) == 0) {
                        DB::INSERT("INSERT INTO academic_year (term_id,syear,sub_institute_id,title,short_name,sort_order,start_date,end_date,post_start_date,post_end_date,does_grades,does_exams,created_at)
                            SELECT term_id,'".$to_next_syear."',sub_institute_id,title,short_name,sort_order,date_add(start_date,INTERVAL 365 DAY),date_add(end_date,INTERVAL 365 DAY),
                            date_add(post_start_date,INTERVAL 365 DAY),date_add(post_end_date,INTERVAL 365 DAY),does_grades,does_exams,Now() 
                            FROM academic_year 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'batch':
                    $check_batch = DB::table('batch')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_batch) == 0) {
                        DB::INSERT("INSERT INTO batch (title,standard_id,division_id,sub_institute_id,syear,created_at,rollover_id)
                            SELECT title,standard_id,division_id,sub_institute_id,'".$to_next_syear."',Now(),id
                            FROM batch 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'class_teacher':
                    $check_class_teacher = DB::table('class_teacher')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_class_teacher) == 0) {
                        DB::INSERT("INSERT INTO class_teacher (syear,sub_institute_id,grade_id,standard_id,division_id,teacher_id,created_at)
                            SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,teacher_id,Now()
                            FROM class_teacher 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'division_capacity_master':
                    $check_division_capacity_master = DB::table('division_capacity_master')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_division_capacity_master) == 0) {
                        DB::INSERT("INSERT INTO division_capacity_master (syear,sub_institute_id,grade_id,standard_id,division_id,capacity,created_on,created_by,created_ip)
                            SELECT '".$to_next_syear."',sub_institute_id,grade_id,standard_id,division_id,capacity,Now(),'".$created_by."','".$created_ip."'
                            FROM division_capacity_master 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'fees_map_years':
                    $check_fees_map_years = DB::table('fees_map_years')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_fees_map_years) == 0) {
                        DB::INSERT("INSERT INTO fees_map_years (from_month,to_month,syear,sub_institute_id,created_at)
                            SELECT from_month,to_month,'".$to_next_syear."',sub_institute_id,Now()
                            FROM fees_map_years 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'fees_title':
                    $check_fees_title = DB::table('fees_title')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_fees_title) == 0) {
                        DB::INSERT("INSERT INTO fees_title (fees_title_id,fees_title,display_name,cumulative_name,append_name,mandatory,syear,sub_institute_id,other_fee_id,created_at,rollover_id)
                            SELECT fees_title_id,fees_title,display_name,cumulative_name,append_name,mandatory,'".$to_next_syear."',sub_institute_id,other_fee_id,Now(),id
                            FROM fees_title 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'fees_breackoff':
                    $check_fees_breackoff = DB::table('fees_breackoff')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();
                    if (count($check_fees_breackoff) == 0) {
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
                    $check_student_optional_subject = DB::table('student_optional_subject')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_student_optional_subject) == 0) {
                        DB::INSERT("INSERT INTO student_optional_subject (syear,sub_institute_id,subject_id,student_id)
                            SELECT '".$to_next_syear."',sub_institute_id,subject_id,student_id
                            FROM student_optional_subject 
                            WHERE syear = '".$from_current_syear."' AND sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'timetable':
                    $check_timetable = DB::table('timetable')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_timetable) == 0) {
                        DB::INSERT("INSERT INTO timetable (sub_institute_id,syear,academic_section_id,standard_id,division_id,batch_id,period_id,subject_id,teacher_id,week_day,created_at)
                            SELECT t.sub_institute_id,'".$to_next_syear."',t.academic_section_id,t.standard_id,t.division_id,b.id,period_id,subject_id,teacher_id,week_day,Now()
                            FROM timetable t
                            LEFT JOIN batch b ON b.rollover_id = t.batch_id AND b.sub_institute_id = t.sub_institute_id AND b.syear = '".$to_next_syear."'
                            WHERE t.syear = '".$from_current_syear."' AND t.sub_institute_id = '".$sub_institute_id."' ");
                    }
                    break;
                case 'transport_map_student':
                    $check_transport_map_student = DB::table('transport_map_student')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $to_next_syear)->get()->toArray();

                    if (count($check_transport_map_student) == 0) {
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
        foreach ($students as $key => $student_id) {
            // START Check student is already exist in next year 
            $check_student = DB::table('tblstudent_enrollment as se')
                ->selectRaw('count(se.id) as total_student')
                ->where('se.student_id', $student_id)
                ->where('se.syear', $to_next_syear)
                ->where('se.sub_institute_id', $sub_institute_id)
                ->where('se.grade_id', $to_academic_section)
                ->where('se.standard_id', $to_standard)
                ->where('se.section_id', $to_division)->get()->toArray();

            if ($check_student[0]->total_student != 0) {
                $res['status'] = 0;
                $res['message'] = "Student is already exist in next year.";

                return is_mobile($type, "rollover.index", $res, "redirect");
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

        return is_mobile($type, "rollover.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function ajax_toAcademicSections(Request $request)
    {
        $to_sub_institute_id = $request->input("to_sub_institute_id");

        return academic_sectionModel::where(['sub_institute_id' => $to_sub_institute_id])->get()->toArray();
    }

    public function ajax_toStandards(Request $request)
    {
        $to_academic_section = $request->input("to_academic_section");

        return standardModel::where(['grade_id' => $to_academic_section])->get()->toArray();
    }

    public function ajax_toDivisions(Request $request)
    {
        $to_standard = $request->input("to_standard");

        return std_div_mappingModel::select('division.*')
            ->join("division", function ($join) {
                $join->on("division.id", "=", "std_div_map.division_id")
                    ->on("division.sub_institute_id", "=", "std_div_map.sub_institute_id");
            })
            ->where(['std_div_map.standard_id' => $to_standard])
            ->get()->toArray();
    }

    public function selected_student_view()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $from_institute_details = school_setupModel::where(['id' => $sub_institute_id])->get()->toArray();
        $from_institute_name = '';
        if (count($from_institute_details) > 0) {
            $from_institute_name = $from_institute_details[0]['SchoolName'];
        }
        $type='';
        $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status'] = 1;
        $res['message'] = "Success";
        $res['to_academic_sections'] = $to_academic_sections;
        $res['from_institute_name'] = $from_institute_name;
        return is_mobile($type, "student.show_rollover_selected_students", $res, "view");

        // return view('student/show_rollover_selected_students', $res);
    }

}
