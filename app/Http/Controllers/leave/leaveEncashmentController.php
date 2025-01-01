<?php

namespace App\Http\Controllers\leave;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use carbon\carbon;
use DB;
use function App\Helpers\countDays;

class leaveEncashmentController extends Controller
{
    //
    // public function index(Request $request)
    // {
    //     try {
    //         $type = $request->type;
    //         $sub_institute_id = session()->get('sub_institute_id');
    //         $syear = session()->get('syear');

    //         if($type=="API"){
    //             $sub_institute_id = $request->get('sub_institute_id');
    //             $syear = $request->get('syear');  
    //         }

    //         $res['status_code']=0;
    //         $res['message']="Failed To get Data";

    //         // get emp with added salary structure 
    //         $empSalaryDetails = DB::table('tbluser as u')
    //         ->join('hrms_departments as hd',function($join) use($sub_institute_id) {
    //             $join->on('hd.id','=','u.department_id')->where('hd.sub_institute_id',$sub_institute_id);
    //         })
    //         ->join('employee_salary_structures as ess',function($join) use($syear){
    //             $join->on('ess.employee_id','=','u.id')->where('ess.year',$syear);
    //         })
    //         ->selectRaw('u.id as emp_id,u.employee_no as emp_code,u.department_id,u.openingleave,u.joined_date,u.CL_opening_leave,u.probation_period_from,u.probation_period_to,CONCAT_WS(" ",COALESCE(u.first_name, "-"),COALESCE(u.middle_name, "-"),COALESCE(u.last_name, "-")) as emp_name,hd.department as department_name,ess.*')
    //         ->where('u.sub_institute_id',$sub_institute_id)
    //         ->where('u.status',1)
    //         ->whereRaw('ess.employee_salary_data != "" ')
    //         ->orderBy('u.first_name','ASC')
    //         ->get();
           
    //         // get encashment as per salary 
    //         $encashmentData = [];
    //             // get acedemic month as per fees map year 
    //             $academic_start_month = "04";
    //             $academic_end_month = "03";
    //             // get today date 
    //             $todays_date = date('Y-m-d');
    //             // get end year date 					
    //             $end_Date = date('Y').'-'.$academic_start_month.'-01';
    //             $end_Date = date('Y-m-d',strtotime($end_Date));	

    //             if(strtotime($todays_date) > strtotime($end_Date) ){
    //                 // $yr = 2023;
    //                 $yr = date('Y');
    //             }else{
    //                 $yr = date('Y')-1;
    //             }
    //             // get leave from leave allocations 
    //             $from_date = $yr."-".$academic_start_month."-01";
    //             $to_date = ($yr+1)."-".$academic_end_month."-31";
    //             // echo "<pre>";print_r($to_date);exit;
    //              foreach ($empSalaryDetails as $key => $value) {
    //                 $emp_id = $value->emp_id;
    //                 $department_id = $value->department_id;
    //                 $probationfrom = $value->probation_period_from;
    //                 $probationto = $value->probation_period_to;
    //                 $CLopeningleave = $value->CL_opening_leave;
     
    //                 // get leave datas
    //                 $getLeaveData = DB::table('tbluser as u')
    //                ->join('hrms_leave_allocation as hla',function($join) use($sub_institute_id,$syear){
    //                     $join->on('hla.department_id','=','u.department_id')
    //                     ->where('hla.sub_institute_id',$sub_institute_id)
    //                     ->where('hla.year',$syear);
    //                })
    //                 ->join('hrms_leave_types as hlt', function($join) use ($sub_institute_id) {
    //                     $join->on('hlt.id', '=', 'hla.leave_type_id')
    //                          ->where('hlt.sub_institute_id', '=', $sub_institute_id);
    //                 })
    //                 ->join('hrms_departments as hd',function($join){
    //                     $join->on('hd.id','=','u.department_id');
    //                 })
    //                 ->leftjoin('hrms_emp_leaves as hel',function($join) use($value,$from_date,$to_date,$sub_institute_id){
    //                     $join->on('hel.user_id','=','u.id')
    //                     ->where('hel.status','!=','approved')
    //                     ->where(['hel.user_id'=>$value->emp_id,'hel.sub_institute_id'=>$sub_institute_id])
    //                     ->whereBetween('hel.from_date',[$from_date,$to_date]);
    //                 })
    //                 ->selectRaw('u.id as emp_id,u.employee_no,u.department_id,
    //                 hel.leave_type_id ,hlt.leave_type_id as leave_code,hlt.leave_type,hel.from_date as leave_date,hel.user_id as leave_user_id,hel.leave_type_id as taken_leave_id,hla.value as no_of_days_allotted,IFNULL(SUM(hel.day_type),0) as taken_leave,IFNULL((hla.value - SUM(hel.day_type)),0) PENDING')
    //                 ->where('u.sub_institute_id',$sub_institute_id)
    //                 ->groupByRaw('hla.leave_type_id,hlt.leave_type_id')
    //                 ->orderBy('u.first_name','ASC')
    //                 ->get()->toArray();    
                    
