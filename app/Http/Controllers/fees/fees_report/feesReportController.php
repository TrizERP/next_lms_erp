<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;

class feesReportController extends Controller
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

        return is_mobile($type, "fees/fees_report/index", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
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
        //
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

    public function showFees(Request $request)
    {
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $receipt_no = $request->input('receipt_no');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $extraSearchArray = array();
        $extraSearchArrayRaw = "  fees_collect.is_deleted = 'N' ";

        if ($grade != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }

        if ($standard != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }

        if ($division != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        if ($enrollment_no != '') {
            $extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
        }

        if ($receipt_no != '') {
            $extraSearchArray['fees_collect.receipt_no'] = $receipt_no;
        }

        if ($from_date != '') {
            $extraSearchArrayRaw .= "  AND fees_collect.receiptdate >= '" . $from_date . "'";
        }

        if ($to_date != '') {
            $extraSearchArrayRaw .= "  AND fees_collect.receiptdate <= '" . $to_date . "'";
        }

        $extraSearchArray['fees_collect.syear'] = $syear;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['fees_collect.sub_institute_id'] = $sub_institute_id;

        $feesData = tblstudentModel::selectRaw("fees_collect.*,SUM(fees_collect.amount) AS amount, CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,tblstudent.uniqueid,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,fees_collect.created_date,CONCAT_WS(' ',tbluser.first_name,tbluser.last_name) as user_name,fees_collect.cheque_no,fees_collect.cheque_date,fees_collect.bank_name,fees_collect.bank_branch,fees_paid_other.actual_amountpaid")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('fees_collect', 'fees_collect.student_id', '=', 'tblstudent.id')
            ->leftjoin('fees_paid_other', function ($join) {
                $join->on('fees_paid_other.student_id', '=', 'tblstudent.id')
                    ->on('fees_paid_other.month_id', '=', 'fees_collect.term_id')
                    ->on('fees_paid_other.receiptdate', '=', 'fees_collect.receiptdate');

            })
            ->leftjoin('tbluser', 'fees_collect.created_by', '=', 'tbluser.id')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->groupBy('fees_collect.student_id', 'fees_collect.receipt_no', 'fees_collect.syear', 'fees_collect.receiptdate', 'fees_collect.payment_mode', 'fees_collect.cheque_no')
            ->orderBy('fees_collect.receiptdate', 'asc')
            ->orderBy('fees_collect.receipt_no', 'asc')
            ->get()
            ->toArray();
        $months = FeeMonthId();


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $feesData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['receipt_no'] = $receipt_no;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['months'] = $months;

        return is_mobile($type, "fees/fees_report/index", $res, "view");
    }
}
