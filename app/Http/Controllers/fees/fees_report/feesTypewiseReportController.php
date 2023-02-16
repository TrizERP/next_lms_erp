<?php

namespace App\Http\Controllers\fees\fees_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\FeeMonthId;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeBreackoff;
use App\Models\fees\feesReceiptBookMasterModel;
use App\Models\fees\tblfeesConfigModel;
use App\Models\student\tblstudentModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Support\Facades\DB;

class feesTypewiseReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";

        return is_mobile($type, "fees/fees_report/show_fees_type_wise_report", $res , "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $mobile_no = $request->input('mobile_no');
        $uniqueid = $request->input('uniqueid');
        $admission_year = $request->input('admission_year');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $extraSearchArrayRaw = " 1=1 ";

        if($grade != '')
        {
            $extraSearchArrayRaw .= "  AND se.grade_id = ".$grade;
        }
    
        if($standard != '')
        {
            $extraSearchArrayRaw .= "  AND se.standard_id = ".$standard;
        }

        if($division != '')
        {
            $extraSearchArrayRaw .= "  AND se.section_id = ".$division;
        }

        if($enrollment_no != '')
        {
            $extraSearchArrayRaw .= "  AND ts.enrollment_no = ".$enrollment_no;
        }

        if($mobile_no != '')
        {
            $extraSearchArrayRaw .= "  AND ts.mobile = ".$mobile_no;            
        }

        if($uniqueid != '')
        {
            $extraSearchArrayRaw .= "  AND ts.uniqueid = ".$uniqueid;            
        }

        if($first_name != '')
        {
            $extraSearchArrayRaw .= "  AND ts.first_name like '%".$first_name."%' ";
        }

        if($last_name != '')
        {
            $extraSearchArrayRaw .= "  AND ts.last_name like '%".$last_name."%' ";
        }

        if($admission_year != '' && $admission_year != '--Select Admission Year--')
        {
            $extraSearchArrayRaw .= "  AND ts.admission_year  = '".$admission_year."'";
        }

        if($from_date != '')
        {
            $extraSearchArrayRaw .= "  AND fc.receiptdate >= '".$from_date."'";
        }

        if($to_date != '')
        {
            $extraSearchArrayRaw .= "  AND fc.receiptdate <= '".$to_date."'";
        }

        $fees_heads = DB::select("SELECT * FROM fees_title FT WHERE FT.sub_institute_id = '".$sub_institute_id."' AND FT.other_fee_id = 0 AND FT.syear = '".$syear."' ");                            

        $fees_heads = array_map(function ($value) {
            return (array) $value;
        }, $fees_heads);
        
        $fees_head_sum = "";
        foreach ($fees_heads as $key => $value) {
            $fees_head_sum .= " SUM(fc.".$value['fees_title'].") AS ".$value['fees_title'].",";
        }
        $sql = "SELECT fc.id,fc.student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,ts.enrollment_no,
                ts.admission_year,ts.mobile,ts.email,
                date_format(ts.dob,'%d-%m-%Y') AS dob,a.title AS section,s.name AS std_name,d.name AS div_name,
                sq.title AS stu_qouta, $fees_head_sum
                SUM(fc.fine) AS total_fine,SUM(fc.fees_discount) AS tot_disc,fc.receipt_no
                FROM fees_collect fc
                INNER JOIN tblstudent ts ON ts.id = fc.student_id AND ts.sub_institute_id = fc.sub_institute_id
                INNER JOIN tblstudent_enrollment se ON se.student_id = ts.id
                INNER JOIN student_quota sq ON sq.id = se.student_quota
                INNER JOIN academic_section a ON a.id = se.grade_id
                INNER JOIN standard s ON s.id = se.standard_id
                INNER JOIN division d ON d.id = se.section_id
                WHERE $extraSearchArrayRaw AND se.syear = '".$syear."' AND fc.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."' AND se.end_date IS NULL 
                AND fc.is_deleted = 'N' 
                GROUP BY ts.id";

        $fees_data = DB::select($sql);
        $fees_data = array_map(function ($value) {
            return (array) $value;
        }, $fees_data);

        // dd($fees_data);
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $fees_data;
        $res['fees_heads'] = $fees_heads;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile_no'] = $mobile_no;
        $res['uniqueid'] = $uniqueid;
        $res['admission_year'] = $admission_year;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        return is_mobile($type, "fees/fees_report/show_fees_type_wise_report", $res , "view");
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
}
