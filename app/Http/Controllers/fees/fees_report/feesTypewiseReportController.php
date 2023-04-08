<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class feesTypewiseReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";

        return is_mobile($type, "fees/fees_report/show_fees_type_wise_report", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
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

        if ($grade != '') {
            $extraSearchArrayRaw .= "  AND se.grade_id = " . $grade;
        }

        if ($standard != '') {
            $extraSearchArrayRaw .= "  AND se.standard_id = " . $standard;
        }

        if ($division != '') {
            $extraSearchArrayRaw .= "  AND se.section_id = " . $division;
        }

        if ($enrollment_no != '') {
            $extraSearchArrayRaw .= "  AND ts.enrollment_no = " . $enrollment_no;
        }

        if ($mobile_no != '') {
            $extraSearchArrayRaw .= "  AND ts.mobile = " . $mobile_no;
        }

        if ($uniqueid != '') {
            $extraSearchArrayRaw .= "  AND ts.uniqueid = " . $uniqueid;
        }

        if ($first_name != '') {
            $extraSearchArrayRaw .= "  AND ts.first_name like '%" . $first_name . "%' ";
        }

        if ($last_name != '') {
            $extraSearchArrayRaw .= "  AND ts.last_name like '%" . $last_name . "%' ";
        }

        if ($admission_year != '' && $admission_year != '--Select Admission Year--') {
            $extraSearchArrayRaw .= "  AND ts.admission_year  = '" . $admission_year . "'";
        }

        if ($from_date != '') {
            $extraSearchArrayRaw .= "  AND fc.receiptdate >= '" . $from_date . "'";
        }

        if ($to_date != '') {
            $extraSearchArrayRaw .= "  AND fc.receiptdate <= '" . $to_date . "'";
        }

        $fees_heads = DB::table('fees_title as FT')
            ->where('FT.sub_institute_id', $sub_institute_id)
            ->where('FT.other_fee_id', '=', 0)
            ->where('FT.syear', $syear)->get()->toArray();

        $fees_heads = array_map(function ($value) {
            return (array)$value;
        }, $fees_heads);

        $fees_head_sum = "";
        foreach ($fees_heads as $key => $value) {
            $fees_head_sum .= " SUM(fc." . $value['fees_title'] . ") AS " . $value['fees_title'] . ",";
        }

        $fees_data = DB::table('fees_collect as fc')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = fc.student_id AND ts.sub_institute_id = fc.sub_institute_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = ts.id');
            })->join('student_quota as sq', function ($join) {
                $join->whereRaw('sq.id = se.student_quota');
            })->join('academic_section as a', function ($join) {
                $join->whereRaw('a.id = se.grade_id');
            })->join('standard as s', function ($join) {
                $join->whereRaw('s.id = se.standard_id');
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id');
            })
            ->selectRaw("fc.id,fc.student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                ts.enrollment_no,ts.admission_year,ts.mobile,ts.email,date_format(ts.dob,'%d-%m-%Y') AS dob,a.title AS section,
                s.name AS std_name,d.name AS div_name,sq.title AS stu_qouta, $fees_head_sum
                SUM(fc.fine) AS total_fine,SUM(fc.fees_discount) AS tot_disc,fc.receipt_no")
            ->whereRaw($extraSearchArrayRaw)
            ->where('se.syear', $syear)
            ->where('fc.syear', $syear)
            ->where('s.sub_institute_id', $sub_institute_id)
            ->whereNull('se.end_date')
            ->where('fc.is_deleted', '=', 'N')
            ->groupBy('ts.id')->get()->toArray();

        $fees_data = array_map(function ($value) {
            return (array)$value;
        }, $fees_data);

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

        return is_mobile($type, "fees/fees_report/show_fees_type_wise_report", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
