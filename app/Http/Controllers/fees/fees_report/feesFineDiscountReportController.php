<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class feesFineDiscountReportController extends Controller
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

    public function feesFineDiscountReportIndex(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "fees/fees_report/show_fees_fine_discount_report", $res, "view");
    }

    public function feesFineDiscountReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $marking_period_id = session()->get('term_id');

        $result = DB::table('tblstudent as S')
            ->join('tblstudent_enrollment as SE', function ($join) use ($syear) {
                $join->whereRaw('S.id = SE.student_id AND SE.SYEAR=' . $syear);
            })->join('standard as CS', function ($join) use($marking_period_id){
                $join->whereRaw('SE.standard_id = CS.id')->when($marking_period_id,function ($query) use($marking_period_id){
                    $query->where('CS.marking_period_id',$marking_period_id);
                });
            })->join('division as SS', function ($join) {
                $join->whereRaw('SE.section_id = SS.id');
            })->join('fees_collect as FP', function ($join) {
                $join->whereRaw('SE.student_id = FP.student_id');
            })->selectRaw("'' AS SR_NO, CONCAT_WS(' ',S.first_name,S.middle_name,S.last_name) AS STUDENT_NAME,
                SUM(FP.fees_discount) AS FEES_MAFI,SUM(FP.fine) AS FINE, CS.name as std, S.enrollment_no, SS.name as div_name,
                CASE WHEN S.gender = 'M' THEN 'MALE' WHEN S.gender = 'F' THEN 'FEMALE' END AS GENDER,
                IFNULL(FP.remarks,'-') AS COMMENT, FP.receipt_no,
                DATE_FORMAT(FP.receiptdate,'%d-%m-%Y') AS RECEIVED_DATE")
            ->where('SE.SYEAR', $syear)
            ->where('FP.SYEAR', $syear)
            ->where('FP.IS_DELETED', '=', 'N')
            ->where('SE.sub_institute_id', $sub_institute_id)
            ->whereRaw("FP.receiptdate BETWEEN '" . $from_date . "' AND '" . $to_date . "'
                AND (FP.fees_discount > 0 OR FP.fine > 0)");

        if ($standard != '') {
            $result = $result->where('SE.standard_id', $standard);
        }

        if ($division != '') {
            $result = $result->where('SE.section_id', $division);
        }

        if ($grade != '') {
            $result = $result->where('SE.grade_id', $grade);
        }

        $result = $result->groupByRaw("SE.STUDENT_ID,FP.receiptdate,FP.receipt_no")
            ->orderBy('FP.receiptdate')->get()->toArray();

        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['report_data'] = $result;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "fees/fees_report/show_fees_fine_discount_report", $res, "view");
    }
}
