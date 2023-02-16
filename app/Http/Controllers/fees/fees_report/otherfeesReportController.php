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
use App\Models\fees\fees_title\fees_title;
use App\Models\student\tblstudentModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Support\Facades\DB;
use function App\Helpers\OtherBreackOffHead;

class otherfeesReportController extends Controller
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
        $sub_institute_id = $request->session()->get('sub_institute_id');

        // $feesOtherHead_data = fees_title::select("*")
        // ->where(["sub_institute_id"=>$sub_institute_id])
        // ->where("other_fee_id",'<>','0')
        // ->get()
        // ->toArray();

        // $res['feesOtherHead_data'] = $feesOtherHead_data;

        return is_mobile($type, "fees/fees_report/show_other_fees_report", $res , "view");
    }
    
    public function create(Request $request)
    {        
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $other_fee_title = OtherBreackOffHead();

        $other_head_title_column = "";
        foreach($other_fee_title as $key => $val)
        {
            $other_head_title_column .= "sum(fp.".$val->fees_title.") as sum_".$val->fees_title.",";
        }            

        $extraSearchArray = array();
        $extraSearchArrayRaw = " 1=1 ";

        if($grade != '')
        {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }
    
        if($standard != '')
        {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }

        if($division != '')
        {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }
        
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;

        $other_feesData = tblstudentModel::selectRaw("tblstudent.id,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS 
            student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,
            tblstudent.mobile,$other_head_title_column tblstudent.uniqueid,fp.reciept_id,fp.receiptdate")
        ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
        ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
        ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
        ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
        ->leftjoin('fees_paid_other as fp', 'fp.student_id', '=', 'tblstudent_enrollment.student_id')
        ->where($extraSearchArray)
        ->whereRaw($extraSearchArrayRaw)
        ->groupby('tblstudent_enrollment.student_id')
        ->get()
        ->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['other_feesData'] = $other_feesData;
        $res['other_fee_title'] = $other_fee_title;        
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        
        return is_mobile($type, "fees/fees_report/show_other_fees_report", $res , "view");
    }
}