    //                 // count leaves 
    //                 $gross_salary = 0;
    //                 // get pay heads
    //                 $employee_salary_data = json_decode($value->employee_salary_data,true);

    //                 $salaryStructuer = [];
    //                 foreach ($employee_salary_data as $key2 => $value2) {
    //                     $payrollName = DB::table('payroll_types')->where('id',$key2 ?? 0)->value('payroll_name');
    //                     $salaryStructuer[$payrollName]= $value2;
    //                 }

    //                 foreach ($getLeaveData as $key1 => $leaveVal) {
    //                     if($leaveVal->leave_code == 'LTY001')
    //                         {
    //                             //check for probation_period
    //                             if(strtotime($probationto) > strtotime($todays_date) ){
    //                                 $l = number_format( ($CLopeningleave) ,2);
    //                             }
    //                             else{
    //                                 $l = number_format( ($leaveVal->no_of_days_allotted) ,2);
    //                             }
                                
    //                             $leaveVal->no_of_days_allotted = $l;
    //                             $leaveVal->PENDING= $l - $leaveVal->taken_leave;
    //                         }
    //                 }
    //                   // get gross salary 
    //                   $LE_payroll_heads = array ('BASIC', 'GRADE PAY', 'D.A', 'HRA' , 'OTHER ALLOW');

    //                   foreach($salaryStructuer as $phead => $pval)
    //                   {
    //                       if(in_array($phead,$LE_payroll_heads))
    //                       $gross_salary += $pval; 
    //                   }

    //                   $per_day_salary = (int)($gross_salary / 31);
                      
    //                   $leaveVal->gross_salary = $gross_salary;
    //                   $leaveVal->per_day_salary = $per_day_salary;
    //                   $leaveVal->total_amount = (int)($per_day_salary * $leaveVal->PENDING);
    //               //  add emp detail in new array 
    //                   $leaveVal->emp_name = $value->emp_name;
    //                   $leaveVal->department_name = $value->department_name;
    //                   $leaveVal->joined_date = ($value->joined_date) ? carbon::parse($value->joined_date)->format('d-m-Y') : '-';

    //                     //Show only those employees whose pending leave is greater than 5
    //                     if($leaveVal->PENDING >= 5)
    //                     {
    //                         $encashmentData[$leaveVal->emp_id] = $leaveVal;
    //                     }
                   
    //             }
    //             // echo "<pre>";print_r($encashmentData);exit;  
    //         $res['record'] = $encashmentData;

    //         return is_mobile($type, "leave.leave_encashment", $res, "view");

