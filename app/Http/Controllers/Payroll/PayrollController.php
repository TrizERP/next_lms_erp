<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMonthlySalaryData;
use App\Models\EmployeeSalaryStructure;
use App\Models\PayrollType;
use App\Models\HrmsDepartment;
use App\Models\user\tbluserModel;
use App\Http\Controllers\HRMS\HrmsController;
use App\Traits\Helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;
use function App\Helpers\employeeDetails;
use function App\Helpers\countDays;
use GenTux\Jwt\GetsJwtToken;
use DB;
use PDF;

class PayrollController extends Controller
{
    use GetsJwtToken;
    
    public function payrollType(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $data['data'] = PayrollType::where('sub_institute_id',$sub_institute_id)->get();
        // return view('payroll.payroll_type.index', ["data" => $data]);
        $type = $request->input('type');
        return is_mobile($type, "payroll.payroll_type.index", $data, "view");
    }

    public function payrollCreate(Request $request, $id = 0)
    {
        $sub_institute_id = session()->get('sub_institute_id');

        if ($id) {
            $payrollType = PayrollType::find($id);
            // echo "<pre>";print_r($payrollType);exit;
            return view('payroll.payroll_type.create', compact('payrollType'));
        }
        $payrollType['payroll_type'] = 1;
        $payrollType['payroll_name'] = '';
        $payrollType['amount_type'] = 1;
        $payrollType['status'] = '';
        $payrollType['payroll_percentage'] = '';
        $payrollType['sub_institute_id'] = $sub_institute_id;
        $payrollType['id'] = 0;
        $payrollType['day_count'] = 1;
        return view('payroll.payroll_type.create', compact('payrollType'));
    }

    public function payrollStore(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');

        if ($request->id > 0) {
            $payrollType = PayrollType::find($request->id);
        } else {
            $payrollType = new PayrollType();
        }
        $payrollType->payroll_type = $request->type;
        $payrollType->payroll_name = $request->payroll_name;
        $payrollType->amount_type = $request->amount_type;
        $payrollType->status = $request->status;
        $payrollType->day_count = $request->day_count;
        $payrollType->sub_institute_id = $sub_institute_id;
        $payrollType->payroll_percentage = $request->payroll_percentage !='' ? $request->payroll_percentage : 0;
        $payrollType->save();

        return redirect('payroll-type');
    }

    public function payrollDestroy(Request $request, $id)
    {
        if ($id > 0) {
            PayrollType::where('id', $id)->delete();
        }
        return redirect('payroll-type');
    }

    public function employeeSalaryStructure(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type=$request->input('type');
        $status=$request->input('emp_status') ?? 1;
        $sub_institute_id=session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $employee_id= ($request->emp_id!=0) ? implode(',',$request->emp_id) : '';
        $department_id= ($request->department_id!=0) ? implode(',',$request->department_id) : '';

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }

        $employees =employeeDetails($sub_institute_id,$employee_id,$status,$department_id);
        // echo "<pre>";print_r($employees);exit;

        $employeeLists= employeeDetails($sub_institute_id,"",$status,$department_id);
    //    echo "<pre>";print_r($employeeLists);exit;
        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();
        
        $employeeSalaryStructures = EmployeeSalaryStructure::where('sub_institute_id',$sub_institute_id)->where('year',$syear)->get();
       
        $employeeSalaryStructures = $employeeSalaryStructures->map(function ($employee) {
            $employee['employee_salary_data'] = json_decode($employee['employee_salary_data'], true);
            return $employee;
        })->pluck('employee_salary_data', 'employee_id');        

        $res['employees']=$employees;
        $res['payrollTypes']=$payrollTypes;
        $res['employeeSalaryStructures']=$employeeSalaryStructures;
        $res['employeeLists']=$employeeLists;
        $res['selected_emp']=$request->emp_id;
        $res['department_id']=$request->department_id;
        $res['emp_status'] = $status;
        // echo "<pre>";print_r($employeeSalaryStructures);exit;
        //return json_decode($employeeSalaryStructures[0]['employee_salary_data'], true);
        return is_mobile($type, "payroll.employee_salary_structure.index", $res, "view");
        
