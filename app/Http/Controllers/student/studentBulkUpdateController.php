<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setupModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\FeeMonthId;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Controllers\leave\leave_summary_report\LeaveSummaryReportController;

class studentBulkUpdateController extends Controller
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

        $get_student_enrollments = tblstudentEnrollmentModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereNull('end_date')->get()->toArray();

        $res['standard_arr'] = DB::table('standard as std')
        ->join('academic_section as grade', 'grade.id', '=', 'std.grade_id')
        ->select('std.name as standard_name', 'std.id as standard_id', 'grade.title as grade_name', 'grade.id as grade_id')
        ->where('grade.short_name', 'not like', '%PREP%')
        ->where('std.sub_institute_id', $sub_institute_id)
        ->get()
        ->toArray();

        $res['get_student_enrollments'] = $get_student_enrollments;
        $res['bk_month'] = FeeMonthId(); 
        return is_mobile($type, "student/student_bluk_update", $res, "view");
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
        $syear = session()->get('syear');
        $type = $request->get('type');
        $bk_months=$request->bk_month;
        // return $request;exit;
        if($request->has('tables') && $request->tables==1){
          $get_student_enrollments = tblstudentEnrollmentModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereNull('end_date')->get();
            foreach ($get_student_enrollments as $get_student_enrollment) {
            $get_student_enrollment->end_date = date('Y-m-d');
            $get_student_enrollment->save();
            }
            if(!empty($get_student_enrollments)){
                $res['status'] = "1";
                $res['message'] = "Inactive Student Bulk Updated Successfully.";
            }else{
                $res['status'] = "0";
                $res['message'] = "Student are already Inactive.";
            }
        }elseif($request->has('bk_month')){
            $bk_data = DB::table('fees_breackoff')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereIn('month_id',$bk_months)->get()->toArray();
            if(!empty($bk_data)){
                foreach($bk_months as $id=>$mon){
                    $get_array = DB::table('fees_breackoff')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->where('month_id',$mon)->get()->toArray(); 

                    foreach($get_array as $key=>$val){
                        $amount = $val->amount;
                        $admission_year =$val->admission_year;  
                        $standard_id = $val->standard_id;    
                        $fee_type_id = $val->fee_type_id;
                        $student_quota = $val->quota;   
                        $grade_id = $val->grade_id;
                        $month_id = $val->month_id;                        
                        $arr=[
                            'syear'=>$syear,
                            'sub_institute_id'=>$sub_institute_id,
                            'admission_year'=>$admission_year,
                            'fee_type_id'=>$fee_type_id,
                            'quota'=>$student_quota,
                            'grade_id'=>$grade_id,
                            'standard_id'=>$standard_id,                                    
                            'section_id'=>0,
                            'month_id'=>$month_id,
                            'amount'=>$amount,
                            'sub_institute_id'=>$sub_institute_id,
                        ];
                    $check_in = DB::table('fees_breackoff_logs')->where($arr)->get()->toArray();

                      if(empty($check_in)){
                        $arr['created_at'] = now();
                        $insert = DB::table('fees_breackoff_logs')->insert($arr);
                      } 

                      $delete = DB::table('fees_breackoff')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->where('month_id',$mon)->delete(); 
                    }
                }
                $res['status'] = "1";
                $res['message'] = "Deleted Successfully.";
            }else{
                $res['status'] = "0";
                $res['message'] = "No Breakoff Found.";
            }
        }
        elseif($request->has('student_active_inactive_excel') && $request->has('attachment'))
        {
            $get_active_inactive = $request->get('student_active_inactive_excel');
            $file = $request->file('attachment');
            
            // Check if a file was uploaded
            if (!$file) 
            {
                return "No file uploaded.";
            }

            // Get the file extension
            $ext = $file->getClientOriginalExtension();

            // Check if the file is an Excel file
            if (!in_array($ext, ['xlsx', 'xls'])) {
                return "Invalid file format. Only Excel files (xlsx or xls) are allowed.";
            }

            // Generate a timestamp
            $timestamp = time();

            // Construct the final file name
            $fileName = 'student_active_inactive_list_' . $timestamp . '.' . $ext;

            // Store the file in the 'public/bazar' directory
            $path = $file->storeAs('public/student_active_inactive_list', $fileName);

            // Read data from the Excel file
            $spreadsheet = IOFactory::load(storage_path("app/$path"));
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
            
            // Assuming the Excel file has headers, skip the first row
            $headers = array_shift($data);

            // Get the total number of rows
            $rowCount = count($data);
          
            $now = now();
            $success = true;

            if($rowCount!=0) 
            {
                $already_active_student = $already_inactive_student = $not_found_student = $style = "";
                $alreadyActive = [];
                $alreadyInactive = [];
                $notInDatabase = [];

                for ($i = 0; $i < $rowCount; $i++) 
                { 
                    $row = $data[$i];

                    $get_student_details = DB::table('tblstudent as s')
                        ->selectRaw("te.*, CONCAT(s.first_name,' ',s.middle_name,' ',s.last_name) as full_name")
                        ->join('tblstudent_enrollment as te', 'te.student_id', '=', 's.id')
                        ->where([
                            's.sub_institute_id' => $sub_institute_id,
                            'te.syear' => $syear,
                            's.enrollment_no' => $row[0]
                        ])
                        ->get()->toArray();
                    
                    if($get_active_inactive == "Active")
                    { 
                        if (!$get_student_details) {
                            $notInDatabase[] = $row;
                        }

                        foreach($get_student_details as $get_student_detail)
                        {
                            if ($get_student_detail && $get_student_detail->end_date === null) 
                            {
                                $alreadyActive[] = $get_student_detail;
                            }

                            if ($get_student_detail && $get_student_detail->end_date !== null) 
                            {
                                $alreadyInactive[] = $get_student_detail;
                            }

                            if(isset($get_student_detail->id)) 
                            {
                                DB::table('tblstudent_enrollment')->where('id', $get_student_detail->id)->where('sub_institute_id', $sub_institute_id)->update([
                                    'end_date' => null,
                                    'updated_on' => $now,
                                ]); 

                                // Delete Excel sheet
                                $filePath = 'public/student_active_inactive_list/student_active_inactive_list_' . $timestamp . '.' . $ext;
                                Storage::delete($filePath);
                            }
                        }
                    }
                    elseif($get_active_inactive == "Inactive")
                    {
                        if (!$get_student_details) {
                            $notInDatabase[] = $row;
                        }

                        foreach($get_student_details as $get_student_detail)
                        {
                            if ($get_student_detail && $get_student_detail->end_date === null) 
                            {
                                $alreadyActive[] = $get_student_detail;
                            }

                            if ($get_student_detail && $get_student_detail->end_date !== null) 
                            {
                                $alreadyInactive[] = $get_student_detail;
                            }

                            if(isset($get_student_detail->id)) 
                            {
                                DB::table('tblstudent_enrollment')->where('id', $get_student_detail->id)->where('sub_institute_id', $sub_institute_id)->update([
                                    'end_date' => $now,
                                    'updated_on' => $now,
                                ]); 

                                // Delete Excel sheet
                                $filePath = 'public/student_active_inactive_list/student_active_inactive_list_' . $timestamp . '.' . $ext;
                                Storage::delete($filePath);
                            }
                        }
                    }
                }
                
                $rowCountNotInDatabase = count($notInDatabase);
                $rowCountAlreadyActive = count($alreadyActive);
                $rowCountAlreadyInactive = count($alreadyInactive);

                $mess = 'Student '.$get_active_inactive.' Successfully.' . PHP_EOL
                    . 'Total Records : ' . $rowCount . PHP_EOL
                    . 'Not In Database : ' . $rowCountNotInDatabase . PHP_EOL
                    . 'Already Active : ' . $rowCountAlreadyActive . PHP_EOL
                    . 'Already Inactive : ' . $rowCountAlreadyInactive;

                if (!empty($mess)) 
                {					
                }

                $res['status'] = 1;
				$res['message'] = nl2br($mess);
            } 
        }
        else if (isset($request->rollno_standard)){
            $students_arr = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se','s.id','=','se.student_id')
            ->selectRaw('s.first_name,se.roll_no,se.student_id,se.standard_id,se.section_id')
            ->where(['s.sub_institute_id'=>$sub_institute_id,'se.syear'=>$syear])
            ->where('se.standard_id',$request->rollno_standard)
            ->where('se.section_id',$request->division)            
            ->whereNull('se.end_date')
            ->orderBy('s.first_name')
            ->get()->toArray();
            
            if(!empty($students_arr)){
                $roll_no = 1;
                foreach ($students_arr as $key => $value) {
                    $update=DB::table('tblstudent_enrollment')->where('student_id',$value->student_id)->update([
                        'roll_no'=>$roll_no,
                    ]);
                    $roll_no++;
                }

            }
            $res['status'] = 1;
            $res['message'] = "Roll No Updated Successfully !!";
        }
        else
        {
            $res['status'] = "0";
            $res['message'] = "No Changes Made.";
        }
        // earned Leave
        if($request->has('leave_rollover') && $request->leave_rollover=="on"){
            $getLeaveAllocation = DB::table('hrms_leave_allocation')
            ->where('sub_institute_id',$sub_institute_id)
            ->where('year',$syear)
            ->where('leave_type_id',9)
            ->whereNotNull('employee_id')
            ->where('value','!=',0)
            ->get()->toArray();

            foreach($getLeaveAllocation as $key=>$allocateValue){
                $checkExists = DB::table('hrms_leave_allocation')
                ->where('sub_institute_id',$sub_institute_id)
                ->where('year',($syear+1))
                ->where('leave_type_id',9)
                ->where('employee_id',$allocateValue->employee_id)
                ->first();

                if(empty($checkExists) && !isset($checkExists->employee_id)){
                    $controller = new LeaveSummaryReportController;
                    $request2 = new Request([
                        'type'=>'API',
                        'department_id'=>$allocateValue->department_id,
                        'emp_id'=>$allocateValue->employee_id,
                        'years'=>$syear,
                        'sub_institute_id'=>$sub_institute_id,
                    ]);
                    $leaveFunction = json_decode($controller->leaveSummaryReportShow($request2),true);
                    // if($allocateValue->employee_id==73){
                    //     echo "here=<pre>";print_r($leaveFunction);
                    // }
                    if(isset($leaveFunction) && !empty($leaveFunction) && isset($leaveFunction['new_data']['Earned Leave'][$allocateValue->employee_id])){
                        $leaveSpend = $leaveFunction['new_data']['Earned Leave'][$allocateValue->employee_id];
                        $opLeave = ($leaveFunction['op_data']['Earned Leave'][$allocateValue->employee_id]) ? $leaveFunction['op_data']['Earned Leave'][$allocateValue->employee_id] : 0;  // added on 25-07-2025
                        // $mainLeave = ($allocateValue->value - $leaveSpend); // commented on 25-07-2025
                        $mainLeave = ($opLeave>0 && $leaveSpend < $opLeave) ? ($opLeave - $leaveSpend) : ($allocateValue->value - $leaveSpend); // added on 25-07-2025
                        if($mainLeave>0){
                            // echo "if emp-".$allocateValue->employee_id."<pre>";print_r([$allocateValue->employee_id,$mainLeave]);
                            DB::table('hrms_leave_allocation')->insert([
                                'employee_id'=>$allocateValue->employee_id,
                                'department_id'=>$allocateValue->department_id,
                                'leave_type_id'=>9,
                                'year'=>($syear+1),
                                'value'=>$mainLeave,
                                'sub_institute_id'=>$allocateValue->sub_institute_id,
                                'created_at'=>now(),
                            ]);
                        }
                        // echo "<pre>";print_r([$allocateValue->employee_id,$mainLeave]);
                    }
                    // added on 01-07-2025 for if no leave taken then insert all value same
                    else if(!empty($leaveFunction) && isset($leaveFunction['op_data']['Earned Leave'])){
                        if($allocateValue->value>0){
                            // echo "else emp-".$allocateValue->employee_id."<pre>";print_r([$allocateValue->employee_id,$mainLeave]);
                            DB::table('hrms_leave_allocation')->insert([
                                'employee_id'=>$allocateValue->employee_id,
                                'department_id'=>$allocateValue->department_id,
                                'leave_type_id'=>9,
                                'year'=>($syear+1),
                                'value'=>$allocateValue->value,
                                'sub_institute_id'=>$allocateValue->sub_institute_id,
                                'created_at'=>now(),
                            ]);
                        }
                    }
                }
            }
            // exit;
            $res['status'] = "1";
            $res['message'] = "Data Rollover successfully!.";
            // echo "<pre>";print_r($getLeaveAllocation);exit;
        }
        // casual leave 
        if($request->has('cleave_rollover') && $request->cleave_rollover=="on"){
            $getLeaveAllocation = DB::table('hrms_leave_allocation')
            ->where('sub_institute_id',$sub_institute_id)
            ->where('year',$syear)
            ->where('leave_type_id',1)
            ->whereNotNull('employee_id')
            ->where('value','!=',0)
            ->get()->toArray();
//  echo "<pre>";print_r($getLeaveAllocation);exit;
            foreach($getLeaveAllocation as $key=>$allocateValue){
                $checkExists = DB::table('hrms_leave_allocation')
                ->where('sub_institute_id',$sub_institute_id)
                ->where('year',($syear+1))
                ->where('leave_type_id',1)
                ->where('employee_id',$allocateValue->employee_id)
                ->first();

                if(empty($checkExists) && !isset($checkExists->employee_id)){
                    $controller = new LeaveSummaryReportController;
                    $request2 = new Request([
                        'type'=>'API',
                        'department_id'=>$allocateValue->department_id,
                        'emp_id'=>$allocateValue->employee_id,
                        'years'=>$syear,
                        'sub_institute_id'=>$sub_institute_id,
                    ]);
                    $leaveFunction = json_decode($controller->leaveSummaryReportShow($request2),true);
                    // if($allocateValue->department_id==20){
                    //     echo "<pre>";print_r($leaveFunction);exit;
                    // }
                    if(isset($leaveFunction) && !empty($leaveFunction) && isset($leaveFunction['new_data']['Earned Leave'][$allocateValue->employee_id])){
                        $leaveSpend = $leaveFunction['new_data']['Earned Leave'][$allocateValue->employee_id];
                        $mainLeave = ($allocateValue->value - $leaveSpend);
                        if($mainLeave>0){
                            DB::table('hrms_leave_allocation')->insert([
                                'employee_id'=>$allocateValue->employee_id,
                                'department_id'=>$allocateValue->department_id,
                                'leave_type_id'=>1,
                                'year'=>($syear+1),
                                'value'=>$mainLeave,
                                'sub_institute_id'=>$allocateValue->sub_institute_id,
                                'created_at'=>now(),
                            ]);
                        }
                        // echo "<pre>";print_r([$allocateValue->employee_id,$mainLeave]);
                    }
                    // added on 01-07-2025 for if no leave taken then insert all value same
                    else if(!empty($leaveFunction) && isset($leaveFunction['op_data']['Earned Leave'])){
                        if($allocateValue->value>0){
                            DB::table('hrms_leave_allocation')->insert([
                                'employee_id'=>$allocateValue->employee_id,
                                'department_id'=>$allocateValue->department_id,
                                'leave_type_id'=>1,
                                'year'=>($syear+1),
                                'value'=>$allocateValue->value,
                                'sub_institute_id'=>$allocateValue->sub_institute_id,
                                'created_at'=>now(),
                            ]);
                        }
                    }
                }
            }

            $res['status'] = "1";
            $res['message'] = "Data Rollover successfully!.";
        }

        $res['sel_bk_month'] = $bk_months;

        return is_mobile($type, "student_bulk_update.index", $res, "redirect");
    }
}