    //     } catch (Exception $e) {
    //         return response()->json($e->getMessage(), 500);
    //     }
    // }
    public function index(Request $request)
    {
        try {
            $type = $request->type;
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            if($type=="API"){
                $sub_institute_id = $request->get('sub_institute_id');
                $syear = $request->get('syear');  
            }

            $res['status_code']=0;
            $res['message']="Failed To get Data";

            // get emp with added salary structure 
            $empSalaryDetails = DB::table('tbluser as u')
            ->join('hrms_departments as hd',function($join) use($sub_institute_id) {
                $join->on('hd.id','=','u.department_id')->where('hd.sub_institute_id',$sub_institute_id);
            })
            ->join('employee_salary_structures as ess',function($join) use($syear){
                $join->on('ess.employee_id','=','u.id')->where('ess.year',$syear);
            })
            ->selectRaw('u.id as emp_id,u.employee_no as emp_code,u.department_id,u.openingleave,u.joined_date,u.CL_opening_leave,u.probation_period_from,u.probation_period_to,CONCAT_WS(" ",COALESCE(u.first_name, "-"),COALESCE(u.middle_name, "-"),COALESCE(u.last_name, "-")) as emp_name,hd.department as department_name,ess.*')
            ->where('u.sub_institute_id',$sub_institute_id)
            ->where('u.status',1)
            ->whereRaw('ess.employee_salary_data != "" ')
            ->orderBy('u.first_name','ASC')
            ->get();
           
            // get encashment as per salary 
            $encashmentData = $takenLeave =$leaveId= [];
                // get acedemic month as per fees map year 
                $academic_start_month = "04";
                $academic_end_month = "03";
                // get today date 
                $todays_date = date('Y-m-d');
                // get end year date 					
                $end_Date = date('Y').'-'.$academic_start_month.'-01';
                $end_Date = date('Y-m-d',strtotime($end_Date));	

                if(strtotime($todays_date) > strtotime($end_Date) ){
                    // $yr = 2023;
                    $yr = date('Y');
                }else{
                    $yr = date('Y')-1;
                }
                // get leave from leave allocations 
                $from_date = $yr."-".$academic_start_month."-01";
                $to_date = ($yr+1)."-".$academic_end_month."-31";
                
                // echo "<pre>";print_r($to_date);exit;
                 foreach ($empSalaryDetails as $key => $value) {
                    $emp_id = $value->emp_id;
                    $department_id = $value->department_id;
                    $probationfrom = $value->probation_period_from;
                    $probationto = $value->probation_period_to;
                    $CLopeningleave = $value->CL_opening_leave;
     
                    // get leave datas
                    $getLeaves = DB::table('hrms_emp_leaves') ->where('sub_institute_id', $sub_institute_id)
                    ->where('from_date','>=',$from_date)
                    ->where('to_date','<=',$to_date)
                    ->where('user_id','<=',$value->emp_id)
                    ->whereNotIn('status',['cancelled','rejected','approved_lwp'])
                    ->get()->toArray();

                    foreach ($getLeaves as $leaveKey => $leaveVal1) {
                        if(!isset($takenLeave[$value->emp_id])){
                            $takenLeave[$value->emp_id] = 0;
                        }
                        if(!isset($leaveId[$leaveVal1->id])){
                            $leaveId[$leaveVal1->id] = $leaveVal1->id;
                            $takenLeave[$value->emp_id] += countDays($leaveVal1->from_date,$leaveVal1->to_date,$leaveVal1->day_type,'');
                        }
                    }
                    $gross_salary = 0;
                    // get pay heads
                    $employee_salary_data = json_decode($value->employee_salary_data,true);

                    $salaryStructuer = [];
                    foreach ($employee_salary_data as $key2 => $value2) {
                        $payrollName = DB::table('payroll_types')->where('id',$key2 ?? 0)->value('payroll_name');
                        $salaryStructuer[$payrollName]= $value2;
                    }
                    $getLeaveData = DB::table('tbluser as u')
                   ->join('hrms_leave_allocation as hla',function($join) use($sub_institute_id,$syear){
                        $join->on('hla.department_id','=','u.department_id')
                        ->where('hla.sub_institute_id',$sub_institute_id)
                        ->where('hla.year',$syear);
                   })
                    ->join('hrms_leave_types as hlt', function($join) use ($sub_institute_id) {
                        $join->on('hlt.id', '=', 'hla.leave_type_id')
                             ->where('hlt.sub_institute_id', '=', $sub_institute_id);
                    })
                    ->join('hrms_departments as hd',function($join){
                        $join->on('hd.id','=','u.department_id');
                    })
                    ->selectRaw('u.id as emp_id,u.employee_no,u.department_id,
                    hlt.leave_type_id ,hlt.leave_type_id as leave_code,hlt.leave_type,hla.value as no_of_days_allotted')
                    ->where('u.sub_institute_id',$sub_institute_id)
                    ->where('u.id',$value->emp_id)
                    ->groupByRaw('hla.leave_type_id,hlt.leave_type_id')
                    ->orderBy('u.first_name','ASC')
                    ->get()->toArray();  

                    foreach ($getLeaveData as $key1 => $leaveVal) {
                        // Initialize properties
                        $leaveVal->taken_leave = isset($takenLeave[$leaveVal->emp_id]) ? $takenLeave[$leaveVal->emp_id] : 0;
                        $leaveVal->PENDING = $leaveVal->no_of_days_allotted - $leaveVal->taken_leave;
                        // echo "<pre>";print_r($leaveVal);
                        if ($leaveVal->leave_code == 'LTY001') {
                            // Check for probation period
                            if (strtotime($probationto) > strtotime($todays_date)) {
                                $l = number_format($CLopeningleave, 2);
                            } else {
                                $l = number_format($leaveVal->no_of_days_allotted, 2);
                            }
                    
                            $leaveVal->no_of_days_allotted = $l;
                            $leaveVal->PENDING = $l - $leaveVal->taken_leave;
                        }
                    
                        // Get gross salary
                        $LE_payroll_heads = array('BASIC', 'GRADE PAY', 'D.A', 'HRA', 'OTHER ALLOW');
                    
                        foreach ($salaryStructuer as $phead => $pval) {
                            if (in_array($phead, $LE_payroll_heads)) {
                                $gross_salary += $pval;
                            }
                        }
                    
                        $per_day_salary = (int)($gross_salary / 31);
                    
                        $leaveVal->gross_salary = $gross_salary;
                        $leaveVal->per_day_salary = $per_day_salary;
                        $leaveVal->total_amount = (int)($per_day_salary * $leaveVal->PENDING);
                    
                        // Add employee details
                        $leaveVal->emp_name = $value->emp_name;
                        $leaveVal->department_name = $value->department_name;
                        $leaveVal->joined_date = ($value->joined_date) ? Carbon::parse($value->joined_date)->format('d-m-Y') : '-';
                    
                        // Show only those employees whose pending leave is greater than 5
                        if ($leaveVal->PENDING >= 5) {
                            $encashmentData[$leaveVal->emp_id] = $leaveVal;
                        }
                    }
                    
                }
                // echo "<pre>";print_r($encashmentData);exit;  
            $res['record'] = $encashmentData;

            return is_mobile($type, "leave.leave_encashment", $res, "view");

        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $type = $request->type;
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            if($type=="API"){
                $sub_institute_id = $request->get('sub_institute_id');
                $syear = $request->get('syear');  
            }

            $month = date('m');
            $year = date('Y');
            $empid_Arr = $request->emp_id;
            $amount_Arr = $request->amount;

            foreach($empid_Arr as $key => $empid)
            {
                $amt = $amount_Arr[$key];
                
                // $check_sql = "SELECT * FROM hs_hr_employee_deduction WHERE employee_id = '".$empid."' AND MONTH = '".$month."' AND YEAR = '".$year."' AND deduction_type = '16'";
                // $res = mysql_query($check_sql);
                // if(mysql_num_rows($res) == 0)
                // {
                //     $sql = "INSERT INTO hs_hr_employee_deduction (MONTH,YEAR,employee_id,deduction_amount,created_by,created_on,deduction_type)
                //     VALUES ('".$month."','".$year."','".$empid."','".$amt."','".$_SESSION['empID']."',now(),'16')";
                // }
                // else
                // {
                //     $sql = "UPDATE hs_hr_employee_deduction SET deduction_amount = '".$amt."',created_by = '".$_SESSION['empID']."',
                //     created_on=now() WHERE employee_id = '".$empid."' AND MONTH = '".$month."' AND YEAR = '".$year."' AND deduction_type = '16'";
                // }
                // //echo $sql;
                // mysql_query($sql);
                // $msg = "Leave Encashment is successfully added in Monthly Payroll";
            }
            return is_mobile($type, "leave_encashment.index",$res);

        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