        // return view('payroll.employee_salary_structure.index', compact('employees', 'payrollTypes', 'employeeSalaryStructures', 'employeeLists'));
    }

    public function employeeSalaryStructureStore(Request $request)
    {
        // return $request->all();exit;
        // $year = Carbon::now()->format('Y');
        $year = session()->get('syear');
        $sub_institute_id =$request->session()->get('sub_institute_id');
        $type=$request->input('type');
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $year = Carbon::now()->format('Y');
        }
        
        $res['status_code']=0;
        $res['message']="Failed to Save";   
        // remove datas with value 0
        $empDetails =[];
        $totalAllowance = 0;

        if(!empty($request->emp)){
            // foreach for employees 
            foreach ($request->emp as $emp_ids => $emp_values) {
                $gender="";
                $totalAllowance = $totalSalary = $totalGrossSalary= 0;
                $allData = $jsonData = [];
                // payroll type details get head and amounts
                $getPfFlat = $getPTFlat = $getPF = $getPT = $payroll_percentage = $amount_type = $hasPF = $hasPT =0;
                // echo "<pre>";print_r($emp_values);

                foreach($emp_values as $key => $value){
                    if($key ==0){
                        $gender = $value;
                    }
                    $getPayrollType = PayrollType::where('id',$key)->first();
                    
                    $Per_Flat = isset($getPayrollType->payroll_percentage) ? $getPayrollType->payroll_percentage : 0;
                    $amount_type = isset($getPayrollType->amount_type) ? $getPayrollType->amount_type : 0;

                    if($key!=0){
                       $payroll_type_id = $value[0];

                       if($amount_type==1 && $Per_Flat!=0 && $value[1] > $Per_Flat){
                        $amount = ($value[1]-$Per_Flat);
                       }else{
                        $amount = $value[1];
                       }

                       $payroll_type_name = $value[2];
                       $payroll_type = $value[3] ?? 0;
                    //    if($payroll_type_name=="BASIC" || $payroll_type_name=="D.A" || $payroll_type_name=="GRADE PAY"){
                    //     $totalAllowance += $amount;
                    //    }
                    //     //    for flat 
                        
                        if($payroll_type_name=='PF'){
                            if($amount_type==1){
                                $getPfFlat = $amount;
                            }
                            $hasPF = $amount_type;
                        }

                        if($payroll_type_name=='PT'){
                            if($amount_type==1){
                                $getPTFlat = $amount;
                            }
                            $hasPT = $amount_type;
                        }

                    //     if(!in_array($payroll_type_name,['TDS','PT','PF'])){
                    //         $totalGrossSalary += $amount;
                    //     }
                       $totalSalary+=$amount;

                        // data to make json 
                       $allData[$payroll_type_id] = [$payroll_type_name=>$amount];

                    // 13-08-2024 caculate PT and PF
                        // check allowance if allowance make it gross Salary
                        if($getPayrollType->payroll_type==1){
                            // for PT Deduction 
                            $totalGrossSalary +=$amount;
                            // for PF Deduction 
                            if($payroll_type_name=="BASIC" || $payroll_type_name=="D.A" || $payroll_type_name=="GRADE PAY"){
                                $totalAllowance += $amount;
                            }
                        }
                    // 13-04-2024 end
                    }
                }
                // for contact emps 
                // $getIsCalculate = DB::table('tbluser as tu')->join('hrms_departments as hd','hd.id','=','tu.department_id')
                // ->where('tu.id',$emp_ids)->where('tu.sub_institute_id',$sub_institute_id)->value('is_calculated');
                // // check hrms_departments table, if is_calculated is 1 then pf or pt will be not count
                // if($getIsCalculate==1){
                //     $getPF = $getPT = 0;
                //     echo "if";
                // }
                // else{
                    // to count PT and PF percentage wise, in payroll_type table amount_type must be 2
                    // $getPF = ($hasPF == 2) ? Helpers::getPF($totalAllowance) : $getPfFlat;
                    // $getPT = ($hasPT == 2) ? Helpers::getPT($totalGrossSalary,$gender) : $getPTFlat; 
                // }           
                // echo "<pre>";print_r($getPT);

                // 13-08-2024 claculate PT as per eligilble emp_ids
                $getPF = ($hasPF == 2) ? Helpers::getPF($totalAllowance) : $getPfFlat; // getPfFlat is for set flat amounts
                $getPT = ($hasPT == 2) ? Helpers::getPT($totalGrossSalary,$gender) : $getPTFlat; // getPtFlat is for set flat amounts
                // 13-08-2024 end 
                // echo "<pre>";print_r($getPF);
                // echo "<pre>";print_r($getPT);
              
                foreach ($allData as $key => $value) {
                   if(isset($value['PF'])){
                    $jsonData[$key] = $getPF;
                   }else if(isset($value['PT'])){
                    $jsonData[$key] = $getPT;
                   }else{
                    $otherData = array_values($value);
                    $jsonData[$key] = intval($otherData[0]);
                   }
                }
                // convert into json 
                $encodeData = json_encode($jsonData);
                // echo "<pre>";print_r($encodeData);
                $find = EmployeeSalaryStructure::where(['employee_id' => $emp_ids, 'year' => $year], ['year' => $year,'sub_institute_id' => $sub_institute_id])->get()->toArray();
                // update data
                $res['status_code']=1;
                if(!empty($find) && $totalSalary!=0){
                    EmployeeSalaryStructure::where(['employee_id' => $emp_ids,'year' => $year,'sub_institute_id' => $sub_institute_id])->update([
                        'employee_id' => $emp_ids, 
                        'employee_salary_data' => $encodeData,
                        'year' => $year,
                        'sub_institute_id' => $sub_institute_id,
                        'updated_at'=>now(),
                    ]);
                    $res['message']="Updated Successfully";
                }
                // insert data 
                else{
                    if($totalSalary!=0){
                        EmployeeSalaryStructure::insert([
                            'employee_id' => $emp_ids, 
                            'employee_salary_data' => $encodeData,
                            'year' => $year,
                            'sub_institute_id' => $sub_institute_id,
                            'created_at'=>now(),
                        ]);
                    }
                    $res['message']="Added Successfully";
                }
            }
        }
        // exit;
        // return redirect('employee-salary-structure');
        return is_mobile($type, "employee_salary_structure.index", $res, "redirect");
        
    }

    public function salaryStructureReport(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $type = $request->type;
     
        $res['years'] = Helpers::getPairYears();
        return is_mobile($type, "payroll/salary_structure_report/index", $res, "view");
    }

    public function showSalaryStructureReport(Request $request)
    {
        $type = $request->type;
        $res['year'] = $year = $request->year;
        $res['selected_emp'] = $request->emp_id;
        $res['department_id'] = $request->department_id;
        $emp_id = ($request->emp_id!=0) ? implode(',',$request->emp_id) : 0;
        $department_id = ($request->department_id!=0) ? implode(',',$request->department_id) : 0;

        $sub_institute_id = session()->get('sub_institute_id');
        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();

        $header = [];
        foreach ($payrollTypes as $payrollType) {
            $header[$payrollType->id] = $payrollType->payroll_name;
        }
        
        $res['headers'] = $header;
        $res['years'] = Helpers::getPairYears();

        $res['salaryStructure'] = EmployeeSalaryStructure::join('tbluser as u',function($join){
            $join->on('u.id','=','employee_salary_structures.employee_id')->where('u.status',1); // 23-04-24 by uma
        })
        ->join('hrms_departments as hd',function($join){
            $join->on('hd.id','=','u.department_id'); // 27-04-24 by uma
        })
        ->select('employee_salary_structures.*', DB::raw('CONCAT_ws(" ",COALESCE(u.first_name,"-"), COALESCE(u.last_name,"-")) as employee_name'),'u.employee_no',DB::raw('IFNULL(hd.department,"-") as department'))
        ->when($emp_id!=0,function($q) use($emp_id){
            $q->whereRaw('employee_salary_structures.employee_id in ('.$emp_id.')');
        })
        ->when($department_id!=0,function($q) use($department_id){
            $q->whereRaw('u.department_id in ('.$department_id.')');
        })
        ->where('employee_salary_structures.sub_institute_id', $sub_institute_id)
        ->when($year!=0, function($query) use($year) {
            $query->where('employee_salary_structures.year', $year);
        })
        ->orderBy('employee_salary_structures.year','Desc')
        ->get();
        // echo "<pre>";print_r($res['salaryStructure']);exit;
        return is_mobile($type, "payroll/salary_structure_report/index", $res, "view");
    }

    public function form16(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }   
        $res['payrollTypes'] = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->get();
        $res['allowance'] = $res['payrollTypes']->where('payroll_type', 1);
        $res['deduction'] = $res['payrollTypes']->where('payroll_type', 2);
        $res['years'] = Helpers::getPairYears();
        $res['DefaultYear'] =$syear;

        return is_mobile($type, "payroll.form16.index", $res, "view");       
    }

    public function getEmployeeLists(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $department_id = $request->input('department_id');
	    $employee_id = $request->get('employee_id');
	
        $employees = tbluserModel::join('tbluserprofilemaster as upm', 'upm.id', '=', 'tbluser.user_profile_id')
        ->selectRaw('tbluser.id,IfNULL(tbluser.first_name, "-") as first_name, IFNULL(tbluser.last_name, "-") as last_name, tbluser.sub_institute_id, IfNULL(upm.name,"-") as user_profile')
        ->where('tbluser.sub_institute_id', $sub_institute_id)
        ->where('tbluser.department_id', $department_id)
        ->where('tbluser.status', 1)
        ->orderBy('tbluser.first_name')
        ->get()
        ->toArray();   

        return response()->json(['employees' => $employees, 'department_id' => $department_id, 'employee_id' =>$employee_id]);
    }

    public function form16Report(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
        }

        $department_id = $request->get('department_id');
	    $employee_id = $request->get('emp_id');
	    $year = $request->get('year');
	    $allowances = $request->get('allowance');
	    $deductions = $request->get('deduction');

        // Check if $allowances and $deductions are set
        if (isset($allowances) && is_array($allowances)) {
            $res['selected_allowances'] = $selected_allowances = array_values($allowances);
        } else {
            // Handle the case when $allowances is not set or not an array
            $res['selected_allowances'] = $selected_allowances = [];
        }

        if (isset($deductions) && is_array($deductions)) {
            $res['selected_deductions'] = $selected_deductions = array_values($deductions);
        } else {
            // Handle the case when $deductions is not set or not an array
            $res['selected_deductions'] = $selected_deductions = [];
        }
        
        // $res['amounts_id'] = $amounts_id = array_merge($selected_allowances, $selected_deductions);
       
        $res['payrollTypes'] = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->get();
        $res['allowance'] = $res['payrollTypes']->where('payroll_type', 1);
        $res['deduction'] = $res['payrollTypes']->where('payroll_type', 2);

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->where('status',1)->get()->toArray(); // 23-04-24 by uma

        $get_map_year = DB::table('fees_map_years')->selectRaw('from_month, to_month')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $year])->first();

        $from_month = $get_map_year->from_month ?? date('m');
        $to_month = $get_map_year->to_month ?? date('m');

        // Assuming $from_month and $to_month are integers
        $from_date = Carbon::createFromDate($year, $from_month, 1)->format('d/M/Y');

        $next_year = $year + 1;
        $to_date = Carbon::createFromDate($next_year, $to_month, 1)->endOfMonth()->format('d/M/Y');

        $get_department_name = DB::table('hrms_departments as hd')
            ->selectRaw('hd.department as department_name')
            ->join('tbluser as u', 'u.department_id', 'hd.id')
            ->where('u.status',1) // 23-04-24 by uma
            ->where('hd.sub_institute_id',$sub_institute_id)
            ->where(['u.sub_institute_id' => $sub_institute_id, 'u.id' => $employee_id])
            ->first();
            
        $res['get_employee_salary'] = DB::table('employee_salary_structures')->where(['employee_id' => $employee_id, 'sub_institute_id' => $sub_institute_id,'year'=>$year])->first();
        // echo "<pre>";print_r($res['get_employee_salary']);exit;
        $res['get_school_detail'] = DB::table('school_setup')->where('id', $sub_institute_id)->first();
        $res['get_employee_detail'] = DB::table('tbluser')->where('id', $employee_id)->first();

        $res['years'] = Helpers::getPairYears();
        $res['search'] = 1;
        $res['employee_id'] = $employee_id;
        $res['department_id'] = $department_id;
        $res['year'] = $year;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['department_name'] = $get_department_name;
        $res['DefaultYear'] =$syear;

        if(empty($res['get_employee_salary'])){
            $res['status_code'] = 0;
            $res['message']='Please Add Salary Structure for selected year !!';
        }
        return is_mobile($type, "payroll.form16.index", $res, "view");

        // return $request->all();
    }

    public function hrmsSalaryCertificateIndex(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $res['employee_id'] = $request->get('employee_id');
        $res['month_ids'] = [1=>"Jan",2=>"Feb",3=>"Mar",4=>"Apr",5=>"May",6=>"Jun",7=>"Jul",8=>"Aug",9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dec"];

        $res['departments'] = $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');
        $res['years'] = Helpers::getYears();
        $res['year'] = date('Y');
        $res['payrollTypes'] = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->where('payroll_type', 1)->get()->toArray();

        return is_mobile($type, "payroll.salary_certificate.index", $res, "view");        
    }

    public function hrmsSalaryCertificateReport(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        $department_id = $request->get('department_id');
	    $employee_id = $request->get('emp_id');
	    $year = $request->get('year');
	    $month_ids = $request->get('month_id');
	    $payroll_type_ids = $request->get('payroll_type_id');
	    $reason = $request->get('reason');
        
        $get_salaray_certificate = DB::table('hrms_salary_certificate')->where(['departement_id' => $department_id, 'employee_id' => $employee_id, 'sub_institute_id' => $sub_institute_id, 'year' => $year])->first();

        $res['pdfName'] = $filename = 'SC' . '_' . $year . '_' . $employee_id.'.pdf';
        
        $get_salary_certificate_html = $this->get_salary_certificate_html($employee_id,$year,$sub_institute_id,$month_ids,$department_id,$payroll_type_ids,$filename);

        // return $get_salary_certificate_html;exit;

        if($get_salaray_certificate)
        {
            DB::table('hrms_salary_certificate')->where(['departement_id' => $department_id, 'employee_id' => $employee_id, 'year' => $year,'sub_institute_id' => $sub_institute_id
                ])->update([
                    'month' => implode(',', $request->get('month_id')),
                    'payroll_type_id' => implode(',', $request->get('payroll_type_id')),
                    'reason' => $reason ?? '',
                    'pdf_file_name' => $filename,
                    'pdf_html' => $get_salary_certificate_html
                ]);

            $request->session()->flash('success', 'Salary Certificate Updated Successfully.');
        }
        else
        {
            // Record does not exist, insert a new one
            DB::table('hrms_salary_certificate')->insert([
                'departement_id' => $department_id,
                'employee_id' => $employee_id,
                'year' => $year,
                'month' => implode(',', $request->get('month_id')),
                'payroll_type_id' => implode(',', $request->get('payroll_type_id')),
                'reason' => $reason ?? '',
                'sub_institute_id' => $sub_institute_id,
                'pdf_file_name' => $filename,
                'pdf_html' => $get_salary_certificate_html,
                'created_by' => session()->get('user_id')
            ]);

            $request->session()->flash('success', 'Salary Certificate Generated Successfully.');
        }

        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->where('payroll_type', 1)->get()->toArray();

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->where('status',1)->get()->toArray(); // 23-04-24 by uma

        $get_department_name = DB::table('hrms_departments as hd')
            ->selectRaw('hd.department as department_name')
            ->join('tbluser as u', 'u.department_id', 'hd.id')
            ->where('hd.sub_institute_id',$sub_institute_id)
            ->where(['u.sub_institute_id' => $sub_institute_id, 'u.id' => $employee_id])
            ->first();
          
        $res['month_ids'] = [1=>"Jan",2=>"Feb",3=>"Mar",4=>"Apr",5=>"May",6=>"Jun",7=>"Jul",8=>"Aug",9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dec"];
        $res['years'] = Helpers::getYears();
        $res['employees'] = $employees;
        $res['employee_id'] = $employee_id;
        $res['department_id'] = $department_id;
        $res['year'] = $year;
        $res['selMonths'] = $month_ids;
        $res['payroll_type_ids'] = $payroll_type_ids;
        $res['reason'] = $reason;
        $res['departments'] = $departments;
        $res['department_name'] = $get_department_name;
        $res['payrollTypes'] = $payrollTypes;
        
        return is_mobile($type, "payroll.salary_certificate.index", $res, "view");
    }

    public function SalaryCertificatePdfDownload(Request $request)
    {
        $employee_id = $request->get('emp_id');
	    $year = $request->get('year');
	    $sub_institute_id = $request->get('sub_institute_id');

        $get_salary_certificate_pdf_file = DB::table('hrms_salary_certificate')->where([['employee_id', $employee_id], ['year', $year], ['sub_institute_id', $sub_institute_id]])->first();
        
        $pdf = PDF::loadHTML($get_salary_certificate_pdf_file->pdf_html);
    
        $filename = 'SC' . '_' . $year . '_' . $employee_id.'.pdf';
       
        return $pdf->download($filename);
    }

    public function get_salary_certificate_html($employee_id,$year,$sub_institute_id,$month_ids,$department_id,$payroll_type_ids,$filename)
    {
        $date = \Carbon\Carbon::now()->format('F jS, Y');

        $get_all_details = DB::table('employee_salary_structures as ess')
            ->selectRaw('ess.*,concat_ws(" ",u.first_name,u.middle_name,u.last_name) as employee_name,u.join_year as joining_year,hd.department as department_name,ss.SchoolName,u.gender')
            ->join('tbluser as u', 'u.id', '=', 'ess.employee_id')
            ->join('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
            ->join('school_setup as ss', 'ss.id', '=', 'u.sub_institute_id')
            ->where('ess.year', $year)                   
            ->where('ess.employee_id', $employee_id)
            ->where('ess.sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $pay_type= DB::table('payroll_types')->where('payroll_type', 1)->whereIn('id',$payroll_type_ids)->get()->pluck('payroll_name','id'); 

        // Constructing the HTML string
        $html = "<p>&nbsp;</p>";
        $html .= "<p>&nbsp;</p>";
        $html .= "<p>Date: <b>$date</b>,</p>";
        $html .= "<p style='text-align:center;'><u>TO WHOMESOEVER IT MAY CONCERN</u></p>";
        $html .= "<p>This is to certify that, <b>{$get_all_details[0]->employee_name}</b> is currently working with our institution as an <b>{$get_all_details[0]->department_name}</b> since <b>{$get_all_details[0]->joining_year}</b>. Her monthly salary breakup is as follows:</p>";

        // HTML table for salary details
        $html .= "<div style='margin: 0 auto; width: fit-content;'>";
        $html .= "<table style='margin: 0 auto;' border='1'>";
        $total_amt = $total = 0;

        foreach ($get_all_details as $key => $value)
        {
            if($value->gender == 'M')
            {
                $his = "His";
            }
            else
            {
                $his = "Her";
            }
            
            $arrayData = json_decode($value->employee_salary_data, true);

            foreach($arrayData as $index => $val)
            {
                if ($index != 2) { // Check if the payment type is not PT
                    $total = count($month_ids) * $val; // Multiply by the number of selected months
                }
                else 
                {
                    $total = $val; // For PT, keep the total as the value itself
                }
                
                if(isset($pay_type[$index]))
                {
                    $html .= "<tr><td>$pay_type[$index]</td><td>".$total."</td></tr>";

                    $total_amt += $total;
                }
            }
        }
        $html .= "<tr><td><strong>TOTAL GROSS MONTHLY SALARY:</strong></td><td>{$total_amt}</td></tr>";
        $html .= "</table>";
        $html .= "</div>";
        $html .= "<p>{$his} Total Gross Yearly Salary is <b>Rs.{$total_amt}</b>/- (Rupees {$this->convert_number_to_words($total_amt)} Only) This certificate is issued as per her request and bears no financial responsibility on or behalf of any authorized signatory.</p>";
        $html .= "<p>&nbsp;</p>";
        $html .= "<p>Yours faithfully,";
        $html .= "<br>{$get_all_details[0]->SchoolName},</p>";
        $html .= "<p>Authorized Signatory.</p>";
       
        return $html;
          
    }

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = [
            0 => 'zero',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'fourty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety',
            100 => 'hundred',
            1000 => 'thousand',
            1000000 => 'million',
            1000000000 => 'billion',
            1000000000000 => 'trillion',
            1000000000000000 => 'quadrillion',
            1000000000000000000 => 'quintillion',
        ];

      
        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int)$number < 0) || (int)$number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );

            return false;
        }

        if ($number < 0) {
            return $negative . $this->convert_number_to_words(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens = ((int)($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . $this->convert_number_to_words($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int)($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= $this->convert_number_to_words($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = [];
            foreach (str_split((string)$fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

    public function payrollDeduction(Request $request)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $payrollTypes = [];
        $res['selMonth'] = date('M');
        $res['selYear'] = date('Y');

        // process to get all emp create
        if($request->has('submit')){
            // return $request->all();
            
            $res['selDeduction'] =  $deduction_type= $request->deduction_type;
            $res['selType'] =  $payroll_type= $request->payroll_type;
            $res['selMonth'] = $month= $request->month;
            $res['selYear'] = $year= $request->year;

            $checkArr = [
                "month"=>$month,
                "year"=>$year,
                "deduction_type"=>$payroll_type,
                "sub_institute_id"=>$sub_institute_id,
            ];

            $getDeduction = DB::table('hrms_emp_payroll_deduction')->where($checkArr)->get()->toArray();
            $deductionArr= [];
            foreach($getDeduction as $key=>$value){
                $deductionArr[$value->employee_id]=$value->deduction_amount;
            }
           $res['all_emp'] = employeeDetails($sub_institute_id,'','','');
           $res['deductionArr'] = $deductionArr;
        }
        // end process  get all emp

        $payrollType = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->get()->toArray();
        $payrollTypeArr=[];
        foreach($payrollType as $key=>$value){
            $payrollTypeArr[$value['payroll_type']][]=[
                "id"=>$value['id'],
                "payroll_name"=>$value['payroll_name'],
            ];
        }
        $res['payrollTypes'] =  $payrollTypeArr;
        $res['months'] = Helpers::getMonths();
        $res['years'] = Helpers::getYears();
        
        // echo "<pre>";print_r($res['deductionArr']);exit;
        // return view('payroll.payroll_deduction.index', $result);
        return is_mobile($type, "payroll.payroll_deduction.index", $res, "view");
    }

    public function payrollDeductionStore(Request $request)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $created_by = session()->get('user_id');
        $payroll_type = $request->payroll_type;
        $month = $request->month;
        $year = $request->year;
        $deductAmt = $request->deductAmt;
        $i=0;
        foreach ($deductAmt as $emp_id => $amount) {
            $checkArr = [
                "month"=>$month,
                "year"=>$year,
                "employee_id"=>$emp_id,
                "deduction_type"=>$payroll_type,
                "sub_institute_id"=>$sub_institute_id,
            ];
            $check = DB::table('hrms_emp_payroll_deduction')->where($checkArr)->first();
            if(empty($check)){
                $checkArr['created_by']=$created_by;
                $checkArr['deduction_amount']=$amount ?? 0;
                $checkArr['created_at']=now();

                $insert = DB::table('hrms_emp_payroll_deduction')->insert($checkArr);
                $i++;
            }else{
                $checkArr['created_by']=$created_by;
                $checkArr['deduction_amount']=$amount ?? 0;
                $checkArr['updated_at']=now();
                
                $update = DB::table('hrms_emp_payroll_deduction')->where('id',$check->id)->update($checkArr);
                $i++;
            }
        }
        // echo "<pre>";print_r($request->all());exit;
        if($i>0){
            $res['status_code'] = 1;
            $res['message']='Added Successfully !!';
        }else{
            $res['status_code'] = 0;
            $res['message']='Failed To Add !!';
        }
        return is_mobile($type, "payroll_deduction.index", $res);
    }

    public function rollOver(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('status', 1)->get();
        
        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();

        $employeeSalaryStructures = EmployeeSalaryStructure::where('year', (Carbon::now()->format('Y')))->get();

        $employeeSalaryStructures = $employeeSalaryStructures->map(function ($employee) {
            $reult['employee_salary_data'] = json_decode($employee->employee_salary_data, true);
            $reult['year'] = $employee->year;
            return $reult;
        });

        //return json_decode($employeeSalaryStructures[0]['employee_salary_data'], true);
        return view('payroll.employee_salary_structure.rollover', compact('employees', 'payrollTypes', 'employeeSalaryStructures'));
    }

    public function rolloverEmployeeSalaryStructure(Request $request)
    {
        $year = Carbon::now()->format('Y');
        // return $year;
        if ($request->emp) {
            foreach ($request->emp as $employee) {
                $employeeDetails = [];
                foreach ($employee as $key => $data) {
                    if ($key == 0) $employeeDetails['id'] = $data;
                    if ($key > 0) $employeeDetails['data'][$data[0]] = $data[1];
                }
                EmployeeSalaryStructure::updateOrCreate(['employee_id' => $employeeDetails['id'], 'year' => $employee['year'] + 1], [
                    'employee_salary_data' => json_encode($employeeDetails['data']),
                    'year' => $employee['year'] + 1
                ]);
            }
        }
        return redirect('roll-over');
    }

    public function monthlyPayrollReport(Request $request)
    {
        $type= $request->type;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile = $request->session()->get('user_profile_name');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $user_profile = $request->user_profile_name;
        }
        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();
        //        return $payrollTypes;
        $employeeDetails = employeeDetails($sub_institute_id);
        $header = [];
        $months = Helpers::getMonths();
        $years = Helpers::getYears();
        $header['total_day'] = 'Total Day';
        $list = [];
        $totalDay = '';
        $hide_button = $show = true;
        $employeeSalaryDetails = 0;
        $totaldeduction = $totalallowance = 0;
        foreach ($payrollTypes as $payrollType) {
            $header[$payrollType->id] = $payrollType->payroll_name;
        }
        $header['total_deduction'] = 'Total Deduction';
        $header['total_payment'] = 'Total Payment';
        $header['received_by'] = 'Received By';
        $del = 0;
        if ($request->emp_id && $request->delete) {
            $employeeSalaryData = EmployeeMonthlySalaryData::where(['employee_id'=> $request->emp_id,'month'=>$request->month,'year'=>$request->year,'sub_institute_id'=>$sub_institute_id])->delete();
        $del = 1;

        }
        if ($request->emp_id && $request->year && $request->month) {
            
            // return $request->all();
            $employeeName = tbluserModel::find($request->emp_id);
            $employeeSalaryData = EmployeeMonthlySalaryData::where([['employee_id', $request->emp_id],['month', $request->month],['year',$request->year],[ 'sub_institute_id', $sub_institute_id]])->first();
            $totalDay = $request->total_day;
            $list['month'] = $request->month;
            $list['year'] = $request->year;
            if ($employeeName) $list['employeeName'] = $employeeName;
            if ($employeeSalaryData) {
                $employeeSalaryDetails = json_decode($employeeSalaryData->employee_salary_data, true);
                $totaldeduction = $employeeSalaryData->total_deduction;
                $totalallowance = $employeeSalaryData->total_payment + $employeeSalaryData->total_deduction;
                $totalDay = $employeeSalaryData->total_day;
                $list['month'] = $employeeSalaryData->month;
                $list['year'] = $employeeSalaryData->year;
              
                if($totalDay!=0){
                    $hide_button = false;
                }
               
            }

            //return $list;
        }

        if ($request->emp_id && $request->month && $request->year && $request->total_day) {
            $employeeSalaryData = EmployeeMonthlySalaryData::where([['employee_id', $request->emp_id],['month',$request->month],['year',$request->year],[ 'sub_institute_id', $sub_institute_id]])->first();
            if ($employeeSalaryData) {
                $employeeSalaryDetails = json_decode($employeeSalaryData->employee_salary_data, true);
                $totalDay = $employeeSalaryData->total_day;

            } else if(!$request->delete){
                //return $request->all();
                $employeeSalaryDetails = EmployeeSalaryStructure::where([['employee_id', $request->emp_id], ['sub_institute_id', $sub_institute_id]])->first();
                if(empty($employeeSalaryDetails)){
                    $res=['employees' => $employeeDetails,'hide_button'=>$hide_button, 'header' => $header, 'list' => $list, 'total_day' => $totalDay, 'hideButton' => $hide_button,'totaldeduction'=> $totaldeduction,'totalallowance' => $totalallowance,'months' => $months,'years' => $years,'employee_id'=>$request->emp_id,'message' => 'Salary Structure Not Found For this User','employee_id'=>$request->emp_id,'department_id'=>$request->department_id];

                    return is_mobile($type, "payroll.monthly_payroll_report.index", $res, "view");
                    // return view('payroll.monthly_payroll_report.index', ['employees' => $employeeDetails,'hide_button'=>$hide_button, 'header' => $header, 'list' => $list, 'total_day' => $totalDay, 'hideButton' => $hide_button,'totaldeduction'=> $totaldeduction,'totalallowance' => $totalallowance,'months' => $months,'years' => $years,'employee_id'=>$request->employee_id])->with(['message' => 'Salary Structure Not Found For this User','employee_id'=>$request->employee_id]);
                }
                $employeeSalaryDetails = json_decode($employeeSalaryDetails->employee_salary_data, true);

                $preparPayrollType = [];
                foreach ($payrollTypes as $payrollType) {
            //                    return $employeeSalaryDetails;
                    if(isset($employeeSalaryDetails[$payrollType->id]) && $payrollType->payroll_type == 1) {
                        $preparPayrollType[]['allowance'] = [$employeeSalaryDetails[$payrollType->id],$payrollType->amount_type,$payrollType->id,$payrollType->payroll_name];
                    } else if (isset($employeeSalaryDetails[$payrollType->id])) {
                        //return $payrollType->amount_type;
                        $preparPayrollType[]['deduction'] = [$employeeSalaryDetails[$payrollType->id],$payrollType->amount_type,$payrollType->id,$payrollType->payroll_name];
                        //return $preparPayrollType;
                    }
                }
                $employeefinalDisplayData = [];
                foreach ($preparPayrollType as $value){
                    //return $preparPayrollType;
                    if(isset($value['allowance'])) {
                        $allowence =  $value['allowance'][0];
                        if($value['allowance'][1] == 1) $allowence = round( ($allowence / 30) * $request->total_day);
                        if($value['allowance'][1] == 2) $allowence = (round(($allowence / 30) * $request->total_day));
                        $employeefinalDisplayData[$value['allowance'][2]] = $allowence;
                        $totalallowance = $totalallowance + $allowence;
                    }

                    if(isset($value['deduction'])) {
                        $deduction =  $value['deduction'][0];
                        $deductionName=  (($value['deduction'][3] == 'Pro.Tax') ? 1 : 0);
                        if($value['deduction'][1] == 1 && !$deductionName) $deduction = round(($deduction / 30) * $request->total_day);
                        if($value['deduction'][1] == 2 && !$deductionName) $deduction = round(($deduction / 30) * $request->total_day);
                        $employeefinalDisplayData[$value['deduction'][2]] = $deduction;
                        $totaldeduction = $totaldeduction + $deduction;
                    }
                }
                $employeeSalaryDetails = $employeefinalDisplayData;
                $totalDay = $request->total_day;

            }
            // return $employeeSalaryDetails;
        }

        if ($request->total_day > 31) {
            $res =['employees' => $employeeDetails,'hide_button'=>$hide_button, 'header' => $header, 'list' => $list, 'employeeSalaryDetails' => $employeeSalaryDetails, 'total_day' => $totalDay, 'hideButton' => $hide_button,'totaldeduction'=> $totaldeduction,'totalallowance' => $totalallowance,'months' => $months,'years' => $years,'message' => 'please enter valid days','employee_id'=>$request->emp_id,'department_id'=>$request->department_id];

            return is_mobile($type, "payroll.monthly_payroll_report.index", $res, "view");

            // return view('payroll.monthly_payroll_report.index', ['employees' => $employeeDetails,'hide_button'=>$hide_button, 'header' => $header, 'list' => $list, 'employeeSalaryDetails' => $employeeSalaryDetails, 'total_day' => $totalDay, 'hideButton' => $hide_button,'totaldeduction'=> $totaldeduction,'totalallowance' => $totalallowance,'months' => $months,'years' => $years])->with(['message' => 'please enter valid days','employee_id'=>$request->employee_id]);
        }
        if($user_profile=="Teacher"){
            $employeeSalaryData = EmployeeMonthlySalaryData::where([['employee_id', $request->emp_id],['month', $request->month],['year',$request->year],[ 'sub_institute_id', $sub_institute_id]])->first();

            $res = ['months'=> $request->month,'year' => $request->year,'employee_id'=>$request->emp_id];
            if(!empty($employeeSalaryData)){
                $res['pdf_link'] = env('APP_URL')."/monthly-payroll-report/pdf/".$request->emp_id."/".$request->month.'/'.$request->year;
            }else{
                $res["status_code"]=0;
                $res['message']="No Slip Found for this Month and Year";
            }
        }else{
            if(isset($request->total_day)){
                $hide_button = false;
            }
            $res = ['employees' => $employeeDetails,'hide_button'=>$hide_button, 'header' => $header, 'list' => $list, 'employeeSalaryDetails' => $employeeSalaryDetails, 'total_day' => $totalDay, 'hideButton' => $hide_button,'totaldeduction'=> $totaldeduction,'totalallowance' => $totalallowance,'months' => $months,'years' => $years,'employee_id'=>$request->emp_id,'department_id'=>$request->department_id];
        }

        if ($request->emp && $request->save) {
            $employeeSalaryData = EmployeeMonthlySalaryData::where([['employee_id', $request->emp['id']],['month',$request->month],['year',$request->year],[ 'sub_institute_id', $sub_institute_id]])->first();
            if(!$employeeSalaryData) {
                // echo "<pre>";print_r($request->all());exit;
                // return $request->emp['total_payment'];
                EmployeeMonthlySalaryData::create([
                    'month' => $request->month,
                    'year' => $request->year,
                    'employee_id' => $request->emp['id'],
                    'sub_institute_id' => $sub_institute_id,
                    'total_deduction' => $request->emp['total_deduction'],
                    'total_payment' => $request->emp['total_payment'],
                    'received_by' => $request->received_by,
                    'total_day' => $request->total_day,
                    'employee_salary_data' => json_encode($request->emp['salary']),
                ]);
                $res['pdf_link'] = env('APP_URL')."/monthly-payroll-report/pdf/".$request->emp['id']."/".$request->month.'/'.$request->year;
            }
            $res['hide_button'] = false;
        }
    
        if($del==1){
            $res['total_day'] = '';
            $res['hide_button'] = true;
        }

        return is_mobile($type, "payroll.monthly_payroll_report.index", $res, "view");

        // return view('payroll.monthly_payroll_report.index', ['employees' => $employeeDetails,'hide_button'=>$hide_button, 'header' => $header, 'list' => $list, 'employeeSalaryDetails' => $employeeSalaryDetails, 'total_day' => $totalDay, 'hideButton' => $hide_button,'totaldeduction'=> $totaldeduction,'totalallowance' => $totalallowance,'months' => $months,'years' => $years,'employee_id'=>$request->employee_id]);
    }

    public function payrollBankWiseReport(Request $request) {
        $months = Helpers::getMonths();
        $years = Helpers::getYears();
        $list = [];
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $list['month'] = date('M');
        $list['year'] = date('Y');
        $employeeSalaryData = [];
        if($request->month && $request->year) {
            $list['month'] = $request->month;
            $list['year'] = $request->year;
            // $employeeSalaryData = EmployeeMonthlySalaryData::with('getUser')->where([['month',$request->month],['year', $request->year],['sub_institute_id', $sub_institute_id]])->get();
            $employeeSalaryData = DB::table('employee_monthly_salary_data as emd')->join('tbluser as u','u.id','=','emd.employee_id')->where([['emd.month',$request->month],['emd.year', $request->year],['emd.sub_institute_id', $sub_institute_id]])->where('u.status',1)->get();
        }
        $currentYear = date('Y');
        // echo "<pre>";print_r($employeeSalaryData);exit;
        return view('payroll.payroll_bankwise_report.index', ['employees' => $employeeSalaryData,'list'=>$list,'months' => $months,'years' => $years,'currentYear'=>$currentYear]);


    }

    public function monthlyPayrollPdf(Request $request,$id, $month, $year)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        
        $employeeSalaryData = EmployeeMonthlySalaryData::with('getUser')->where([['employee_id', $id],[ 'sub_institute_id', $sub_institute_id],['month', $month],['year', $year]])->first();

        $employeeSalaryStructure = EmployeeSalaryStructure::where([['employee_id', $id],[ 'sub_institute_id', $sub_institute_id]])->first();

        $get_school_name = DB::table('school_setup')->select('ReceiptHeader')->where(['id' => $sub_institute_id])->first();

        $get_user_detail = DB::table('tbluser as ts')
            ->selectRaw('ts.*,tum.name as profile_name')
            ->join('tbluserprofilemaster as tum',function($join){
                $join->on('ts.user_profile_id','=','tum.id')->where('ts.status',1);  // 23-04-24 by uma
            })
            ->where(['tum.sub_institute_id' => $sub_institute_id, 'ts.id' => $id])
            ->first();

        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();
        if ($employeeSalaryData) {
            $employeeData = [];
            $employeeData['name'] = $get_user_detail->first_name . ' '. $get_user_detail->last_name;
            $employeeData['emp_code'] = $get_user_detail->employee_no;
            $employeeData['designation'] = $get_user_detail->occupation;
            $employeeData['join_date'] = date('Y-m-d', strtotime($get_user_detail->joined_date));
            $employeeData['profile_name'] = $get_user_detail->profile_name;
            $employeeData['account_no'] = $get_user_detail->account_no;
            $employeeData['total_day'] = number_format($employeeSalaryData->total_day, 1); // For 1 decimal places
            $employeeData['pf_no'] = $get_user_detail->pf_no;
            $employeeData['bank_ac_no'] = $get_user_detail->account_no;
            // get lwp 
            $startOfMonth = Carbon::createFromFormat('M Y', $request->month . ' ' . $request->year)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromFormat('M Y', $request->month . ' ' . $request->year)->endOfMonth()->format('Y-m-d'); 
            
            $lwpQuery = DB::table('hrms_emp_leaves as a')->whereRaw("a.sub_institute_id=$sub_institute_id AND (a.from_date BETWEEN '$startOfMonth' AND '$endOfMonth' OR a.to_date BETWEEN '$startOfMonth' AND '$endOfMonth') AND a.`status`='approved_lwp' AND a.user_id=$id")->get()->toArray();

            $lwpCounts=0;
            foreach($lwpQuery as $lkey => $lvalue){
                $loopDate = Carbon::parse($lvalue->from_date);
                $loopDateEnd = Carbon::parse($lvalue->to_date);
                while ($loopDate->lte($loopDateEnd)) {
                    if ($loopDate->month == Carbon::parse($lvalue->from_date)->month) {
                        // Run the logic if the months match
                        $lwpCounts += countDays($lvalue->from_date, $lvalue->to_date, $lvalue->day_type);
                    }
                    $loopDate->addDay();
                }
            }
            $employeeData['leave_without_pay'] = $lwpCounts;
            $employeeData['month'] = $employeeSalaryData->month;
            $employeeData['year'] = $employeeSalaryData->year;
            $employeeData['total_payment'] = $employeeSalaryData->total_payment + $employeeSalaryData->total_deduction;
            $employeeData['deduction'] =  $employeeSalaryData->total_deduction;
            $employeeData['net_salary'] =  $employeeSalaryData->total_payment;
            $employeeSalaryDetails = json_decode($employeeSalaryData->employee_salary_data, true);
            $employeeSalaryStructureDetails = json_decode($employeeSalaryStructure->employee_salary_data, true);
            $actualpayment = 0;
            $allowancekey = -1;
            $deductionkey = 0;
            $salaryData = [];
//            return $employeeSalaryDetails;
            foreach ($payrollTypes as $payrollType) {
                $empSal = isset($employeeSalaryStructureDetails[$payrollType->id]) ? $employeeSalaryStructureDetails[$payrollType->id] : 0;
                $empDetailSal =isset($employeeSalaryDetails[$payrollType->id]) ? $employeeSalaryDetails[$payrollType->id] : 0;
                if($payrollType->payroll_type == 1) {
                    $allowancekey = $allowancekey + 1;
                    $salaryData[$allowancekey] = [$payrollType->payroll_name,$empSal,$empDetailSal,'allowance'];
                    $actualpayment = $actualpayment + $empSal;
                    $allowancekey =$allowancekey + 1 ;
                } else {
                    //return $payrollType->amount_type;
                    $deductionkey = $deductionkey + 1;
                    $salaryData[$deductionkey] = isset($empDetailSal) ? [$payrollType->payroll_name,$empSal,$empDetailSal,'deduction'] : [];
                    $deductionkey = $deductionkey + 1;
                }
            }
            ksort($salaryData);
            $salaryData = array_chunk($salaryData,2);
                //            return $salaryData;
            $employeeData['salary_data'] = $salaryData;
            $employeeData['school_name'] = $get_school_name;
            $employeeData['ruppee_in_word']= $this->displaywords($employeeData['net_salary']);


            $employeeData['total_actual_payment'] = $actualpayment;
            view()->share('employeeData',$employeeData);
            $pdf = PDF::loadView('payroll.monthly_payroll_report.employeeSalaryPdf');
            return $pdf->download('salary.pdf');
        } else{
            return redirect()->back();
        }
    }

    public function displaywords($num){
        $num    = ( string ) ( ( int ) $num );

        if( ( int ) ( $num ) && ctype_digit( $num ) )
        {
            $words  = array( );

            $num    = str_replace( array( ',' , ' ' ) , '' , trim( $num ) );

            $list1  = array('','one','two','three','four','five','six','seven',
                'eight','nine','ten','eleven','twelve','thirteen','fourteen',
                'fifteen','sixteen','seventeen','eighteen','nineteen');

            $list2  = array('','ten','twenty','thirty','forty','fifty','sixty',
                'seventy','eighty','ninety','hundred');

            $list3  = array('','thousand','million','billion','trillion',
                'quadrillion','quintillion','sextillion','septillion',
                'octillion','nonillion','decillion','undecillion',
                'duodecillion','tredecillion','quattuordecillion',
                'quindecillion','sexdecillion','septendecillion',
                'octodecillion','novemdecillion','vigintillion');

            $num_length = strlen( $num );
            $levels = ( int ) ( ( $num_length + 2 ) / 3 );
            $max_length = $levels * 3;
            $num    = substr( '00'.$num , -$max_length );
            $num_levels = str_split( $num , 3 );

            foreach( $num_levels as $num_part )
            {
                $levels--;
                $hundreds   = ( int ) ( $num_part / 100 );
                $hundreds   = ( $hundreds ? ' ' . $list1[$hundreds] . ' Hundred' . ( $hundreds == 1 ? '' : 's' ) . ' ' : '' );
                $tens       = ( int ) ( $num_part % 100 );
                $singles    = '';

                if( $tens < 20 ) { $tens = ( $tens ? ' ' . $list1[$tens] . ' ' : '' ); } else { $tens = ( int ) ( $tens / 10 ); $tens = ' ' . $list2[$tens] . ' '; $singles = ( int ) ( $num_part % 10 ); $singles = ' ' . $list1[$singles] . ' '; } $words[] = $hundreds . $tens . $singles . ( ( $levels && ( int ) ( $num_part ) ) ? ' ' . $list3[$levels] . ' ' : '' ); } $commas = count( $words ); if( $commas > 1 )
        {
            $commas = $commas - 1;
        }

            $words  = implode( ', ' , $words );

            $words  = trim( str_replace( ' ,' , ',' , ucwords( $words ) )  , ', ' );
            if( $commas )
            {
                $words  = str_replace( ',' , ' and' , $words );
            }

            return $words;
        }
        else if( ! ( ( int ) $num ) )
        {
            return 'Zero';
        }
        return '';

    }

    public function payrollReport(Request $request)
    {
        $type= $request->type;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->sub_institute_id;
        }
        $res['months'] = Helpers::getMonths();
        $res['years']= Helpers::getYears();

        $employeeDetails = [];
        $res['month'] = date('M');
        $res['year'] = date('Y');

        $newData=[];
        
        if ($request->year && $request->month) {
            $empData = EmployeeMonthlySalaryData::join('tbluser as u',function($join) use($request){
                $join->on('u.id','=','employee_monthly_salary_data.employee_id')
                ->when($request->department_id!=0,function($q) use($request){
                    $q->whereIn('u.department_id',$request->department_id);
                });
            })
            ->selectRaw('employee_monthly_salary_data.*,u.id,CONCAT_WS(" ",COALESCE(u.first_name, "-"),COALESCE(u.last_name, "-")) as full_name,u.employee_no,u.department_id as department_ids')
            ->where([['employee_monthly_salary_data.month',$request->month],['employee_monthly_salary_data.year',$request->year],['employee_monthly_salary_data.sub_institute_id',$sub_institute_id]])
            ->get()->toArray();

            $startOfMonth = Carbon::createFromFormat('M Y', $request->month . ' ' . $request->year)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromFormat('M Y', $request->month . ' ' . $request->year)->endOfMonth()->format('Y-m-d'); 

            $employeeIds = array_map(function($employee) {
                return $employee['employee_id'];
            }, $empData);

            $department_ids = array_map(function($employee) {
                return $employee['department_ids'];
            }, $empData);

            $lwpArr = $leaveDays = $absentArr = $checkVal = $holidayArr= []; 
            // get absent,lwp,leave
            if(!empty($employeeIds)){
                // get employees lwp
                $lwpQuery = DB::table('hrms_emp_leaves')->whereRaw('((from_date between "'.$startOfMonth.'" and "'.$endOfMonth.'") OR (to_date between "'.$startOfMonth.'" and "'.$endOfMonth.'")) and user_id in ('.implode(',',$employeeIds).') and sub_institute_id = '.$sub_institute_id.' AND STATUS = "approved_lwp" and status !="cancelled"')->get()->toArray();
                
                foreach($lwpQuery as $lkey => $lvalue){
                    if(!isset($lwpArr[$lvalue->user_id])){
                        $lwpArr[$lvalue->user_id]=0;
                    }
                    $lFromDate =$lvalue->from_date;
                    $lToDate =$lvalue->to_date;
                    $ldayType =$lvalue->day_type;

                    $lwpArr[$lvalue->user_id] += countDays($lFromDate,$lToDate,$ldayType);
                    // lwp dates to find absent days 
                    $loopDate1 = Carbon::parse($lFromDate);
                    $loopDateEnd1 = Carbon::parse($lToDate);
                    while ($loopDate1->lte($loopDateEnd1)) {
                        $formattedDate = $loopDate1->format('Y-m-d');
                        if (!isset($checkVal[$lvalue->user_id][$formattedDate])) {
                            $checkVal[$lvalue->user_id][$formattedDate] = 0;
                        }
                        $checkVal[$lvalue->user_id][$formattedDate] = 1;
                        $loopDate1->addDay();
                    }
                }

                // get leave Days
                $leaveQuery = DB::table('hrms_emp_leaves')->whereRaw('((from_date between "'.$startOfMonth.'" and "'.$endOfMonth.'") OR (to_date between "'.$startOfMonth.'" and "'.$endOfMonth.'")) and user_id in ('.implode(',',$employeeIds).') and sub_institute_id = '.$sub_institute_id.' AND STATUS!="approved_lwp" and status !="cancelled" ')->get()->toArray();

                foreach($leaveQuery as $ldkey => $ldvalue){
                    if(!isset($leaveDays[$ldvalue->user_id])){
                        $leaveDays[$ldvalue->user_id]=0;
                    }
                  
                    $lFromDate =$ldvalue->from_date;
                    $lToDate =$ldvalue->to_date;
                    $ldayType =$ldvalue->day_type;

                    $leaveDays[$ldvalue->user_id] += countDays($lFromDate,$lToDate,$ldayType);

                    // leave dates to find absent days 
                    $loopDate2 = Carbon::parse($lFromDate);
                    $loopDateEnd2 = Carbon::parse($lToDate);
                    while ($loopDate2->lte($loopDateEnd2)) {
                        $formattedDate = $loopDate2->format('Y-m-d');
                        if (!isset($checkVal[$ldvalue->user_id][$formattedDate])) {
                            $checkVal[$ldvalue->user_id][$formattedDate] = 0;
                        }
                        $checkVal[$ldvalue->user_id][$formattedDate] = 1;
                        $loopDate2->addDay();
                    }
                }
                 // get attandance Days
                 $attendanceDate = DB::table('hrms_attendances')->whereRaw('(day between "'.$startOfMonth.'" and "'.$endOfMonth.'") and user_id in ('.implode(',',$employeeIds).') and sub_institute_id = '.$sub_institute_id)->get()->toArray();

                 foreach($attendanceDate as $adkey => $advalue){
                     if(!isset($checkVal[$advalue->user_id][$advalue->day])){
                         $checkVal[$advalue->user_id][$advalue->day]=0;
                     }
 
                     $checkVal[$advalue->user_id][$advalue->day] = 1;
                 }

                 // get absent Days
                 $holidayData = DB::table('hrms_holidays')->whereRaw('((from_date between "'.$startOfMonth.'" and "'.$endOfMonth.'") OR (to_date between "'.$startOfMonth.'" and "'.$endOfMonth.'")) and sub_institute_id = '.$sub_institute_id.' and department in ('.implode(',',$department_ids).')')->get()->toArray();

                 foreach($holidayData as $hdkey => $hdvalue){
                      // leave dates to find absent days 
                    $loopDate3 = Carbon::parse($hdvalue->from_date);
                    $loopDateEnd3 = Carbon::parse($hdvalue->to_date);
                    while ($loopDate3->lte($loopDateEnd3)) {
                        $formattedDate = $loopDate3->format('Y-m-d');
                        if (!isset($holidayArr[$formattedDate])) {
                            $holidayArr[$formattedDate] = 0;
                        }
                        $holidayArr[$formattedDate] = 1;
                        $loopDate3->addDay();
                    }
                 }
            }
            // echo "<pre>";print_r($holidayArr);exit;
            // make new Array for data for each employee
            foreach ($empData as $key => $value) {
               $newData[$key] = $value;
               $newData[$key]['lwp_days']= isset($lwpArr[$value['employee_id']]) ? $lwpArr[$value['employee_id']] : 0;
               $newData[$key]['leave_days'] = isset($leaveDays[$value['employee_id']]) ? $leaveDays[$value['employee_id']] : 0;
               $newData[$key]['absent_days'] = 0;
                // leave dates to find absent days 
                $monthFromDate = Carbon::parse($startOfMonth);
                $monthEndDate = Carbon::parse($endOfMonth);

                while ($monthFromDate->lte($monthEndDate)) {
                    $formattedDate = $monthFromDate->format('Y-m-d');
                    $existsDate = [];
                    if(!isset($checkVal[$value['employee_id']][$formattedDate]) && !isset($holidayArr[$formattedDate])){
                        $newData[$key]['absent_days'] += 1;
                    }

                    $monthFromDate->addDay();
                }
            }

            if(empty($empData)){
                $res['status_code']=0;
                $res['message']="Monthly Payroll Not Found !!";
            }

            $res['employeeDetails'] = $newData;
            $res['month'] = $request->month;
            $res['year'] = $request->year;
            $res['department_id'] = $request->department_id;
        }
        // echo "<pre>";print_r($res['employeeDetails']);
        // exit;
        // return view('payroll.payroll_report.index', ['employees' => $employeeDetails, 'list' => $list,'months'=> $months,'years'=> $years]);
        return is_mobile($type, "payroll.payroll_report.index", $res, "view");
    }

    public function employeePayrollHistory(Request $request)
    {
        //return $request->all();
        $type = $request->type;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->get('sub_institute_id');
        }
        $employeeLists = employeeDetails($sub_institute_id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();
        $currentYearemployeeDetails = [];
        $nextYearemployeeDetails = [];
        $years = Helpers::getYears();
        $header = [];
        $list = [];
        $employeeDetails = [];
        foreach ($payrollTypes as $payrollType) {
            $header[$payrollType->id] = $payrollType->payroll_name;
        }

        if ($request->year) {
            $year = explode('-',$request->year);
            // $startYear = $year[0];
            // $endYear = $year[1];
            
            $currentYearemployeeDetails = EmployeeMonthlySalaryData::join('tbluser as u',function($join) use($request){
                $join->on('u.id','=','employee_monthly_salary_data.employee_id')
                ->when($request->department_id!=0,function($q) use($request){
                    $q->where('u.department_id',$request->department_id);
                    });
                })
                ->when($request->emp_id!=0,function($q) use($request){
                    $q->where('employee_monthly_salary_data.employee_id',$request->emp_id);
                })
                ->where([['employee_monthly_salary_data.year',$year],['employee_monthly_salary_data.sub_institute_id',$sub_institute_id]])
                ->whereIn('employee_monthly_salary_data.month',['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'])
            ->get();
                // echo "<pre>";print_r($currentYearemployeeDetails);exit;
            $currentYearemployeeDetails = $currentYearemployeeDetails->map(function($employee){
                $data = [];
                $data['employee_id'] = $employee->employee_id;
                $data['employee_no'] = $employee->employee_no;
                $data['employee_name'] = $employee->first_name . ' ' . $employee->middle_name . ' ' . $employee->last_name;
                $data['data'] = json_decode($employee->employee_salary_data, true);
                $data['total_day'] =$employee->total_day;
                $data['month'] =$employee->month;
                $data['year'] =$employee->year;
                $data['total_deduction'] =$employee->total_deduction;
                $data['total_payment'] =$employee->total_payment;
                return $data;
            });
            // $nextYearemployeeDetails = EmployeeMonthlySalaryData::whereIn('month',[])->where([['year',$endYear],['sub_institute_id',$sub_institute_id],['employee_id',$request->employee_id]])->get();
            // $nextYearemployeeDetails = $nextYearemployeeDetails->map(function($employee){
            //     $data = [];
            //     $data['employee_id'] = $employee->employee_id;
            //     $data['employee_name'] = $employee->getUser->first_name . ' ' . $employee->getUser->middle_name . ' ' . $employee->getUser->last_name;
            //     $data['data'] = json_decode($employee->employee_salary_data, true);
            //     $data['total_day'] =$employee->total_day;
            //     $data['month'] =$employee->month;
            //     $data['year'] =$employee->year;
            //     $data['total_deduction'] =$employee->total_deduction;
            //     $data['total_payment'] =$employee->total_payment;
            //     return $data;
            // }); 'nextYearemployeeDetails'=>$nextYearemployeeDetails, 
            $list['month'] = $request->month;
            $list['year'] = $request->year;
            $list['employee_id'] = $request->emp_id;
            //return $list;
        }
        $res = ['employeeLists' => $employeeLists,'currentYearemployeeDetails' => $currentYearemployeeDetails,'header' => $header, 'list' => $list,'years' => $years,'selEmp'=>$request->emp_id,'selDept'=>$request->department_id,'selYear'=>$request->year];
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "payroll.employee_payroll_history.index", $res, "view");

        // return view('payroll.employee_payroll_history.index', ['employeeLists' => $employeeLists,'currentYearemployeeDetails' => $currentYearemployeeDetails,'header' => $header, 'list' => $list,'years' => $years]);
    }

    public function payrollTypeReport(Request $request){
        $type=$request->type;
        $sub_institute_id=session()->get('sub_institute_id');

        $res=session()->get('data');
        $res['months'] = Helpers::getMonths();
        $res['years'] = Helpers::getYears();
        $res['py_types'] = PayrollType::where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->where('status',1)->get()->toArray();
        
        return is_mobile($type, "payroll.payroll_report.payrollTypeReport", $res, "view");
    }

    public function payrollTypeReportCreate(Request $request){
        $type=$request->type;
        $sub_institute_id=session()->get('sub_institute_id');

        // echo "<pre>";print_r($request->all());exit;
        $res['selectedMonth']=$month=$request->month;
        $res['selectedYear']=$year=$request->year;
        $res['selectedPayrollType']=$payrollTypes=$request->payroll_type;
        $res['payrollHeads'] =PayrollType::where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')
            ->when($payrollTypes,function($q) use($payrollTypes){
                $q->whereIn('id',$payrollTypes);
            })->where('status',1)->select('payroll_name','id')->get()->toArray();
        
        $res['payrollData'] = DB::table('employee_monthly_salary_data as emsd')
            ->join('tbluser as u',function($query) {
                $query->on('u.id','=','emsd.employee_id')->where('u.status',1);
            })
            ->join('tbluserprofilemaster as up','up.id','=','u.user_profile_id')
            ->selectRaw('emsd.*,concat_ws(" ",COALESCE(u.first_name,"-"),COALESCE(u.last_name,"-")) as emp_name,u.employee_no,up.name as profile_name')
            ->where(['emsd.month'=>$month,'emsd.year'=>$year])->get()->toArray();

            if(empty($res['payrollData'])){
                $res['status_code'] = 0;
                $res['message'] = "No data Found";
            }
            // echo "<pre>";print_r($res['payrollData']);exit;
        return is_mobile($type, "payrollTypeReport.index", $res);
    }

    public function monthlyPayroll(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
        $res = session()->get('data');
        $res['months'] = Helpers::getMonths();
        $res['years'] = Helpers::getYears();
        $res['selYear'] = date('Y');
        $res['selMonth'] = date('M');
        // echo "<pre>";print_r(session()->all());exit;
        return is_mobile($type,'payroll.monthly_payroll_report.newIndex',$res,'view');
    }

    public function monthlyPayrollCreate(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userProfile = session()->get('user_profile_name');
        $profileUserId = session()->get('user_id');

        $res['employee_id'] = $employee_id= ($request->emp_id!=0) ? implode(',',$request->emp_id) : '';
        $res['department_id'] = $department_id= ($request->department_id!=0) ? implode(',',$request->department_id) : '';
        
        $res['selYear'] = $year = $request->year;
        $res['selMonth'] = $month = $request->month;

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $userProfile = $request->user_profile_name;
            $profileUserId = $request->user_id;
        }
       
        // get emp by search 
        $employeeDetails = employeeDetails($sub_institute_id,$employee_id,'',$department_id,$userProfile,$profileUserId);

        // empData with val 
        $newData = [];
 
        foreach ($employeeDetails as $key => $value) {
            # store all details of employee
            $newData[$key] = $value;
            // get monthly salary Data and add into newData array
            $newData[$key]['monthlyData'] = DB::table('employee_monthly_salary_data')->where(['sub_institute_id'=>$sub_institute_id,'year'=>$year])->where('employee_id',$value['id'])->where('month',$month)->first();
            if(isset($newData[$key]['monthlyData']->total_day)){
                $newData[$key]['totalDay'] = round($newData[$key]['monthlyData']->total_day,2);
            }else{
                $year = $year ?? Carbon::now()->year;
                $month = $month ?? Carbon::now()->format('M');

                $monthNumber = date('n', strtotime($month));
                $currentMonth = date('n');

                $from_date = Carbon::createFromDate($year, $monthNumber, 1);
                if($currentMonth==$monthNumber && $year == date('Y')){
                    $to_date = now();
                }else{
                    $to_date = $from_date->copy()->endOfMonth();
                }
                // $emp_att = DB::table('hrms_attendances')->whereBetween('day',[$from_date,$to_date])->where('user_id',$value['id'])->count();
              
                $request2 = new Request(['type'=>"API",'sub_institute_id'=>$sub_institute_id ,'syear'=>$syear,'from_date'=>$from_date,'to_date'=>$to_date,'department_id'=>[$value['department_id']],'emp_id'=>$value['id']]);
                // $hrmsController = new HrmsController;
                // $attResponse = json_decode($hrmsController->departmentAttendanceReportCreate($request2),true);
                // $AttTotalDays = isset($attResponse['empData'][0]['totalDays']) ? $attResponse['empData'][0]['totalDays'] : 0;
                // $AttTotalAb = isset($attResponse['empData'][0]['total_ab_day']) ? $attResponse['empData'][0]['total_ab_day'] : 0;

                // $emp_att = ($AttTotalDays - $AttTotalAb);
                $emp_att = $this->getTotalDays($request2);
                $newData[$key]['totalDay'] = round($emp_att,2);
            }
        }
                // echo "<pre>";print_r($newData);
                // exit;

        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();

        $header = [];
        foreach ($payrollTypes as $payrollType) {
            $header[$payrollType->id] = $payrollType->payroll_name;
        }

        // echo "<pre>";print_r($newData);exit;
        if(empty($newData)){
            $res['status_code'] = 0;
            $res['message'] = "Failed Find Employees";
        }

        if(empty($header)){
            $res['status_code'] = 0;
            $res['message'] = "Payroll Not Found";
        }else{
            $header['total_deduction'] = 'Total Deduction';
            $header['total_payment'] = 'Total Payment';
            $header['received_by'] = 'Received By';
        }
        $res['selected_emp']=$request->emp_id;
        $res['department_id']=$request->department_id;
        $res['header'] =$header;
        $res['employeeDetails'] = $newData;
        $res['months'] = Helpers::getMonths();
        $res['years'] = Helpers::getYears();
      
        // echo "<pre>";print_r($newData);exit;
        return is_mobile($type,'payroll.monthly_payroll_report.newIndex',$res,'view');
    }

    function getEmpMonthlyData(Request $request){
        // echo "<pre>";print_r($request->all());exit;
        $sub_institute_id = session()->get('sub_institute_id');
        $totalDay = $request->totalDay;
        
        $payrollTypes = PayrollType::where('sub_institute_id',$sub_institute_id)->where('status', 1)->orderBy('sort_order')->get();
        $employeeSalaryDetails = EmployeeSalaryStructure::where(['employee_id'=> $request->emp_id, 'sub_institute_id'=>$sub_institute_id,'year'=>$request->year])->first();

        if(empty($employeeSalaryDetails)){
            $res['status_code']=0;
            return $res;
        }
        $employeeSalaryDetails = json_decode($employeeSalaryDetails->employee_salary_data, true);

        $preparPayrollType = [];
        
        $totaldeduction = $totalallowance = 0;
        foreach ($payrollTypes as $payrollType) {
            // for allowance
            if(isset($employeeSalaryDetails[$payrollType->id]) && $payrollType->payroll_type == 1) {

                $checkAllowance = DB::table('hrms_emp_payroll_deduction')->where('employee_id',$request->emp_id)->where(['sub_institute_id'=>$sub_institute_id,'month'=>$request->month,'year'=>$request->year,'deduction_type'=>$payrollType->id])->first();
                $payrollAmount=$employeeSalaryDetails[$payrollType->id];
                if(isset($checkAllowance->deduction_amount)){
                    $payrollAmount = ($payrollAmount + $checkAllowance->deduction_amount);
                }

                $preparPayrollType[]['allowance'] = [$payrollAmount,$payrollType->amount_type,$payrollType->id,$payrollType->payroll_name,$payrollType->day_count];
            }
            // for deduction
             else if (isset($employeeSalaryDetails[$payrollType->id])) {

                $checkDeduction = DB::table('hrms_emp_payroll_deduction')->where('employee_id',$request->emp_id)->where(['sub_institute_id'=>$sub_institute_id,'month'=>$request->month,'year'=>$request->year,'deduction_type'=>$payrollType->id])->first();
                // echo "<pre>";print_r($checkDeduction);exit;
                $payrollAmount=$employeeSalaryDetails[$payrollType->id];
                if(isset($checkDeduction->deduction_amount)){
                    $payrollAmount = ($payrollAmount + $checkDeduction->deduction_amount);
                }

                $preparPayrollType[]['deduction'] = [$payrollAmount,$payrollType->amount_type,$payrollType->id,$payrollType->payroll_name,$payrollType->day_count];
            }
        }
        $employeefinalDisplayData = [];
        $totalSal =0;
        foreach ($preparPayrollType as $value){
            // for allowance
            $monthNo = date('n', strtotime($request->month)); // Converts months
            $payrollMonthDays = Carbon::create($request->year, $monthNo)->daysInMonth;
            if(isset($value['allowance'])) {
                $allowence =  $value['allowance'][0];
                if($value['allowance'][1] == 1  && $value['allowance'][4]==0) {
                    $allowence = round( ($allowence / $payrollMonthDays) * $request->totalDay);
                }
                if($value['allowance'][1] == 2  && $value['allowance'][4]==0) {
                    $allowence = (round(($allowence / $payrollMonthDays) * $request->totalDay));
                }
                $employeefinalDisplayData[$value['allowance'][2]] = $allowence;

                if(in_array($value['allowance'][3],["BASIC","GRADE PAY","D.A"])){
                    $totalSal= ($totalSal+$allowence);
                }
                $totalallowance = $totalallowance + $allowence;
            }
          
            // for deduction
            if(isset($value['deduction'])) {
                // 13-08-2024 start
                    // check eligible
                    $getEligible = DB::table('tbluser')->where('id',$request->emp_id)->first(); 

                    if($getEligible->pf_deduction=="N" && $value['deduction'][3]=="PF"){
                        $value['deduction'][0]=0;
                    }
                    if($getEligible->pt_deduction=="N" && $value['deduction'][3]=="PT"){
                        $value['deduction'][0]=0;
                    }
                $deduction =  $value['deduction'][0];

                // 13-08-2024 end 
                $deductionName=  (($value['deduction'][3] == 'PT') ? 1 : 0);
                if($totalSal < 15000 && $value['deduction'][3]=="PF"){
                    //$deduction = round(($deduction / $payrollMonthDays) * $request->totalDay);  
                    $deduction = round(($totalSal / 100) * 12);
                    // echo $deduction.'<br>';  
                    if($deduction > 1800){
                        $deduction=1800;
                    }
                }
                else if($value['deduction'][1] == 1 && !$deductionName && $value['deduction'][3]!="PF" && $value['deduction'][4]==0){
                    $deduction = round(($deduction / $payrollMonthDays) * $request->totalDay);
                }
                else if($value['deduction'][1] == 2 && !$deductionName && $value['deduction'][3]!="PF" && $value['deduction'][4]==0){
                    $deduction = round(($deduction / $payrollMonthDays) * $request->totalDay);  
                }
                
                $employeefinalDisplayData[$value['deduction'][2]] = $deduction;
                $totaldeduction = $totaldeduction + $deduction;
            }
        
        }
        if(!empty($employeefinalDisplayData)){
            $employeefinalDisplayData['total_deduction'] = $totaldeduction;
            $employeefinalDisplayData['total_payment'] = ($totalallowance - $totaldeduction);
        }
        // echo "<pre>";print_r($totalSal);exit;

        $res['salaryData'] = $employeefinalDisplayData;
        $res['totalDay'] = round($request->totalDay,2);

        return $res;
    }

    public function monthlyPayrollStore(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $payrollVal = $request->payrollVal;
        $jsonVal=[];
        // echo "<pre>";print_r($request->all());exit;
        // make json
        foreach ($payrollVal as $emp_id => $value) {
           $jsonVal[$emp_id] = json_encode($value['payrollHead']); 
        }
        // add update value;
        $i=0;
        foreach ($payrollVal as $emp_id => $value) {
            $employeeSalaryData = EmployeeMonthlySalaryData::where('employee_id', $emp_id)->where('month',$request->month)->where('year',$request->year)->where(['sub_institute_id'=> $sub_institute_id])->first();

            $dataArr = [
                'month' => $request->month,
                'year' => $request->year,
                'employee_id' => $emp_id,
                'sub_institute_id' => $sub_institute_id,
            ];

            if(!empty($employeeSalaryData)){
                $dataArr['total_deduction'] = $value['total_deduction'];
                $dataArr['total_payment'] = $value['total_payment'];
                $dataArr['received_by'] = $value['received_by'];
                $dataArr['total_day'] = $value['total_day'];
                $dataArr['employee_salary_data'] = $jsonVal[$emp_id];
                $dataArr['updated_at'] = now();
                $update = DB::table("employee_monthly_salary_data")->where('id',$employeeSalaryData->id)->update($dataArr);
                $i++;
            }else{
                $dataArr['total_deduction'] = $value['total_deduction'];
                $dataArr['total_payment'] = $value['total_payment'];
                $dataArr['received_by'] = $value['received_by'];
                $dataArr['total_day'] = $value['total_day'];
                $dataArr['employee_salary_data'] = $jsonVal[$emp_id];
                $dataArr['created_at'] = now();
                $insert = DB::table("employee_monthly_salary_data")->insert($dataArr);
                $i++;
            }
         }

        if($i==0){
            $res['status_code'] = 0;
            $res['message'] = "Not able to add data";
        }else{
            $res['status_code'] = 1;
            $res['message'] = "Inserted Successfully";
        }
        return is_mobile($type,'monthly_payroll.index',$res);
    }

    // 2024-08-20 getTotal Days
    public function getTotalDays(Request $request){
        $sub_institute_id=$request->sub_institute_id;
        $syear=$request->syear;
        $from_date =$request->from_date;
        $to_date =$request->to_date;
        $user_id=$request->emp_id;
        $department_id=$request->department_id;
        // getUserData 
        $userData = DB::table('tbluser')->where('id',$user_id)->first();
        // get weekDays
        $startDate = Carbon::parse($from_date);
        $endDate = Carbon::parse($to_date);
        $weekDays = [];
        $countSundays = $totalDays = $attAb = 0;
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            if ($date->isSunday()) {
                $countSundays++;
                $weekDays[] = $date->format('Y-m-d');
            }
        }
        $weekday_off = $countSundays;
        // echo "<br>weekDays<br>";
        // echo "<pre>";print_r($weekDays);
        // get Holidays 
        $get_hrms_holidays = DB::table('hrms_holidays')
        ->where('sub_institute_id', $sub_institute_id)
        ->whereBetween('from_date',[$from_date,$to_date])
        ->whereBetween('to_date',[$from_date,$to_date])
        ->whereRaw('FIND_IN_SET("'.$department_id.'", department)')
        ->get()->toArray();
        
        $holiday = 0;
        // $hstartDate = Carbon::parse($from_date);
        // $hendDate = Carbon::parse($to_date);
        $holidayDates = [];
        foreach ($get_hrms_holidays as $key => $value) {
            $hstartDate = Carbon::parse($value->from_date);
            $hendDate = Carbon::parse($value->to_date);
            for ($date = $hstartDate; $date->lte($hendDate); $date->addDay()) {
                $holiday++;
                $holidayDates[] = $date->format('Y-m-d');
            }
        }
        // echo "<br>Holidays<br>";
        // echo "<pre>";print_r($holidayDates);
        // get users attandance
        $totalAtt = 0;
        $noAtt=$attArr=  [];
        $astartDate = Carbon::parse($from_date);
        $aendDate = Carbon::parse($to_date);
        for ($date = $astartDate; $date->lte($aendDate); $date->addDay()) {
            if ($date->isSunday()) {
                $countSundays++;
            }else{
                $searchDate = Carbon::parse($date)->format('Y-m-d');
                $attData = DB::table('hrms_attendances')
                ->where(['sub_institute_id'=>$sub_institute_id,'user_id'=>$user_id])->where('day',$searchDate)->groupBy('day')->count();
                if($attData>0){
                    if(!in_array($searchDate,$holidayDates)){
                        $totalAtt += $attData;
                        $attArr[]= $searchDate;
                    }
                }else{
                    $noAtt[] = $searchDate;
                }
            }
        }   
        // echo "<br>Attendance<br>";
        // echo "<pre>";print_r($attArr);
        // echo "<br>No Att<br>";
        // echo "<pre>";print_r($noAtt);
        // get users leave
        $userLeaves = DB::table('hrms_emp_leaves as hel')
        // ->where('hel.user_id',$user_id)
        ->whereRaw('((hel.from_date >= "'.$from_date->format('Y-m-d').'" and hel.to_date <="'.$to_date->format('Y-m-d').'") 
                    OR hel.from_date like "'.$to_date->format('Y-m').'%" OR hel.to_date like "'.$to_date->format('Y-m').'%")
                    and hel.user_id = "'.$user_id.'"') 
        //->whereRaw('(hel.from_date >= "'.$from_date->format('Y-m-d').'")
        ->get()->toArray();
        // echo "<pre>";print_r($userLeaves);
      
        $totDayPlaus = $totDayMinus = $noData = 0;
        $leaveDates=[];
        // check leave date in attandance and also aprroved_lwp
        foreach ($userLeaves as $key => $value) {
            $leaveFrom = Carbon::parse($value->from_date);
            $leaveTo = Carbon::parse($value->to_date);
            // echo $lastDateOfMonth;
            for ($leavedate = $leaveFrom; $leavedate->lte($leaveTo); $leavedate->addDay()) {
                $checkLeave = $leavedate->format('Y-m-d');
                $checkMonth = $leavedate->format('Y-m');
                $leaveMonth = $leaveTo->format('Y-m');
                    if($checkMonth == $leaveMonth){
                        // Leaves that are not in attandance.. 
                        if(!in_array($checkLeave,$attArr) && !in_array($checkLeave,$holidayDates) && !in_array($value->status,["cancelled"])){
                            $totDayPlaus = ($totDayPlaus+$value->day_type);
                        // echo $checkLeave.'<br>';

                        }
                        // if date not found in attdance and leave is approved lwp then minus, count only full day leave because half day will be in attandance
                        if($value->status == "approved_lwp" && $value->day_type=="0.5" && !in_array($checkLeave,$holidayDates)){
                            $totalAtt = ($totalAtt - $value->day_type);
                        }
                        else if(!in_array($checkLeave,$attArr) && !in_array($checkLeave,$holidayDates) && $value->status == "approved_lwp"){
                            $totDayMinus = ($totDayMinus+$value->day_type);
                        }
                        // echo $leavedate->format('Y-m-d');
                        if(in_array($checkLeave,$holidayDates) && $totDayMinus!=0){
                            $holiday--;
                        }
                        if(in_array($checkLeave,$weekDays)){
                            $weekday_off--;
                        }
                        
                        $leaveDates[] =$checkLeave;
                    }
                }
        }
        // echo "<br>Leaves<br>";
        // echo "<p/re>";print_r($leaveDates);
       // date not found in attandance and leave, no punch in and punch out and also no leave entry in database
        $noEnrty = 0;
        foreach ($noAtt as $key => $value) {
            if(!in_array($value,$leaveDates) && !in_array($value,$holidayDates)){
                $noEnrty++;
                // echo "<pre>";print_r($value);
            }
        }
        // $arr = [
        //     "att " =>$totalAtt,
        //     "holidays" => $holiday,
        //     "week" => $weekday_off,
        //     "Leaves"=>$totDayPlaus,
        //     "no Att"=>$noEnrty,
        //     "leave lwp"=> $totDayMinus
        // ];
        // echo "<pre>";print_r($arr);exit;
        $daysCount = $from_date->diffInDays($to_date);
       
        $totalDays = ($totalAtt + $holiday + $weekday_off + $totDayPlaus + $noEnrty); // 31
        $totalDays = ($totalDays - $totDayMinus - $noEnrty); // 16
        $totalDays = ($totalDays>0) ? $totalDays : 0; // totDays should not be in minus
      
        return $totalDays;
    }
}
