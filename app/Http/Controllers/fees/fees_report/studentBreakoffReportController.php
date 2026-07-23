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
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\OtherBreackOff;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\get_map_month;

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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->get('sub_institute_id', $sub_institute_id);
            $syear = $request->get('syear', $syear);
        }

        $months_arr = get_map_month($sub_institute_id, $syear);
        if (empty($months_arr)) {
            $res['status_code'] = 0;
            $res['message'] = 'Fees year mapping is not configured for the selected academic year';
            $res['months_arr'] = [];
            return is_mobile($type, 'fees/fees_report/student_breakoff_report', $res, 'view');
        }

        $res['status_code'] = '1';
        $res['message'] = 'Success';
        $res['months_arr'] = $months_arr;

        return is_mobile($type, 'fees/fees_report/student_breakoff_report', $res, 'view');
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
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

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->get('sub_institute_id', $sub_institute_id);
            $syear = $request->get('syear', $syear);
        }

        $months_arr = get_map_month($sub_institute_id, $syear);
        if (empty($months_arr)) {
            $res['status_code'] = 0;
            $res['message'] = 'Fees year mapping is not configured for the selected academic year';
            $res['fees_data'] = [];
            $res['months_arr'] = [];
            $res['fees_titles'] = [];
            return is_mobile($type, 'fees/fees_report/student_breakoff_report', $res, 'view');
        }

        $name = $first_name ?? $last_name ?? '';
        $student_data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear, '', $name, $uniqueid, $mobile_no, $enrollment_no, '');

        $final_array = [];
        $month_arr = FeeMonthId($syear, $sub_institute_id);
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month . $currunt_year;

        $search_ids = [];
        foreach ($month_arr as $id => $arr) {
            if ($id == $currunt_month_id) {
                $search_ids[] = $id;
            } else {
                $search_ids[] = $id;
            }
        }

        if (isset($month) && !empty($month)) {
            $search_ids = $month;
        }

        foreach ($student_data as $id => $arr) {
            $stu_arr = ['0' => $arr['id']];
            $final_array[] = FeeBreakoffHeadWise($stu_arr, '', '', '', $syear, $search_ids, $sub_institute_id);
            $final_array[$id][$arr['id']]['quota'] = $arr['student_quota'];
            $final_array[$id][$arr['id']]['uniqueid'] = $arr['uniqueid'];
            $final_array[$id][$arr['id']]['otherfees'] = OtherBreackOff($stu_arr, $search_ids, '', '', '', $syear, $sub_institute_id);
        }

        $get_fees_titles = DB::table('fees_title')
            ->select('display_name', 'fees_title')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->get()->toArray();

        $res['status_code'] = count($final_array) > 0 ? 1 : 0;
        $res['message'] = count($final_array) > 0 ? 'Success' : 'No records found';
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

        return is_mobile($type, 'fees/fees_report/student_breakoff_report', $res, 'view');
    }
}
