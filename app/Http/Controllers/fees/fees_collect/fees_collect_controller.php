<?php

namespace App\Http\Controllers\fees\fees_collect;

use Illuminate\Support\Facades\Validator;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\FeeMonthId;
use function App\Helpers\FeeBreackoff;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\OtherBreackOff;
use function App\Helpers\OtherBreackOffHead;
use function App\Helpers\OtherBreackOfMonth;
use function App\Helpers\OtherBreackOfMonthHead;
use App\Models\fees\map_year\map_year;
use App\Models\school_setup\SchoolModel;
use App\Models\fees\fees_cancel\feesCancelModel;
use App\Models\fees\bank_master\bankmasterModel;
use App\Models\fees\tblfeesConfigModel;
use App\Models\fees\fees_title\fees_title;
use App\Models\settings\templateMasterModel;
use App\Models\fees\fees_collect\fees_collect;
use Illuminate\Support\Facades\DB;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;

class fees_collect_controller extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {         

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        //        $school_data['data'] = $this->getData();
        $school_data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "fees/fees_collect/show", $school_data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    public function show_student()
    {
        //    echo "<pre>";
        //    print_r($_REQUEST);
        //    exit;

        $responce_arr = array();

        $month_arr = FeeMonthId();
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month . $currunt_year;

        $search_ids = array();
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

        // echo '<pre>';
        // print_r($search_ids);

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
        //        $breackoff_join = rtrim($breackoff_join, "OR");
        //        $breackoff_join .= ")";


        $extra_where = "";
        if (isset($_REQUEST['mobile']) && $_REQUEST['mobile'] != '') {
            $extra_where .= " AND s.mobile = '" . $_REQUEST['mobile'] . "'";

            $responce_arr['mobile'] = $_REQUEST['mobile'];
        }
        if (isset($_REQUEST['grno']) && $_REQUEST['grno'] != '') {
            $extra_where .= " AND s.enrollment_no = '" . $_REQUEST['grno'] . "'";
            $responce_arr['grno'] = $_REQUEST['grno'];
        }
        if (isset($_REQUEST['uniqueid']) && $_REQUEST['uniqueid'] != '') {
            $extra_where .= " AND s.uniqueid = '" . $_REQUEST['uniqueid'] . "'";
            $responce_arr['uniqueid'] = $_REQUEST['uniqueid'];
        }        
        if (isset($_REQUEST['grade']) && $_REQUEST['grade'] != '') {
            $grade_val = $_REQUEST['grade'];
            $extra_where .= " AND se.grade_id =  '" . $_REQUEST['grade'] . "'";
        }
        if (isset($_REQUEST['standard']) && $_REQUEST['standard'] != '') {
            $extra_where .= " AND se.standard_id =  '" . $_REQUEST['standard'] . "'";
            $responce_arr['standard'] = $_REQUEST['standard'];
        }
        if (isset($_REQUEST['division']) && $_REQUEST['division'] != '') {
            $extra_where .= " AND se.section_id ='" . $_REQUEST['division'] . "'";
            $responce_arr['division'] = $_REQUEST['division'];
        }
        if (isset($_REQUEST['stu_name']) && $_REQUEST['stu_name'] != '') {
            $extra_where .= " AND (s.first_name LIKE ('%" . $_REQUEST['stu_name'] . "%') OR 
                            s.middle_name LIKE ('%" . $_REQUEST['stu_name'] . "%') OR 
                            s.last_name LIKE ('%" . $_REQUEST['stu_name'] . "%'))";
            $responce_arr['stu_name'] = $_REQUEST['stu_name'];
        }

        $sql = "SELECT s.*,se.syear,se.student_id,se.grade_id,
                se.standard_id,se.section_id,se.student_quota,sq.title AS stu_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number,
                sum(fb.amount)+
                    (select ifnull(sum(fbo.amount),0) 
                     from fees_breakoff_other fbo
                     where fbo.syear = '" . session()->get('syear') . "' AND
                         fbo.student_id = s.id AND   
                          fb.grade_id = se.grade_id AND 
                         fb.standard_id = se.standard_id AND 
                         
                         fbo.sub_institute_id = '" . session()->get('sub_institute_id') . "'     
                         $breackoff_other_join
                    )
                bkoff,
                st.name standard_name,
                d.name as division_name
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                left JOIN division d ON  d.id = se.section_id
                LEFT JOIN student_quota sq ON sq.id = se.student_quota AND sq.sub_institute_id = se.sub_institute_id
                INNER JOIN fees_breackoff fb ON 
                        (fb.syear = '" . session()->get('syear') . "' AND
                         fb.admission_year = s.admission_year AND 
                         fb.quota = se.student_quota AND 
                         fb.grade_id = se.grade_id AND 
                         fb.standard_id = se.standard_id AND 
                         
                         fb.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                         $breackoff_join) 
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "'
                AND se.syear = '" . session()->get('syear') . "' AND se.end_date is null
                $extra_where
                GROUP BY s.id
                HAVING bkoff IS NOT NULL
                ";
        //fb.section_id = se.section_id AND
        //fb.section_id = se.section_id AND


        //        INNER JOIN fees_breakoff_other fbo ON
        //                        (fbo.syear = '" . session()->get('syear') . "' AND
        //                         fbo.student_id = s.id AND
        //                          fb.grade_id = se.grade_id AND
        //                         fb.standard_id = se.standard_id AND
        //                         fb.section_id = se.section_id AND
        //                         fbo.sub_institute_id = '" . session()->get('sub_institute_id') . "'
        //                         $breackoff_other_join)
        $sql = preg_replace('/\n+/', '', $sql);
        // echo $sql;
        // exit;
        $result = DB::select($sql);
        // echo ('<pre>');print_r($result);exit;



        //        $sql = "SELECT s.*,se.syear,se.student_id,se.grade_id,
        //                se.standard_id,se.section_id,se.student_quota,se.start_date,
        //                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
        //                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
        //                se.house_id,se.lc_number,
        //                sum(fc.amount) + SUM(fc.fees_discount) + (select sum(fpo.actual_amountpaid) + SUM(fpo.fees_discount)
        //                     from fees_paid_other fpo
        //                     where fpo.syear = '" . session()->get('syear') . "' AND
        //                         fpo.student_id = s.id AND
        //                         fpo.sub_institute_id = '" . session()->get('sub_institute_id') . "'
        //                         $paid_other_join
        //                    )
        //                paid_amt,
        //                st.name standard_name,
        //                d.name as division_name
        //                FROM tblstudent s
        //                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
        //                INNER JOIN academic_section g ON g.id = se.grade_id
        //                INNER JOIN standard st ON st.id = se.standard_id
        //                INNER JOIN division d ON  d.id = se.section_id
        //                INNER JOIN fees_collect fc ON
        //                        (
        //                         fc.student_id = s.id AND
        //                         fc.sub_institute_id = '" . session()->get('sub_institute_id') . "'
        //                             $fees_join
        //                         )
        //                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "'
        //                AND se.syear = '" . session()->get('syear') . "'
        //                $extra_where
        //                GROUP BY s.id
        //                HAVING paid_amt IS NOT NULL
        //                ";
        //
        $sql = "
                SELECT SUM(amount) paid_amt,student_id id
       FROM(
            select SUM(fc.amount)+SUM(fc.fees_discount) amount,se.student_id
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.syear = '" . session()->get('syear') . "'
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                LEFT JOIN division d ON  d.id = se.section_id
                INNER JOIN fees_collect fc ON 
                        (
                         fc.student_id = s.id AND 
                         fc.is_deleted = 'N' AND
                         fc.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND
                         fc.syear = '" . session()->get('syear') . "' 
                             $fees_join
                        ) 
                
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                GROUP BY s.id
                UNION ALL
                select SUM(fpo.actual_amountpaid)+SUM(fpo.fees_discount) aa,se.student_id
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.syear = '" . session()->get('syear') . "'
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                LEFT JOIN division d ON  d.id = se.section_id
                INNER JOIN fees_paid_other fpo ON 
                    (fpo.student_id = s.id  $paid_other_join)
                WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                GROUP BY s.id
            ) temp_table
            GROUP BY student_id
                
                ";

        //        INNER JOIN fees_breakoff_other fbo ON
        //                        (fbo.syear = '" . session()->get('syear') . "' AND
        //                         fbo.student_id = s.id AND
        //                          fb.grade_id = se.grade_id AND
        //                         fb.standard_id = se.standard_id AND
        //                         fb.section_id = se.section_id AND
        //                         fbo.sub_institute_id = '" . session()->get('sub_institute_id') . "'
        //                         $breackoff_other_join)
        $sql = preg_replace('/\n+/', '', $sql);
        // echo $sql;
        // exit;
        $paid_result = DB::select($sql);
               // echo "<pre>";
               // print_r($result);
               // print_r($paid_result);
               // exit;

        foreach ($result as $id => $arr) {
            $bk_stu_id = $arr->id;
            foreach ($paid_result as $r_id => $r_arr) {
                $pd_stu_id = $r_arr->id;
                if ($bk_stu_id == $pd_stu_id) {
                    $arr->bkoff = $arr->bkoff - $r_arr->paid_amt;
                }
            }
        }


