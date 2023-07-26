<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class feesMonthlyReportController extends Controller
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
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'fees/fees_report/fees_monthly_report', $res, "view");
    }


    public function getfeesMonthlyReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $fees_title_result = DB::table('fees_title')
            ->where('sub_institute_id', session()->get('sub_institute_id'))->where('syear',session()->get('syear'))->orderBy('sort_order')->get()->toArray();
        $fees_title_result = json_decode(json_encode($fees_title_result), true);

        $columns = "";
        $heading_arr = $report_data = array();
        // foreach ($fees_title_result as $key => $val) {
        //     $columns .= " SUM(`" . $val['fees_title'] . "`) as total_" . $val['fees_title'] . ",";
        //     $heading_arr[$val['fees_title']] = $val['display_name'];
        // }

            $discountAdded = false;
            $fineAdded = false;

            foreach ($fees_title_result as $key => $val) {
                $columns .= "IFNULL(SUM(`" . $val['fees_title'] . "`),0) as total_" . $val['fees_title'] . ",";
                $heading_arr[$val['fees_title']] = $val['display_name'];
                
                if (!$discountAdded) {
                    $heading_arr[$val['fees_title'] . '_discount'] = "Discount";
                    $discountAdded = true;
                }
                if (!$fineAdded) {
                    $heading_arr[$val['fees_title'] . '_fine'] = "Fine";
                    $fineAdded = true;
                }
            }

        $extra_query = "";
        if ($grade != "") {
            $extra_query = " AND s.grade_id = '" . $grade . "'";
        }
        if ($standard != "") {
            $extra_query = " AND s.standard_id = '" . $standard . "'";
        }
        if ($division != "") {
            $extra_query = " AND s.section_id = '" . $division . "'";
        }

        $final_data = array();
        $query = "SELECT " . $columns . "
		DATE_FORMAT(f.receiptdate,'%Y-%m-%d') AS fees_date
		FROM fees_collect f
		INNER JOIN tblstudent_enrollment s ON s.sub_institute_id = f.sub_institute_id AND f.student_id = s.student_id
		LEFT JOIN fees_paid_other fo ON fo.sub_institute_id = f.sub_institute_id and fo.student_id = f.student_id and fo.month_id = f.term_id
		WHERE f.is_deleted='N' AND f.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND DATE_FORMAT(f.receiptdate,'%Y-%m-%d') between '" . $from_date . "'
		AND '" . $to_date . "'"
            . $extra_query . "
		GROUP BY DATE_FORMAT(f.receiptdate,'%Y-%m-%d')";

        $data = DB::table('fees_collect as f')
            ->join('tblstudent_enrollment as s', function ($join) {
                $join->whereRaw('s.sub_institute_id = f.sub_institute_id AND f.student_id = s.student_id');
            })->leftJoin('fees_paid_other as fo', function ($join) {
                $join->whereRaw('fo.sub_institute_id = f.sub_institute_id AND f.student_id = fo.student_id AND fo.is_deleted = "N" ');
            })
            ->selectRaw("" . $columns . "IFNULL(SUM(f.fees_discount),0) as total_tution_fee_discount,IFNULL(SUM(f.fine),0) as total_tution_fee_fine, DATE_FORMAT(f.receiptdate,'%Y-%m-%d') AS fees_date")
            ->whereRaw('f.is_deleted = "N"')
            ->where('f.sub_institute_id', session()->get('sub_institute_id'))
            ->where('f.syear',session()->get('syear'))
            ->whereRaw("DATE_FORMAT(f.receiptdate,'%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "'");
        if ($grade != "") {
            $data = $data->where('s.grade_id', $grade);
        }
        if ($standard != "") {
            $data = $data->where('s.standard_id', $standard);
        }
        if ($division != "") {
            $data = $data->where('s.section_id', $division);
        }
        $data = $data->groupByRaw("DATE_FORMAT(f.receiptdate,'%Y-%m-%d')")->get()->toArray();
        // dd($data->toSql());
        $data = json_decode(json_encode($data), true);
        foreach ($data as $key => $val) {
            $final_data[$val['fees_date']] = $val;
        }

        $i = 0;
        $from_date_new = $from_date;
        while (strtotime($from_date_new) <= strtotime($to_date)) {
            $i++;

            if (array_key_exists($from_date_new, $final_data)) {
                $report_data[$from_date_new] = $final_data[$from_date_new];
            } else {
                $report_data[$from_date_new] = array();
            }
            $from_date_new = date("Y-m-d", strtotime("+1 day", strtotime($from_date_new)));
        }
        // echo "<pre>";print_r($report_data);exit;
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['heading_arr'] = $heading_arr;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['report_data'] = $report_data;

        return is_mobile($type, "fees/fees_report/fees_monthly_report", $res, "view");
    }
}
