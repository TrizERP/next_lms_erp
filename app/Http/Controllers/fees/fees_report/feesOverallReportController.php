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
use Illuminate\Support\Facades\DB;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;

class feesOverallReportController extends Controller
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

        return is_mobile($type, "fees/fees_report/show_fees_overall_report", $res, "view");
    }

    public function showFeesOverall(Request $request)
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
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        $extraSearchArray = array();
        $extraSearchArrayRaw = " 1=1 ";

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

        if ($mobile_no != '') {
            $extraSearchArray['tblstudent.mobile'] = $mobile_no;
        }

        if ($uniqueid != '') {
            $extraSearchArray['tblstudent.uniqueid'] = $uniqueid;
        }

        if ($first_name != '') {
            $extraSearchArrayRaw .= "  AND tblstudent.first_name like '%" . $first_name . "%' ";
        }

        if ($last_name != '') {
            $extraSearchArrayRaw .= "  AND tblstudent.last_name like '%" . $last_name . "%' ";
        }
        $extraSearchArrayRaw .= "  AND tblstudent_enrollment.end_date IS NULL ";
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;

        $feesData = tblstudentModel::selectRaw("tblstudent.id,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,tblstudent.mobile,tblstudent.uniqueid")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('standard.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->get()
            ->toArray();

        $fees_fine_discount_data = DB::table('fees_collect')
            ->selectRaw("SUM(fine) AS total_fine, SUM(fees_discount) AS total_disc, student_id")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->groupBy('student_id')->get()->toArray();

        $fees_fine_discount_data = array_map(function ($value) {
            return (array)$value;
        }, $fees_fine_discount_data);

        foreach ($fees_fine_discount_data as $k => $val) {
            $fees_fine_discount_data[$val['student_id']] = $val;
        }

        $controller = new fees_collect_controller;

        $month_arr = FeeMonthId();

        $final_array = array();


        foreach ($feesData as $key => $value) {
            $bk_data = $controller->getBk($request, $value['id']);
            if (count($bk_data) > 0) {
                $final_array[$value['id']]['enrollment'] = $bk_data['stu_data']['enrollment'];
                $final_array[$value['id']]['name'] = $bk_data['stu_data']['name'];
                $final_array[$value['id']]['stddiv'] = $bk_data['stu_data']['stddiv'];
                $final_array[$value['id']]['admission'] = $bk_data['stu_data']['admission'];
                $final_array[$value['id']]['email'] = $bk_data['stu_data']['email'];
                $final_array[$value['id']]['pending'] = $bk_data['stu_data']['pending'];
                $final_array[$value['id']]['mobile'] = $bk_data['stu_data']['mobile'];
                $final_array[$value['id']]['uniqueid'] = $bk_data['stu_data']['uniqueid'];
                $total_fees_array = array();
                foreach ($bk_data as $stu_id => $total_fees) {
                    $total_fees_array[] = $total_fees;
                    foreach ($total_fees_array[0] as $key => $month_data) {
                        if (isset($month_data['month_id'])) {
                            $final_array[$value['id']][$month_data['month_id']]['paid'] = $month_data['paid'];
                            $final_array[$value['id']][$month_data['month_id']]['remain'] = $month_data['remain'];
                            $final_array[$value['id']][$month_data['month_id']]['bk'] = $month_data['bk'];
                        }
                    }
                }
            }
            if (isset($fees_fine_discount_data[$value['id']])) {
                $final_array[$value['id']]['fine'] = $fees_fine_discount_data[$value['id']]['total_fine'];
                $final_array[$value['id']]['discount'] = $fees_fine_discount_data[$value['id']]['total_disc'];
            } 

        if (isset($final_array[$value['id']])) {
            $student_data = $final_array[$value['id']];
            $total_paid_student =  $total_remain_student =  $total_bk_student = 0;

            foreach ($student_data as $key => $data) {
                if ($key !== 'total_bk' && is_array($data) && isset($data['bk']) ||  isset($data['paid']) ||  isset($data['remain']) ) {
                    $total_paid_student += $data['paid'];
                    $total_remain_student += $data['remain'];
                    $total_bk_student += $data['bk'];            
                    $final_array[$value['id']]['-']['paid'] = $total_paid_student;
                    $final_array[$value['id']]['-']['remain'] = $total_remain_student; 
                    $final_array[$value['id']]['-']['bk'] = $total_bk_student; 
                }
            }
        } 
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $final_array;
        $res['month_arr'] = $month_arr;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile_no'] = $mobile_no;
        $res['uniqueid'] = $uniqueid;
        // echo "<pre>";print_r($final_array);exit;
        return is_mobile($type, "fees/fees_report/show_fees_overall_report", $res, "view");
    }
}
