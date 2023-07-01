<?php

namespace App\Http\Controllers\fees\fees_collect;

use App\Http\Controllers\Controller;
use App\Models\fees\bank_master\bankmasterModel;
use App\Models\fees\map_year\map_year;
use App\Models\fees\tblfeesConfigModel;
use App\Models\school_setup\SchoolModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\FeeBreackoff;
use function App\Helpers\FeeBreackofflast;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeBreakoffHeadWiselast;
use function App\Helpers\FeeMonthId;
use function App\Helpers\FeeMonthIdlast;
use function App\Helpers\is_mobile;
use function App\Helpers\get_string;
use function App\Helpers\OtherBreackOff;
use function App\Helpers\OtherBreackOfflast;
use function App\Helpers\OtherBreackOffHead;
use function App\Helpers\OtherBreackOffHeadlast;
use function App\Helpers\OtherBreackOfMonth;
use function App\Helpers\OtherBreackOfMonthlast;
use function App\Helpers\OtherBreackOfMonthHead;
use function App\Helpers\OtherBreackOfMonthHeadlast;
use function Illuminate\Session\expired;

class fees_collect_controller extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function index(Request $request)
    {

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = [];
        $type = $request->input('type');
        return is_mobile($type, "fees/fees_collect/show", $school_data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */

    public function show_student()
    {
        $responce_arr = [];
        $type = $_REQUEST['type'] ?? "";
        $last_year = (session()->get('syear') - 1);
        $month_arr = FeeMonthId();
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

        $breackoff_join = "";
        $breackoff_other_join = "";
        $fees_join = "";
        $paid_other_join = "";

        foreach ($search_ids as $id => $val) {
            if ($id == 0) {
                $breackoff_join .= " AND (";
                $breackoff_other_join .= " AND (";
                $fees_join .= " AND (";
                $paid_other_join .= " AND (";
            }
            if (count($search_ids) == ($id + 1)) {
                $breackoff_join .= "fb.month_id = $val)";
                $breackoff_other_join .= "fbo.month_id = $val)";
                $fees_join .= "fc.term_id = $val)";
                $paid_other_join .= "fpo.month_id = $val)";
            } else {
                $breackoff_join .= "fb.month_id = $val OR ";
                $breackoff_other_join .= "fbo.month_id = $val OR ";
                $fees_join .= "fc.term_id = $val OR ";
                $paid_other_join .= "fpo.month_id = $val OR ";
            }
        }

        $extra_where = "";
        if (isset($_REQUEST['mobile']) && $_REQUEST['mobile'] != '') {
            $responce_arr['mobile'] = $_REQUEST['mobile'];
        }
        if (isset($_REQUEST['grno']) && $_REQUEST['grno'] != '') {
            $responce_arr['grno'] = $_REQUEST['grno'];
        }
        if (isset($_REQUEST['uniqueid']) && $_REQUEST['uniqueid'] != '') {
            $responce_arr['uniqueid'] = $_REQUEST['uniqueid'];
        }
        if (isset($_REQUEST['grade']) && $_REQUEST['grade'] != '') {
            $grade_val = $_REQUEST['grade'];
            $responce_arr['grade'] = $_REQUEST['grade'];

        }
        if (isset($_REQUEST['standard']) && $_REQUEST['standard'] != '') {
            $responce_arr['standard'] = $_REQUEST['standard'];
        }
        if (isset($_REQUEST['division']) && $_REQUEST['division'] != '') {
            $responce_arr['division'] = $_REQUEST['division'];
        }
        if (isset($_REQUEST['stu_name']) && $_REQUEST['stu_name'] != '') {
            $responce_arr['stu_name'] = $_REQUEST['stu_name'];
        }

        $request = $_REQUEST;
        // DB::enableQueryLog();
        $result = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id');
            })->join('academic_section as g', function ($join) {
                $join->whereRaw('g.id = se.grade_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = se.standard_id');
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id');
            })->leftJoin('student_quota as sq', function ($join) {
                $join->whereRaw('sq.id = se.student_quota AND sq.sub_institute_id = se.sub_institute_id');
            })->join('fees_breackoff as fb', function ($join) use ($breackoff_join, $last_year) {
                $join->whereRaw("(fb.syear = '" . session()->get('syear') . "' AND
                 fb.admission_year = s.admission_year AND fb.quota = se.student_quota AND fb.grade_id = se.grade_id AND
                 fb.standard_id = se.standard_id AND fb.sub_institute_id = '" . session()->get('sub_institute_id') . "' $breackoff_join)");
            })->selectRaw("s.*,se.syear,se.student_id,se.grade_id,
                se.standard_id,se.section_id,se.student_quota,sq.title AS stu_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number,
                sum(fb.amount) + (select ifnull(sum(fbo.amount),0) from fees_breakoff_other fbo
                 where fbo.syear = '" . session()->get('syear') . "' AND fbo.student_id = s.id AND fb.grade_id = se.grade_id AND
                 fb.standard_id = se.standard_id AND fbo.sub_institute_id = '" . session()->get('sub_institute_id') . "' $breackoff_other_join)
                bkoff, st.name standard_name, d.name as division_name")
            ->where('s.sub_institute_id', session()->get('sub_institute_id'))
            ->where('se.syear', session()->get('syear'))
            ->whereNotNull('s.admission_date')
            ->whereNull('se.end_date')
            ->where(function ($q) use ($request) {
                if (isset($request['mobile']) && $request['mobile'] != '') {
                    $q->where('s.mobile', $request['mobile']);
                }
                if (isset($request['grno']) && $request['grno'] != '') {
                    $q->where('s.enrollment_no', $request['grno']);
                }
                if (isset($request['uniqueid']) && $request['uniqueid'] != '') {
                    $q->where('s.uniqueid', $request['uniqueid']);
                }
                if (isset($request['grade']) && $request['grade'] != '') {
                    $q->where('se.grade_id', $request['grade']);
                }
                if (isset($request['standard']) && $request['standard'] != '') {
                    $q->where('se.standard_id', $request['standard']);
                }
                if (isset($request['division']) && $request['division'] != '') {
                    $q->where('se.section_id', $request['division']);
                }
                if (isset($request['stu_name']) && $request['stu_name'] != '') {
                    $q->where(function ($query) use ($request) {
                        $query->where('s.first_name', 'like', '%' . $request['stu_name'] . '%')
                            ->orWhere('s.middle_name', 'like', '%' . $request['stu_name'] . '%')
                            ->orWhere('s.last_name', 'like', '%' . $request['stu_name'] . '%');
                    });
                }
            })->groupBy('s.id')->havingNotNull('bkoff')->get()->toArray();
// dd(DB::getQueryLog($result));

        $paid_result = DB::table(function ($query) use ($fees_join, $paid_other_join) {
            $query->select(DB::raw('SUM(amount) as paid_amt, student_id as id'))
                ->from(function ($subquery) use ($fees_join, $paid_other_join) {
                    $subquery->select(
                        DB::raw('SUM(fc.amount) + SUM(fc.fees_discount) as amount, se.student_id')
                    )
                        ->from('tblstudent as s')
                        ->join('tblstudent_enrollment as se', function ($join) {
                            $join->on('se.student_id', '=', 's.id')
                                ->where('se.syear', session()->get('syear'));
                        })
                        ->join('academic_section as g', 'g.id', '=', 'se.grade_id')
                        ->join('standard as st', 'st.id', '=', 'se.standard_id')
                        ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
                        ->join('fees_collect as fc', function ($join) use ($fees_join) {
                            $join->on('fc.student_id', '=', 's.id')
                                ->where('fc.is_deleted', 'N')
                                ->where('fc.sub_institute_id', session()->get('sub_institute_id'))
                                ->whereRaw('fc.syear = ' . session()->get('syear') . ' ' . $fees_join);
                        })
                        ->where('s.sub_institute_id', session()->get('sub_institute_id'))
                        ->groupBy('s.id');

                    if ($paid_other_join) {
                        $subquery->unionAll(function ($union) use ($paid_other_join) {
                            $union->select(
                                DB::raw('SUM(fpo.actual_amountpaid) + SUM(fpo.fees_discount) as aa, se.student_id')
                            )
                                ->from('tblstudent as s')
                                ->join('tblstudent_enrollment as se', function ($join) {
                                    $join->on('se.student_id', '=', 's.id')
                                        ->where('se.syear', session()->get('syear'));
                                })
                                ->join('academic_section as g', 'g.id', '=', 'se.grade_id')
                                ->join('standard as st', 'st.id', '=', 'se.standard_id')
                                ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
                                ->join('fees_paid_other as fpo', function ($join) use ($paid_other_join) {
                                    $join->on('fpo.student_id', '=', 's.id');
                                    $join->whereRaw('1=1' . $paid_other_join);
                                })
                                ->where('s.sub_institute_id', session()->get('sub_institute_id'))
                                ->groupBy('s.id');
                        });
                    }
                }, 'temp_table')
                ->groupBy('student_id');
        })->get();

        // return $result;exit;
        foreach ($result as $id => $arr) {
            $bk_stu_id = $arr->id;
            foreach ($paid_result as $r_id => $r_arr) {
                $pd_stu_id = $r_arr->id;
            if ($bk_stu_id == $pd_stu_id) {
                if($r_arr->paid_amt > $arr->bkoff){
                    $arr->bkoff = 0;
                }else{
                    $arr->bkoff = abs($arr->bkoff - $r_arr->paid_amt);
                }
                }
            }
        }
        // fees validation admission year,student quota,division,fees_breakoff
        if (empty($result)) {
            $check = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id');
                })
                ->where('s.sub_institute_id', session()->get('sub_institute_id'))
                ->where('se.syear', session()->get('syear'))
                ->whereNotNull('s.admission_date')
                ->whereNull('se.end_date')
                ->where(function ($q) use ($request) {
                    if (isset($request['mobile']) && $request['mobile'] != '') {
                        $q->where('s.mobile', $request['mobile']);
                    }
                    if (isset($request['grno']) && $request['grno'] != '') {
                        $q->where('s.enrollment_no', $request['grno']);
                    }
                    if (isset($request['uniqueid']) && $request['uniqueid'] != '') {
                        $q->where('s.uniqueid', $request['uniqueid']);
                    }
                    if (isset($request['grade']) && $request['grade'] != '') {
                        $q->where('se.grade_id', $request['grade']);
                    }
                    if (isset($request['standard']) && $request['standard'] != '') {
                        $q->where('se.standard_id', $request['standard']);
                    }
                    if (isset($request['division']) && $request['division'] != '') {
                        $q->where('se.section_id', $request['division']);
                    }
                    if (isset($request['stu_name']) && $request['stu_name'] != '') {
                        $q->where(function ($query) use ($request) {
                            $query->where('s.first_name', 'like', '%' . $request['stu_name'] . '%')
                                ->orWhere('s.middle_name', 'like', '%' . $request['stu_name'] . '%')
                                ->orWhere('s.last_name', 'like', '%' . $request['stu_name'] . '%');
                        });
                    }
                })->groupBy('s.id')->get()->toArray();
        // return $check;exit;
            if (!empty($check)) {
                if ($check[0]->section_id == null || $check[0]->section_id == 0) {
                    $responce_arr['status_code'] = 0;
                    $responce_arr['message'] = "Devision Not Found";
                } elseif ($check[0]->student_quota == null || $check[0]->student_quota == 0) {
                    $responce_arr['status_code'] = 0;
                    $responce_arr['message'] = "Student Quota Not Found";
                } elseif ($check[0]->admission_year == null || $check[0]->admission_year == 0) {
                    $responce_arr['status_code'] = 0;
                    $responce_arr['message'] = "Admission Year Not Found";
                } else {
                    $responce_arr['status_code'] = 0;
                    $responce_arr['message'] = "Fees Breakoff Not Found";
                }
            } else {
                $responce_arr['status_code'] = 0;
                $responce_arr['message'] = "Student Details Not Found";
            }
        }
        // return $result;exit;
        $responce_arr['stu_data'] = $result;


        return is_mobile($type, "fees/fees_collect/show", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return array
     */
    public function pay_fees(Request $request)
    {

        $fees_data = [];
        foreach ($_REQUEST['fees_data'] as $id => $arr) {
            if ($arr != 0) {
                $fees_data[$id] = $arr;
            }
        }
        $_REQUEST['fees_data'] = $fees_data;

        $stu_arr = session()->get('stu_arr');
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
        $reg_bk_off = FeeBreackoff($stu_arr);

        $other_bk_off = OtherBreackOff($stu_arr, $search_ids);
        $other_bk_off_month_wise = OtherBreackOfMonth($stu_arr);
        $other_bk_off_month_head_wise = OtherBreackOfMonthHead($stu_arr, $search_ids);
        $year_arr = FeeMonthId();
        $head_wise_fees = FeeBreakoffHeadWise($stu_arr);

        $reg_fee_heads = [];
        $reg_fee_bk = [];

        foreach ($head_wise_fees as $student_id => $detail_arr) {
            $reg_fee_bk = $detail_arr['breakoff'];
            foreach ($detail_arr['breakoff'] as $id => $arr) {
                foreach ($arr as $head_name => $vals) {
                    if (!in_array($head_name, $reg_fee_heads)) {
                        $reg_fee_heads[] = $head_name;
                    }
                }
            }
        }
 
        $other_fee_heads = [];
        foreach ($_REQUEST['fees_data'] as $id => $vals) {
            if (!in_array($id, $reg_fee_heads)) {
                $other_fee_heads[] = $id;
            }
        }

        //getting reg fee month_id that we need to pay
        $reg_months_pay = [];
        foreach ($reg_fee_bk as $month_id => $arr) {
            if (in_array($month_id, $_REQUEST['months'])) {
                $reg_months_pay[] = $month_id;
            }
        }

        $oth_months_pay = [];
        foreach ($other_bk_off_month_wise as $month_id => $arr) {
            if (in_array($month_id, $_REQUEST['months'])) {
                $oth_months_pay[] = $month_id;
            }
        }

        $reg_insert_arr = [];
        foreach ($reg_fee_bk as $month => $bk_off) {
            if (in_array($month, $reg_months_pay)) {
                foreach ($bk_off as $title => $arr) {
                    if (array_key_exists($title, $_REQUEST['fees_data'])) {
                        $insert_amount = 0;
                        if ($_REQUEST['fees_data'][$title] > $arr['amount']) {
                            $_REQUEST['fees_data'][$title] = $_REQUEST['fees_data'][$title] - $arr['amount'];
                            $insert_amount = $arr['amount'];
                        } else {
                            $insert_amount = $_REQUEST['fees_data'][$title];
                            $_REQUEST['fees_data'][$title] = 0;
                        }
                        $reg_insert_arr[$month][$title] = $insert_amount;
                    }
                }
            }
        }
        // last year fees start
        if (isset($_REQUEST['fees_data']['previous_fees']) && $_REQUEST['fees_data']['previous_fees'] != 0) {
            $other_bk_off2 = OtherBreackOfflast($stu_arr, $search_ids);
            $other_bk_off_month_wise2 = OtherBreackOfMonthlast($stu_arr);
            $other_bk_off_month_head_wise2 = OtherBreackOfMonthHeadlast($stu_arr, $search_ids);
            $year_arr2 = FeeMonthIdlast() ?? [];
            $head_wise_fees2 = FeeBreakoffHeadWiselast($stu_arr);

            $reg_fee_heads2 = [];
            $reg_fee_bk2 = [];

            foreach ($head_wise_fees2 as $student_id => $detail_arr) {
                $reg_fee_bk2 = $detail_arr['breakoff'];
                foreach ($detail_arr['breakoff'] as $id => $arr) {
                    foreach ($arr as $head_name => $vals) {
                        if (!in_array($head_name, $reg_fee_heads2)) {
                            $reg_fee_heads2[] = $head_name;
                        }
                    }
                }
            }
       
        //getting reg fee month_id that we need to pay
            $syear = session()->get('syear');
            $last_y_month_id = $currunt_month . ($syear - 1);
            $reg_months_pay2 = [];
            foreach ($year_arr2 as $id => $arr) {
                if ($id == $last_y_month_id) {
                    $reg_months_pay2[] = $id;
                // break;
                } else {
                    $reg_months_pay2[] = $id;
                }
            }

            foreach ($reg_fee_bk2 as $month => $bk_off) {
                if (in_array($month, $reg_months_pay2)) {
                    foreach ($bk_off as $title => $arr) {
                        if (array_key_exists($title, $_REQUEST['fees_data'])) {
                            $insert_amount = 0;
                            if ($_REQUEST['fees_data'][$title] < $arr['amount']) {
                                $_REQUEST['fees_data'][$title] = $_REQUEST['fees_data'][$title] - $arr['amount'];
                                $insert_amount = $arr['amount'];
                            } else {
                                $insert_amount = $_REQUEST['fees_data'][$title];
                                $_REQUEST['fees_data'][$title] = 0;
                            }
                            if ($insert_amount != 0) {
                                $reg_insert_arr[$month][$title] = $insert_amount;
                            }
                        }
                    }
                }
            }
            $reg_insert_arr2 = [];
        }
        // last year fees end
        $receipt_number = $this->gunrate_receipt_number();
        // getting all heads with id
        $ret_heds_with_id = DB::table('fees_title')->selectRaw('id,fees_title')
            ->where('SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))
            ->orderBy('sort_order')->get()->toArray();
        $heds_with_id = [];
        foreach ($ret_heds_with_id as $id => $arr) {
            $heds_with_id[$arr->fees_title] = $arr->id;
        }
        $new_insert_arr = [];
        foreach ($reg_insert_arr as $month_id => $arr) {
            foreach ($arr as $id => $val) {
                $head_id = $heds_with_id[$id];
                foreach ($receipt_number as $temp_id => $arr_head_rid) {
                    $heds = explode(',', $arr_head_rid['heds']);
                    if (in_array($head_id, $heds)) {
                        $receipt_number[$temp_id]['used'] = 1;
                        $new_insert_arr[$month_id][$arr_head_rid['rid'] . '_' . $temp_id][$id] = $val;
                    }
                }
            }
        }

        $oth_insert_arr = [];
        foreach ($other_bk_off_month_head_wise as $month => $bk_off) {
            if (in_array($month, $oth_months_pay)) {
                foreach ($bk_off as $title => $amount) {
                    if (array_key_exists($title, $_REQUEST['fees_data'])) {
                        $insert_amount = 0;
                        if ($_REQUEST['fees_data'][$title] > $amount) {
                            $_REQUEST['fees_data'][$title] = $_REQUEST['fees_data'][$title] - $amount;
                            $insert_amount = $amount;
                        } else {
                            $insert_amount = $_REQUEST['fees_data'][$title];
                            $_REQUEST['fees_data'][$title] = 0;
                        }
                        $oth_insert_arr[$month][$title] = $insert_amount;
                    }
                }
            }
        }

        $new_insert_other_arr = [];
        foreach ($oth_insert_arr as $month_id => $arr) {
            foreach ($arr as $id => $val) {
                $head_id = $heds_with_id[$id];
                foreach ($receipt_number as $temp_id => $arr_head_rid) {
                    $heds = explode(',', $arr_head_rid['heds']);
                    if (in_array($head_id, $heds)) {
                        $receipt_number[$temp_id]['used'] = 1;
                        $new_insert_other_arr[$month_id][$arr_head_rid['rid'] . '_' . $temp_id][$id] = $val;
                    }
                }
            }
        }

        $new_insert_arr = $this->add_discount($new_insert_arr, 'fees_collect');
        $new_insert_other_arr = $this->add_discount($new_insert_other_arr, 'fees_paid_other');
        $new_insert_arr = $this->add_fine($new_insert_arr);
        $new_insert_other_arr = $this->add_fine($new_insert_other_arr);
        $standard_ids = $syears = [];
        foreach ($new_insert_arr as $key => $val) {
            if (array_key_exists($key, $year_arr)) {
                $standard_ids[$key] = $_REQUEST['standard_id'];
                $syears[$key] = session()->get('syear');
            }
            if (isset($year_arr2) && array_key_exists($key, $year_arr2)) {
                // $standard_ids
                $standard_ids[$key] = ($_REQUEST['standard_id'] - 1);
                $syears[$key] = (session()->get('syear') - 1);
            }
        }


        foreach ($new_insert_arr as $month_id => $arr) {
            foreach ($arr as $r_id => $vals) {
                if (isset($vals['fine'])) {
                    $amount = $vals['amount'];
                    $fine = $vals['fine'];
                    $totalAmount = $amount + $fine;
                    $vals['amount'] = $totalAmount;
                }

                if (isset($_REQUEST['cheque_date']) && $_REQUEST['cheque_date'] != '') {
                    $cheque_date = $_REQUEST['cheque_date'];
                } else {
                    $cheque_date = $_REQUEST['receiptdate'];
                }

                if (isset($_REQUEST['remarks']) && $_REQUEST['remarks'] != '') {
                    $remarks = $_REQUEST['remarks'];
                } else {
                    $remarks = '';
                }

                $receipt_id_arr = explode('_', $r_id);
                $receipt_id = $receipt_id_arr[0];

                $insert_arr = [
                    'student_id' => $stu_arr[0],
                    'standard_id' => $standard_ids[$month_id] ?? null,
                    'term_id' => $month_id,
                    'syear' => $syears[$month_id],
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'payment_mode' => $_REQUEST['PAYMENT_MODE'],
                    'created_date' => date('Y-m-d h:i:s'),
                    'bank_branch' => $_REQUEST['bank_branch'],
                    'receiptdate' => $_REQUEST['receiptdate'],
                    'cheque_no' => $_REQUEST['cheque_no'],
                    'cheque_date' => $cheque_date,
                    'cheque_bank_name' => $_REQUEST['bank_name'],
                    'receipt_no' => $receipt_id,
                    'remarks' => $remarks,
                    'created_by' => session()->get('user_id'),
                ];

                $insert_arr = array_merge($insert_arr, $vals);
                $insert_id = DB::table('fees_collect')->insertGetId($insert_arr);
                $regular_insert_arr[] = $insert_id;

            }
        }
        
        $other_insert_arr = [];
        foreach ($new_insert_other_arr as $month_id => $arr) {
            foreach ($arr as $r_id => $vals) {

                if (isset($vals['fine'])) {
                    $amount = $vals['amount'];
                    $fine = $vals['fine'];
                    $totalAmount = $amount + $fine;
                    $vals['amount'] = $totalAmount;
                }

                if (isset($_REQUEST['cheque_date']) && $_REQUEST['cheque_date'] != '') {
                    $cheque_date = $_REQUEST['cheque_date'];
                } else {
                    $cheque_date = $_REQUEST['receiptdate'];
                }

                if (isset($_REQUEST['remarks']) && $_REQUEST['remarks'] != '') {
                    $remarks = $_REQUEST['remarks'];
                } else {
                    $remarks = '';
                }

                $receipt_id_arr = explode('_', $r_id);
                $receipt_id = $receipt_id_arr[0];
                $insert_arr = [
                    'student_id' => $stu_arr[0],
                    'month_id' => $month_id,
                    'syear' => isset($syear) ? $syears[$month_id] : session()->get('syear'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'payment_mode' => $_REQUEST['PAYMENT_MODE'],
                    'created_date' => date('Y-m-d h:i:s'),
                    'bank_branch' => $_REQUEST['bank_branch'],
                    'receiptdate' => $_REQUEST['receiptdate'],
                    'cheque_dd_no' => $_REQUEST['cheque_no'],
                    'cheque_dd_date' => $cheque_date,
                    'bank_name' => $_REQUEST['bank_name'],
                    'reciept_id' => $receipt_id,
                    'remarks' => $remarks,
                    'created_by' => session()->get('user_id'),
                ];

                $insert_arr += $vals;

                $insert_id = DB::table('fees_paid_other')->insertGetId($insert_arr);
                $other_insert_arr[] = $insert_id;
            }
        }

        //getting array ready for insert into fees receipt
        $fees_receipt_insert = [];
        foreach ($receipt_number as $id => $arr) {
            if (isset($arr['used'])) {
                $fees_receipt_insert['RECEIPT_ID_' . $id] = $arr['rid'];
            }
        }
        $fees_receipt_insert['FEES_ID'] = implode(',', $regular_insert_arr);
        $fees_receipt_insert['OTHER_FEES_ID'] = implode(',', $other_insert_arr);
        $fees_receipt_insert['SYEAR'] = session()->get('syear');
        $fees_receipt_insert['SUB_INSTITUTE_ID'] = session()->get('sub_institute_id');
        $fees_receipt_insert['STANDARD'] = $_REQUEST['standard_id'];
        $fees_receipt_insert['CREATED_ON'] = date('Y-m-d');
        $insert_id = DB::table('fees_receipt')->insertGetId($fees_receipt_insert);
        $receipt_html = $this->gunrate_receipt($insert_id, $receipt_number, $heds_with_id);

        $receipt_id_html = '';
        foreach ($receipt_number as $s_order => $val_number) {
            if (isset($val_number['used'])) {
                $receipt_id_html = $val_number['rid'];
            }
        }

        $sub_institute_id = session()->get('sub_institute_id');

        $fees_config = DB::table('fees_config_master as fc')
            ->join('fees_receipt_css as frc', 'frc.receipt_id', '=', 'fc.fees_receipt_template')
            ->selectRaw('fc.* ,frc.css')->where('fc.sub_institute_id', $sub_institute_id)->get()->toArray();

        $res = [];
        if (count($fees_config)) {

            $receipt_html_with_css = '<style>' . $fees_config[0]->css . '</style>' . $receipt_html;

            $res = [
                "data" => $receipt_html_with_css,
                "paper" => $fees_config[0]->fees_receipt_template,
                "css" => $fees_config[0]->css,
                "student_id" => $stu_arr[0],
                "receipt_id_html" => $receipt_id_html,
            ];
        } else {
            $fees_config = DB::table('fees_receipt_css')->select('css')
                ->where('frc.receipt_id', 'A5')->get()->toArray();

            $receipt_html_with_css = '<style>' . $fees_config[0]->css . '</style>' . $receipt_html;

            $res = [
                "data" => $receipt_html_with_css,
                "paper" => "A5",
                "css" => $fees_config[0]->css,
                "student_id" => $stu_arr[0],
                "receipt_id_html" => $receipt_id_html,
            ];
        }

        return $res;
    }

    public function store(Request $request)
    {

        $res = $this->pay_fees($request);
        // return $res;exit;
        $res['standard_id'] = $request->standard_id;
        $type = $request->input('type');

        return is_mobile($type, "fees/fees_collect/receipt_view", $res, "view");
    }

    public function add_discount($fees_arr, $insert_table)
    {
        $discount_field = "";
        $total_field = "";
        if ($insert_table == "fees_collect") {
            $discount_field = "fees_discount";
            $total_field = "amount";
        } else {
            $discount_field = "fees_discount";
            $total_field = "actual_amountpaid";
        }

        foreach ($fees_arr as $month_id => $detail_arr) {
            foreach ($detail_arr as $receipt_id => $arr) {
                $sum = array_sum($arr);
                if ($sum == 0) {
                    unset($fees_arr[$month_id][$receipt_id]);
                }
            }
            if (count($fees_arr[$month_id]) == 0) {
                unset($fees_arr[$month_id]);
            }
        }

        /** START If Total Discount is there unset regular discount added on 16th Jun **/
        if (isset($_REQUEST['discount_data']) && isset($_REQUEST['totalDis']) && array_sum($_REQUEST['discount_data']) < $_REQUEST['totalDis']) {
            unset($_REQUEST['discount_data']);
        } else {
            unset($_REQUEST['totalDis']);
        }
        /** END If Total Discount is there unset regular discount added on 16th Jun **/

        foreach ($fees_arr as $month_id => $detail_arr) {
            foreach ($detail_arr as $receipt_id => $arr) {
                $fees_arr[$month_id][$receipt_id][$discount_field] = 0;
                foreach ($arr as $title => $val) {
                    if (isset($_REQUEST['discount_data'][$title])) {
                        $dis = 0;

                        if ($val > $_REQUEST['discount_data'][$title] || $val == $_REQUEST['discount_data'][$title]) {
                            $dis = $_REQUEST['discount_data'][$title];
                            $_REQUEST['discount_data'][$title] = 0;
                            unset($_REQUEST['discount_data'][$title]);
                        } else {
                            // 26/08/2021 Start Added for The Millennium School for Advanced Imprest Collection payment
                            if ($val < 0) {
                                $dis = 0;
                                $_REQUEST['discount_data'][$title] = $_REQUEST['discount_data'][$title] - 0;//$val
                            } else {
                                $dis = $val;
                                $_REQUEST['discount_data'][$title] = $_REQUEST['discount_data'][$title] - $val;
                            }
                            // 26/08/2021 END Added for The Millennium School for Advanced Imprest Collection payment
                            // $_REQUEST['discount_data'][$title] = $_REQUEST['discount_data'][$title] - $val;


                        }
                        $fees_arr[$month_id][$receipt_id][$discount_field] = $fees_arr[$month_id][$receipt_id][$discount_field] + $dis;
                        //$fees_arr[$month_id][$receipt_id][$total_field] = $fees_arr[$month_id][$total_field][$discount_field] + $dis;
                    }
                }
            }
        }

        /** START Cumulative Discount code added on 16th Jun **/
        if (isset($_REQUEST['totalDis']) && $_REQUEST['totalDis'] != 0) {
            $newdis = $_REQUEST['totalDis'];
            foreach ($fees_arr as $month_id => $detail_arr) {
                foreach ($detail_arr as $receipt_id => $arr) {
                    $soni_val = array_sum($arr);
                    $fees_arr[$month_id][$receipt_id][$discount_field] = 0;

                    /* START Cumulative Logic for discount */
                    if ($soni_val > $newdis) {
                        $fees_arr[$month_id][$receipt_id][$discount_field] = $newdis;
                        $newdis = 0;
                    } else {
                        $newdis -= $soni_val;
                        $fees_arr[$month_id][$receipt_id][$discount_field] = $soni_val;
                    }
                    /* END Cumulative Logic for discount */
                }
            }
        }
        /** END Cumulative Discount code added on 16th Jun **/

        foreach ($fees_arr as $month_id => $detail_arr) {
            foreach ($detail_arr as $receipt_id => $arr) {
                $sum = 0;
                foreach ($arr as $id => $val) {
                    if ($id != $discount_field) {
                        $sum += $val;
                    } else {
                        $sum -= $val;
                    }
                }
                $fees_arr[$month_id][$receipt_id][$total_field] = $sum;
            }
        }

        return $fees_arr;
    }

    public function add_fine($fees_arr)
    {
        $discount_field = "";
        $total_field = "";

        $fine_data = $_REQUEST['fine_data'] ?? [];
        foreach ($fine_data as $id => $val) {
            if ($val == 0) {
                unset($fine_data[$id]);
            }
        }

        if (count($fine_data) > 0) {
            foreach ($fees_arr as $month_id => $detail_arr) {
                foreach ($detail_arr as $receipt_id => $arr) {
                    $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                    foreach ($arr as $title => $val) {
                        if (isset($fine_data[$title])) {
                            $fin = $fine_data[$title];
                            if (isset($_REQUEST['hidden_cheque_return_charges'])) {
                                $fin = $fin + $_REQUEST['hidden_cheque_return_charges'];
                            }
                            if (!isset($fees_arr[$month_id][$receipt_id]['fine'])) {
                                $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                            }
                            $fees_arr[$month_id][$receipt_id]['fine'] = $fees_arr[$month_id][$receipt_id]['fine'] + $fin;
                            unset($fine_data[$title]);
                            unset($_REQUEST['hidden_cheque_return_charges']);
                        }
                    }
                }
            }

        } else {

            // 30-12-2021 START for display fine total value in fees receipt if indiviual fine not given
            foreach ($fees_arr as $month_id => $detail_arr) {
                foreach ($detail_arr as $receipt_id => $arr) {
                    $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                    if (isset($_REQUEST['fees_data']['fine'])) {
                        $fees_arr[$month_id][$receipt_id]['fine'] = $_REQUEST['fees_data']['fine'];
                        unset($_REQUEST['fees_data']['fine']);
                    }
                }
            }
            // 30-12-2021 END for display fine total value in fees receipt if indiviual fine not given
        }

        return $fees_arr;
    }

    public function gunrate_receipt_number()
    {
        $fc_syear = "";
        if (session()->get('sub_institute_id') != 47) {
            $fc_syear = " AND fr.syear = '" . session()->get('syear') . "' ";
        }

        $result = DB::table('fees_receipt_book_master')
            ->selectRaw("fees_receipt_book_master.*,GROUP_CONCAT(fees_receipt_book_master.fees_head_id ORDER BY fees_title.sort_order) heads")
            ->join('fees_title', 'fees_title.id', '=', 'fees_receipt_book_master.fees_head_id')
            ->where('fees_receipt_book_master.grade_id', $_REQUEST['grade_id'])
            ->where('fees_receipt_book_master.standard_id', $_REQUEST['standard_id'])
            ->where('fees_receipt_book_master.syear', session()->get('syear'))
            ->where('fees_receipt_book_master.sub_institute_id', session()->get('sub_institute_id'))
            ->groupBy('fees_receipt_book_master.receipt_line_1', 'fees_receipt_book_master.receipt_line_2', 'fees_receipt_book_master.receipt_line_3', 'fees_receipt_book_master.receipt_line_4', 'fees_receipt_book_master.receipt_prefix', 'fees_receipt_book_master.receipt_logo', 'fees_receipt_book_master.last_receipt_number')
            ->orderBy('fees_title.sort_order')
            ->get()
            ->toArray();

        $id_arr = [];
        foreach ($result as $id => $arr) {

            if (isset($arr->receipt_prefix) && $arr->receipt_prefix != '') {
                $sub_string_count = (strlen($arr->receipt_prefix) + 1);

                $result_id = DB::table('fees_receipt as fr')
                    ->leftJoin('fees_collect as fc', function ($join) use ($arr) {
                        $join->whereRaw("fc.receipt_no = fr.RECEIPT_ID_" . $arr->sort_order . "");
                    })->leftJoin('fees_paid_other as fo', function ($join) use ($arr) {
                        $join->whereRaw("fo.reciept_id = fr.RECEIPT_ID_" . $arr->sort_order . "");
                    })->selectRaw("ifnull(max(cast(fr.RECEIPT_ID_" . $arr->sort_order . " as UNSIGNED))," . $arr->last_receipt_number . ") as rid1,
                        MAX(CAST(SUBSTRING(fr.RECEIPT_ID_" . $arr->sort_order . "," . $sub_string_count . ") AS INT)) as rid")
                    ->where('fr.SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
                    ->where(function ($q) {
                        if (session()->get('sub_institute_id') != 47) {
                            $q->where('fr.syear', session()->get('syear'));
                        }
                    })->get()->toArray();

                $rid = $arr->receipt_prefix . ($result_id[0]->rid + 1);

                $id_arr[$arr->sort_order]['heds'] = $arr->heads;
                $id_arr[$arr->sort_order]['rid'] = $rid;
            } else {
                $result_id = DB::table('fees_receipt as fr')
                    ->leftJoin('fees_collect as fc', function ($join) use ($arr) {
                        $join->whereRaw("fc.receipt_no = fr.RECEIPT_ID_" . $arr->sort_order . "");
                    })->leftJoin('fees_paid_other as fo', function ($join) use ($arr) {
                        $join->whereRaw("fo.reciept_id = fr.RECEIPT_ID_" . $arr->sort_order . "");
                    })->selectRaw("ifnull(max(cast(fr.RECEIPT_ID_" . $arr->sort_order . " as UNSIGNED))," . $arr->last_receipt_number . ") as rid")
                    ->where('fr.SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
                    ->where(function ($q) {
                        if (session()->get('sub_institute_id') != 47) {
                            $q->where('fr.syear', session()->get('syear'));
                        }
                    })->get()->toArray();

                $id_arr[$arr->sort_order]['heds'] = $arr->heads;
                $id_arr[$arr->sort_order]['rid'] = $result_id[0]->rid + 1;
            }


        }

        return $id_arr;
    }

    public function gunrate_receipt($receipt_id, $receipt_arr, $id_heads)
    {

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $month_name = '';
        $month_name2 = '';
        $all_months = '';
        foreach ($_REQUEST['months'] as $id => $arr) {
            $y = $arr / 10000;
            $month = (int)$y;
            $year = substr($arr, -4);
            $month_name .= $months[$month] . "/" . $year . ',';
            $all_months .= $month . $year . ',';
        }
        $fees_paid_name = [];
        $month_name = substr($month_name, 0, -1);
        $config_master = DB::table('fees_config_master')->whereRaw('sub_institute_id=' . session()->get('sub_institute_id') . ' and syear=' . session()->get('syear') . ' and show_month !=0')->get()->toArray();
    //   return $config_master;exit;
        if (!empty($config_master)) {
            $fees_paid_name = DB::table('fees_collect as fc')
                ->join('fees_receipt as fr', function ($join) {
                    $join->whereRaw('find_in_set(fc.id,fr.FEES_ID)');
                })->selectRaw('fc.term_id,fc.tution_fee,fc.admission_fee,fc.activity_fee,fc.term_fee,fc.deposit,fc.co_curriculam_fees,fc.computer_fees,fc.smart_class,fc.security_charges,fc.photograph,fc.cal_misc,fc.title_1,fc.title_2,fc.title_3,fc.title_4,fc.title_5,fc.title_6,fc.title_7,fc.title_8,fc.title_9,fc.title_10,fc.title_11,fc.title_12')
                ->where('fr.id', $receipt_id)
                ->get()->map(function ($row) {
                    // Filter out the columns that are equal to 0
                    return collect($row)->filter(function ($value, $key) {
                        return $value != 0;
                    })->toArray();
                })->toArray();
        }
        foreach ($fees_paid_name as $id => $arr) {
            $y = $arr['term_id'] / 10000;
            $month = (int)$y;
            $year = substr($arr['term_id'], -4);
            $month_name2 = $months[$month] . ',';
                // Replace the term_id value with month_name2
            $fees_paid_name[$id]['term_id'] = substr($month_name2, 0, -1);
        }

        $fees_paid = DB::table('fees_collect as fc')
            ->join('fees_receipt as fr', function ($join) {
                $join->whereRaw('find_in_set(fc.id,fr.FEES_ID)');
            })->selectRaw('fc.*')
            ->where('fr.id', $receipt_id)->get()->toArray();
            // ->where('fr.id', 285)->get()->toArray();            

        $other_fees_paid = DB::table('fees_paid_other as fc')
            ->join('fees_receipt as fr', function ($join) {
                $join->whereRaw('find_in_set(fc.id,fr.OTHER_FEES_ID)');
            })->selectRaw('fc.*')
            ->where('fr.id', $receipt_id)->get()->toArray();
            // ->where('fr.id', 285)->get()->toArray();            

        $ret_heds_with_id = DB::table('fees_title')
            ->where('SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))
            ->orderBy('sort_order')
            ->get()->toArray();
        $other_fees_heads = [];
        $reg_fees_heads = [];

        foreach ($fees_paid_name as $index => $data) {
            foreach ($data as $key => $value) {
                foreach ($ret_heds_with_id as $ret_head) {
                    if ($ret_head->fees_title === $key) {
                        $fees_paid_name[$index][$ret_head->display_name] = $value;
                        unset($fees_paid_name[$index][$key]);
                        break;
                    }
                }
            }
        }

        foreach ($ret_heds_with_id as $id => $arr) {
            if ($arr->fees_title_id == '1') {
                $other_fees_heads[] = $arr;
            } else {
                $reg_fees_heads[] = $arr;
            }
        }
        $fees_arr = [];
        $insert_html_ids = [];
        foreach ($receipt_arr as $sort_order => $arr) {
            $heads_arr = explode(',', $arr['heds']);
            $insert_html_ids[$sort_order] = [];
            foreach ($heads_arr as $id => $head_id) {
                $head = "REG";
                foreach ($other_fees_heads as $temp_id => $detail) {
                    if ($detail->id == $head_id) {
                        $head = "OTHER";
                    }
                }
                if ($head == "REG") {
                    $head_name = "";
                    foreach ($id_heads as $ids => $val) {
                        if ($val == $head_id) {
                            $head_name = $ids;
                        }
                    }
                    $total = 0;
                    if ($head_name != "") {
                        $total = 0;

                        foreach ($fees_paid as $ids => $arrs) {
                            if ($arrs->$head_name != null && $arrs->$head_name != '' && $arrs->$head_name != 0) {
                                if (isset($insert_html_ids[$sort_order]['REG'])) {
                                    if (!in_array($arrs->id, $insert_html_ids[$sort_order]['REG'])) {
                                        $insert_html_ids[$sort_order]['REG'][] = $arrs->id;
                                    }
                                } else {
                                    $insert_html_ids[$sort_order]['REG'][] = $arrs->id;
                                }
                            }
                            $total += $arrs->$head_name;
                        }
                    }
                    // finding display name
                    $diplay_name = "";
                    foreach ($reg_fees_heads as $ids => $arrs) {
                        if ($head_id == $arrs->id) {
                            $diplay_name = $arrs->display_name;
                        }
                    }

                    $fees_arr[$arr['rid'] . "_" . $sort_order][$diplay_name] = $total;
                } else {
                    $head_name = "";
                    foreach ($id_heads as $ids => $val) {
                        if ($val == $head_id) {
                            $head_name = $ids;
                        }
                    }
                    $total = 0;
                    if ($head_name != "") {
                        $total = 0;
                        foreach ($other_fees_paid as $ids => $arrs) {
                            if ($arrs->$head_name != null && $arrs->$head_name != '' && $arrs->$head_name != 0) {
                                if (isset($insert_html_ids[$sort_order]['OTHER'])) {
                                    if (!in_array($arrs->id, $insert_html_ids[$sort_order]['OTHER'])) {
                                        $insert_html_ids[$sort_order]['OTHER'][] = $arrs->id;
                                    }
                                } else {
                                    $insert_html_ids[$sort_order]['OTHER'][] = $arrs->id;
                                }
                            }
                            $total += $arrs->$head_name;
                        }
                    }
                    // finding display name
                    $diplay_name = "";
                    foreach ($other_fees_heads as $ids => $arrs) {
                        if ($head_id == $arrs->id) {
                            $diplay_name = $arrs->display_name;
                        }
                    }
                }

                $fees_arr[$arr['rid'] . "_" . $sort_order][$diplay_name] = $total;
            }
        }
      
        //adding discount in array
        foreach ($insert_html_ids as $sort_order => $arr) {
            $total_discount = 0;
            $total_fine = 0;
            foreach ($arr as $key => $detai_arr) {
                if ($key == 'REG') {
                    $paid_result = DB::table('tblstudent as s')
                        ->join('fees_collect as fc', function ($join) {
                            $join->whereRaw("(fc.student_id = s.id AND fc.sub_institute_id = '" . session()->get('sub_institute_id') . "')");
                        })->selectRaw('SUM(fc.fees_discount) amount,SUM(fc.fine) fine_amount')
                        ->where('s.sub_institute_id', session()->get('sub_institute_id'))
                        ->whereIn('fc.id', $detai_arr)->get()->toArray();
                    $total_discount += $paid_result[0]->amount;
                    $total_fine += $paid_result[0]->fine_amount;
                } else {
                    $paid_result = DB::table('tblstudent as s')
                        ->join('fees_paid_other as fpo', function ($join) {
                            $join->whereRaw("(fpo.student_id = s.id)");
                        })->selectRaw('SUM(fpo.fees_discount) amount,SUM(fpo.fine) fine_amount')
                        ->where('s.sub_institute_id', session()->get('sub_institute_id'))
                        ->whereIn('fpo.id', $detai_arr)->get()->toArray();
                    $total_discount += $paid_result[0]->amount;
                    $total_fine += $paid_result[0]->fine_amount;
                }
            }
            foreach ($fees_arr as $sort_order_id => $arr) {
                $order_id = explode('_', $sort_order_id);
                if ($order_id[1] == $sort_order) {
                    $fees_arr[$sort_order_id]['Fine'] = $total_fine;

                    $fees_arr[$sort_order_id][get_string('discount', 'request')] = $total_discount;
                }
            }
        }

        //removing all balnk array
        $new_fees_arr = [];
        foreach ($fees_arr as $id => $arr) {
            foreach ($arr as $head_id => $amount) {
                if ($amount != 0) {
                    $months = [];
                    foreach ($fees_paid_name as $paid_arr) {
                        if (isset($paid_arr[$head_id])) {
                            $months[] = $paid_arr['term_id'];
                        }
                    }
                    $new_head_id = $head_id;
                    if (!empty($months)) {
                        $new_head_id .= ' (' . implode(',', $months) . ')';
                    }
                    $new_fees_arr[$id][$new_head_id] = $amount;
                }
            }
        }
              
        // echo "<pre>";print_r($new_fees_arr);
        // exit;
        foreach ($new_fees_arr as $id => $arr) {
            if (count($arr) == 0) {
                unset($new_fees_arr[$id]);
            }
        }
        $fees_arr = $new_fees_arr;
        
        // 31/03/2021 - START FOR making cumulative fees recepit array
        $get_cumulative_result = DB::table('fees_title')
            ->selectRaw('id,display_name,cumulative_name,append_name')
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->whereNotNull('cumulative_name')
            ->orderBy('sort_order')->get()->toArray();

        $get_cumulative_result = array_map(function ($value) {
            return (array)$value;
        }, $get_cumulative_result);

        $cumulative_arr = $append_arr = array();
        foreach ($get_cumulative_result as $key => $value) {
            $cumulative_arr[$value['display_name']] = $value['cumulative_name'];
            $append_arr[$value['display_name']] = $value['append_name'];
        }
        // 31/03/2021 - END FOR making cumulative fees recepit array

        $result = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('grade_id', $_REQUEST['grade_id'])
            ->where('standard_id', $_REQUEST['standard_id'])
            ->where('syear', session()->get('syear'))
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
            ->get()->toArray();

        $sub_institute_id = session()->get('sub_institute_id');
        $final_html = "";

        foreach ($fees_arr as $id => $arr) {

            $id_arr = explode('_', $id);
            $RECEIPT_NO = $id_arr[0];
            $sort_order = $id_arr[1];

            $receipt_book_arr = [];
            foreach ($result as $temp_id => $receipt_detail) {
                if ($sort_order == $receipt_detail->sort_order) {
                    $receipt_book_arr = $receipt_detail;
                }
            }

            $image_path1 = "/storage/fees/" . $receipt_book_arr->receipt_logo;
            $image_path = '<img class="logo" src="' . $image_path1 . '" alt="SCHOOL LOGO">';


            $syear1 = session()->get('syear');
            $syear2 = $syear1 + 1;
            $edu_year = "$syear1-$syear2";


            $rwspan = count($fees_arr);
            $recTotal = 0;

            foreach ($arr as $key => $pval) {
                if ($key == 'Discount') {
                    $recTotal = $recTotal - $pval;
                } else {
                    $recTotal = $recTotal + $pval;
                }
            }

            $fees_head_content = '<table class="particulars" width="100%" border="0">
               <tbody><tr>
                  <td colspan="3"><b>Description</b></td>
                  <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>
               </tr>';

            // 31/03/2021 START for Cumulative Fees Receipt

            if (count($cumulative_arr) > 0) {
                $arrnew = $appendnew = [];
                foreach ($arr as $pkey => $pval) {
                    if (array_key_exists($pkey, $cumulative_arr)) {
                        $newkey = $cumulative_arr[$pkey];

                        if (array_key_exists($newkey, $arrnew)) {
                            $arrnew[$newkey] = $arrnew[$newkey] + $pval;
                            $appendnew[$newkey][] = $append_arr[$pkey];
                        } else {
                            $arrnew[$newkey] = $pval;
                            $appendnew[$newkey][] = $append_arr[$pkey];
                        }
                    } else //for discount ,fines and other types
                    {
                        $arrnew[$pkey] = $pval;
                    }
                }
                $arr = $arrnew;
            }

            // 31/03/2021 END for Cumulative Fees Receipt

            foreach ($arr as $pkey => $pval) {
                //  31/03/2021 - Start For Cumulative name
                if (isset($appendnew[$pkey])) {
                    $append_name = implode(",", $appendnew[$pkey]);
                    if ($append_name != "") {
                        $pkey .= ' (' . $append_name . ') ';
                    }
                }
                //  31/03/2021 - End For Cumulative name

                //START Added on 16th june 2021
                if ($pkey == 'Discount') {
                    $minus_sign = "-";
                } else {
                    $minus_sign = "";
                }
                //END Added on 16th june 2021

                $fees_head_content .= '<tr>';
                $fees_head_content .= '  <td colspan="3" align="left">' . $pkey . '</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                $fees_head_content .= '  <td align="right">' . $minus_sign . $pval . '</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                $fees_head_content .= '</tr>';
            }
            $fees_head_content .= '<tr>
                  <td align="left" colspan="3"><b>Total</b></td>
                  <td align="right"><b>&lt;&lt;grand_total&gt;&gt;</b></td>
               </tr>
            </tbody></table>';

            $total_amount_in_words = ucwords($this->convert_number_to_words($recTotal));
            if ($total_amount_in_words != "") {
                $total_amount_in_words_str = "Rupees " . $total_amount_in_words . " Only";
            } else {
                $total_amount_in_words_str = "";
            }

            $payMethod = $_REQUEST['PAYMENT_MODE'];
            if ($payMethod == '') {
                $payment_mode = $payMethod;
            } else {
                $payment_mode = $payMethod . ' ' . strtoupper($_REQUEST['bank_name']) . ' - ' . $_REQUEST['cheque_no'];
            }

            if (isset($_REQUEST['remarks']) && $_REQUEST['remarks'] != '' && $_REQUEST['remarks'] != '-') {
                $discount_remarks = $_REQUEST['remarks'];
            } else {
                $discount_remarks = '';

            }
            // START Dynamic Template Logic
            $tData = DB::table('template_master')
                ->where('module_name', '=', 'Fees')
                ->whereRaw('sub_institute_id = IFNULL((SELECT sub_institute_id FROM template_master WHERE module_name ="Fees" AND
                    sub_institute_id = "' . session()->get('sub_institute_id') . '"),0)')
                ->get()->toArray();

            $tData = json_decode(json_encode($tData), true);

            $father_name = $_REQUEST['father_name'] ?? '-';
            $mother_name = $_REQUEST['mother_name'] ?? '-';
            $medium = $_REQUEST['medium'] ?? '-';
            $uniqueid = $_REQUEST['uniqueid'] ?? '-';
            $enrollment = $_REQUEST['enrollment'] ?? '-';
            $roll_no = $_REQUEST['roll_no'] ?? '-';

            $html_content = $tData[0]['html_content'];

            $html_content = str_replace(htmlspecialchars("<<receipt_logo>>"), $image_path, $html_content);
            if ($receipt_book_arr->receipt_line_1 != '') {
                $html_content = str_replace(
                    htmlspecialchars("<<receipt_line_1>>"),
                    $receipt_book_arr->receipt_line_1,
                    $html_content
                );
            }
            if ($receipt_book_arr->receipt_line_2 != '') {
                $html_content = str_replace(
                    htmlspecialchars("<<receipt_line_2>>"),
                    $receipt_book_arr->receipt_line_2,
                    $html_content
                );
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $html_content = str_replace(
                    htmlspecialchars("<<receipt_line_3>>"),
                    $receipt_book_arr->receipt_line_3,
                    $html_content
                );
            }
            if ($receipt_book_arr->receipt_line_4 != '') {
                $html_content = str_replace(
                    htmlspecialchars("<<receipt_line_4>>"),
                    $receipt_book_arr->receipt_line_4,
                    $html_content
                );
            }
            $html_content = str_replace(htmlspecialchars("<<student_board_value>>"), $medium, $html_content);
            $html_content = str_replace(htmlspecialchars("<<admission_number_value>>"), $uniqueid, $html_content);
            $html_content = str_replace(htmlspecialchars("<<receipt_year_value>>"), $edu_year, $html_content);

            $html_content = str_replace(htmlspecialchars("<<receipt_number_value>>"), $RECEIPT_NO, $html_content);
            $html_content = str_replace(
                htmlspecialchars("<<receipt_date_value>>"),
                date("d-m-Y", strtotime($_REQUEST['receiptdate'])),
                $html_content
            );

            $html_content = str_replace(
                htmlspecialchars("<<student_name_value>>"),
                $_REQUEST['full_name'],
                $html_content
            );
            $html_content = str_replace(htmlspecialchars("<<student_enrollment_value>>"), $enrollment, $html_content);
            $html_content = str_replace(htmlspecialchars("<<student_roll_value>>"), $roll_no, $html_content);
            $html_content = str_replace(htmlspecialchars("<<student_father_name>>"), $father_name, $html_content);
            $html_content = str_replace(htmlspecialchars("<<student_mother_name>>"), $mother_name, $html_content);
            $html_content = str_replace(
                htmlspecialchars("<<student_standard_value>>"),
                $_REQUEST['std_div'],
                $html_content
            );
            $html_content = str_replace(
                htmlspecialchars("<<student_mobile_value>>"),
                $_REQUEST['mobile'],
                $html_content
            );

            $html_content = str_replace(htmlspecialchars("<<fees_months_display>>"), $month_name, $html_content);

            $html_content = str_replace(htmlspecialchars("<<fees_head_content>>"), $fees_head_content, $html_content);
            $html_content = str_replace(htmlspecialchars("<<grand_total>>"), $recTotal, $html_content);

            $html_content = str_replace(
                htmlspecialchars("<<total_amount_in_words>>"),
                $total_amount_in_words_str,
                $html_content
            );
            $html_content = str_replace(htmlspecialchars("<<payment_mode>>"), $payment_mode, $html_content);
            $html_content = str_replace(htmlspecialchars("<<discount_remarks>>"), $discount_remarks, $html_content);
            $html_content = str_replace(htmlspecialchars("<<admin_user>>"), session()->get('name'), $html_content);

            $recHtml = $html_content;
            // END Dynamic Template Logic

            $sArr = ["'"];//'"',
            $rArr = ["\'"];//'\"',

            foreach ($insert_html_ids as $sort_order_id => $other_reg) {
                if ($sort_order == $sort_order_id) {
                    foreach ($other_reg as $identifiyer => $vals) {
                        if ($identifiyer == "OTHER") {
                            DB::table('fees_paid_other')
                                ->whereIn('id', $vals)
                                ->update([
                                    'paid_fees_html' => str_replace($sArr, $rArr, $recHtml),
                                ]);
                        } else {
                            DB::table('fees_collect')
                                ->whereIn('id', $vals)
                                ->update([
                                    'fees_html' => str_replace($sArr, $rArr, $recHtml),
                                ]);
                        }
                    }
                }
            }
            $final_html .= $recHtml;
        }

        return $final_html;
    }

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = [
            0 => 'zero',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'fourty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety',
            100 => 'hundred',
            1000 => 'thousand',
            1000000 => 'million',
            1000000000 => 'billion',
            1000000000000 => 'trillion',
            1000000000000000 => 'quadrillion',
            1000000000000000000 => 'quintillion',
        ];

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int)$number < 0) || (int)$number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );

            return false;
        }

        if ($number < 0) {
            return $negative . $this->convert_number_to_words(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens = ((int)($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . $this->convert_number_to_words($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int)($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= $this->convert_number_to_words($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = [];
            foreach (str_split((string)$fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

    public function get_receipt_id()
    {
        $result = DB::table('fees_collect')
            ->selectRaw('ifnull(max(receipt_no),1)+1 as maxid')
            ->where('sub_institute_id', session()->get('sub_institute_id'))->get()->toArray();

        return $result[0]->maxid;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Request  $request
     * @return false|string|JsonResponse
     */

    public function PaidUnpaid(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 200);
        }
        $response = ['response' => '', 'status' => '0', 'message' => 'Data Not Found.'];
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'syear' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $student_id = $_REQUEST['student_id'];
            $syear = $_REQUEST['syear'];


            $data = map_year::where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
            ])->get()->toArray();
            if (!$data) {
                $response['response'] = ["year_error" => ["Maping Year Error."]];

                return $response;
            }

            $start_month = $data[0]['from_month'];
            $end_month = $data[0]['to_month'];

            $months = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            ];
            $months_arr = [];

            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
                if ($start_month == 12) {
                    $start_month = 0;
                    ++$syear;
                }
                ++$start_month;
            }
            $month_arr = $months_arr;
            $responce_arr = [];

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

            $breackoff_join = "";
            $breackoff_other_join = "";
            $fees_join = "";
            $paid_other_join = "";
            foreach ($search_ids as $id => $val) {
                if ($id == 0) {
                    $breackoff_join .= " AND (";
                    $breackoff_other_join .= " AND (";
                    $fees_join .= " AND (";
                    $paid_other_join .= " AND (";
                }
                if (count($search_ids) == ($id + 1)) {
                    $breackoff_join .= "fb.month_id = $val)";
                    $breackoff_other_join .= "fbo.month_id = $val)";
                    $fees_join .= "fc.term_id = $val)";
                    $paid_other_join .= "fpo.month_id = $val)";
                } else {
                    $breackoff_join .= "fb.month_id = $val OR ";
                    $breackoff_other_join .= "fbo.month_id = $val OR ";
                    $fees_join .= "fc.term_id = $val OR ";
                    $paid_other_join .= "fpo.month_id = $val OR ";
                }
            }

            $requestData = $_REQUEST;

            $result = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id');
                })->join('academic_section as g', function ($join) {
                    $join->whereRaw('g.id = se.grade_id');
                })->join('standard as st', function ($join) {
                    $join->whereRaw('st.id = se.standard_id');
                })->leftJoin('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->join('fees_breackoff as fb', function ($join) use ($breackoff_join, $requestData) {
                    $join->whereRaw("(fb.syear = '" . $requestData['syear'] . "' AND
                 fb.admission_year = s.admission_year AND fb.quota = se.student_quota AND fb.grade_id = se.grade_id AND
                 fb.standard_id = se.standard_id AND fb.sub_institute_id = '" . session()->get('sub_institute_id') . "' $breackoff_join)");
                })->selectRaw("s.*,se.syear,se.student_id,se.grade_id,
                    se.standard_id,se.section_id,se.student_quota,se.start_date,
                    se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                    se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                    se.house_id,se.lc_number,
                    sum(fb.amount)+ (select ifnull(sum(fbo.amount),0) from fees_breakoff_other fbo where fbo.syear = '" . $_REQUEST['syear'] . "'
                    AND fbo.student_id = s.id AND fb.standard_id = se.standard_id AND fbo.sub_institute_id = '" . $sub_institute_id . "' )
                    bkoff,st.name standard_name, d.name as division_name")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $requestData['syear'])
                ->where(function ($q) use ($requestData) {
                    if (isset($requestData['student_id']) && $requestData['student_id'] != '') {
                        $q->where('s.id', $requestData['student_id']);
                    }
                })->groupBy('s.id')->havingNotNull('bkoff')->get()->toArray();

            if (!$result) {
                $response['response'] = ["bf_error" => ["No Breackoff Found."]];

                return $response;
            }

            // TODO: Chnage this query to DB/Eloqunt Model
            $sql = "
                    SELECT SUM(amount) paid_amt,student_id id
            FROM(
                select SUM(fc.amount)+SUM(fc.fees_discount) amount,se.student_id
                    FROM tblstudent s
                    INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                    INNER JOIN academic_section g ON g.id = se.grade_id
                    INNER JOIN standard st ON st.id = se.standard_id
                    LEFT JOIN division d ON  d.id = se.section_id
                    INNER JOIN fees_collect fc ON
                            (
                             fc.student_id = s.id AND
                             fc.is_deleted = 'N' AND
                             fc.sub_institute_id = '" . $sub_institute_id . "'
                                 $fees_join
                            )

                    WHERE s.sub_institute_id = '" . $sub_institute_id . "'
                    AND s.id = '" . $student_id . "'
                    GROUP BY s.id
                    UNION ALL
                    select SUM(fpo.actual_amountpaid)+SUM(fpo.fees_discount) aa,se.student_id
                    FROM tblstudent s
                    INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                    INNER JOIN academic_section g ON g.id = se.grade_id
                    INNER JOIN standard st ON st.id = se.standard_id
                    LEFT JOIN division d ON  d.id = se.section_id
                    INNER JOIN fees_paid_other fpo ON
                        (fpo.student_id = s.id  $paid_other_join)
                    WHERE s.sub_institute_id = '" . $sub_institute_id . "'
                    AND s.id = '" . $student_id . "'
                    GROUP BY s.id
                ) temp_table
                GROUP BY student_id

                    ";

            $sql = preg_replace('/\n+/', '', $sql);

            $paid_result = DB::select($sql);

            $return_data = [
                "student_id" => $student_id,
            ];

            $return_data['breack_off_amount'] = $result[0]->bkoff;
            if ($paid_result) {
                $return_data['paid_amount'] = $paid_result[0]->paid_amt;
            } else {
                $return_data['paid_amount'] = 0;
            }
            $return_data['unpaid_amount'] = $return_data['breack_off_amount'] - $return_data['paid_amount'];

            $response['response'] = $return_data;
            $response['message'] = "Sucsess";
            $response['status'] = '1';
        }

        return json_encode($response);
    }


    public function getOnlinebk(Request $request, $sub_institute_id, $syear, $student_id)
    {
        $request->session()->put('sub_institute_id', $sub_institute_id);
        $request->session()->put('syear', $syear);
        $request->session()->put('student_id', $student_id);

        return $this->getBk($request, $student_id);
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
        $month_arr2 = FeeMonthIdlast();
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month . $currunt_year;
        $last_y_month_id = $currunt_month . ($syear - 1);
// echo $last_y_month_id;exit;
        $search_ids = [];
        foreach ($month_arr as $id => $arr) {
            if ($id == $currunt_month_id) {
                $search_ids[] = $id;
                // break;
            } else {
                $search_ids[] = $id;
            }
        }

        foreach ($month_arr2 as $id => $arr) {
            if ($id == $last_y_month_id) {
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

        $sql2 = "
            SELECT SUM(amount) amount,term_id
       FROM(
            select SUM(fc.amount)+SUM(fc.fees_discount) amount,fc.term_id
                FROM tblstudent s
                INNER JOIN fees_collect fc ON(fc.student_id = s.id AND fc.is_deleted = 'N' AND fc.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND
                         (fc.syear = '" . (session()->get('syear') - 1) . "' or fc.syear ='" . session()->get('syear') . "' ) $fees_join )
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND s.id = $student_id
                GROUP BY s.id,fc.term_id
                UNION ALL
                select SUM(fpo.actual_amountpaid)+SUM(fpo.fees_discount) aa,fpo.month_id
                FROM tblstudent s
                INNER JOIN fees_paid_other fpo ON
                    (fpo.student_id = s.id  AND fpo.syear='" . (session()->get('syear') - 1) . "' $paid_other_join)
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND s.id = $student_id
                GROUP BY s.id,fpo.month_id
            ) temp_table
            GROUP BY term_id";

        $sql2 = preg_replace('/\n+/', '', $sql2);
        $paid_result2 = DB::select($sql2);

        // echo "<pre>";print_r($paid_result2);exit;
        $fees_paid_arr = [];
        foreach ($paid_result as $id => $arr) {
            $fees_paid_arr[$arr->term_id] = $arr->amount;
        }

        $fees_paid_arr2 = [];
        foreach ($paid_result2 as $id => $arr) {
            $fees_paid_arr2[$arr->term_id] = $arr->amount;
        }

        $reg_bk_off = FeeBreackoff($stu_arr, $request->standard);
        $reg_bk_off2 = FeeBreackofflast($stu_arr, $request->standard);

// echo "<pre>";print_r($reg_bk_off2);exit;
        $reg_bk_off_count = is_array($reg_bk_off) ? count($reg_bk_off) : $reg_bk_off->count();

        if (count($reg_bk_off) == 0) {
            return [];
        }
        $other_bk_off = OtherBreackOff($stu_arr, $search_ids);

        $other_bk_off_month_wise = OtherBreackOfMonth($stu_arr);
// echo "<pre>";print_r($month_arr2);exit;

        $year_arr = FeeMonthId();
        // $year_arr2 = FeeMonthId();

        $reg_bk_month_wise = $reg_bk_month_wise2 = [];
        foreach ($reg_bk_off as $id => $arr) {
            $reg_bk_month_wise[$arr->month_id] = $arr->bkoff;
        }


        $new_month_arr = [];
        $new_month_arr2 = [];
        foreach ($reg_bk_month_wise as $month_id => $val) {
            $new_month_arr[$month_id] = $month_arr[$month_id];
        }
// echo "<pre>";print_r($month_arr2 ?? []);exit;


        foreach ($other_bk_off_month_wise as $month_id => $val) {
            $new_month_arr[$month_id] = $month_arr[$month_id];
        }
// echo "<pre>";print_r($reg_bk_month_wise2 ?? []);exit;

   // foreach ($other_bk_off_month_wise as $month_id => $val) {
   //          $new_month_arr2[$month_id] = $month_arr2[$month_id];
   //      }


        $merge_bk_month_wise = [];
        foreach ($reg_bk_month_wise as $month_id => $amount) {
            $merge_bk_month_wise[$month_id] = $amount;
            foreach ($other_bk_off_month_wise as $MonthId => $amt) {
                if ($month_id == $MonthId) {
                    $merge_bk_month_wise[$month_id] += $amt;
                }
            }
        }

// echo "<pre>";print_r($merge_bk_month_wise2 ?? []);exit;

        $left_bk_table = $this_month = $last_month = $left_bk_table2 = [];
        $i = 1;
        $last_fees = 0;
        $fees_total = $fees_total_last = 0;
        $paid_total = $paid_total_last = 0;
        $remain_total = $remain_total_last = 0;

        foreach ($merge_bk_month_wise as $id => $val) {
            $left_bk_table[$i]['month'] = $year_arr[$id];
            $left_bk_table[$i]['month_this'] = substr($year_arr[$id], 0, 3);
            $this_month[] = $left_bk_table[$i]['month_this'];
            $left_bk_table[$i]['month_id'] = $id;
            $left_bk_table[$i]['bk'] = $val;
            if (isset($fees_paid_arr[$id]) && $fees_paid_arr[$id] > 0) {
                $left_bk_table[$i]['paid'] = $fees_paid_arr[$id];
            } else {
                $left_bk_table[$i]['paid'] = 0;
            }
            if($left_bk_table[$i]['paid'] > $left_bk_table[$i]['bk']){
                $left_bk_table[$i]['remain'] =0;
            }else{
                $left_bk_table[$i]['remain'] = $left_bk_table[$i]['bk'] - $left_bk_table[$i]['paid'];
            }

            $fees_total = $fees_total + $left_bk_table[$i]['bk'];
            $paid_total = $paid_total + $left_bk_table[$i]['paid'];
            $remain_total = $remain_total + $left_bk_table[$i]['remain'];
            $i = $i + 1;
            // $this_month[] = $left_bk_table[$i]['month_this'];

        }
        $pending_fees = 0;

        foreach ($search_ids as $id => $val) {
            foreach ($left_bk_table as $temp_id => $arr) {
                if ($arr['month_id'] == $val) {
                    $pending_fees = $pending_fees + $arr['remain'];
                }
            }
        }
        // echo "<pre>";print_r($reg_bk_off2);exit; 
        if (isset($reg_bk_off2) && $reg_bk_off2 != null) {
            $reg_bk_off_count2 = is_array($reg_bk_off2) ? count($reg_bk_off2) : $reg_bk_off2->count();
            if (count($reg_bk_off2) == 0) {
                return [];
            }
            $other_bk_off2 = OtherBreackOfflast($stu_arr, $search_ids);
            foreach ($reg_bk_off2 as $id => $arr) {
                $reg_bk_month_wise2[$arr->month_id] = $arr->bkoff;
            }
            foreach ($reg_bk_month_wise2 as $month_id2 => $val) {
                $new_month_arr2[$month_id2] = $month_arr2[$month_id2];
            }
            $merge_bk_month_wise2 = [];
            foreach ($reg_bk_month_wise2 as $month_id => $amount) {
                $merge_bk_month_wise2[$month_id] = $amount;
                foreach ($other_bk_off_month_wise as $MonthId => $amt) {
                    if ($month_id == $MonthId) {
                        $merge_bk_month_wise2[$month_id] += $amt;
                    }
                }
            }

        }

        $pending_fees = 0;
        foreach ($search_ids as $id => $val) {
            foreach ($left_bk_table as $temp_id => $arr) {
                if ($arr['month_id'] == $val) {
                    // echo "<pre>";print_r($arr);
                    $pending_fees = $pending_fees + $arr['remain'];
                }
            }
        }
      
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
            ->orderBy('ft.sort_order')->get()->toArray();

        $get_imprest_balance = json_decode(json_encode($get_imprest_sql), true);

        if (count($get_imprest_balance) > 0) {
            $previous_year_imprest_balance = $get_imprest_balance[0]['previous_imprest_amt'];
        } else {
            $previous_year_imprest_balance = 0;
        }

        // End Getting previous year imprest balance for The Millennium School Surat

        $stu_detail = [
            "student_id" => $reg_bk_off[0]->student_id,
            "enrollment" => $reg_bk_off[0]->enrollment_no,
            "roll_no" => $reg_bk_off[0]->roll_no,
            "name" => $reg_bk_off[0]->first_name . " " . $reg_bk_off[0]->middle_name . " " . $reg_bk_off[0]->last_name,
            "stddiv" => $reg_bk_off[0]->standard_name . "/" . $reg_bk_off[0]->division_name,
            "admission" => $reg_bk_off[0]->admission_year,
            "email" => $reg_bk_off[0]->email,
            "medium" => $reg_bk_off[0]->medium,
            "father_name" => $reg_bk_off[0]->father_name,
            "mother_name" => $reg_bk_off[0]->mother_name,
            "pending" => $pending_fees,
            "mobile" => $reg_bk_off[0]->mobile,
            "uniqueid" => $reg_bk_off[0]->uniqueid,
            "std_id" => $reg_bk_off[0]->standard_id,
            "grade_id" => $reg_bk_off[0]->grade_id,
            "div_id" => $reg_bk_off[0]->section_id,
            "student_quota" => $reg_bk_off[0]->stu_quota,
            "previous_year_imprest_balance" => $previous_year_imprest_balance,
        ];

        $head_wise_fees = FeeBreakoffHeadWise($stu_arr);
        $head_wise_fees2 = FeeBreakoffHeadWiselast($stu_arr);

        $till_now_breckoff = $till_now_breckoff2 = [];
        foreach ($search_ids as $id => $val) {
            foreach ($head_wise_fees as $temp_id => $arr) {
                foreach ($head_wise_fees[$temp_id]['breakoff'] as $month_id => $fees_detail) {
                    if ($month_id == $val) {
                        $till_now_breckoff[$month_id] = $fees_detail;
                    }
                }
            }

            foreach ($head_wise_fees2 as $temp_id => $arr) {
                foreach ($head_wise_fees2[$temp_id]['breakoff'] as $month_id => $fees_detail) {
                    if ($month_id == $val) {
                        $till_now_breckoff2[$month_id] = $fees_detail;
                    }
                }
            }
        }
        // echo "<pre>";print_r($till_now_breckoff2);exit();

        $reg_bk_month_wise = $reg_bk_month_wise2 = [];
        $reg_month_wise = $reg_month_wise2 = array();
        $final_bk_name = [];
        $total = 0;

        foreach ($till_now_breckoff as $month_id => $fees_detail) {
            foreach ($fees_detail as $head_name => $arr) {
                if (!isset($reg_bk_month_wise[$arr['title']])) {
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

        foreach ($till_now_breckoff2 as $month_id => $fees_detail) {
            foreach ($fees_detail as $head_name => $arr) {
                if (!isset($reg_bk_month_wise2[$arr['title']])) {
                    $reg_bk_month_wise2[$arr['title']] = 0;
                    $reg_month_wise2[$arr['title']] = [
                        'title' => $arr['title'],
                        'amount' => 0,
                        'mandatory' => $arr['mandatory'],
                    ];
                }
                if (isset($arr['amount'])) {
                    $reg_bk_month_wise2[$arr['title']] += $arr['amount'];
                    $reg_month_wise2[$arr['title']] = [
                        'title' => $arr['title'],
                        'amount' => $reg_bk_month_wise2[$arr['title']],
                        'mandatory' => $arr['mandatory'],
                    ];
                }
                $final_bk_name[$arr['title']] = $head_name;
            }
        }
        // echo "<pre>";print_r($reg_month_wise2);exit();

        $full_bk = array_merge($reg_bk_month_wise, $other_bk_off);
        $full_bk_new = array_merge($reg_month_wise, $other_bk_off);
        if (isset($reg_bk_off2) && !empty($reg_bk_off2)) {

            $full_bk2 = array_merge($reg_bk_month_wise2, $other_bk_off2);
            $full_bk_new2 = array_merge($reg_month_wise2, $other_bk_off2);
            $previous = array_sum($full_bk2);
            $full_bk['Previous Fees'] = $previous;
            $full_bk_new['Previous Fees'] = array(
                'title' => 'Previous Fees',
                'amount' => $previous,
                'mandatory' => 1,
            );
        }
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
            if (isset($reg_bk_off2) && !empty($reg_bk_off2)) {

                if ($previous > 0) {
                    $final_bk_name["Previous Fees"] = "previous_fees";
                }
            }
        }



        $full_bk["Total"] = $total;
        $full_bk_new["Total"] = $total;
// echo "<pre>";print_r($full_bk_new);exit;

        $type = "web";
        $res['total_fees'] = $left_bk_table ?? [];
        $res['stu_data'] = $stu_detail;
        $res['month_arr'] = $new_month_arr;
        $res['search_ids'] = $search_ids;
        $res['final_fee'] = $full_bk;
        if (isset($reg_bk_off2) && !empty($reg_bk_off2)) {

            $res['previous_fees'] = array('Previous Fees' => $previous);
        }
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
        // echo "<pre>";print_r($res);exit;
        return $res;
    }

    public function edit($id, Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res = $this->getBk($request, $id);
        $res['bank_data'] = bankmasterModel::get()->toArray();
        $res['fees_config_data'] = tblfeesConfigModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();
        // echo "<pre>";print_r($res);exit;
        if (count($res['fees_config_data']) > 0) {
            $res['fees_config_data'] = $res['fees_config_data'][0];
            $type = "web";

            return is_mobile($type, "fees/fees_collect/fees_collect", $res, "view");
        } else {
            $type = "web";

            $res = [
                "status_code" => 0,
                "message" => "Fees config master setting is missing",
            ];

            return is_mobile($type, "fees_collect.index", $res, "redirect");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function studentFeesDetailAPI(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            //START Get Fees pending array
            $request->session()->put('sub_institute_id', $sub_institute_id);
            $request->session()->put('syear', $syear);
            $request->session()->put('student_id', $student_id);
            $new_pending_arr = [];


            $fees_online_link = DB::table('fees_online_maping')
                ->where('syear', $syear)
                ->where('sub_institute_id', $sub_institute_id)
                ->get()->toArray();

            $fees_online_link = json_decode(json_encode($fees_online_link), true);

            $online_link = "";
            if (count($fees_online_link) > 0) {
                $online_link = "http://" . $_SERVER['SERVER_NAME'] . "/fees/online_fees_collect";
            }

            if (isset($fees_data['total_fees'])) {
                $fees_data = $this->getBk($request, $student_id);
                foreach ($fees_data['total_fees'] as $key => $val) {
                    unset($val['bk']);
                    unset($val['paid']);
                    //Set link in PAY NOW
                    if ($online_link != "") {
                        $val['PayNow'] = $online_link;
                    }
                    if ($val['remain'] != 0 && $val['month'] != 'Total') {
                        $new_pending_arr[] = (object)$val;
                    }
                }
            }

            $data['PENDING'] = $new_pending_arr;
            //END Get Fees pending array

            //START Get Fees paid array
            $paid_data = DB::table('fees_collect as c')
                ->selectRaw('c.receipt_no,c.receiptdate,c.payment_mode,c.bank_branch,c.bank_branch,c.bank_name,c.fees_html,
                    c.cheque_date,c.cheque_no,c.cheque_bank_name,SUM(amount) as paid_amount')
                ->where('c.student_id', $student_id)
                ->where('c.syear', $syear)
                ->where('sub_institute_id', $sub_institute_id)
                ->groupBy('receipt_no')->get()->toArray();

            $data['PAID'] = $paid_data;
            //END Get Fees paid array

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function retrieveDataByUserId(Request $request, $user_id, $stud_id)
    {
        $division = $request->input('division');
        $enrollment_no = $user_id;
        $stud_id = $stud_id;
        $name = $request->input('name');
        $mb_no = $request->input('mb_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $receipt_no = $request->input('receipt_no');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $extra_fp = "  AND fp.syear = '" . $syear . "' AND te.syear = '" . $syear . "' AND t.sub_institute_id = '" . $sub_institute_id . "' AND fp.sub_institute_id = '" . $sub_institute_id . "' AND fp.is_deleted = 'N' ";

        $extra_fo = "  AND fo.syear = '" . $syear . "' AND te.syear = '" . $syear . "' AND t.sub_institute_id = '" . $sub_institute_id . "' AND fo.sub_institute_id = '" . $sub_institute_id . "' AND fo.is_deleted = 'N' ";

        if ($division != '') {
            $extra_fp .= " AND te.section_id = '" . $division . "'";
            $extra_fo .= " AND te.section_id = '" . $division . "'";
        }

        if ($stud_id != '') {
            $extra_fp .= " AND te.student_id = '" . $stud_id . "'";
            $extra_fo .= " AND te.student_id = '" . $stud_id . "'";
        }

        if ($enrollment_no != '') {
            $extra_fp .= " AND t.enrollment_no = '" . $enrollment_no . "'";
            $extra_fo .= " AND t.enrollment_no = '" . $enrollment_no . "'";
        }
        if ($name != '') {
            $extra_fp .= " AND (t.first_name = '" . $name . "' OR t.last_name = '" . $name . "' OR t.middle_name = '" . $name . "') ";
            $extra_fo .= " AND (t.first_name = '" . $name . "' OR t.last_name = '" . $name . "' OR t.middle_name = '" . $name . "')";
        }
        if ($mb_no != '') {
            $extra_fp .= " AND t.mobile = '" . $mb_no . "'";
            $extra_fo .= " AND t.mobile = '" . $mb_no . "'";
        }
        if ($from_date != '') {
            $extra_fp .= " AND fp.receiptdate >= '" . $from_date . "'";
            $extra_fo .= " AND fo.receiptdate >= '" . $from_date . "'";
        }

        if ($to_date != '') {
            $extra_fp .= " AND fp.receiptdate <= '" . $to_date . "'";
            $extra_fo .= " AND fo.receiptdate <= '" . $to_date . "'";
        }
        if ($sub_institute_id == 200) {
            $extra_fp .= " AND fp.standard_id=te.standard_id ";
            //$extra_fo .= " AND fo.receiptdate <= '".$to_date."'";
        }

        $sql = "SELECT M.student_id,M.enrollment_no,M.roll_no,M.uniqueid,M.student_name,M.mobile,M.grade,M.standard_name,M.division_name,M.created_date,M.user_name,M.term_id,M.receiptdate,M.receipt_no,M.payment_mode,M.cheque_bank_name,M.bank_branch,M.cheque_no,M.cheque_date,
            (IFNULL(M.amount,0) + IFNULL(N.actual_amountpaid,0)) AS actual_amountpaid
            FROM (
            SELECT fp.student_id,t.enrollment_no,t.roll_no,t.uniqueid,CONCAT_WS(' ',t.first_name,t.middle_name,t.last_name) AS student_name,t.mobile,ac.title AS grade,s.name AS standard_name,d.name AS division_name,fp.created_date,CONCAT_WS(' ',u.first_name,u.last_name) AS user_name,fp.term_id,fp.receiptdate,fp.receipt_no,fp.payment_mode,fp.cheque_bank_name,fp.bank_branch,fp.cheque_no,fp.cheque_date,SUM(IFNULL(fp.amount,0)) AS amount
            FROM tblstudent t
            -- WHERE t.first_name = $name OR t.middle_name = $name OR t.last_name = $name
            INNER JOIN tblstudent_enrollment te ON t.id = te.student_id
            INNER JOIN academic_section ac ON ac.id = te.grade_id
            INNER JOIN standard s ON s.id = te.standard_id
            INNER JOIN division d ON d.id = te.section_id
            INNER JOIN fees_collect fp ON fp.student_id = te.student_id
            LEFT JOIN tbluser u ON fp.created_by = u.id
            WHERE 1=1 $extra_fp
            GROUP BY fp.student_id, fp.receipt_no, fp.syear, fp.receiptdate, fp.payment_mode, fp.cheque_no
            ORDER BY fp.receiptdate ASC, fp.receipt_no ASC) AS M
            LEFT JOIN (
            SELECT fo.student_id, SUM(IFNULL(fo.actual_amountpaid,0)) AS actual_amountpaid
            FROM tblstudent t
            INNER JOIN tblstudent_enrollment te ON t.id = te.student_id
            INNER JOIN academic_section ac ON ac.id = te.grade_id
            INNER JOIN standard s ON s.id = te.standard_id
            INNER JOIN division d ON d.id = te.section_id
            INNER JOIN fees_paid_other fo ON fo.student_id = te.student_id
            WHERE 1=1 $extra_fo
            GROUP BY fo.student_id, fo.reciept_id, fo.syear, fo.receiptdate, fo.payment_mode, fo.cheque_dd_no
            ORDER BY fo.receiptdate ASC, fo.reciept_id ASC) AS N ON M.student_id = N.student_id
            HAVING (M.receiptdate IS NOT NULL)
            ORDER BY M.receiptdate,CAST(M.receipt_no AS SIGNED)";

        $result = DB::select(DB::raw($sql));
        $feesData = json_decode(json_encode($result), true);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $feesData;
        $res['enrollment_no'] = $enrollment_no;

        return $feesData;
    }

}
