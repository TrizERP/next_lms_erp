<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;

class feesPayoutController extends Controller
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

        return is_mobile($type, "fees/fees_report/fees_payout_index", $res, "view");
    }

    public function showFeesPayout(Request $request)
    {
        $type = $request->input("type");
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $client_id = $request->session()->get('client_id');
        $marking_period_id = session()->get('term_id');

        $extra_fp = "  AND fp.syear = '" . $syear . "' AND  fp.sub_institute_id = '" . $sub_institute_id . "' AND fp.is_deleted = 'N' ";
        $extra_fo = "  AND fo.syear = '" . $syear . "' AND fo.sub_institute_id = '" . $sub_institute_id . "' AND fo.is_deleted = 'N' ";
      
        if ($from_date != '' && $to_date != '') {
            $extra_fp .= " AND DATE_FORMAT(fp.receiptdate,'%Y-%m-%d') between '" . $from_date . "' AND '" . $to_date . "' ";
            $extra_fo .= " AND DATE_FORMAT(fo.receiptdate,'%Y-%m-%d') between '" . $from_date . "' AND '" . $to_date . "'";
        }

        $data = DB::table(function ($query) use ($sub_institute_id, $syear, $extra_fo, $extra_fp) {
            $query->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
                . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', s.name as standard_name, d.name as division_name, fp.created_date, '
                . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fp.term_id, fp.receiptdate, fp.receipt_no, fp.payment_mode, '
                . 'fp.cheque_bank_name, fp.bank_branch, fp.cheque_no, fp.cheque_date, b.title as batch,  '
                . 'IFNULL(fp.amount, 0) AS actual_amountpaid, t.gender, hm.id as hm_house_id'))
                ->from('tblstudent as t')
                ->join('tblstudent_enrollment as te', function ($join) use($syear){
                    $join->on('te.student_id', '=', 't.id')->where('te.syear',$syear);
                })
                ->join('standard as s', 's.id', '=', 'te.standard_id')
                ->join('division as d', 'd.id', '=', 'te.section_id')
                ->leftJoin('house_master as hm', 'hm.id', '=', 'te.house_id')
                ->leftJoin('batch as b','b.id', '=', 't.studentbatch')
                ->Join('fees_collect as fp', 'fp.student_id', '=', 'te.student_id')
                ->leftJoin('tbluser as u', 'fp.created_by', '=', 'u.id')
                ->whereRaw("t.studentbatch is not null and 1=1 " . $extra_fp)
        
                ->unionAll(function ($query) use ($sub_institute_id, $syear, $extra_fo, $extra_fp) {
                    $query->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
                        . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', s.name as standard_name, d.name as division_name, NULL AS created_date, '
                        . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fo.month_id AS term_id, fo.receiptdate AS receiptdate, fo.reciept_id AS receipt_no, fo.payment_mode AS payment_mode, '
                        . 'fo.bank_name as cheque_bank_name, fo.bank_branch, fo.cheque_dd_no as cheque_no, fo.cheque_dd_date AS cheque_date, b.title as batch, '
                        . 'IFNULL(fo.actual_amountpaid, 0) AS actual_amountpaid, t.gender, hm.id as hm_house_id'))->from('tblstudent as t')
                        ->join('tblstudent_enrollment as te', function ($join) use($syear){
                            $join->on('te.student_id', '=', 't.id')->where('te.syear',$syear);
                        })
                        ->join('standard as s', 's.id', '=', 'te.standard_id')
                        ->join('division as d', 'd.id', '=', 'te.section_id')
                        ->leftJoin('house_master as hm', 'hm.id', '=', 'te.house_id')
                        ->leftJoin('batch as b','b.id', '=', 't.studentbatch')
                        ->leftJoin('fees_paid_other as fo', 'fo.student_id', '=', 'te.student_id')
                        ->leftJoin('tbluser as u', 'fo.created_by', '=', 'u.id')
                        ->whereRaw("t.studentbatch is not null and 1=1 " . $extra_fo);
                });
        })
        ->selectRaw('standard_name, division_name, batch, 
                     SUM(IFNULL(actual_amountpaid, 0)) AS actual_amountpaid, 
                     SUM(CASE WHEN gender = "M" and hm_house_id != 25 THEN 1 WHEN gender = "Male" and hm_house_id != 25 THEN 1 ELSE 0 END) AS male_count,
                     SUM(CASE WHEN gender = "F" and hm_house_id != 25 THEN 1 WHEN gender = "Female" and hm_house_id != 25 THEN 1 ELSE 0 END) AS female_count, 
                     SUM(CASE WHEN hm_house_id != 25 THEN 1 ELSE 0 END) AS cn_school, 
                     SUM(CASE WHEN hm_house_id = 25 THEN 1 ELSE 0 END) AS os_school')
        ->groupBy(['standard_name', 'division_name', 'batch']);
        
        $data = $data->get();                        

        // Convert the result objects to arrays
        $data = $data->map(function ($item) {
            return (array) $item;
        })->toArray();

        // Initialize an empty array to store the grouped data
        $groupedData = [];

        foreach ($data as $item) {
            $standardName = $item['standard_name'];

            // Check if the standard name exists in the grouped data array
            if (!isset($groupedData[$standardName])) {
                $groupedData[$standardName] = [];
            }

            // Append the current item to the corresponding standard in the grouped data array
            $groupedData[$standardName][] = $item;
        }

        // The $groupedData variable now contains the desired output structure with standard_name keys.
        $feesData = $groupedData;
        /* echo("<pre>");
        print_r($feesData);
        echo("</pre>");
        die; */
        //$feesData = json_decode(json_encode($data), true);
       
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $feesData;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['months'] = FeeMonthId();

        return is_mobile($type, "fees/fees_report/fees_payout_index", $res, "view");
    }
}
