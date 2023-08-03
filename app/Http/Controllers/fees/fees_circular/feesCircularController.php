<?php

namespace App\Http\Controllers\fees\fees_circular;

use App\Http\Controllers\Controller;
use App\Models\fees\fees_circular\feesCircularMasterModel;
use App\Models\fees\fees_circular\feesCircularModel;
use App\Models\fees\tblfeesConfigModel;
use App\Models\school_setup\SchoolModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeBreackoff;
use function App\Helpers\FeeMonthId;
use function App\Helpers\OtherBreackOff;
use function App\Helpers\OtherBreackOffHead;
use function App\Helpers\OtherBreackOfMonth;
use function App\Helpers\OtherBreackOfMonthHead;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class feesCircularController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $months = FeeMonthId();

        $result = DB::table('fees_receipt_book_master')->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('syear', $syear)
            ->where('sub_institute_id', $sub_institute_id)
            ->groupByRaw("receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number")
            ->get()->toArray();
        $result = json_decode(json_encode($result), true);

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['months'] = $months;
        $res['receipt_books'] = $result;

        return is_mobile($type, "fees/fees_circular/show", $res, "view");
    }

       public function showStudent(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $month = $request->input('month');
        $receipt_id = $request->input('receipt_id');
        $marking_period_id = session()->get('marking_period_id');

        $months = FeeMonthId();

        $fees_join = "";
        $stu_arr = array();
        $gb = array();
        $paid_other_join = "";
        if ($sub_institute_id != 201 && $sub_institute_id != 202 && $sub_institute_id != 203 && $sub_institute_id != 204) {
            $studentData = SearchStudent($grade, $standard, $division);
        } else {
            $data = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id');
                })->join('academic_section as g', function ($join) {
                    $join->whereRaw('g.id = se.grade_id');
                })->join('standard as st', function ($join) use($marking_period_id){
                    $join->on('st.id', '=', 'se.standard_id');
                    // ->when($marking_period_id,function($query) use ($marking_period_id){
                    //     $query->where('st.marking_period_id',$marking_period_id);
                    // });
                })->leftJoin('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->join('fees_breackoff as fb', function ($join) use ($syear, $sub_institute_id) {
                    $join->whereRaw("(fb.syear = '".$syear."' AND fb.admission_year = s.admission_year 
                        AND fb.quota = se.student_quota AND fb.grade_id = se.grade_id AND fb.standard_id = se.standard_id 
                        AND fb.sub_institute_id = '".$sub_institute_id."')");
                })->join('fees_title as ft', function ($join) {
                    $join->whereRaw('(fb.fee_type_id = ft.id)');
                })->selectRaw("s.id,s.enrollment_no,s.first_name,s.last_name,st.name standard_name, 
                    d.name AS division_name,fb.amount,ft.display_name,ft.fees_title,SUM(fb.amount) AS total_breakoff")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                ->when($grade,function ($q) use ($grade) {
                        $q->where('se.grade_id', $grade);
                })
                ->when($standard,function ($q) use ($standard) {
                        $q->where('se.standard_id', $standard);
                })
                ->when($division,function ($q) use ($division) {
                    $q->where('se.section_id', $division);
            })->groupBy('s.id')->get()->toArray();

            $studentData = json_decode(json_encode($data), true);
            foreach($studentData as $key => $val){
                $gb[] = $this->getBk($request, $val['id']);

            }
         }
         

        $result = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('syear', $syear)
            ->where('sub_institute_id', $sub_institute_id)
            ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
            ->get()->toArray();

        $result = json_decode(json_encode($result), true);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $gb;
        $res['months'] = $months;
        $res['month'] = $month;
        $res['receipt_books'] = $result;
        $res['receipt_id'] = $receipt_id;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
// echo"<pre>", print_r($gb);exit;
        // echo ;exit;
        return is_mobile($type, "fees/fees_circular/show", $res, "view");
    }

