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

        $where_arr = array(
            $from_date,
            $to_date,
            'N',
            $sub_institute_id,
            $syear,
            $from_date,
            $to_date,
            'N',
            $sub_institute_id,
            $syear,
        );

        $results = DB::select("
            SELECT 
                gender,
                standard_name,
                coach_name,
                batch_name,
                house_name,
                SUM(total_reg_paid) + SUM(total_other_paid) AS tot
            FROM (
                SELECT 
                    se.student_id,
                    s.first_name,
                    s.gender,
                    sd.name AS standard_name,
                    d.name AS coach_name,
                    IFNULL(b.title, 'Not Set') AS batch_name,
                    hm.house_name,
                    SUM(fc.amount) AS total_reg_paid,
                    0 AS total_other_paid 
                FROM tblstudent_enrollment se
                INNER JOIN tblstudent s ON s.id = se.student_id
                INNER JOIN standard sd ON sd.id = se.standard_id
                INNER JOIN division d ON d.id = se.section_id
                LEFT JOIN batch b ON b.id = s.studentbatch
                LEFT JOIN house_master hm ON hm.id = se.house_id
                INNER JOIN fees_collect fc ON (fc.student_id = se.student_id AND fc.receiptdate BETWEEN ? AND ? AND fc.is_deleted = ?)
                WHERE se.sub_institute_id = ? AND se.syear = ?
                GROUP BY se.student_id
                
                UNION
                
                SELECT 
                    se.student_id,
                    s.first_name,
                    s.gender,
                    sd.name AS standard_name,
                    d.name AS coach_name,
                    IFNULL(b.title, 'Not Set') AS batch_name,
                    hm.house_name,
                    0 AS total_reg_paid, 
                    SUM(fo.actual_amountpaid) AS total_other_paid
                FROM tblstudent_enrollment se
                INNER JOIN tblstudent s ON s.id = se.student_id
                INNER JOIN standard sd ON sd.id = se.standard_id
                INNER JOIN division d ON d.id = se.section_id
                LEFT JOIN batch b ON b.id = s.studentbatch
                LEFT JOIN house_master hm ON hm.id = se.house_id
                INNER JOIN fees_paid_other fo ON (fo.student_id = se.student_id AND fo.receiptdate BETWEEN ? AND ? AND fo.is_deleted = ?)
                WHERE se.sub_institute_id = ? AND se.syear = ?
                GROUP BY se.student_id
                ) AS temp_tbl
                GROUP BY standard_name, coach_name, batch_name, gender;
            ", $where_arr);
        
        $resultArray = [];
        foreach ($results as $object) {
            $resultArray[] = (array)$object;
        }

        $new_arr = array();
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
        }
        /* echo "<pre>";
        print_r($new_arr);
        print_r($resultArray);
        exit; */
  
        // $results now contains the query results


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $new_arr;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['months'] = FeeMonthId();

        return is_mobile($type, "fees/fees_report/fees_payout_index", $res, "view");
    }
}
