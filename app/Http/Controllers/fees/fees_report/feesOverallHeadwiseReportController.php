<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Models\student\tblstudentModel;
use App\Models\fees\fees_title\fees_title;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;
use function App\Helpers\OtherBreackOff;

class feesOverallHeadwiseReportController extends Controller
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
        $months = FeeMonthId();
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $feesHead = fees_title::where(['sub_institute_id' => $sub_institute_id, 'other_fee_id' => 0])
        ->orderBy('sort_order', 'asc') 
        ->pluck('display_name', 'fees_title')
        ->toArray();

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['months'] = $months;
        $res['fees_heads'] = $feesHead;

        return is_mobile($type, "fees/fees_report/show_fees_overall_headwise_report", $res, "view");
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
        $month = $request->input('month');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $fees_head = $request->input('fees_head');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $months = FeeMonthId();
        $marking_period_id = session()->get('term_id');

        $extraSearchArray = array();
        $extraSearchArrayRaw = " 1=1 ";
        $bk_extra_fees = $bk_extra_other_fees = '';

        $feesHead = fees_title::where(['sub_institute_id' => $sub_institute_id, 'other_fee_id' => 0])
        ->orderBy('sort_order', 'asc') 
        ->pluck('display_name', 'fees_title')
        ->toArray();

        if ($grade != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
            $bk_extra_fees .= " AND ft.grade_id = '" . $grade . "' ";
            $bk_extra_other_fees .= "  AND se.grade_id = '" . $grade . "' ";
        }

        if ($standard != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
            $bk_extra_fees .= " AND ft.standard_id = '" . $standard . "' ";
            $bk_extra_other_fees .= "  AND se.standard_id = '" . $standard . "' ";
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
        // $extraSearchArrayRaw .= "  AND tblstudent.enrollment_no IN ('N033','N061','N112')";//,'N047',,,'N079','N112') ";
        //$extraSearchArrayRaw .= "  AND tblstudent_enrollment.end_date IS NULL ";
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['student_quota.sub_institute_id'] = $sub_institute_id;
        
        // DB::enableQueryLog();
        $studentData = tblstudentModel::selectRaw("tblstudent.id,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,tblstudent.mobile,tblstudent.uniqueid,student_quota.title as stu_quota,tblstudent_enrollment.end_date,IF(tblstudent_enrollment.end_date IS NULL,'Active','In-Active') as student_status")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard',function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id')->when($marking_period_id,function($query) use($marking_period_id){
                    $query->where('standard.marking_period_id',$marking_period_id);
                });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->orderBy('standard.sort_order', 'ASC')
            ->orderBy('tblstudent.first_name', 'ASC')
            ->get()
            ->toArray();
            // dd(DB::getQueryLog($studentData));
       // DB::enableQueryLog();
        $bk_array = DB::table('fees_breackoff as ft')
        ->select('ft.*', 'f.fees_title', 'f.sort_order','f.display_name')
        ->selectRaw('GROUP_CONCAT(DISTINCT ft.month_id  ORDER BY ft.month_id) AS months , SUM(ft.amount) AS tot_amt')
        ->join('fees_title as f', function ($join) use ($fees_head) {
            $join->on('f.id', '=', 'ft.fee_type_id')
                ->whereColumn('f.sub_institute_id', '=', 'ft.sub_institute_id')
                ->whereColumn('f.syear', '=', 'ft.syear')
                ->when($fees_head, function ($join) use ($fees_head) {
                    $join->whereRaw('f.fees_title In ("'.implode('","', $fees_head).'")');
                });
        })
        
        ->where('ft.syear', $syear)
        ->where('ft.sub_institute_id', $sub_institute_id)
        ->groupBy('ft.fee_type_id')
        ->union(function ($query) use ($syear, $sub_institute_id, $bk_extra_other_fees, $fees_head) {
            $query->select('fbo.id', 'fbo.syear', DB::raw("'' AS admission_year"), 'fbo.fee_type_id', DB::raw("'' AS quota"),
                'se.grade_id', 'se.standard_id', 'se.section_id', 'fbo.month_id', 'fbo.amount', 'fbo.sub_institute_id',
                DB::raw("'' AS created_at"), DB::raw("'' AS updated_at"), 'f.fees_title', 'f.display_name','f.sort_order')
                ->selectRaw('GROUP_CONCAT(DISTINCT fbo.month_id) AS months, SUM(fbo.amount) AS tot_amt')
                ->from('fees_breakoff_other as fbo')
                ->join('fees_title as f', function ($join) use ($fees_head) {
                    $join->on('f.other_fee_id', '=', 'fbo.fee_type_id')
                        ->whereColumn('f.sub_institute_id', '=', 'fbo.sub_institute_id')
                        ->whereColumn('f.syear', '=', 'fbo.syear')
                        ->when($fees_head, function ($join) use ($fees_head) {
                            $join->whereRaw('f.fees_title In ("'.implode('","', $fees_head).'")');
                        });
                })
                ->join('tblstudent as s', function ($join) {
                    $join->on('s.id', '=', 'fbo.student_id')
                        ->whereColumn('s.sub_institute_id', '=', 'fbo.sub_institute_id');
                })
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->on('se.student_id', '=', 's.id')
                        ->whereColumn('s.sub_institute_id', '=', 'se.sub_institute_id')
                        ->where('se.syear', $syear)
                        ->whereNull('se.end_date');
                })
                ->where('fbo.syear', $syear)
                ->whereRaw('fbo.sub_institute_id='.$sub_institute_id.$bk_extra_other_fees)
                ->groupBy('fbo.fee_type_id','fbo.month_id','fbo.student_id')->orderBy('f.sort_order');
        })
        ->orderBy('sort_order')
        ->get()
        ->toArray();
        //dd($bk_array);
            //dd(DB::getQueryLog($bk_array));
        $i = 0;
        $bk_title_months_array = [];
        $sorted_bk_title_months_array = [];

        foreach ($bk_array as $v) {
            // ksort($bk_array);                    

            $explod_months = explode(',', $v->months);
    
            foreach ($explod_months as $v1) {
                if (in_array($v1, $month)) {
                    $bk_title_months_array[$v->display_name . '/' . $v->fees_title][$v1] = $months[$v1];
                    $i++;
                }
            }
            // ksort($bk_title_months_array);
        }
      

        // dd($bk_title_months_array);
        $count_of_array = count($bk_title_months_array);
        // return count($bk_title_months_array);exit;
        $fees_fine_discount_data = DB::table('fees_collect')
            ->selectRaw('SUM(fine) AS total_fine, SUM(fees_discount) AS total_disc, student_id')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear);
        if (isset($to_date)) {
            $fees_fine_discount_data = $fees_fine_discount_data->where('receiptdate', '<=', $to_date);
        }
        $fees_fine_discount_data = $fees_fine_discount_data->groupBy('student_id')
            ->get()
            ->toArray();
        
        $fees_fine_discount_data = array_combine(array_column($fees_fine_discount_data, 'student_id'), $fees_fine_discount_data);        

        $fees_fine_discount_data = array_map(function ($value) {
            return (array)$value;
        }, $fees_fine_discount_data);
        foreach ($fees_fine_discount_data as $k => $val) {
            $fees_fine_discount_data[$val['student_id']] = $val;
        }

        $controller = new fees_collect_controller;
        $final_array = array();

        foreach ($studentData as $key => $value) {
            $bk_data = $controller->getBk($request, $value['id']);

            $stu_arr = array(
                "0" => $value['id']
            );

            $head_wise_fees = FeeBreakoffHeadWise($stu_arr, $from_date, $to_date, $fees_head);
            $head_wise_Other_fees = OtherBreackOff($stu_arr, array_keys($months), 'Yes', $from_date, $to_date);

            if (count($bk_data) > 0) {
                $final_array[$value['id']]['enrollment'] = $bk_data['stu_data']['enrollment'];
                $final_array[$value['id']]['name'] = $bk_data['stu_data']['name'];
                $final_array[$value['id']]['stddiv'] = $bk_data['stu_data']['stddiv'];
                $final_array[$value['id']]['admission'] = $bk_data['stu_data']['admission'];
                $final_array[$value['id']]['email'] = $bk_data['stu_data']['email'];
                $final_array[$value['id']]['pending'] = $bk_data['stu_data']['pending'];
                $final_array[$value['id']]['mobile'] = $bk_data['stu_data']['mobile'];
                $final_array[$value['id']]['uniqueid'] = $bk_data['stu_data']['uniqueid'];
                $final_array[$value['id']]['stu_quota'] = $value['stu_quota'];
                $final_array[$value['id']]['end_date'] = $value['end_date'];
                $final_array[$value['id']]['student_status'] = $value['student_status'];

                $total_paid_new = $total_unpaid_new = 0;

                foreach ($head_wise_fees as $stu_id => $total_paid_fees) {
                    foreach ($total_paid_fees['breakoff'] as $month_id => $paid_data) {
                        if (in_array($month_id, $month)) {
                            foreach ($paid_data as $fees_title => $data) {
                                if ($final_array[$stu_id]['end_date'] != '') {
                                    $final_array[$stu_id]['unpaid_fees'][$fees_title][$month_id] = 0;
                                    $final_array[$stu_id]['paid_fees'][$fees_title][$month_id] = $data['paid_amount'];
                                    // $final_array[$stu_id][$fees_title]['amount'] = $data['amount'];
                                    $total_paid_new = $total_paid_new + $data['paid_amount'];
                                    // $total_unpaid_new = $total_unpaid_new + $data['amount'];
                                } else {
                                    $final_array[$stu_id]['unpaid_fees'][$fees_title][$month_id] = $data['amount'];
                                    $final_array[$stu_id]['paid_fees'][$fees_title][$month_id] = $data['paid_amount'];
                                    // $final_array[$stu_id][$fees_title]['amount'] = $data['amount'];
                                    $total_paid_new = $total_paid_new + $data['paid_amount'];
                                    $total_unpaid_new = $total_unpaid_new + $data['amount'];
                                }

                            }
                        }
                    }
                }

                foreach ($head_wise_Other_fees as $stu_id => $total_paid_other_fees) {
                    foreach ($total_paid_other_fees as $fees_title => $other_paid_data) {
                        foreach ($other_paid_data as $month_id => $other_data) {
                            if (in_array($month_id, $month)) {
                                if ($final_array[$stu_id]['end_date'] != '') {
                                    $final_array[$stu_id]['unpaid_fees'][$fees_title][$month_id] = 0;
                                    $final_array[$stu_id]['paid_fees'][$fees_title][$month_id] = $other_data['paid_amount'];

                                    $total_paid_new = $total_paid_new + $other_data['paid_amount'];
                                    // $total_unpaid_new = $total_unpaid_new + ($other_data['bf_amount'] - $other_data['paid_amount']);
                                } else {
                                    $final_array[$stu_id]['unpaid_fees'][$fees_title][$month_id] = ($other_data['bf_amount'] - $other_data['paid_amount']);
                                    $final_array[$stu_id]['paid_fees'][$fees_title][$month_id] = $other_data['paid_amount'];

                                    $total_paid_new = $total_paid_new + $other_data['paid_amount'];
                                    $total_unpaid_new = $total_unpaid_new + ($other_data['bf_amount'] - $other_data['paid_amount']);
                                }
                            }
                        }
                    }
                }

                 $total_fees_array = array();
                foreach ($bk_data as $stu_id => $total_fees) {
                    // print_r($total_fees);
                    $total_fees_array[]=$total_fees;
                    foreach ($total_fees_array[0] as $key => $month_data) {
                    // print_r($month_data);
                        
                       if (isset($month_data['month_id'])) {
                            $final_array[$value['id']]['-']['paid'] = $total_paid_new;
                            $final_array[$value['id']]['-']['remain'] = $total_unpaid_new;
                            $final_array[$value['id']]['-']['bk'] = ($total_paid_new + $total_unpaid_new);
                        }

                        if (isset($final_array[$value['id']]['-']['paid']) && isset($final_array[$value['id']]['-']['remain'])) {
                            if ($final_array[$value['id']]['-']['paid'] == 0 && $final_array[$value['id']]['-']['remain'] == 0) {
                                unset($final_array[$value['id']]);
                            }
                        }
                    }
                }
                // exit;
                if (isset($final_array[$value['id']])) {
                    if (isset($fees_fine_discount_data[$value['id']])) {
                        $final_array[$value['id']]['fine'] = $fees_fine_discount_data[$value['id']]['total_fine'];
                        $final_array[$value['id']]['discount'] = $fees_fine_discount_data[$value['id']]['total_disc'];
                    }
                }
            }
        }
        // Sort the array based on year and month in descending order
        foreach ($bk_title_months_array as &$value) {
            uksort( $value , function($a, $b) use($months){
                $a = strtotime(substr($months[$a],-4) );
                $b = strtotime(substr($months[$b],-4) );
                return $a - $b;
            });
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $final_array;
        $res['bk_title_months_array'] = $bk_title_months_array;
        $res['count_of_array'] = $count_of_array;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile_no'] = $mobile_no;
        $res['uniqueid'] = $uniqueid;
        $res['months'] = $months;
        $res['month'] = $month;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['fees_heads'] = $feesHead;
        $res['fees_head'] = $fees_head;

        return is_mobile($type, "fees/fees_report/show_fees_overall_headwise_report", $res, "view");
    }  
    
}
