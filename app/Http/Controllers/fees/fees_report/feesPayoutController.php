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

        $results = DB::table(function ($query) use ($from_date, $to_date, $sub_institute_id, $syear){
            $query->select(
                'se.student_id',
                's.first_name',
                DB::raw("LEFT(s.gender, 1) AS gender"),
                'sd.name AS standard_name',
                'd.name AS coach_name',
                'b.title AS batch_name',
                'hm.house_name',
                DB::raw("SUM(fc.amount) AS total_reg_paid"),
                DB::raw("0 AS total_other_paid")
            )
            ->from('tblstudent_enrollment AS se')
            ->join('tblstudent AS s', 's.id', '=', 'se.student_id')
            ->join('standard AS sd', 'sd.id', '=', 'se.standard_id')
            ->join('division AS d', 'd.id', '=', 'se.section_id')
            ->leftJoin('batch AS b', 'b.id', '=', 's.studentbatch')
            ->leftJoin('house_master AS hm', 'hm.id', '=', 'se.house_id')
            ->join('fees_collect AS fc', function ($join) use ($from_date, $to_date){
                $join->on('fc.student_id', '=', 'se.student_id')
                    ->whereBetween('fc.receiptdate', [$from_date, $to_date])
                    ->where('fc.is_deleted', 'N');
            })
            ->where('se.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->groupBy('se.student_id');
        
            $query->unionAll(
                DB::table(function ($query) use ($from_date, $to_date, $sub_institute_id, $syear){
                    $query->select(
                        'se.student_id',
                        's.first_name',
                        DB::raw("LEFT(s.gender, 1) AS gender"),
                        'sd.name AS standard_name',
                        'd.name AS coach_name',
                        'b.title AS batch_name',
                        'hm.house_name',
                        DB::raw("0 AS total_reg_paid"),
                        DB::raw("SUM(fo.actual_amountpaid) AS total_other_paid")
                    )
                    ->from('tblstudent_enrollment AS se')
                    ->join('tblstudent AS s', 's.id', '=', 'se.student_id')
                    ->join('standard AS sd', 'sd.id', '=', 'se.standard_id')
                    ->join('division AS d', 'd.id', '=', 'se.section_id')
                    ->leftJoin('batch AS b', 'b.id', '=', 's.studentbatch')
                    ->leftJoin('house_master AS hm', 'hm.id', '=', 'se.house_id')
                    ->join('fees_paid_other AS fo', function ($join) use ($from_date, $to_date){
                        $join->on('fo.student_id', '=', 'se.student_id')
                            ->whereBetween('fo.receiptdate', [$from_date, $to_date])
                            ->where('fo.is_deleted', 'N');
                    })
                    ->where('se.sub_institute_id', $sub_institute_id)
                    ->where('se.syear', $syear)
                    ->groupBy('se.student_id');
                })
            );
        }, 'temp_tbl')
        ->select(
            'standard_name',
            'coach_name',
            'batch_name',
            DB::raw("SUM(CASE WHEN house_name = 'CN' AND gender = 'M' THEN 1 ELSE 0 END) AS cn_male_count"),
            DB::raw("SUM(CASE WHEN house_name = 'CN' AND gender = 'F' THEN 1 ELSE 0 END) AS cn_female_count"),
            DB::raw("SUM(CASE WHEN house_name = 'Other School' AND gender = 'M' THEN 1 ELSE 0 END) AS other_male_count"),
            DB::raw("SUM(CASE WHEN house_name = 'Other School' AND gender = 'F' THEN 1 ELSE 0 END) AS other_female_count"),
            DB::raw("COUNT(house_name) AS tot_count"),
            DB::raw("SUM(CASE WHEN house_name = 'CN' THEN (total_reg_paid + total_other_paid) ELSE 0 END) AS cn_tot"),
            DB::raw("SUM(CASE WHEN house_name = 'Other School' THEN (total_reg_paid + total_other_paid) ELSE 0 END) AS other_tot"),
            DB::raw("SUM(total_reg_paid) + SUM(total_other_paid) AS tot")
        )
        ->groupBy('standard_name', 'coach_name', 'batch_name')
        ->get();
                
        $resultArray = [];
        foreach ($results as $object) 
        {
            $resultArray[] = (array)$object;
        }

        /* $new_arr = array();
        foreach ($resultArray as $id => $arr) {
            $standard_name = $arr["standard_name"];
            $coach_name = $arr["coach_name"];
            $batch_name = $arr["batch_name"] ?? "Not Set";
            $school = $arr["house_name"] ?? "Other School";
            $gender = $arr["gender"] ?? "F";


            // Check if the standard_name exists in $new_arr
            if (!isset($new_arr[$standard_name])) {
                $new_arr[$standard_name] = array();
            }

            // Check if the coach_name exists in $new_arr[$standard_name]
            if (!isset($new_arr[$standard_name][$coach_name])) {
                $new_arr[$standard_name][$coach_name] = array();
            }

            if (!isset($new_arr[$standard_name][$coach_name][$batch_name])) {
                $new_arr[$standard_name][$coach_name][$batch_name] = array();
            }

            // Check if the gender exists in $new_arr[$standard_name][$coach_name]
            if (!isset($new_arr[$standard_name][$coach_name][$batch_name][$school])) {
                $new_arr[$standard_name][$coach_name][$batch_name][$school] = array();
            }

            // Add the record to the gender-specific array
            $new_arr[$standard_name][$coach_name][$batch_name][$school][] = $arr;
        } */
        /* echo "<pre>";
        print_r($new_arr);
        print_r($resultArray);
        exit; */
  
        // $results now contains the query results


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $resultArray;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['months'] = FeeMonthId();

        return is_mobile($type, "fees/fees_report/fees_payout_index", $res, "view");
    }
}
