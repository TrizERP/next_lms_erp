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
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;
use function App\Helpers\OtherBreackOff;
use function App\Helpers\OtherBreackOfMonth;
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

        $data = map_year::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ])->get()->toArray();
        
        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = [
            1  => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $months_arr = [];
        $syear = session()->get('syear');

        if($data[0]['type'] == "yearly_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
        }
        else if($data[0]['type'] == "half_year_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
            $sixmonths = ($start_month+6);
            $months_arr[$sixmonths.$syear] = $months[$sixmonths].'/'.$syear;

        }
        else if($data[0]['type'] == "quarterly_fees")
        {
            for ($i = $start_month; $i <= 12; $i++) 
            {
                if ($start_month <= 12) 
                {
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    $start_month = ($start_month+3);
                }
                else
                {
                    $start_month = 1;
                    ++$syear;
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    break;
                }
            }
        }
        else
        {
            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                if ($start_month == 12) {
                    $start_month = 0;
                    ++$syear;
                }
                ++$start_month;
            }
        }

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['months'] = $months_arr;

        return is_mobile($type, "fees/fees_report/student_breakoff_report", $res , "view");
    }

    public function showStudentBreakoff(Request $request)
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

        /* if(!isset($month) || empty($month) || $month == '' ){
            $res['status_code'] = 0;
            $res['message'] = 'Please Select Month';
         return redirect()->route('stduent_breakoff_report.index')->with(['data'=>$res]);
        } */

        $extraSearchArray = array();
        $extraSearchArrayRaw = " 1=1 ";
        $bk_extra_fees = $bk_extra_other_fees =  '';

        if($grade != '')
        {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
            $bk_extra_fees .= " AND ft.grade_id = '".$grade."' ";
            $bk_extra_other_fees .= "  AND se.grade_id = '".$grade."' ";
        }

        if($standard != '')
        {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
            $bk_extra_fees .= " AND ft.standard_id = '".$standard."' ";
            $bk_extra_other_fees .= "  AND se.standard_id = '".$standard."' ";
        }

        if($division != '')
        {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        if($enrollment_no != '')
        {
            $extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
        }

        if($mobile_no != '')
        {
            $extraSearchArray['tblstudent.mobile'] = $mobile_no;
        }

        if($uniqueid != '')
        {
            $extraSearchArray['tblstudent.uniqueid'] = $uniqueid;
        }

        if($first_name != '')
        {
            $extraSearchArrayRaw .= "  AND tblstudent.first_name like '%".$first_name."%' ";
        }

        if($last_name != '')
        {
            $extraSearchArrayRaw .= "  AND tblstudent.last_name like '%".$last_name."%' ";
        }

        $extraSearchArrayRaw .= "  AND tblstudent_enrollment.end_date IS NULL ";
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['student_quota.sub_institute_id'] = $sub_institute_id;
        
        $feesData = tblstudentModel::selectRaw("tblstudent.id,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,tblstudent.mobile,tblstudent.uniqueid,tblstudent.roll_no,student_quota.title as stu_quota")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard',function($join) {
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id');
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->orderByRaw('standard.sort_order, division.id, tblstudent.roll_no')
            ->get()
            ->toArray();
    
        /* $data = map_year::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ])->get()->toArray();

        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = [
            1  => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $months_arr = [];
        $syear = session()->get('syear');

        if($data[0]['type'] == "yearly_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
        }
        else if($data[0]['type'] == "half_year_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
            $sixmonths = ($start_month+6);
            $months_arr[$sixmonths.$syear] = $months[$sixmonths].'/'.$syear;

        }
        else if($data[0]['type'] == "quarterly_fees")
        {
            for ($i = $start_month; $i <= 12; $i++) 
            {
                if ($start_month <= 12) 
                {
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    $start_month = ($start_month+3);
                }
                else
                {
                    $start_month = 1;
                    ++$syear;
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    break;
                }
            }
        }
        else
        {
            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                if ($start_month == 12) {
                    $start_month = 0;
                    ++$syear;
                }
                ++$start_month;
            }
        } */

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
        $final_array = array();

        foreach ($feesData as $key => $value) {
            
            $bk_data = $controller->getBk($request, $value['id']);
            /* echo "<pre>";
            print_r($bk_data);
            echo "</pre>";
            die; */
            if (count($bk_data) > 0) {
                $final_array[$value['id']]['enrollment'] = $bk_data['stu_data']['enrollment'];
                $final_array[$value['id']]['name'] = $bk_data['stu_data']['name'];
                $final_array[$value['id']]['stddiv'] = $bk_data['stu_data']['stddiv'];
                $final_array[$value['id']]['admission'] = $bk_data['stu_data']['admission'];
                $final_array[$value['id']]['email'] = $bk_data['stu_data']['email'];
                $final_array[$value['id']]['pending'] = $bk_data['stu_data']['pending'];
                $final_array[$value['id']]['mobile'] = $bk_data['stu_data']['mobile'];
                $final_array[$value['id']]['uniqueid'] = $bk_data['stu_data']['uniqueid'];
                $final_array[$value['id']]['roll_no'] = $bk_data['stu_data']['roll_no'];
                $final_array[$value['id']]['stu_quota'] = $value['stu_quota'];

                $total_fees_array = array();
                foreach ($bk_data as $stu_id => $total_fees) {
                    $total_fees_array[] = $total_fees;
                    foreach ($total_fees_array[0] as $key => $month_data) {
                        if (isset($month_data['month_id'])) {
                            $final_array[$value['id']][$month_data['month_id']]['bk'] = $month_data['bk'];
                        }
                    }
                }
            }

            if (isset($fees_fine_discount_data[$value['id']])) {
                $final_array[$value['id']]['fine'] = $fees_fine_discount_data[$value['id']]['total_fine'];
                $final_array[$value['id']]['discount'] = $fees_fine_discount_data[$value['id']]['total_disc'];
            }

            if (isset($bk_data['final_fee'])) {
                $final_array[$value['id']]['final_fee'] = $bk_data['final_fee'];
            }

            if (isset($final_array[$value['id']])) {
                $student_data = $final_array[$value['id']];
                $total_bk_student = 0;

                foreach ($student_data as $key => $data) {
                    if ($key !== 'total_bk' && is_array($data) && isset($data['bk'])) {
                        $total_bk_student += $data['bk'];   
                        $final_array[$value['id']]['-']['bk'] = $total_bk_student; 
                    }
                }
            } 
        }
        
        $get_fees_titles = DB::table('fees_title')
            ->select('display_name', 'fees_title')
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))
            // ->where('other_fee_id', '<=', 0)
            // ->orderBy('other_fee_id')
            ->get()->toArray();
            /* echo "<pre>";
            print_r($get_fees_titles);
            echo "</pre>";
            die; */
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $final_array;
        // $res['months'] = $months_arr;
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
