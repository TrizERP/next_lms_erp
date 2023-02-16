<?php

namespace App\Http\Controllers\fees\fees_circular;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\FeeMonthId;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeBreackoff;
use App\Models\fees\feesReceiptBookMasterModel;
use App\Models\fees\tblfeesConfigModel;
use App\Models\fees\fees_circular\feesCircularModel;
use App\Models\fees\fees_circular\feesCircularMasterModel;
use DB;

class feesCircularController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $months = FeeMonthId();

        $sql = "SELECT *,GROUP_CONCAT(fees_head_id) heads
            FROM fees_receipt_book_master
            WHERE syear = '".$syear."'
            AND sub_institute_id = '" .$sub_institute_id. "'
            GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number";
        $sql = preg_replace('/\n+/', '', $sql);
        $result = DB::select($sql);
        $result = json_decode(json_encode($result),true);     

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['months'] = $months;
        $res['receipt_books'] = $result;
        return is_mobile($type, "fees/fees_circular/show", $res , "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function showStudent(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $month = $request->input('month');
        $receipt_id = $request->input('receipt_id');
        
        $months = FeeMonthId();
        if($sub_institute_id != 201 && $sub_institute_id != 202 && $sub_institute_id != 203 && $sub_institute_id != 204)
        {
            $studentData = SearchStudent($grade,$standard,$division);
        }else{

            $extra = "";
            if($grade != '')
            {
                $extra .= " AND se.grade_id = '".$grade."' ";
            }
            if($standard != '')
            {
                $extra .= " AND se.standard_id = '".$standard."' ";
            }

            $data = DB::select("SELECT s.id,s.enrollment_no,s.first_name,s.last_name,st.name standard_name, 
                                d.name AS division_name,fb.amount,ft.display_name,ft.fees_title,SUM(fb.amount) AS total_breakoff
                                FROM tblstudent s
                                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                                INNER JOIN academic_section g ON g.id = se.grade_id
                                INNER JOIN standard st ON st.id = se.standard_id
                                LEFT JOIN division d ON d.id = se.section_id
                                INNER JOIN fees_breackoff fb ON (fb.syear = '".$syear."' AND fb.admission_year = s.admission_year 
                                AND fb.quota = se.student_quota AND fb.grade_id = se.grade_id AND fb.standard_id = se.standard_id 
                                AND fb.sub_institute_id = '".$sub_institute_id."')
                                INNER JOIN fees_title ft ON (fb.fee_type_id = ft.id)
                                WHERE s.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' $extra
                                GROUP BY s.id");
            $studentData = json_decode(json_encode($data),true);
        }
        
        if(!isset($studentData[0]['enrollment_no']))
        {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";
            return is_mobile($type, "fees_circular.index", $res);
        }

        $sql = "SELECT *,GROUP_CONCAT(fees_head_id) heads
            FROM fees_receipt_book_master
            WHERE syear = '".$syear."'
            AND sub_institute_id = '" .$sub_institute_id. "'
            GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number";
        $sql = preg_replace('/\n+/', '', $sql);
        $result = DB::select($sql);
        $result = json_decode(json_encode($result),true);     

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $studentData;
        $res['months'] = $months;
        $res['month'] = $month;
        $res['receipt_books'] = $result;
        $res['receipt_id'] = $receipt_id;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        return is_mobile($type, "fees/fees_circular/show", $res , "view");
    }

    public function showCircular(Request $request)
    {
        // dd($request);
        $type = $request->input('type');
        $student_ids = $request->input('students');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $month = $request->input('month');
        $receipt_id = $request->input('receipt_id');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');
        $fees_circular_amount = $request->input('fees_circular_amount');
        $fees_circular_remarks = $request->input('fees_circular_remarks');
        
        $monthArray = explode(",",$month);
        
        $whereArray = array();
        $extra = '';
        $whereArray['syear'] = $syear;
        $whereArray['sub_institute_id'] = $sub_institute_id;

        $feesConfig = tblfeesConfigModel::where($whereArray)->get()->toArray();

        if($grade_id != '')
        {
            $whereArray['grade_id'] = $grade_id;
            $extra .= " AND f.grade_id = '".$grade_id."' ";
        }
    
        if($standard_id != '')
        {
            $whereArray['standard_id'] = $standard_id;
            $extra .= " AND f.standard_id = '".$standard_id."' ";

        }

        // if($receipt_id != '')
        // {
        //     $whereArray['receipt_id'] = $receipt_id;
        // }

        if($sub_institute_id == 201 || $sub_institute_id == 202 || $sub_institute_id == 203 || $sub_institute_id == 204)
        {
            $feesCircularMaster = feesCircularMasterModel::where($whereArray)->get()->toArray();
            if(!isset($feesCircularMaster[0]['id']))
            {
                $res['status_code'] = 0;
                $res['message'] = "Please enter fees circular master to view fees circular";
                return is_mobile($type, "fees_circular.index", $res);
            }
        }    

        // $receiptBook = feesReceiptBookMasterModel::where($whereArray)->get()->toArray();
        $receiptBook = DB::select("SELECT f.*,GROUP_CONCAT(DISTINCT f.fees_head_id) AS fees_head_id,
GROUP_CONCAT(DISTINCT ft.fees_title) AS fees_title_name,
GROUP_CONCAT(DISTINCT ft.display_name) AS display_name
FROM fees_receipt_book_master f
INNER JOIN fees_title ft ON ft.id = f.fees_head_id AND ft.sub_institute_id = f.sub_institute_id AND ft.syear = f.syear
WHERE f.sub_institute_id = '".$sub_institute_id."' AND f.syear = '".$syear."' AND f.receipt_id = '".$receipt_id."' $extra ");

        $receiptBook = json_decode(json_encode($receiptBook),true);

        $get_fees_title_arr = explode(',', $receiptBook[0]['fees_title_name']);
        
        if(!isset($receiptBook[0]['receipt_id']))
        {
            $res['status_code'] = 0;
            $res['message'] = "Please enter fees receipt book master to view fees circular";
            return is_mobile($type, "fees_circular.index", $res);
        }

        if(!isset($feesConfig[0]['id']))
        {
            $res['status_code'] = 0;
            $res['message'] = "Please enter fees config master to view fees circular";
            return is_mobile($type, "fees_circular.index", $res);
        }        

        $displayBreakoff = $data = array();
        $all_inserted_id ='';

        if(isset($student_ids))
        {
            $data = FeeBreakoffHeadWise($student_ids);
            foreach($student_ids as $student_key => $student_id)
            {
                if(isset($data[$student_id]['breakoff']))
                {
                    foreach($data[$student_id]['breakoff'] as $key => $value)
                    {
                        foreach ($value as $fees_title => $fees_title_value) 
                        {
                            if(!in_array($fees_title, $get_fees_title_arr))
                            {
                                unset($data[$student_id]['breakoff'][$key][$fees_title]);
                            }
                        }
                    }
                }           
            }
            foreach($student_ids as $student_key => $student_id)
            {
                $amountLogs = 0;
                $logs = array();
                $logs['MONTH'] = $month;
                $logs['STUDENT_ID'] = $student_id;
                $logs['CREATED_BY'] = $request->session()->get('user_id');
                $logs['SYEAR'] = $syear;
                $logs['SUB_INSTITUTE_ID'] = $sub_institute_id;
                $logs['RECEIPT_BOOK_ID'] = $receiptBook[0]['receipt_id'];

                $display_months = array();
                if(isset($data[$student_id]['breakoff']))
                {
                    foreach($data[$student_id]['breakoff'] as $key => $value)
                    {      
                        $display_months[] = $key;         
                        if(in_array($key,$monthArray))
                        {
                            foreach($value as $head => $valueArray)
                            {
                                $amountLogs += $valueArray['amount'];

                                if(isset($displayBreakoff[$student_id][$valueArray['title']]))
                                {
                                    $displayBreakoff[$student_id][$valueArray['title']] = $valueArray['amount'] + $displayBreakoff[$student_id][$valueArray['title']];
                                }else{
                                    $displayBreakoff[$student_id][$valueArray['title']] = $valueArray['amount'];
                                }
                            }
                        }
                    }
                }

                $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
                $dis_month = '';
                foreach ($display_months as $k => $m)
                {
                    $y = $m / 10000;
                    $month = (int) $y;     
                    $year = substr($m,-4);
                    // $dis_month .= $months[$month]."/".$year.",";
                    $dis_month .= $months[$month].",";
                }    
                $display_month_name = rtrim($dis_month,',');

                $logs['AMOUNT'] = $amountLogs;
                feesCircularModel::insert($logs);
                $last_inserted_ids = DB::getPdo()->lastInsertId();

                $all_inserted_id .= $last_inserted_ids.',';
            }
            $inserted_ids = rtrim($all_inserted_id,',');
            // echo '<pre>';
            // print_r($fees_circular_amount);
            // dd($displayBreakoff);

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
            $res['breakoff'] = $displayBreakoff;
            $res['last_inserted_ids'] = $inserted_ids;
            if(count($feesConfig) > 0)
            {
                $res['feesconfig'] = $feesConfig[0];
            }
            if(count($receiptBook) > 0)
            {
                $res['receiptbook'] = $receiptBook[0];
            }

            if($sub_institute_id == 201 || $sub_institute_id == 202 || $sub_institute_id == 203 || $sub_institute_id == 204)
            {
                if(count($feesCircularMaster) > 0)
                {
                    $res['feesCircularMaster'] = $feesCircularMaster[0];
                    $res['display_month_name'] = 'Second Term';//$display_month_name;
                    $res['fees_circular_amount'] = $fees_circular_amount;
                    $res['fees_circular_remarks'] = $fees_circular_remarks;
                }
                return is_mobile($type, "fees/fees_circular/show_circular_hills", $res , "view");            
            }else{
                return is_mobile($type, "fees/fees_circular/show_circular", $res , "view");
            }
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Please select one student for display fees circular.";
            return is_mobile($type, "fees_circular.index", $res);
        }

    }
}
