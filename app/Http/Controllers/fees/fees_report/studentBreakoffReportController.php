<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Models\student\tblstudentModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\OtherBreackOff;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\OtherBreackOfMonth;
use function App\Helpers\get_map_month;
use App\Models\fees\map_year\map_year;

class studentBreakoffReportController extends Controller
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
        $months_arr = get_map_month();
        // echo "<pre>";print_r($months_arr);exit;
        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['months_arr'] = $months_arr;

        return is_mobile($type, "fees/fees_report/student_breakoff_report", $res, "view");
    }

    public function create(Request $request)
    {
        // echo("hi");die;
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $mobile_no = $request->input('mobile_no');
        $uniqueid = $request->input('uniqueid');
        $month = $request->input('month');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        $months_arr = get_map_month();
        $name = $first_name ?? $last_name ?? '';
        // get student details
        $student_data = SearchStudent($grade, $standard, $division, "", "", "", $name, "", "", $enrollment_no, "");

        $responce_arr = [];
        $final_array = [];
        $other_bk_off=[];
        $month_arr = FeeMonthId();
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month . $currunt_year;

        $search_ids = [];
        foreach ($month_arr as $id => $arr) {
            if ($id == $currunt_month_id) {
                $search_ids[] = $id;
                // break;
            } else {
                $search_ids[] = $id;
            }
        }
        if(isset($month)){
            $search_ids = $month;
        }
        foreach ($student_data as $id => $arr) {
            $stu_arr = ['0' => $arr['id']];
            // $student_ids, $from_date = null, $to_date = null, $fees_head = null, $syear = ''
            $final_array[] = FeeBreakoffHeadWise($stu_arr,"","","","",$month); //for current year
            $final_array[$id][$arr['id']]['quota'] = $arr['student_quota'];
            $final_array[$id][$arr['id']]['uniqueid'] = $arr['uniqueid']; 
            $final_array[$id][$arr['id']]['otherfees'] = OtherBreackOff($stu_arr,$search_ids);                                   
       
        }
        $get_fees_titles = DB::table('fees_title')
        ->select('display_name', 'fees_title')
        ->where('sub_institute_id', session()->get('sub_institute_id'))
        ->where('syear', session()->get('syear'))
        ->get()->toArray();
              
        // echo "<pre>";
        // print_r($final_array);
        // exit;
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $final_array;
        $res['months_arr'] = $months_arr;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile_no'] = $mobile_no;
        $res['month'] = $month;
        $res['fees_titles'] = $get_fees_titles;
        //  echo "<pre>";print_r($final_array);exit;
        return is_mobile($type, "fees/fees_report/student_breakoff_report", $res, "view");
    }

    

}