        $responce_arr['stu_data'] = $result;
        // echo 'divya';
        // echo ('<pre>');print_r($responce_arr['stu_data']);
        // echo 'soni';
        // echo ('<pre>');print_r($paid_result);
        // exit;

        if (isset($_REQUEST['type'])) {
            $type = $_REQUEST['type'];
        } else {
            $type = "";
        }

        return \App\Helpers\is_mobile($type, "fees/fees_collect/show", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function pay_fees(Request $request)
    {
        // echo "<pre>";
        // print_r($_REQUEST);
        // exit;

        $fees_data = array();
        foreach ($_REQUEST['fees_data'] as $id => $arr) {
            //            foreach ($arr as $head_id => $val) {
            if ($arr != 0) {
                $fees_data[$id] = $arr;
            }
            //            }
        }
        $_REQUEST['fees_data'] = $fees_data;
               // echo "<pre>";
               // print_r($_REQUEST);
               // exit;

        $stu_arr = session()->get('stu_arr');
        $month_arr = FeeMonthId();
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month . $currunt_year;

        $search_ids = array();
        foreach ($month_arr as $id => $arr) {
            if ($id == $currunt_month_id) {
                $search_ids[] = $id;
                // break;
            } else {
                $search_ids[] = $id;
            }
        }
               // echo "<pre>";
               // print_r($_REQUEST);
               // exit;
        // echo ('<pre>');print_r($stu_arr);exit;
        $reg_bk_off = FeeBreackoff($stu_arr);
        // echo ('<pre>');print_r($reg_bk_off);exit;

        $other_bk_off = OtherBreackOff($stu_arr, $search_ids);
        $other_bk_off_month_wise = OtherBreackOfMonth($stu_arr);
        $other_bk_off_month_head_wise = OtherBreackOfMonthHead($stu_arr, $search_ids);
        $year_arr = FeeMonthId();
        $head_wise_fees = FeeBreakoffHeadWise($stu_arr);

               // echo "reg_bk_off" . "<br>";
               // echo '<pre>';
               // print_r($reg_bk_off);
               // echo "other_bk_off" . "<br>";
               // print_r($other_bk_off);
               // echo "other_bk_off_month_wise" . "<br>";
               // print_r($other_bk_off_month_wise);
               // echo "year_arr" . "<br>";
               // print_r($year_arr);
               // echo "head_wise_fees" . "<br>";
               // print_r($head_wise_fees);
               // echo "other_bk_off_month_head_wise" . "<br>";
               // print_r($other_bk_off_month_head_wise);
               // exit;
        //
        //getting heads of reg fees and all reg breackoff
        $reg_fee_heads = array();
        $reg_fee_bk = array();
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
            //    echo "reg_fee_heads" . "<br>";
            //    print_r($reg_fee_heads);
            //    exit;
        //getting heads of other fee
        $other_fee_heads = array();
        foreach ($_REQUEST['fees_data'] as $id => $vals) {
            if (!in_array($id, $reg_fee_heads)) {
                $other_fee_heads[] = $id;
            }
        }
        //        echo "other_fee_heads" . "<br>";
        //        print_r($other_fee_heads);
        //        echo "reg_fee_bk" . "<br>";
        //        print_r($reg_fee_bk);
        //getting reg fee month_id that we need to pay
        $reg_months_pay = array();
        foreach ($reg_fee_bk as $month_id => $arr) {
            if (in_array($month_id, $_REQUEST['months'])) {
                $reg_months_pay[] = $month_id;
            }
        }

        $oth_months_pay = array();
        foreach ($other_bk_off_month_wise as $month_id => $arr) {
            if (in_array($month_id, $_REQUEST['months'])) {
                $oth_months_pay[] = $month_id;
            }
        }

        //        foreach ($reg_months_pay as $id => $month_id) {
        //        echo "<pre>";
        //        print_r($reg_fee_bk);
        //        exit;
        // echo ('<pre>');print_r($_REQUEST);exit;
        $reg_insert_arr = array();
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
        // echo ('<pre>');
        // print_r($_REQUEST);
        // print_r($reg_insert_arr);
        // exit;
        //        }
        //        echo "reg_months_pay" . "<br>";
        //        print_r($reg_months_pay);
        //        echo "reg_insert_arr" . "<br>";
        //        print_r($reg_insert_arr);
        //        exit;
        //        echo "<pre>";
        $receipt_number = $this->gunrate_receipt_number();
               // print_r($receipt_number);
               // exit;
        // getting all heads with id
        $sql_heds_with_id = "SELECT id,fees_title 
            FROM fees_title WHERE 
            SUB_INSTITUTE_ID = '" . session()->get('sub_institute_id') . "' and
            syear = '" . session()->get('syear') . "'
        ";
        $sql_heds_with_id = preg_replace('/\n+/', '', $sql_heds_with_id);
        $ret_heds_with_id = DB::select($sql_heds_with_id);
        $heds_with_id = array();
        foreach ($ret_heds_with_id as $id => $arr) {
            //            $heds_with_id[$arr->id] = $arr->fees_title;
            $heds_with_id[$arr->fees_title] = $arr->id;
        }

        // echo ('<pre>');
        // print_r($reg_insert_arr);
        // print_r($receipt_number);
        // exit;
        $new_insert_arr = array();
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
        // echo ('<pre>');
        // print_r($reg_insert_arr);
        // print_r($new_insert_arr);
        // exit;
        //        echo "<pre>";
        //        print_r($other_bk_off_month_head_wise
        //        );
        //        exit;

        $oth_insert_arr = array();
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

        //        echo "<pre>";
        //        print_r($oth_insert_arr
        //        );
        //        exit;
        $new_insert_other_arr = array();
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

               // echo "<pre>";
               // print_r($_REQUEST);
               // echo "heads with id";
               // print_r($heds_with_id);
               // print_r($new_insert_arr);
               // print_r($new_insert_other_arr);
               // die;

        $new_insert_arr = $this->add_discount($new_insert_arr, 'fees_collect');
        $new_insert_other_arr = $this->add_discount($new_insert_other_arr, 'fees_paid_other');        
        $new_insert_arr = $this->add_fine($new_insert_arr);
        $new_insert_other_arr = $this->add_fine($new_insert_other_arr);
               // echo "<pre>";
               // print_r($_REQUEST);
               // print_r($new_insert_arr);
               // print_r($new_insert_other_arr);
               // exit;
        //        exit;
        // echo ('<pre>');
        // print_r($new_insert_arr);
        // print_r($_REQUEST);
        // exit;
        $regular_insert_arr = array();
        foreach ($new_insert_arr as $month_id => $arr) 
        {
            foreach ($arr as $r_id => $vals) 
            {
                if(isset($_REQUEST['cheque_date']) && $_REQUEST['cheque_date'] != ''){
                    $cheque_date = $_REQUEST['cheque_date'];
                }else{
                    $cheque_date = $_REQUEST['receiptdate'];                    
                }

                if(isset($_REQUEST['remarks']) && $_REQUEST['remarks'] != ''){
                    $remarks = $_REQUEST['remarks'];
                }else{
                    $remarks = '';                    
                }

                //                if (array_sum($vals) != 0) {
                $receipt_id_arr = explode('_', $r_id);
                $receipt_id = $receipt_id_arr[0];
                $insert_arr = array(
                    'student_id' => $stu_arr[0],
                    'term_id' => $month_id,
                    'syear' => session()->get('syear'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    //'amount' => array_sum($vals),
                    'payment_mode' => $_REQUEST['PAYMENT_MODE'],
                    'created_date' => date('Y-m-d h:i:s'),
                    'bank_branch' => $_REQUEST['bank_branch'],
                    'receiptdate' => $_REQUEST['receiptdate'],
                    'cheque_no' => $_REQUEST['cheque_no'],
                    'cheque_date' => $cheque_date,
                    'cheque_bank_name' => $_REQUEST['bank_name'],
                    'receipt_no' => $receipt_id,
                    'remarks' =>  $remarks,
                    'created_by' => session()->get('user_id')
                );

                $insert_arr = array_merge($insert_arr, $vals);

                $insert_id = DB::table('fees_collect')->insertGetId($insert_arr);
                $regular_insert_arr[] = $insert_id;

                //                }
            }
        }


       
        $other_insert_arr = array();
        foreach ($new_insert_other_arr as $month_id => $arr) 
        {
            foreach ($arr as $r_id => $vals) 
            {
                if(isset($_REQUEST['cheque_date']) && $_REQUEST['cheque_date'] != ''){
                    $cheque_date = $_REQUEST['cheque_date'];
                }else{
                    $cheque_date = $_REQUEST['receiptdate'];                    
                }

                if(isset($_REQUEST['remarks']) && $_REQUEST['remarks'] != ''){
                    $remarks = $_REQUEST['remarks'];
                }else{
                    $remarks = '';                    
                }

                $receipt_id_arr = explode('_', $r_id);
                $receipt_id = $receipt_id_arr[0];
                $insert_arr = array(
                    'student_id' => $stu_arr[0],
                    'month_id' => $month_id,
                    'syear' => session()->get('syear'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    //'actual_amountpaid' => array_sum($vals),
                    'payment_mode' => $_REQUEST['PAYMENT_MODE'],
                    'created_date' => date('Y-m-d h:i:s'),
                    'bank_branch' => $_REQUEST['bank_branch'],
                    'receiptdate' => $_REQUEST['receiptdate'],
                    'cheque_dd_no' => $_REQUEST['cheque_no'],
                    'cheque_dd_date' => $cheque_date,
                    'bank_name' => $_REQUEST['bank_name'],
                    'reciept_id' => $receipt_id,
                    'remarks' => $remarks,
                    'created_by' => session()->get('user_id')
                );

                $insert_arr = $insert_arr + $vals;
                //                echo "<pre>";
                //                print_r($insert_arr);
                //                exit;
                //

                $insert_id = DB::table('fees_paid_other')->insertGetId($insert_arr);
                $other_insert_arr[] = $insert_id;
            }
        }

               // echo "<pre>";
               // print_r($regular_insert_arr);
            //    print_r($other_insert_arr);
        
              
        //getting array ready for insert into fees receipt
        $fees_receipt_insert = array();
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
        // exit;
        // echo '<pre>';
        // print_r($receipt_number);
        // exit;

        $receipt_id_html = '';
        foreach ($receipt_number as $s_order => $val_number)
        {
            if (isset($val_number['used']))
                $receipt_id_html = $val_number['rid'];
        }

        $sub_institute_id = session()->get('sub_institute_id');
        $sql = "select fc.* ,frc.css
        from  fees_config_master fc
        inner join fees_receipt_css frc on frc.receipt_id = fc.fees_receipt_template
        where fc.sub_institute_id = '$sub_institute_id'
        ";
        $sql = preg_replace('/\n+/', '', $sql);
        $fees_config = DB::select($sql);

        $res = array();
        if (count($fees_config)) 
        {

            $receipt_html_with_css = '<style>'.$fees_config[0]->css.'</style>'.$receipt_html;

            $res = array(
                "data" => $receipt_html_with_css,
                "paper" => $fees_config[0]->fees_receipt_template,
                "css" => $fees_config[0]->css,
                "student_id" => $stu_arr[0],
                "receipt_id_html" => $receipt_id_html
            );
        } else {
            $sql = "select frc.css
        from fees_receipt_css frc
        where frc.receipt_id = 'A5'
        ";
            $sql = preg_replace('/\n+/', '', $sql);
            $fees_config = DB::select($sql);

            $receipt_html_with_css = '<style>'.$fees_config[0]->css.'</style>'.$receipt_html;

            $res = array(
                "data" => $receipt_html_with_css,
                "paper" => "A5",
                "css" => $fees_config[0]->css,
                "student_id" => $stu_arr[0],
                "receipt_id_html" => $receipt_id_html
            );
        }
        // echo '<pre>';
        // print_r($res);
        // exit;
        return $res;
    }
    public function store(Request $request)
    {
        //dd($request);
        $res = $this->pay_fees($request);
        // dd($res);
        $type = $request->input('type');
        // return is_mobile($type, "fees/fees_collect/add", $res, "view");
        return is_mobile($type, "fees/fees_collect/receipt_view", $res, "view");
        //        return \App\Helpers\is_mobile($type, "fees_collect.index", $res, "redirect");
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
                $sum = 0;
                $sum = array_sum($arr);
                if ($sum == 0) {
                    unset($fees_arr[$month_id][$receipt_id]);
                }
            }
            if (count($fees_arr[$month_id]) == 0) {
                unset($fees_arr[$month_id]);
            }
        }

        //        foreach ($_REQUEST['discount_data'] as $id => $discount) {
               // echo "<pre>";
               // print_r($fees_arr);
        //print_r($detail_arr);
        //exit;
        
        
        // echo '<pre>';            
        // print_r($_REQUEST['discount_data']);
        // echo array_sum($_REQUEST['discount_data']);
        // print_r($_REQUEST['totalDis']);
        // print_r($fees_arr);
      
        // echo "===========================================================================";

        /** START If Total Discount is there unset regular discount added on 16th Jun **/
        if(isset($_REQUEST['discount_data']) && isset($_REQUEST['totalDis']) && array_sum($_REQUEST['discount_data']) < $_REQUEST['totalDis'] )
        {
            unset($_REQUEST['discount_data']);
        }
        else{
            unset($_REQUEST['totalDis']);    
        }
        /** END If Total Discount is there unset regular discount added on 16th Jun **/

        foreach ($fees_arr as $month_id => $detail_arr) 
        {
            foreach ($detail_arr as $receipt_id => $arr) {
                $fees_arr[$month_id][$receipt_id][$discount_field] = 0;                             
                foreach ($arr as $title => $val) {                                                         
                    if (isset($_REQUEST['discount_data'][$title])) {
                        $dis = 0;
                            
                        if ($val > $_REQUEST['discount_data'][$title] || $val == $_REQUEST['discount_data'][$title]) 
                        {
                            $dis = $_REQUEST['discount_data'][$title];
                            $_REQUEST['discount_data'][$title] = 0;
                            unset($_REQUEST['discount_data'][$title]);
                        } else {
                            // 26/08/2021 Start Added for The Millennium School for Advanced Imprest Collection payment
                            if($val < 0)
                            {
                                $dis = 0;
                                $_REQUEST['discount_data'][$title] = $_REQUEST['discount_data'][$title] - 0;//$val
                            }
                            else
                            {                              
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

        // echo 'dd';
        // echo "<pre>";
        // print_r($fees_arr);
        // die;

        /** START Cumulative Discount code added on 16th Jun **/
        if(isset($_REQUEST['totalDis']) && $_REQUEST['totalDis'] != 0)
        {
            $newdis = $_REQUEST['totalDis'];
            foreach ($fees_arr as $month_id => $detail_arr) {
                foreach ($detail_arr as $receipt_id => $arr) {
                    $soni_val = array_sum($arr);
                    $fees_arr[$month_id][$receipt_id][$discount_field] = 0;                             
                                                                            
                    /* START Cumulative Logic for discount */                                                                                 
                    // echo "<br>soni_val->".$soni_val."<br>";
                    // echo "<br>".$soni_val .">". $newdis."<br>";                
                    if ($soni_val > $newdis)
                    {   
                        // echo "<br>2nd step<br>";
                        $fees_arr[$month_id][$receipt_id][$discount_field] = $newdis;
                        $newdis = 0;
                        // echo "<br>After minus newdis->".$newdis."<br>";
                        // echo "<br>================================<br>";
                    }
                    else
                    {   
                        // echo "<br>3rd step<br>";                            
                        $newdis = $newdis - $soni_val;
                        $fees_arr[$month_id][$receipt_id][$discount_field] = $soni_val;
                        // echo "<br>After minus newdis->".$newdis."<br>";
                        // echo "<br>================================<br>";
                    }                        
                    /* END Cumulative Logic for discount */                                                                              
                }
            }
        }
        /** END Cumulative Discount code added on 16th Jun **/
                            
       //print_r($fees_arr);
       //die;
        
        foreach ($fees_arr as $month_id => $detail_arr) {
            foreach ($detail_arr as $receipt_id => $arr) {
                $sum = 0;
                foreach ($arr as $id => $val) {
                    if ($id != $discount_field) {
                        $sum = $sum + $val;
                    } else {
                        $sum = $sum - $val;
                    }
                }
                $fees_arr[$month_id][$receipt_id][$total_field] = $sum;
            }
        }
               // echo "<pre>";
               // print_r($fees_arr);
               // exit;
        return $fees_arr;
    }

    public function add_fine($fees_arr)
    {
        $discount_field = "";
        $total_field = "";

        foreach ($_REQUEST['fine_data'] as $id => $val) {
            if ($val == 0) {
                unset($_REQUEST['fine_data'][$id]);
            }
        }

        if (count($_REQUEST['fine_data']) > 0) {
            foreach ($fees_arr as $month_id => $detail_arr) {
                foreach ($detail_arr as $receipt_id => $arr) {
                    $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                    foreach ($arr as $title => $val) {
                        if (isset($_REQUEST['fine_data'][$title])) {
                            $fin = $_REQUEST['fine_data'][$title];
                            if(isset($_REQUEST['hidden_cheque_return_charges'])){
                                 $fin = $fin + $_REQUEST['hidden_cheque_return_charges'];
                            }
                            if (!isset($fees_arr[$month_id][$receipt_id]['fine'])) {
                                $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                            }
                            $fees_arr[$month_id][$receipt_id]['fine'] = $fees_arr[$month_id][$receipt_id]['fine'] + $fin;
                            unset($_REQUEST['fine_data'][$title]);
                            unset($_REQUEST['hidden_cheque_return_charges']);
                        }
                    }
                }
            }
            // if ($_REQUEST['hidden_cheque_return_charges'] > 0) 
            // {
            //     $fees_arr[$month_id][$receipt_id]['fine'] += $_REQUEST['hidden_cheque_return_charges'];
            // }
        }else{

            // 30-12-2021 START for display fine total value in fees receipt if indiviual fine not given
            foreach ($fees_arr as $month_id => $detail_arr) 
            {
                foreach ($detail_arr as $receipt_id => $arr) 
                {
                    $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                    if (isset($_REQUEST['fees_data']['fine'])) 
                    {                             
                        $fees_arr[$month_id][$receipt_id]['fine'] = $_REQUEST['fees_data']['fine'];
                        unset($_REQUEST['fees_data']['fine']);
                    }                    
                }
            }
            // 30-12-2021 END for display fine total value in fees receipt if indiviual fine not given    
        }

        //        echo "<pre>";
        //        print_r($_REQUEST['fine_data']);
        //        exit;
        //        if ($insert_table == "fees_collect") {
        //            $discount_field = "fees_discount";
        //            $total_field = "amount";
        //        } else {
        //            $discount_field = "fees_discount";
        //            $total_field = "actual_amountpaid";
        //        }
        //        foreach ($_REQUEST['discount_data'] as $id => $discount) {
        //        echo "<pre>";
        //        print_r($fees_arr);
        //print_r($detail_arr);
        //exit;
        //        echo "Fess array without process ";
        //        print_r($fees_arr);
        //        }
        //        foreach ($fees_arr as $month_id => $detail_arr) {
        //            foreach ($detail_arr as $receipt_id => $arr) {
        //                $sum = 0;
        //                foreach ($arr as $id => $val) {
        //                    if ($id != $discount_field) {
        //                        $sum = $sum + $val;
        //                    } else {
        //                        $sum = $sum - $val;
        //                    }
        //                }
        //                $fees_arr[$month_id][$receipt_id][$total_field] = $sum;
        //            }
        //        }
        //        echo "<pre>";
        //        print_r($fees_arr);
        //        exit;
        return $fees_arr;
    }

    public function gunrate_receipt_number()
    {
               // echo "<pre>";
               // echo 'dkk ';
               // print_r($_REQUEST);
               // exit;
        $fc_syear = "";
        if(session()->get('sub_institute_id') != 47){
            $fc_syear = " AND fr.syear = '" . session()->get('syear') . "' ";
        }

        $sql = "SELECT *,GROUP_CONCAT(fees_head_id) heads
            FROM fees_receipt_book_master
            WHERE grade_id = '" . $_REQUEST['grade_id'] . "'
            AND standard_id = '" . $_REQUEST['standard_id'] . "'
            AND syear = '" . session()->get('syear') . "'
            AND sub_institute_id = '" . session()->get('sub_institute_id') . "'
            GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number";
            // die;
        $sql = preg_replace('/\n+/', '', $sql);
           // echo $sql;
        $result = DB::select($sql);
        // echo "<pre>";
        // print_r($result);
        // exit;

        $id_arr = array();
        foreach ($result as $id => $arr) 
        {

            if(isset($arr->receipt_prefix) && $arr->receipt_prefix != '')
            {
                $sub_string_count = (strlen($arr->receipt_prefix) + 1);

                $sql = "SELECT ifnull(max(cast(fr.RECEIPT_ID_" . $arr->sort_order . " as UNSIGNED)),".$arr->last_receipt_number.") as rid1,MAX(CAST(SUBSTRING(fr.RECEIPT_ID_" . $arr->sort_order . ",".$sub_string_count.") AS INT)) as rid
                FROM fees_receipt fr
                LEFT JOIN fees_collect fc ON fc.receipt_no = fr.RECEIPT_ID_" . $arr->sort_order . "
                LEFT JOIN fees_paid_other fo ON fo.reciept_id = fr.RECEIPT_ID_" . $arr->sort_order . "
                WHERE fr.SUB_INSTITUTE_ID = '" . session()->get('sub_institute_id') . "' $fc_syear
                ";//STANDARD = '" . $arr->standard_id . "' AND 
                $sql = preg_replace('/\n+/', '', $sql);
                $result_id = DB::select($sql);

                $rid = $arr->receipt_prefix.($result_id[0]->rid + 1);

                $id_arr[$arr->sort_order]['heds'] = $arr->heads;
                $id_arr[$arr->sort_order]['rid'] = $rid;
            }
            else
            {
                // $sql = "SELECT ifnull(max(cast(fr.RECEIPT_ID_" . $arr->sort_order . " as UNSIGNED)),".$arr->last_receipt_number.") rid
            // FROM fees_receipt fr
            // INNER JOIN fees_collect fc ON fc.receipt_no = fr.RECEIPT_ID_" . $arr->sort_order . " AND fc.sub_institute_id = fr.SUB_INSTITUTE_ID
            // WHERE fr.SUB_INSTITUTE_ID = '" . session()->get('sub_institute_id') . "' $fc_syear
            // ";//STANDARD = '" . $arr->standard_id . "' AND 
                $sql = "SELECT ifnull(max(cast(fr.RECEIPT_ID_" . $arr->sort_order . " as UNSIGNED)),".$arr->last_receipt_number.") as rid
                FROM fees_receipt fr
                LEFT JOIN fees_collect fc ON fc.receipt_no = fr.RECEIPT_ID_" . $arr->sort_order . "
                LEFT JOIN fees_paid_other fo ON fo.reciept_id = fr.RECEIPT_ID_" . $arr->sort_order . "
                WHERE fr.SUB_INSTITUTE_ID = '" . session()->get('sub_institute_id') . "' $fc_syear
                ";//STANDARD = '" . $arr->standard_id . "' AND 
                $sql = preg_replace('/\n+/', '', $sql);
                $result_id = DB::select($sql);
                $id_arr[$arr->sort_order]['heds'] = $arr->heads;
                $id_arr[$arr->sort_order]['rid'] = $result_id[0]->rid + 1;
            }   

            
        }
       // echo "<pre>";
       // print_r($id_arr);
       // exit;

        return $id_arr;
        //        echo "<pre>";
        //        echo "<pre>";
        //        print_r($result);
        //        exit;
    }

    public function gunrate_receipt($receipt_id, $receipt_arr, $id_heads)
    {        

        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');

        $month_name = '';
        foreach ($_REQUEST['months'] as $id => $arr) {
            $y = $arr / 10000;
            $month = (int) $y;
            $year = substr($arr,-4);
            $month_name .= $months[$month]. "/".$year.',';
        }
        $month_name = substr($month_name, 0, -1);
        // $new_month_name .= ')';

        // echo '<pre>';
        // echo $month_name;
        // die;

        $sql = "select fc.* 
            from  fees_collect fc
            inner join fees_receipt fr on find_in_set(fc.id,fr.FEES_ID)
            where fr.id = '$receipt_id'
            ";
        $sql = preg_replace('/\n+/', '', $sql);
        $fees_paid = DB::select($sql);

        $sql = "select fc.* 
            from  fees_paid_other fc
            inner join fees_receipt fr on find_in_set(fc.id,fr.OTHER_FEES_ID)
            where fr.id = '$receipt_id'
            ";
        $sql = preg_replace('/\n+/', '', $sql);
        $other_fees_paid = DB::select($sql);

        $sql_heds_with_id = "SELECT * 
                FROM fees_title WHERE 
                SUB_INSTITUTE_ID = '" . session()->get('sub_institute_id') . "' and 
                syear = '" . session()->get('syear') . "'    
            ";
        $sql_heds_with_id = preg_replace('/\n+/', '', $sql_heds_with_id);
        $ret_heds_with_id = DB::select($sql_heds_with_id);

        $other_fees_heads = array();
        $reg_fees_heads = array();

        foreach ($ret_heds_with_id as $id => $arr) {
            if ($arr->fees_title_id == '1') {
                $other_fees_heads[] = $arr;
            } else {
                $reg_fees_heads[] = $arr;
            }
        }

        $fees_arr = array();
        $insert_html_ids = array();
        foreach ($receipt_arr as $sort_order => $arr) {
            $heads_arr = explode(',', $arr['heds']);
            $insert_html_ids[$sort_order] = array();
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
                            $total = $total + $arrs->$head_name;
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
                            $total = $total + $arrs->$head_name;
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
                    $ids = implode(',', $detai_arr);
                    $sql = "
                             select SUM(fc.fees_discount) amount,SUM(fc.fine) fine_amount
                                 FROM tblstudent s
                                 INNER JOIN fees_collect fc ON 
                                         (
                                          fc.student_id = s.id AND 
                                          fc.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                                         ) 
                                 WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                                AND fc.id in ($ids) 
                            ";
                    $sql = preg_replace('/\n+/', '', $sql);
                    $paid_result = DB::select($sql);
                    $total_discount = $total_discount + $paid_result[0]->amount;
                    $total_fine = $total_fine + $paid_result[0]->fine_amount;
                } else {
                    $ids = implode(',', $detai_arr);
                    $sql = "
                            select SUM(fpo.fees_discount) amount,SUM(fpo.fine) fine_amount
                            FROM tblstudent s
                            INNER JOIN fees_paid_other fpo ON 
                                (fpo.student_id = s.id)
                            WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                            AND fpo.id in ($ids) 
                            ";
                    $sql = preg_replace('/\n+/', '', $sql);
                    $paid_result = DB::select($sql);
                    $total_discount = $total_discount + $paid_result[0]->amount;
                    $total_fine = $total_fine + $paid_result[0]->fine_amount;
                }
            }
            foreach ($fees_arr as $sort_order_id => $arr) {
                $order_id = explode('_', $sort_order_id);
                if ($order_id[1] == $sort_order) {
                    $fees_arr[$sort_order_id]['Fine'] = $total_fine;

                    $fees_arr[$sort_order_id]['Discount'] = $total_discount;
                }
            }
            //            $insert_html_ids[$sort_order]['Discount'] = $total_discount;
        }
        //        echo "<pre>";
        //        print_r($insert_html_ids);
        //        print_r($fees_arr);
        //        exit;
        //removing all balnk array
        $new_fees_arr = array();
        foreach ($fees_arr as $id => $arr) {
            foreach ($arr as $head_id => $amount) {
                if ($amount != 0) {
                    $new_fees_arr[$id][$head_id] = $amount;
                }
            }
        }
        foreach ($new_fees_arr as $id => $arr) {
            if (count($arr) == 0) {
                unset($new_fees_arr[$id]);
            }
        }
        $fees_arr = $new_fees_arr;


        // 31/03/2021 - START FOR making cumulative fees recepit array  
        $get_cumulative_sql = "SELECT id,display_name,cumulative_name,append_name FROM fees_title WHERE sub_institute_id = '".session()->get('sub_institute_id')."' AND cumulative_name IS NOT NULL";
        $get_cumulative_result = DB::select($get_cumulative_sql);

        $get_cumulative_result = array_map(function ($value) {
            return (array)$value;
        }, $get_cumulative_result);

        $cumulative_arr = $append_arr = array();
        foreach ($get_cumulative_result as $key => $value) {
            $cumulative_arr[$value['display_name']] = $value['cumulative_name'];
            $append_arr[$value['display_name']] = $value['append_name'];
        }
        // 31/03/2021 - END FOR making cumulative fees recepit array

        $sql = "SELECT *,GROUP_CONCAT(fees_head_id) heads
            FROM fees_receipt_book_master
            WHERE grade_id = '" . $_REQUEST['grade_id'] . "'
            AND standard_id = '" . $_REQUEST['standard_id'] . "'
            AND syear = '" . session()->get('syear') . "'
            AND sub_institute_id = '" . session()->get('sub_institute_id') . "'
            GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number";
        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql);

        $sub_institute_id = session()->get('sub_institute_id');
        $final_html = "";

        foreach ($fees_arr as $id => $arr) {

            // echo '<pre>';
            // print_r($arr);
            // print_r($cumulative_arr);
            // die;
            $id_arr = explode('_', $id);
            $RECEIPT_NO = $id_arr[0];
            $sort_order = $id_arr[1];

            $receipt_book_arr = array();
            foreach ($result as $temp_id => $receipt_detail) {
                if ($sort_order == $receipt_detail->sort_order) {
                    $receipt_book_arr = $receipt_detail;
                }
            }           

            $image_path1 = "/storage/fees/" . $receipt_book_arr->receipt_logo;
            $image_path = '<img class="logo" src="'.$image_path1.'" alt="SCHOOL LOGO">';
            
            // $recHtml = '
            //         <table class="fees-receipt" border-collapse="collapse" style="margin:0 auto;" width="100%">
            //         <tbody>
            //             <tr class="double-border">
            //                 <td class="logo-width" align="left">
            //     ';
            // $recHtml .= '    <img class="logo" src="' . $image_path . '" alt="SCHOOL LOGO">';
            // $recHtml .= '</td>';
            // $recHtml .= '<td colspan="3" align="center">    ';
            // if ($receipt_book_arr->receipt_line_1 != '') {
            //     $recHtml .= '   <span class="ma-hd">' . $receipt_book_arr->receipt_line_1 . '</span><br>';
            // }
            // if ($receipt_book_arr->receipt_line_2 != '') {
            //     $recHtml .= '   <span class="sc-hd">' . $receipt_book_arr->receipt_line_2 . '</span><br>';
            // }
            // if ($receipt_book_arr->receipt_line_3 != '') {
            //     $recHtml .= '   <span class="rg-hd">' . $receipt_book_arr->receipt_line_3 . '</span><br>';
            // }
            // if ($receipt_book_arr->receipt_line_4 != '') {
            //     $recHtml .= '   <span class="rg-hd">' . $receipt_book_arr->receipt_line_4 . '</span><br>';
            // }
            // $recHtml .= '</td>';
            // $recHtml .= '</tr>';
            // $recHtml .= '<tr>';
            // $recHtml .= '<td class="mg-top" colspan="4" align="center" style="padding-bottom:20px;">';
            // $recHtml .= '   <label class="receipt-hd">Fee Receipt</label>';
            // $recHtml .= '</td>';
            // $recHtml .= '</tr>';

            $syear1 = session()->get('syear');
            $syear2 = $syear1 + 1;
            $edu_year = "$syear1-$syear2";

            // $recHtml .= '<tr>';
            // if($sub_institute_id == 198)
            // {
            //     $recHtml .= '   <td align="left" colspan="2">';
            //     $recHtml .= '       Admission No.: <label><b>' . $_REQUEST['uniqueid'] . '</b></label>';
            //     $recHtml .= '   </td>';
            // }else{
            //     $recHtml .= '   <td align="left" colspan="2">';
            //     $recHtml .= '       UniqueId/Adm.No.: <label><b>' . $_REQUEST['uniqueid'] . '</b></label>';
            //     $recHtml .= '   </td>';
            // }

            // if($sub_institute_id == 198)
            // {
            //     $recHtml .= '   <td align="right" colspan="2">';
            //     $recHtml .= '       Session : <label><b>' . $edu_year . '</b></label>';
            //     $recHtml .= '   </td>';
            // }else{
            //     $recHtml .= '   <td align="right" colspan="2">';
            //     $recHtml .= '       Edu Year/Session : <label><b>' . $edu_year . '</b></label>';
            //     $recHtml .= '   </td>';
            // }

            
            // $recHtml .= '</tr>';

            // $recHtml .= '<tr>';
            // $recHtml .= '   <td align="left" colspan="2" style="white-space:nowrap;">';
            // $recHtml .= '       Receipt No. : <label><b>' . $RECEIPT_NO . '</b></label>';
            // $recHtml .= '   </td>';
            // $recHtml .= '   <td align="right" colspan="2">';
            // $recHtml .= '       Date : <label><b>' . date("d-m-Y", strtotime($_REQUEST['receiptdate'])) . '</b></label>';
            // $recHtml .= '   </td>';
            // $recHtml .= '</tr>';

            // $recHtml .= '<tr>';
            // $recHtml .= '   <td align="left" colspan="2">';
            // $recHtml .= '       Name : <label><b>' . $_REQUEST['full_name'] . '</b></label>';
            // $recHtml .= '   </td>';
            // if($sub_institute_id != 198)
            // {
            //     $recHtml .= '   <td align="right" colspan="2">';
            //     $recHtml .= '       Gr.No : <label><b>' . $_REQUEST['enrollment'] . '</b></label>';
            //     $recHtml .= '   </td>'; 
            // }
            // $recHtml .= '</tr>';

            // $recHtml .= '<tr>';
            // $recHtml .= '   <td align="left" colspan="2">';
            // $recHtml .= '       Std/Div. : <label><b>' . $_REQUEST['std_div'] . '</b></label>';
            // $recHtml .= '   </td>';
            // $recHtml .= '   <td align="right" colspan="2">';
            // $recHtml .= '       Mobile : <label><b>' . $_REQUEST['mobile'] . '</b></label>';
            // $recHtml .= '   </td>';            
            // $recHtml .= '</tr>';

            // $recHtml .= '<tr>';
            // $recHtml .= '   <td colspan="4" valign="top">';
            // $recHtml .= '       <table class="particulars" width="100%" border="0">';
            // $recHtml .= '       <tr>';
            // $recHtml .= '               <td colspan="3"><b>Fee Description</b></td>';
            // $recHtml .= '               <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>  ';
            // $recHtml .= '           </tr>';

            $rwspan = count($fees_arr);
            $recTotal = 0;
            //            echo "<pre>";
            //            print_r($fees_arr);
            //            exit;
            //            foreach ($fees_arr as $pkey => $temp_arr) {
            foreach ($arr as $key => $pval) {
                if ($key == 'Discount') {
                    $recTotal = $recTotal - $pval;
                } else {
                    $recTotal = $recTotal + $pval;
                }
            }
            //            }
            //            foreach ($fees_arr as $temp_id => $temp_arr) {
            
            $fees_head_content = '<table class="particulars" width="100%" border="0">       
               <tbody><tr>               
                  <td colspan="3"><b>Description</b></td>               
                  <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>        
               </tr>';
             
            // 31/03/2021 START for Cumulative Fees Receipt

            if(count($cumulative_arr) > 0)
            {                
                $arrnew = $appendnew = array();                
                foreach ($arr as $pkey => $pval) 
                {             
                    if(array_key_exists($pkey,$cumulative_arr))
                    {
                        $newkey = $cumulative_arr[$pkey];

                        if(array_key_exists($newkey,$arrnew))
                        {                        
                            $arrnew[$newkey] = $arrnew[$newkey] + $pval;
                            $appendnew[$newkey][] = $append_arr[$pkey];                                                    
                        }
                        else
                        {
                            $arrnew[$newkey] = $pval;
                            $appendnew[$newkey][] = $append_arr[$pkey];                                
                        }                        
                    }
                    else //for discount ,fines and other types
                    {
                        $arrnew[$pkey] = $pval;
                    }
                }                
                $arr = $arrnew;                                 
            }
            
            // 31/03/2021 END for Cumulative Fees Receipt
            // echo '<pre>';
            // print_r($arr);
            // die;
           
            foreach ($arr as $pkey => $pval) 
            {   
                //  31/03/2021 - Start For Cumulative name          
                if(isset($appendnew[$pkey])){
                    $append_name = implode(",",$appendnew[$pkey]);
                    if($append_name != "")
                    {
                        $pkey = $pkey.' ('.$append_name.') ';    
                    }                    
                }   
                //  31/03/2021 - End For Cumulative name 

                //START Added on 16th june 2021
                if($pkey == 'Discount')
                {
                    $minus_sign = "-";
                }
                else
                {
                    $minus_sign = "";    
                }
                //END Added on 16th june 2021

                $fees_head_content .= '<tr>';
                $fees_head_content .= '  <td colspan="3" align="left">' . $pkey . '</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                $fees_head_content .= '  <td align="right">' . $minus_sign.$pval . '</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                $fees_head_content .= '</tr>';

                // $recHtml .= '           <tr>';
                // $recHtml .= '               <td colspan="3" align="left">' . $pkey . '</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                // $recHtml .= '               <td align="right">' . $pval . '</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                // $recHtml .= '           </tr>';                            

            }
            $fees_head_content .= '<tr>               
                  <td align="left" colspan="3"><b>Total</b></td>       
                  <td align="right"><b>&lt;&lt;grand_total&gt;&gt;</b></td>           
               </tr>        
            </tbody></table>';
            //            }
            //            $recHtml .= '               <td align="right" rowspan="' . $rwspan . '">' . $recTotal . '</td>';
            
            // $recHtml .= '           <tr>';
            // $recHtml .= '               <td align="right" colspan="3">Total</td>';
            // $recHtml .= '               <td align="right" >' . $recTotal . '</td>';
            // $recHtml .= '           </tr>';
            // $recHtml .= '       </table>';
            // $recHtml .= '   </td>';
            // $recHtml .= '</tr>';

            $total_amount_in_words = ucwords($this->convert_number_to_words($recTotal));
            if ($total_amount_in_words != "") {
                $total_amount_in_words_str = "Rupees " . $total_amount_in_words . " Only";
            } else {
                $total_amount_in_words_str = "";
            }

            // $recHtml .= '<tr>';
            // $recHtml .= '   <td colspan="4">';
            // $recHtml .= '       <label><b>In Words : </b></label>';
            // $recHtml .= '       <span>' . $total_amount_in_words_str . '</span>';
            // $recHtml .= '   </td>';
            // $recHtml .= '</tr>';

            //            if ($PAYMENT_MODE == 1) {
            //                $payMethod = 'Cash';
            //            } else if ($PAYMENT_MODE == 2) {
            //                $payMethod = 'Cheque';
            //            } else if ($PAYMENT_MODE == 3) {
            //                $payMethod = 'DD';
            //            } else if ($PAYMENT_MODE == 4) {
            //                $payMethod = 'Other';
            //            }
            $payMethod = $_REQUEST['PAYMENT_MODE'];
            // $REMARKS = $_REQUEST['remarks'];
            // $recHtml .= '<tr>';
            // if ($REMARKS != '' && $REMARKS != '-') {
            //     $recHtml .= '   <td colspan="4">';
            // } else {
            //     $recHtml .= '   <td colspan="4" class="padding">';
            // }
            // $recHtml .= '       <label><b>Payment By : </b></label>';
            if ($payMethod == '') {
                //$recHtml .= '       <span><u>' . $payMethod . '</u></span>';
                $payment_mode = $payMethod;
            } else {
                //$recHtml .= '       <span><u>' . $payMethod . '</u> ' . strtoupper($_REQUEST['bank_name']) . ' - ' . $_REQUEST['cheque_no'] . '</span>';
                $payment_mode = $payMethod . strtoupper($_REQUEST['bank_name']) . ' - ' . $_REQUEST['cheque_no'];
            }
            // $recHtml .= '   </td>';
            // $recHtml .= '</tr>';

            if (isset($_REQUEST['remarks']) && $_REQUEST['remarks'] != '' && $_REQUEST['remarks'] != '-')
            {
                $discount_remarks = $_REQUEST['remarks'];
            }else{
                $discount_remarks = '';

            }


            // $FEES_NOTE = "THIS IS COMPUTER GENERATED RECEIPT.";
            // $recHtml .= '<tr>';
            // $recHtml .= '   <td colspan="3"><b>' . $FEES_NOTE . '</b></td>';
         
            //     $recHtml .= '   <td class="logo-width"><label style="text-align:center;">' . session()->get('name') . '<br>Signature</label></td>';
          
            // $recHtml .= '</tr>';

            // $recHtml .= '</table><br>';



            // START Dynamic Template Logic
            $tData = DB::select('SELECT * FROM template_master WHERE  module_name ="Fees" AND
                sub_institute_id = IFNULL(
                    (
                        SELECT sub_institute_id FROM template_master WHERE module_name ="Fees" AND 
                        sub_institute_id = "'.session()->get('sub_institute_id').'"
                    ),0
                    )');
            $tData = json_decode(json_encode($tData),true);

            $uniqueid = isset($_REQUEST['uniqueid']) ? $_REQUEST['uniqueid'] : '-';
            $enrollment = isset($_REQUEST['enrollment']) ? $_REQUEST['enrollment'] : '-';
            
            $html_content = $tData[0]['html_content'];
            
            $html_content = str_replace(htmlspecialchars("<<receipt_logo>>"), $image_path, $html_content);
            if ($receipt_book_arr->receipt_line_1 != '') {                
                $html_content = str_replace(htmlspecialchars("<<receipt_line_1>>"), $receipt_book_arr->receipt_line_1, $html_content);
            }
            if ($receipt_book_arr->receipt_line_2 != '') {
                $html_content = str_replace(htmlspecialchars("<<receipt_line_2>>"), $receipt_book_arr->receipt_line_2, $html_content);
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $html_content = str_replace(htmlspecialchars("<<receipt_line_3>>"), $receipt_book_arr->receipt_line_3, $html_content);
            }
            if ($receipt_book_arr->receipt_line_4 != '') {
                $html_content = str_replace(htmlspecialchars("<<receipt_line_4>>"), $receipt_book_arr->receipt_line_4, $html_content);
            }            
            
            $html_content = str_replace(htmlspecialchars("<<admission_number_value>>"), $uniqueid, $html_content);
            $html_content = str_replace(htmlspecialchars("<<receipt_year_value>>"), $edu_year, $html_content);

            $html_content = str_replace(htmlspecialchars("<<receipt_number_value>>"), $RECEIPT_NO, $html_content);            
            $html_content = str_replace(htmlspecialchars("<<receipt_date_value>>"), date("d-m-Y", strtotime($_REQUEST['receiptdate'])), $html_content);            
            
            $html_content = str_replace(htmlspecialchars("<<student_name_value>>"), $_REQUEST['full_name'], $html_content);                        
            $html_content = str_replace(htmlspecialchars("<<student_enrollment_value>>"), $enrollment, $html_content);
            
            $html_content = str_replace(htmlspecialchars("<<student_standard_value>>"), $_REQUEST['std_div'], $html_content);
            $html_content = str_replace(htmlspecialchars("<<student_mobile_value>>"), $_REQUEST['mobile'], $html_content);  

            $html_content = str_replace(htmlspecialchars("<<fees_months_display>>"), $month_name, $html_content);   
                     
            $html_content = str_replace(htmlspecialchars("<<fees_head_content>>"), $fees_head_content, $html_content);            
            $html_content = str_replace(htmlspecialchars("<<grand_total>>"), $recTotal, $html_content);  

            $html_content = str_replace(htmlspecialchars("<<total_amount_in_words>>"), $total_amount_in_words_str, $html_content);            
            $html_content = str_replace(htmlspecialchars("<<payment_mode>>"), $payment_mode, $html_content);            
            $html_content = str_replace(htmlspecialchars("<<discount_remarks>>"), $discount_remarks, $html_content);            
            $html_content = str_replace(htmlspecialchars("<<admin_user>>"),session()->get('name'), $html_content);            
                    
            $recHtml = $html_content;            
            // END Dynamic Template Logic

            $sArr = array('"', "'");
            $rArr = array('\"', "\'");
            foreach ($insert_html_ids as $sort_order_id => $other_reg) {
                if ($sort_order == $sort_order_id) {
                    foreach ($other_reg as $identifiyer => $vals) {
                        if ($identifiyer == "OTHER") {
                            $upsql = "UPDATE 
                            fees_paid_other SET 
                            paid_fees_html = '" . str_replace($sArr, $rArr, $recHtml) . "' 
                            WHERE id IN (" . implode(',', $vals) . ")";
                        } else {
                            $upsql = "UPDATE 
                            fees_collect SET 
                            fees_html = '" . str_replace($sArr, $rArr, $recHtml) . "' 
                            WHERE id IN (" . implode(',', $vals) . ")";
                        }
                        DB::statement($upsql);
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
        $dictionary = array(
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
            1000000000000000000 => 'quintillion'
        );

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
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
                $tens = ((int) ($number / 10)) * 10;
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
                $numBaseUnits = (int) ($number / $baseUnit);
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
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

    public function get_receipt_id()
    {
        $sql = "select ifnull(max(receipt_no),1)+1 maxid from fees_collect 
                    where sub_institute_id = " . session()->get('sub_institute_id') . "
                ";
        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql);

        return $result[0]->maxid;

        //        echo "<pre>";
        //        print_r($result);
        //        exit;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function PaidUnpaid(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 200);
        }
        $response = array('response' => '', 'status' => '0', 'message' => 'Data Not Found.');
        $validator =  Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'syear' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $student_id = $_REQUEST['student_id'];
            $syear = $_REQUEST['syear'];

            // $month_arr = FeeMonthId();

            $data = map_year::where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
            ])->get()->toArray();
            if (!$data) {
                $response['response'] = array("year_error" => array("Maping Year Error."));
                return $response;
                exit;
            }

            // echo ('<pre>');print_r($data);exit;
            $start_month = $data[0]['from_month'];
            $end_month = $data[0]['to_month'];

            $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
            $months_arr = array();
            // $syear = session()->get('syear');

            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
                if ($start_month == 12) {
                    $start_month = 0;
                    $syear = $syear + 1;
                }
                $start_month = $start_month + 1;
            }
            // echo ('<pre>');print_r($months_arr);exit;
            $month_arr = $months_arr;
            $responce_arr = array();

            // $month_arr = FeeMonthId();
            $currunt_month = date('m');
            $currunt_year = date('Y');
            $currunt_month_id = $currunt_month . $currunt_year;

            $search_ids = array();
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
            //        $breackoff_join = rtrim($breackoff_join, "OR");
            //        $breackoff_join .= ")";


            $extra_where = "";
            if (isset($_REQUEST['student_id']) && $_REQUEST['student_id'] != '') {
                $extra_where .= " AND s.id = '" . $_REQUEST['student_id'] . "'";

                // $responce_arr['mobile'] = $_REQUEST['mobile'];
            }


            $sql = "SELECT s.*,se.syear,se.student_id,se.grade_id,
                    se.standard_id,se.section_id,se.student_quota,se.start_date,
                    se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,
                    se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                    se.house_id,se.lc_number,
                    sum(fb.amount)+
                        (select ifnull(sum(fbo.amount),0) 
                         from fees_breakoff_other fbo
                         where fbo.syear = '" .  $_REQUEST['syear'] . "' AND
                             fbo.student_id = s.id AND   
                              fb.grade_id = se.grade_id AND 
                             fb.standard_id = se.standard_id AND 
                             
                             fbo.sub_institute_id = '" . $sub_institute_id . "'     
                             $breackoff_other_join
                        )
                    bkoff,
                    st.name standard_name,
                    d.name as division_name
                    FROM tblstudent s
                    INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                    INNER JOIN academic_section g ON g.id = se.grade_id
                    INNER JOIN standard st ON st.id = se.standard_id
                    left JOIN division d ON  d.id = se.section_id
                    INNER JOIN fees_breackoff fb ON 
                            (fb.syear = '" .  $_REQUEST['syear'] . "' AND
                             fb.admission_year = s.admission_year AND 
                             fb.quota = se.student_quota AND 
                             fb.grade_id = se.grade_id AND 
                             fb.standard_id = se.standard_id AND 
                             
                             fb.sub_institute_id = '" . $sub_institute_id . "' 
                             $breackoff_join) 
                    WHERE s.sub_institute_id = '" . $sub_institute_id . "'
                    AND se.syear = '" .  $_REQUEST['syear'] . "'
                    $extra_where
                    GROUP BY s.id
                    HAVING bkoff IS NOT NULL
                    ";

            $sql = preg_replace('/\n+/', '', $sql);
            // echo $sql;
            // exit;
            $result = DB::select($sql);
            if (!$result) {
                $response['response'] = array("bf_error" => array("No Breackoff Found."));
                return $response;
                exit;
            }
            // echo ('<pre>');print_r($result);exit;


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
            //echo $sql;
            //exit;
            $paid_result = DB::select($sql);

            $return_data = array(
                "student_id" => $student_id,
            );
            $return_data['breack_off_amount'] = $result[0]->bkoff;
            if ($paid_result) {
                $return_data['paid_amount'] = $paid_result[0]->paid_amt;
            } else {
                $return_data['paid_amount'] = 0;
            }
            $return_data['unpaid_amount'] =  $return_data['breack_off_amount'] - $return_data['paid_amount'];

            $response['response'] = $return_data;
            $response['message'] = "Sucsess";
            $response['status'] = '1';
        }

        return json_encode($response);

        exit;
    }


    public function getOnlinebk(Request $request, $sub_institute_id, $syear, $student_id)
    {
        $request->session()->put('sub_institute_id', $sub_institute_id);
        $request->session()->put('syear', $syear);
        $request->session()->put('student_id', $student_id);

        $data = $this->getBk($request, $student_id);

        return $data;
        // echo "here";
    }
    public function getBk(Request $request, $id)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $stu_arr = array(
            "0" => $id
        );

        $request->session()->put('stu_arr', $stu_arr);
        
        $student_id = $id;
        
        $month_arr = FeeMonthId();
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month . $currunt_year;

        $search_ids = array();
        foreach ($month_arr as $id => $arr) {
            if ($id == $currunt_month_id) {
                $search_ids[] = $id;
                // break;
            } else {
                $search_ids[] = $id;
            }
        }
        // echo '<pre>';
        // echo 'month';
        // print_r($month_arr);
        // echo 'search';
        // print_r($search_ids);
        // exit;
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


        $sql = "
            SELECT SUM(amount) amount,term_id
       FROM(
            select SUM(fc.amount)+SUM(fc.fees_discount) amount,fc.term_id
                FROM tblstudent s
                INNER JOIN fees_collect fc ON 
                        (
                         fc.student_id = s.id AND 
                         fc.is_deleted = 'N' AND
                         fc.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND 
                         fc.syear = '" . session()->get('syear') . "'
                             $fees_join
                        ) 
                
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
            GROUP BY term_id
                
                ";
        $sql = preg_replace('/\n+/', '', $sql);
               // echo $sql;
               // exit;
        $paid_result = DB::select($sql);
        //
               // echo "<pre>";
               // print_r($paid_result);
               // exit;
        //getting fees paid array
        $fees_paid_arr = array();
        foreach ($paid_result as $id => $arr) {
            $fees_paid_arr[$arr->term_id] = $arr->amount;
        }

        //        echo "<pre>";
        //        print_r($fees_paid_arr);
        //        exit;



        $reg_bk_off = FeeBreackoff($stu_arr);
        if (count($reg_bk_off) == 0) {
            return array();
            exit;
        }
        // echo ('<pre>');print_r($reg_bk_off);exit;
        $other_bk_off = OtherBreackOff($stu_arr, $search_ids);
        //    echo "<pre>";
        //    print_r($other_bk_off);
        //    exit;

        $other_bk_off_month_wise = OtherBreackOfMonth($stu_arr);

        $year_arr = FeeMonthId();

        //

        $reg_bk_month_wise = array();
        foreach ($reg_bk_off as $id => $arr) {
            $reg_bk_month_wise[$arr->month_id] = $arr->bkoff;
        }


        //    echo "<pre>";
        //    print_r($reg_bk_month_wise);
        //    print_r($other_bk_off_month_wise);
        //    print_r($month_arr);
        //    exit;

        $new_month_arr = array();
        foreach ($reg_bk_month_wise as $month_id => $val) {
            $new_month_arr[$month_id] = $month_arr[$month_id];
        }
        foreach ($other_bk_off_month_wise as $month_id => $val) {
            $new_month_arr[$month_id] = $month_arr[$month_id];
        }

        //    echo "<pre>";
        //    print_r($new_month_arr);
        //    exit;

        $merge_bk_month_wise = array();
        foreach ($reg_bk_month_wise as $month_id => $amount) {
            $merge_bk_month_wise[$month_id] = $amount;
            foreach ($other_bk_off_month_wise as $MonthId => $amt) {
                if ($month_id == $MonthId) {
                    $merge_bk_month_wise[$month_id] = $merge_bk_month_wise[$month_id] + $amt;
                }
            }
        }

        $left_bk_table = array();
        $i = 1;
        $fees_total = 0;
        $paid_total = 0;
        $remain_total = 0;
        //    echo "<pre>";
        //    print_r($merge_bk_month_wise);
        //    exit;

        foreach ($merge_bk_month_wise as $id => $val) {
            //            $left_bk_table[$year_arr[$id]] = $val;
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

        // echo "<pre>";
        // print_r($reg_bk_off);
        // exit;

        // Start Getting previous year imprest balance for The Millennium School Surat

        $syear = session()->get('syear');
        $prviouse_syear = $syear - 1;

        $get_imprest_sql = DB::select("SELECT fb.id,fb.student_id,fb.sub_institute_id,IFNULL(fb.amount,0) as previous_imprest_amt,fb.syear,ft.fees_title,ft.display_name 
                    FROM fees_breakoff_other fb
                    INNER JOIN fees_title ft ON ft.fees_title = fb.fee_type_id AND ft.sub_institute_id = fb.sub_institute_id AND ft.syear = '".session()->get('syear')."'
                    WHERE fb.sub_institute_id = '".session()->get('sub_institute_id')."' AND fb.syear = '".$prviouse_syear."' AND ft.display_name LIKE '%Imprest%' AND fb.student_id = '".$reg_bk_off[0]->student_id."' ");
        $get_imprest_balance = json_decode(json_encode($get_imprest_sql),true);
        
        if(count($get_imprest_balance) > 0){
            $previous_year_imprest_balance = $get_imprest_balance[0]['previous_imprest_amt'];
        }else{
            $previous_year_imprest_balance = 0;        
        }

        // End Getting previous year imprest balance for The Millennium School Surat

        $stu_detail = array(
            "student_id" => $reg_bk_off[0]->student_id,
            "enrollment" => $reg_bk_off[0]->enrollment_no,
            "name" => $reg_bk_off[0]->first_name . " " . $reg_bk_off[0]->middle_name . " " . $reg_bk_off[0]->last_name,
            "stddiv" => $reg_bk_off[0]->standard_name . "/" . $reg_bk_off[0]->division_name,
            "admission" => $reg_bk_off[0]->admission_year,
            "email" => $reg_bk_off[0]->email,
            "pending" => $pending_fees,
            "mobile" => $reg_bk_off[0]->mobile,
            "uniqueid" => $reg_bk_off[0]->uniqueid,
            "std_id" => $reg_bk_off[0]->standard_id,
            "grade_id" => $reg_bk_off[0]->grade_id,
            "div_id" => $reg_bk_off[0]->section_id,
            "student_quota" => $reg_bk_off[0]->stu_quota,
            "previous_year_imprest_balance" => $previous_year_imprest_balance,            
        );
        // dd($stu_detail);
        $head_wise_fees = FeeBreakoffHeadWise($stu_arr);
        // echo ('<pre>');print_r($head_wise_fees);exit;
        $till_now_breckoff = array();
        foreach ($search_ids as $id => $val) {
            foreach ($head_wise_fees as $temp_id => $arr) {
                foreach ($head_wise_fees[$temp_id]['breakoff'] as $month_id => $fees_detail) {
                    if ($month_id == $val) {
                        $till_now_breckoff[$month_id] = $fees_detail;
                    }
                }
            }
        }

        $reg_bk_month_wise = array();
        $final_bk_name = array();
        $total = 0;

        foreach ($till_now_breckoff as $month_id => $fees_detail) {
            foreach ($fees_detail as $head_name => $arr) {
                if (!isset($reg_bk_month_wise[$arr['title']])) {
                    $reg_bk_month_wise[$arr['title']] = 0;
                }
                if( isset($arr['amount']) )
                {                    
                    $reg_bk_month_wise[$arr['title']] += $arr['amount'];
                }
                $final_bk_name[$arr['title']] = $head_name;
                //                $total = $total + $arr['amount'];
            }
        }

        $full_bk = array_merge($reg_bk_month_wise, $other_bk_off);

        //24-04-2021 START Check Cheque Return charges

        $get_cheque_return_amt = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
        $cheque_return_charges = $get_cheque_return_amt[0]['cheque_return_charges'];
        

        $cheque_return_exist_sql = "SELECT fc.id,fc.student_id,fc.sub_institute_id,fc.syear,fc.receipt_no,fc.is_deleted,f.id AS fees_cancel_id,f.cancel_type,f.cancel_remark,f.cancel_date,f.received_date
                                FROM fees_collect fc
                                INNER JOIN fees_cancel f ON f.reciept_id = fc.receipt_no AND f.student_id = fc.student_id 
                                AND f.sub_institute_id = fc.sub_institute_id AND f.syear = fc.syear 
                                WHERE fc.syear = '".$syear."' AND fc.sub_institute_id = '".$sub_institute_id."' AND fc.student_id = '".$stu_detail['student_id']."' 
                                AND is_deleted = 'Y' AND f.cancel_type = 'Cheque Return'
                                ORDER BY fc.id DESC
                                LIMIT 1";
        $cheque_return_exist_RET = DB::select($cheque_return_exist_sql);
        $cheque_return_exist = count($cheque_return_exist_RET);

        // 06/01/2022 SQL for checking if cheque return charges already paid 
        $check_paid_cheque_return_charge = DB::select("SELECT * FROM fees_collect f
                                    WHERE f.receipt_no > CAST((
                                    SELECT fc.reciept_id
                                    FROM fees_cancel fc
                                    WHERE fc.syear = '".$syear."' AND fc.sub_institute_id = '".$sub_institute_id."' AND fc.student_id = '".$stu_detail['student_id']."' 
                                    AND fc.cancel_type = 'Cheque Return'
                                    ORDER BY id DESC 
                                    LIMIT 0,1) AS INT) AND f.syear = '".$syear."' AND f.sub_institute_id = '".$sub_institute_id."' AND f.student_id = '".$stu_detail['student_id']."' 
                                    AND f.is_deleted = 'N' 
                                    ");

        if($cheque_return_charges > 0 && $cheque_return_exist > 0 && count($check_paid_cheque_return_charge) == 0)
        {
            $cheque_return_charges_new[] = $cheque_return_charges;
        }else{
            $cheque_return_charges_new[] = 0;
        }

        //24-04-2021 END Check Cheque Return charges


        foreach ($full_bk as $id => $val) {
            $total = $total + $val;
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

        $type = "web";
        $res['total_fees'] = $left_bk_table;
        $res['stu_data'] = $stu_detail;
        //        $res['month_arr'] = $month_arr;
        $res['month_arr'] = $new_month_arr;
        $res['search_ids'] = $search_ids;
        $res['final_fee'] = $full_bk;        
        $res['cheque_return_charges'] = $cheque_return_charges_new;
        $res['final_fee_name'] = $final_bk_name;
        $res['search_id'] = $search_ids;
           // echo "<pre>";
           // print_r($res);
           // exit;
        return $res;
    }

    public function edit($id, Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res = $this->getBk($request, $id);
        $res['bank_data'] = bankmasterModel::get()->toArray();
        $res['fees_config_data'] = tblfeesConfigModel::where(['sub_institute_id' => $sub_institute_id,'syear' => $syear])->get()->toArray();
        
        if(count($res['fees_config_data']) > 0)
        {
            $res['fees_config_data'] = $res['fees_config_data'][0];
            $type = "web";
            return is_mobile($type, "fees/fees_collect/fees_collect", $res, "view");
        }else{
            $type = "web";
            $res = array(
                    "status_code" => 0,
                    "message" => "Fees config master setting is missing",
                );
             return is_mobile($type, "fees_collect.index", $res, "redirect");
        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

   public function studentFeesDetailAPI(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {     
            //START Get Fees pending array
            $request->session()->put('sub_institute_id', $sub_institute_id);
            $request->session()->put('syear', $syear);
            $request->session()->put('student_id', $student_id);
            $new_pending_arr = array();


            $fees_online_link = DB::select("SELECT * FROM fees_online_maping c 
                WHERE c.syear = '".$syear."' AND c.sub_institute_id = '".$sub_institute_id."'                        
            ");
            $fees_online_link = json_decode(json_encode($fees_online_link),true);            

            $online_link = "";
            if(count($fees_online_link) > 0)
            {
                $online_link = "http://".$_SERVER['SERVER_NAME']."/fees/online_fees_collect";
            }

            $fees_data = $this->getBk($request, $student_id);
            foreach($fees_data['total_fees'] as $key => $val)
            {                
                unset($val['bk']);
                unset($val['paid']);                
                //Set link in PAY NOW
                if($online_link != "")
                {
                    $val['PayNow'] = $online_link;
                }
                if($val['remain'] != 0 && $val['month'] != 'Total')
                {
                    $new_pending_arr[] = (object)$val;
                }                  
            }

            $data['PENDING'] = $new_pending_arr;
            //END Get Fees pending array

            //START Get Fees paid array
            $paid_data = DB::select("SELECT c.receipt_no,c.receiptdate,c.payment_mode,c.bank_branch,c.bank_branch,c.bank_name,c.fees_html,
                        c.cheque_date,c.cheque_no,c.cheque_bank_name,SUM(amount) as paid_amount 
            FROM fees_collect c WHERE c.student_id = '".$student_id."' AND c.syear = '".$syear."' AND c.sub_institute_id = '".$sub_institute_id."'
            GROUP BY receipt_no            
            ");
           
            $data['PAID'] = $paid_data;   
            //END Get Fees paid array   
            
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;   
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);               
    }

   
}
