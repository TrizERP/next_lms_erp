<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class feesInstituteWiseFeesReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return void
     */
    public function index(Request $request)
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return void
     */
    public function create(Request $request)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return void
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

    public function instituteWiseFeesPaidReportIndex(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "fees/fees_report/show_institute_wise_fees_paid_report", $res, "view");
    }

    public function instituteWiseFeesPaidReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $result = DB::table('tblclient as c')
            ->join('school_setup as ss', function ($join) {
                $join->whereRaw('ss.client_id = c.id');
            })->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.sub_institute_id = ss.Id');
            })->join('tblstudent_enrollment as te', function ($join) {
                $join->whereRaw('te.student_id = ts.id');
            })->selectRaw("ss.Id as sub_institute_id,ss.SchoolName,ss.ShortCode,ss.Mobile,ss.Email,
                COUNT(DISTINCT ts.id) AS TOTAL_STUDENT")
            ->where('ss.Id', $sub_institute_id)
            ->where('te.syear', $syear)->get()->toArray();

        $result = json_decode(json_encode($result), true);

        $fees_result = DB::table('fees_collect as fc')
            ->selectRaw('COUNT(DISTINCT fc.student_id) AS TOOTAL_PAID,SUM(fc.amount) as Total_Fees_Collected')
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)
            ->whereRaw("date_format(fc.created_date,'%Y-%m-%d') between '" . $from_date . "' AND '" . $to_date . "'")
            ->get()->toArray();

        $fees_result = json_decode(json_encode($fees_result), true);

        $all_result[0] = array_merge($result[0], $fees_result[0]);


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['report_data'] = $all_result;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "fees/fees_report/show_institute_wise_fees_paid_report", $res, "view");
    }
}
