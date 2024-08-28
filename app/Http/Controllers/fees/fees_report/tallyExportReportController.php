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

class tallyExportReportController extends Controller
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

        return is_mobile($type, "fees/fees_report/show_tally_export_fees_report", $res, "view");
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
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

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
            $extraSearchArrayRaw .= "  AND ts.enrollment_no = '" . $enrollment_no . "' ";
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

        if ($from_date != '') {
            $extraSearchArrayRaw .= "  AND fc.receiptdate >= '" . $from_date . "'";
        }

        if ($to_date != '') {
            $extraSearchArrayRaw .= "  AND fc.receiptdate <= '" . $to_date . "'";
        }

        $fees_heads = DB::table('fees_title as FT')
            ->where('FT.sub_institute_id', $sub_institute_id)
            ->where('FT.other_fee_id', '=', '0')
            ->where('FT.syear', $syear)->orderBy('FT.sort_order')->get()->toArray();

        $fees_heads = array_map(function ($value) {
            return (array)$value;
        }, $fees_heads);

        $tuition_fees_head_sum = $admission_fees_head_sum = $academic_fees_head_sum = $tuition_heads = $admission_heads = $academic_heads = "";
        foreach ($fees_heads as $key => $value) {
            if (strstr($value['fees_title'], "title")) {
                $tuition_fees_head_sum .= " SUM(fc." . $value['fees_title'] . ") + ";
                $tuition_heads .= "CASE WHEN " . $value['fees_title'] . " != 0 THEN '" . $value['append_name'] . " ,' ELSE '' END,";
            } else if (strstr($value['fees_title'], "admission")) {
                $admission_fees_head_sum .= " SUM(fc." . $value['fees_title'] . ") + ";
                $admission_heads .= "CASE WHEN " . $value['fees_title'] . " != 0 THEN '" . $value['append_name'] . " ,' ELSE '' END,";
            } else {
                $academic_fees_head_sum .= " SUM(fc." . $value['fees_title'] . ") + ";
                $academic_heads .= "CASE WHEN " . $value['fees_title'] . " != 0 THEN '" . $value['append_name'] . " ,' ELSE '' END,";
            }
        }
/* ##############################################  */
        $tuition_fees_head_sum = rtrim($tuition_fees_head_sum, "+ ");
        $tuition_heads = rtrim($tuition_heads, ", ");

        $admission_fees_head_sum = rtrim($admission_fees_head_sum, "+ ");
        $admission_heads = rtrim($admission_heads, ", ");

        $academic_fees_head_sum = rtrim($academic_fees_head_sum, "+ ");
        $academic_heads = rtrim($academic_heads, ", ");
/* ##############################################  */
        if (isset($tuition_fees_head_sum) && $tuition_fees_head_sum != '') {
            $extra_tuition = "($tuition_fees_head_sum)";
        } else {
            $extra_tuition = "' '";
        }

        if (isset($admission_fees_head_sum) && $admission_fees_head_sum != '') {
            $extra_admission = "($admission_fees_head_sum)";
        } else {
            $extra_admission = "' '";
        }

        if (isset($academic_fees_head_sum) && $academic_fees_head_sum != '') {
            $extra_academic = "($academic_fees_head_sum)";
        } else {
            $extra_academic = "' '";
        }
/* ##############################################  */
        if (isset($tuition_heads) && $tuition_heads != '') {
            $tuition_concate = "CONCAT_WS(' '," . $tuition_heads . ")";
        } else {
            $tuition_concate = "' '";
        }

        if (isset($admission_heads) && $admission_heads != '') {
            $admission_concate = "CONCAT_WS(' '," . $admission_heads . ")";
        } else {
            $admission_concate = "' '";
        }

        if (isset($academic_heads) && $academic_heads != '') {
            $academic_concate = "CONCAT_WS(' '," . $academic_heads . ")";
        } else {
            $academic_concate = "' '";
        }
/* ##############################################  */
        $fees_data = DB::table('fees_collect as fc')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = fc.student_id AND ts.sub_institute_id = fc.sub_institute_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = ts.id');
            })->join('student_quota as sq', function ($join) {
                $join->whereRaw('sq.id = se.student_quota');
            })->join('academic_section as a', function ($join) {
                $join->whereRaw('a.id = se.grade_id');
            })->join('standard as s', function ($join) use($marking_period_id) {
                $join->whereRaw('s.id = se.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id');
            })->selectRaw("fc.id,fc.student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                ts.enrollment_no,ts.admission_year,ts.mobile,ts.email,date_format(ts.dob,'%d-%m-%Y') AS dob,a.title AS section,
                s.name AS std_name,d.name AS div_name,sq.title AS stu_qouta,SUM(fc.fees_discount) AS tot_fees_discount,
                GROUP_CONCAT(fc.receipt_no) AS vchno,fc.receiptdate,fc.cheque_no,fc.cheque_date,fc.cheque_bank_name,fc.bank_branch,
                fc.remarks,
                $extra_tuition as tuition_fees,
                $extra_admission as admission_fees,
                $extra_academic as academic_fees,
                $tuition_concate AS tuition_fees_narration,
                $admission_concate AS admission_fees_narration,
                'Tuition_Fee, ' AS tf,
                $academic_concate AS academic_fees_narration,
                SUM(fc.fine) AS total_fine")
            ->whereRaw($extraSearchArrayRaw)
            ->where('se.syear', $syear)
            ->where('fc.syear', $syear)
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('fc.is_deleted', '=', 'N')
            ->whereNull('se.end_date')
            ->groupByRaw('fc.student_id,fc.receipt_no')
            ->orderByRaw('fc.receipt_no')->get()->toArray();

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
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "fees/fees_report/show_tally_export_fees_report", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return Response
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
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

}