public function getBk(Request $request, $id)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $stu_arr = [
            "0" => $id,
        ];

        $request->session()->put('stu_arr', $stu_arr);

        $student_id = $id;

        $month_arr = FeeMonthId();
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month.$currunt_year;

        $search_ids = [];
        foreach ($month_arr as $id => $arr) {
            if ($id == $currunt_month_id) {
                $search_ids[] = $id;
                // break;
            } else {
                $search_ids[] = $id;
            }
        }

        $fees_join = "";
        $paid_other_join = "";

        foreach ($search_ids as $id => $val) {
            if ($id == 0) {
                $fees_join .= " AND (";
                $paid_other_join .= " AND (";
            }
            if (count($search_ids) == ($id + 1)) {
                $fees_join .= "fc.term_id = $val)";
                $paid_other_join .= "fpo.month_id = $val)";
            } else {
                $fees_join .= "fc.term_id = $val OR ";
                $paid_other_join .= "fpo.month_id = $val OR ";
            }
        }

        // TODO: Change this query
             $sql = "
            SELECT SUM(amount) amount,term_id
       FROM(
            select SUM(fc.amount)+SUM(fc.fees_discount) amount,fc.term_id
                FROM tblstudent s
                INNER JOIN fees_collect fc ON(fc.student_id = s.id AND fc.is_deleted = 'N' AND fc.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND
                         fc.syear = '" . session()->get('syear') . "' $fees_join )
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND s.id = $student_id
                GROUP BY s.id,fc.term_id
                UNION ALL
                select SUM(fpo.actual_amountpaid)+SUM(fpo.fees_discount) aa,fpo.month_id
                FROM tblstudent s
                INNER JOIN fees_paid_other fpo ON
                    (fpo.student_id = s.id  AND fpo.syear='" . session()->get('syear') . "' $paid_other_join)
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND s.id = $student_id
                GROUP BY s.id,fpo.month_id
            ) temp_table
            GROUP BY term_id";

        $sql = preg_replace('/\n+/', '', $sql);
        $paid_result = DB::select($sql);

        $fees_paid_arr = [];
        foreach ($paid_result as $id => $arr) {
            $fees_paid_arr[$arr->term_id] = $arr->amount;
        }

        $reg_bk_off = FeeBreackoff($stu_arr, $request->standard);
        $reg_bk_off_count = is_array($reg_bk_off) ? count($reg_bk_off) : $reg_bk_off->count();
        if (count($reg_bk_off) == 0) {
            return [];
        }
        $other_bk_off = OtherBreackOff($stu_arr, $search_ids);

        $other_bk_off_month_wise = OtherBreackOfMonth($stu_arr);

        $year_arr = FeeMonthId();

        $reg_bk_month_wise = [];
        foreach ($reg_bk_off as $id => $arr) {
            $reg_bk_month_wise[$arr->month_id] = $arr->bkoff;
        }

        $new_month_arr = [];
        foreach ($reg_bk_month_wise as $month_id => $val) {
            $new_month_arr[$month_id] = $month_arr[$month_id];
        }
        foreach ($other_bk_off_month_wise as $month_id => $val) {
            $new_month_arr[$month_id] = $month_arr[$month_id];
        }

        $merge_bk_month_wise = [];
        foreach ($reg_bk_month_wise as $month_id => $amount) {
            $merge_bk_month_wise[$month_id] = $amount;
            foreach ($other_bk_off_month_wise as $MonthId => $amt) {
                if ($month_id == $MonthId) {
                    $merge_bk_month_wise[$month_id] += $amt;
                }
            }
        }

        $left_bk_table = [];
        $i = 1;
        $fees_total = 0;
        $paid_total = 0;
        $remain_total = 0;

        foreach ($merge_bk_month_wise as $id => $val) {
            $left_bk_table[$i]['month'] = $year_arr[$id];
            $left_bk_table[$i]['month_id'] = $id;
            $left_bk_table[$i]['bk'] = $val;
            if (isset($fees_paid_arr[$id])) {
                $left_bk_table[$i]['paid'] = $fees_paid_arr[$id];
            } else {
                $left_bk_table[$i]['paid'] = 0;
            }
            $left_bk_table[$i]['remain'] = $left_bk_table[$i]['bk'] - $left_bk_table[$i]['paid'];

            $fees_total = $fees_total + $left_bk_table[$i]['bk'];
            $paid_total = $paid_total + $left_bk_table[$i]['paid'];
            $remain_total = $remain_total + $left_bk_table[$i]['remain'];
            $i = $i + 1;
        }
        $left_bk_table[$i]['month'] = "Total";
        $left_bk_table[$i]['month_id'] = "-";
        $left_bk_table[$i]['bk'] = $fees_total;
        $left_bk_table[$i]['paid'] = $paid_total;
        $left_bk_table[$i]['remain'] = $remain_total;

        $pending_fees = 0;
        foreach ($search_ids as $id => $val) {
            foreach ($left_bk_table as $temp_id => $arr) {
                if ($arr['month_id'] == $val) {
                    $pending_fees = $pending_fees + $arr['remain'];
                }
            }
        }

        // Start Getting previous year imprest balance for The Millennium School Surat

        $syear = session()->get('syear');
        $prviouse_syear = $syear - 1;

        $get_imprest_sql = DB::table('fees_breakoff_other as fb')
            ->join('fees_title as ft', function ($join) {
                $join->whereRaw("ft.fees_title = fb.fee_type_id AND ft.sub_institute_id = fb.sub_institute_id
                    AND ft.syear = '" . session()->get('syear') . "'");
            })
            ->selectRaw("fb.id,fb.student_id,fb.sub_institute_id,IFNULL(fb.amount,0) as previous_imprest_amt,fb.syear,
                ft.fees_title,ft.display_name ")
            ->where('fb.sub_institute_id', session()->get('sub_institute_id'))
            ->where('fb.syear', $prviouse_syear)
            ->where('ft.display_name', 'LIKE', '%Imprest%')
            ->where('fb.student_id', $reg_bk_off[0]->student_id)
            ->orderBy('ft.sort_order', 'ASC')->get()->toArray();

        $get_imprest_balance = json_decode(json_encode($get_imprest_sql), true);

        if (count($get_imprest_balance) > 0) {
            $previous_year_imprest_balance = $get_imprest_balance[0]['previous_imprest_amt'];
        } else {
            $previous_year_imprest_balance = 0;
        }

        // End Getting previous year imprest balance for The Millennium School Surat

        $stu_detail = [
            "student_id"                    => $reg_bk_off[0]->student_id,
            "enrollment"                    => $reg_bk_off[0]->enrollment_no,
            "name"                          => $reg_bk_off[0]->first_name." ".$reg_bk_off[0]->middle_name." ".$reg_bk_off[0]->last_name,
            "stddiv"                        => $reg_bk_off[0]->standard_name."/".$reg_bk_off[0]->division_name,
            "admission"                     => $reg_bk_off[0]->admission_year,
            "email"                         => $reg_bk_off[0]->email,
            "pending"                       => $pending_fees,
            "mobile"                        => $reg_bk_off[0]->mobile,
            "uniqueid"                      => $reg_bk_off[0]->uniqueid,
            "std_id"                        => $reg_bk_off[0]->standard_id,
            "grade_id"                      => $reg_bk_off[0]->grade_id,
            "div_id"                        => $reg_bk_off[0]->section_id,
            "student_quota"                 => $reg_bk_off[0]->stu_quota,
            "previous_year_imprest_balance" => $previous_year_imprest_balance,
        ];

        $head_wise_fees = FeeBreakoffHeadWise($stu_arr);

        $till_now_breckoff = [];
        foreach ($search_ids as $id => $val) {
            foreach ($head_wise_fees as $temp_id => $arr) {
                foreach ($head_wise_fees[$temp_id]['breakoff'] as $month_id => $fees_detail) {
                    if ($month_id == $val) {
                        $till_now_breckoff[$month_id] = $fees_detail;
                    }
                }
            }
        }

        $reg_bk_month_wise = [];
        $reg_month_wise = array();
        $final_bk_name = [];
        $total = 0;

        foreach ($till_now_breckoff as $month_id => $fees_detail) {
            foreach ($fees_detail as $head_name => $arr) {
                if (! isset($reg_bk_month_wise[$arr['title']])) {
                    $reg_bk_month_wise[$arr['title']] = 0;
                    $reg_month_wise[$arr['title']] = [
                        'title' => $arr['title'],
                        'amount' => 0,
                        'mandatory' => $arr['mandatory'],
                    ];
                }
                if (isset($arr['amount'])) {
                    $reg_bk_month_wise[$arr['title']] += $arr['amount'];
                    $reg_month_wise[$arr['title']] = [
                        'title' => $arr['title'],
                        'amount' => $reg_bk_month_wise[$arr['title']],
                        'mandatory' => $arr['mandatory'],
                    ];
                }
                $final_bk_name[$arr['title']] = $head_name;
            }
        }
        // echo "<pre>";print_r($final_bk_name);exit();

        $full_bk = array_merge($reg_bk_month_wise, $other_bk_off);
        $full_bk_new = array_merge($reg_month_wise, $other_bk_off);
//echo "<pre>";
//print_r($full_bk);
//print_r($full_bk_new);
//die();
        //24-04-2021 START Check Cheque Return charges

        $get_cheque_return_amt = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
        $cheque_return_charges = $get_cheque_return_amt[0]['cheque_return_charges'];

        $cheque_return_exist_RET = DB::table('fees_collect as fc')
            ->join('fees_cancel as f', function ($join) {
                $join->whereRaw('f.reciept_id = fc.receipt_no AND f.student_id = fc.student_id
                    AND f.sub_institute_id = fc.sub_institute_id AND f.syear = fc.syear');
            })
            ->selectRaw("fc.id,fc.student_id,fc.sub_institute_id,fc.syear,fc.receipt_no,fc.is_deleted,
                f.id AS fees_cancel_id,f.cancel_type,f.cancel_remark,f.cancel_date,f.received_date")
            ->where('fc.syear', $syear)
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.student_id', $stu_detail['student_id'])
            ->where('is_deleted', '=', 'Y')
            ->where('f.cancel_type', '=', 'Cheque Return')
            ->orderBy('fc.id', 'DESC')->limit(1)->get()->toArray();
        $cheque_return_exist = count($cheque_return_exist_RET);

        // 06/01/2022 SQL for checking if cheque return charges already paid
        $check_paid_cheque_return_charge = DB::table('fees_collect as f')
            ->whereRaw("f.receipt_no > CAST((SELECT fc.reciept_id FROM fees_cancel fc WHERE fc.syear = '" . $syear . "' AND
                fc.sub_institute_id = '" . $sub_institute_id . "' AND fc.student_id = '" . $stu_detail['student_id'] . "' AND
                fc.cancel_type = 'Cheque Return' ORDER BY id DESC LIMIT 0,1) AS INT)")
            ->where('f.syear', $syear)
            ->where('f.sub_institute_id', $sub_institute_id)
            ->where('f.student_id', $stu_detail['student_id'])
            ->where('f.student_id', $stu_detail['student_id'])
            ->where('f.is_deleted', '=', 'N')->get()->toArray();

        if ($cheque_return_charges > 0 && $cheque_return_exist > 0 && count($check_paid_cheque_return_charge) == 0) {
            $cheque_return_charges_new[] = $cheque_return_charges;
        } else {
            $cheque_return_charges_new[] = 0;
        }

        //24-04-2021 END Check Cheque Return charges

        foreach ($full_bk as $id => $val) {
            $total += $val;
        }

        $other_fee_title = OtherBreackOffHead();

        foreach ($other_fee_title as $id => $arr) {
            foreach ($full_bk as $title => $val) {
                if ($title == $arr->display_name) {
                    $final_bk_name[$title] = $arr->other_fee_id;
                }
            }
        }

        $full_bk["Total"] = $total;
        $full_bk_new["Total"] = $total;

        $type = "web";
        $res['total_fees'] = $left_bk_table;
        $res['stu_data'] = $stu_detail;
        $res['month_arr'] = $new_month_arr;
        $res['search_ids'] = $search_ids;
        $res['final_fee'] = $full_bk;
        $res['final_fee_new'] = $full_bk_new;
        $res['cheque_return_charges'] = $cheque_return_charges_new;
        $res['final_fee_name'] = $final_bk_name;
        $res['search_id'] = $search_ids;

        $fees_config = DB::table('fees_config_master as fc')
            ->join('fees_receipt_css as frc', function ($join) {
                $join->whereRaw('frc.receipt_id = fc.fees_receipt_template');
            })->selectRaw("fc.* ,frc.css")
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)->get()->toArray();

        if (count($fees_config) > 0) {
            $receipt_css = $fees_config[0]->css;
            $paper_size = $fees_config[0]->fees_receipt_template;
        } else {
            $fees_config = DB::table('fees_receipt_css')->select('css')
                ->where('receipt_id', 'A5')->get()->toArray();
            $receipt_css = $fees_config[0]->css;
            $paper_size = 'A5';
        }
        $res['receipt_css_data'] = $receipt_css;
        $res['paper_size'] = $paper_size;

        return $res;
    }


        public function showCircular(Request $request)
    {
        $type = $request->input('type');
        $student_ids = $request->input('students');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $month = $request->input('month');
        $receipt_id = $request->input('receipt_id');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');
        $fees_circular_amount = $request->input('fees_circular_amount');
        $fees_circular_remarks = $request->input('fees_circular_remarks');

        $monthArray = explode(",", $month);
        $whereArray = [];
        $whereArray['syear'] = $syear;
        $whereArray['sub_institute_id'] = $sub_institute_id;

        $feesConfig = tblfeesConfigModel::where($whereArray)->get()->toArray();

        if ($grade_id != '') {
            $whereArray['grade_id'] = $grade_id;
        }

        if ($standard_id != '') {
            $whereArray['standard_id'] = $standard_id;
        }


        if ($sub_institute_id == 201 || $sub_institute_id == 202 || $sub_institute_id == 203 || $sub_institute_id == 204) {
            $feesCircularMaster = feesCircularMasterModel::where($whereArray)->get()->toArray();
            if (! isset($feesCircularMaster[0]['id'])) {
                $res['status_code'] = 0;
                $res['message'] = "Please enter fees circular master to view fees circular";

                return is_mobile($type, "fees_circular.index", $res);
            }
        }

        $receiptBook = DB::table('fees_receipt_book_master as f')
            ->join('fees_title as ft', function ($join) {
                $join->whereRaw('ft.id = f.fees_head_id AND ft.sub_institute_id = f.sub_institute_id AND ft.syear = f.syear');
            })->selectRaw('f.*,GROUP_CONCAT(DISTINCT f.fees_head_id) AS fees_head_id,
                GROUP_CONCAT(DISTINCT ft.fees_title) AS fees_title_name,
                GROUP_CONCAT(DISTINCT ft.display_name) AS display_name')
            ->where('f.sub_institute_id', $sub_institute_id)
            ->where('f.syear', $syear)
            ->where('f.receipt_id', $receipt_id)
            ->where(function ($q) use ($grade_id, $standard_id) {
                if ($grade_id != '') {
                    $q->where('f.grade_id', $grade_id);
                }

                if ($standard_id != '') {
                    $q->where('f.standard_id', $standard_id);
                }
            })->get()->toArray();

        $receiptBook = json_decode(json_encode($receiptBook), true);

        $get_fees_title_arr = explode(',', $receiptBook[0]['fees_title_name']);

        if (! isset($receiptBook[0]['receipt_id'])) {
            $res['status_code'] = 0;
            $res['message'] = "Please enter fees receipt book master to view fees circular";

            return is_mobile($type, "fees_circular.index", $res);
        }

        if (! isset($feesConfig[0]['id'])) {
            $res['status_code'] = 0;
            $res['message'] = "Please enter fees config master to view fees circular";

            return is_mobile($type, "fees_circular.index", $res);
        }

        $displayBreakoff = $data = [];
        $all_inserted_id = '';

        if (isset($student_ids)) {
            $data = FeeBreakoffHeadWise($student_ids);
            foreach ($student_ids as $student_key => $student_id) {
                if (isset($data[$student_id]['breakoff'])) {
                    foreach ($data[$student_id]['breakoff'] as $key => $value) {
                        foreach ($value as $fees_title => $fees_title_value) {
                            if (! in_array($fees_title, $get_fees_title_arr)) {
                                unset($data[$student_id]['breakoff'][$key][$fees_title]);
                            }
                        }
                    }
                }
            }
            foreach ($student_ids as $student_key => $student_id) {
                $amountLogs = 0;
                $logs = [];
                $logs['MONTH'] = $month;
                $logs['STUDENT_ID'] = $student_id;
                $logs['CREATED_BY'] = $request->session()->get('user_id');
                $logs['SYEAR'] = $syear;
                $logs['SUB_INSTITUTE_ID'] = $sub_institute_id;
                $logs['RECEIPT_BOOK_ID'] = $receiptBook[0]['receipt_id'];

                $display_months = array();
                if (isset($data[$student_id]['breakoff'])) {
                    foreach ($data[$student_id]['breakoff'] as $key => $value) {
                        $display_months[] = $key;
                        if (in_array($key, $monthArray)) {
                            foreach ($value as $head => $valueArray) {
                                $amountLogs += $valueArray['amount'];

                                if (isset($displayBreakoff[$student_id][$valueArray['title']])) {
                                    $displayBreakoff[$student_id][$valueArray['title']] = $valueArray['amount'] + $displayBreakoff[$student_id][$valueArray['title']];
                                } else {
                                    $displayBreakoff[$student_id][$valueArray['title']] = $valueArray['amount'];
                                }
                            }
                        }
                    }
                }

                $months = [
                    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                    9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
                ];

                $dis_month = '';
                foreach ($display_months as $k => $m) {
                    $y = $m / 10000;
                    $month = (int) $y;
                    $year = substr($m, -4);
                    $dis_month .= $months[$month].",";
                }

                $display_month_name = rtrim($dis_month, ',');

                $logs['AMOUNT'] = $amountLogs;
                feesCircularModel::insert($logs);
                $last_inserted_ids = DB::getPdo()->lastInsertId();

                $all_inserted_id .= $last_inserted_ids.',';
            }
            $inserted_ids = rtrim($all_inserted_id, ',');

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
            $res['breakoff'] = $displayBreakoff;
            $res['last_inserted_ids'] = $inserted_ids;

            if (count($feesConfig) > 0) {
                $res['feesconfig'] = $feesConfig[0];
            }
            if (count($receiptBook) > 0) {
                $res['receiptbook'] = $receiptBook[0];
            }

            if ($sub_institute_id == 201 || $sub_institute_id == 202 || $sub_institute_id == 203 || $sub_institute_id == 204) {
                $hillsterm = session()->get('term_id');
                if (count($feesCircularMaster) > 0) {
                    $res['feesCircularMaster'] = $feesCircularMaster[0];
                    if($hillsterm=='146' ||$hillsterm=='144' ){
                        $res['display_month_name'] = 'First Term';
                    }elseif($hillsterm=='147' || $hillsterm=='145'){
                        $res['display_month_name'] = 'Second Term';
                    }elseif($hillsterm=='148' || $hillsterm=='149'){
                        $res['display_month_name'] = 'Third Term';
                    }//$display_month_name;
                    $res['fees_circular_amount'] = $fees_circular_amount;
                    $res['fees_circular_remarks'] = $fees_circular_remarks;
                    // $res['term'] = session()->get('Term');

                }
                // echo "<pre>";print_r($res);exit;
                return is_mobile($type, "fees/fees_circular/show_circular_hills", $res, "view");
            } else {

                return is_mobile($type, "fees/fees_circular/show_circular", $res, "view");
            }
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Please select one student for display fees circular.";

            return is_mobile($type, "fees_circular.index", $res);
        }

    }
}
