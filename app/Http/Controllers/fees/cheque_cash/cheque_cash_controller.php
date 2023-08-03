<?php

namespace App\Http\Controllers\fees\cheque_cash;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;

class cheque_cash_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $where = [
            "other_fee_id"     => 0,
            "SUB_INSTITUTE_ID" => session()->get('sub_institute_id'),
            "syear"            => session()->get('syear'),
        ];

        $fees_title = DB::table('fees_title')
            ->where($where)->orderBy('sort_order')
            ->pluck('display_name', 'fees_title');


        $school_data['data'] = [];
        $school_data['data']['fees_title'] = $fees_title;
        $type = $request->input('type');

        return is_mobile($type, "fees/cheque_cash/show", $school_data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create()
    {
        $months = FeeMonthId();
        $marking_period_id = session()->get('term_id');
        $where = [
            "other_fee_id"     => 0,
            "SUB_INSTITUTE_ID" => session()->get('sub_institute_id'),
            "syear"            => session()->get('syear'),
        ];

        $fees_title = DB::table('fees_title')
            ->where($where)->orderBy('sort_order')
            ->pluck('display_name', 'fees_title');

        $title_arr = [];
        foreach ($fees_title as $id => $val) {
            $title_arr[$id] = $val;
        }

        $all_fields = [
            "fc.created_date",
            "fc.receipt_no",
            "fc.term_id",
            "ts.enrollment_no",
            "fc.cheque_no",
            "fc.cheque_bank_name",
            "fc.fees_discount",
        ];

        $select_fields = "se.syear,se.student_id,se.enrollment_code,
                        fc.payment_mode,fc.amount
                        ";
        $select_fields = preg_replace('/\s+/', '', $select_fields);
        $columns = explode(',', $select_fields);
        $columns[] = "s.name as standard_name";
        $columns[] = "d.name as division_name";
        $columns[] = "ts.first_name";
        $columns[] = "ts.last_name";

        foreach ($_REQUEST['fees_heads'] as $id => $val) {
            $columns[] = $val;
        }
        foreach ($all_fields as $id => $val) {
            $columns[] = $val;
        }


        $query = tblstudentModel::from('tblstudent as ts');

        $where = [
            'se.syear'            => session()->get('syear'),
            'fc.syear'            => session()->get('syear'),
            'ts.sub_institute_id' => session()->get('sub_institute_id'),
            ['fc.created_date', '>=', $_REQUEST['from_date']],
            ['fc.created_date', '<=', $_REQUEST['to_date']],
        ];

        $enrollment_join = [
            'se.student_id'       => 'ts.id',
            'se.sub_institute_id' => 'ts.sub_institute_id',
        ];
        $grade_join = [
            'acs.id'               => 'se.grade_id',
            'acs.sub_institute_id' => 'se.sub_institute_id',
        ];
        $std_join = [
            's.id'               => 'se.standard_id',
            's.sub_institute_id' => 'se.sub_institute_id',
        ];
        $div_join = [
            'd.id'               => 'se.section_id',
            'd.sub_institute_id' => 'se.sub_institute_id',
        ];
        $paid_join = [
            ['fc.student_id', 'ts.id'],
        ];

        $query->join('tblstudent_enrollment as se', $enrollment_join);
        $query->join('academic_section as acs', $grade_join);
        $query->join('standard as s',function($join) use($marking_period_id,$std_join){
            $join->on($std_join);
            // ->when($marking_period_id,function($query) use($marking_period_id){
            //     $query->where('s.marking_period_id',$marking_period_id);
            // });
        });
        $query->join('division as d', $div_join);
        $query->join('fees_collect as fc', $paid_join);

        if (isset($_REQUEST['grade'])) {
            $query->WhereIn('acs.id', $_REQUEST['grade']);
        }
        if (isset($_REQUEST['standard'])) {
            $query->WhereIn('s.id', $_REQUEST['standard']);
        }
        if (isset($_REQUEST['division'])) {
            $query->WhereIn('d.id', $_REQUEST['division']);
        }
        $query->where($where);
        $query->orderBy('created_date');
        $query->orderBy('receipt_no');

        $records = $query->get($columns)->toArray();

        $total_arr = $cash_arr = $cheque_arr = [];

        if (count($records) > 0) {
            foreach ($records as $id => $arr) {
                if ($arr['payment_mode'] == 'Cash') {
                    $cash_arr[] = $arr;
                } else {
                    $cheque_arr[] = $arr;
                }
            }
        }

        $responce_html = $old_date = "";
        $grand_total_cash = $grand_total_cheque = $total_cash = $total_cheque = 0;

        if (count($cheque_arr) > 0) {
            $responce_html .= "<center><h3>Cheque Data</h3></center>";

            foreach ($cheque_arr as $id => $arr) {

                $cur_date = date("Y-m-d", strtotime($arr['created_date']));
                if ($old_date != $cur_date) {
                    $old_date = $cur_date;

                    if ($id != 0) {
                        $responce_html .= " <tr>";
                        $responce_html .= "     <td align=right colspan=7>";
                        $responce_html .= "         <b>Date Wise Total :</b>";
                        $responce_html .= "     </td>";
                        foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                            $responce_html .= "     <td>";
                            $responce_html .= "<b>".$total_arr[$test_id]."</b>";
                            $responce_html .= "     </td>";
                        }
                        $responce_html .= "     <td>";
                        $responce_html .= "<b>".$total_arr['fees_discount']."</b>";
                        $responce_html .= "     </td>";
                        $responce_html .= "     <td>";
                        $responce_html .= "<b>".$total_arr['amount']."</b>";
                        $responce_html .= "     </td>";
                        $responce_html .= "</table><br>";
                        $total_cheque = $total_cheque + $total_arr['amount'];
                    }
                    $total_arr = [];
                    $responce_html .= "<table width='100%' class='customers'>";
                    $responce_html .= " <tr>";
                    $responce_html .= "     <td>";
                    $responce_html .= "Date : ".$cur_date;
                    $responce_html .= "     </td>";
                    $responce_html .= "     <td>";
                    $responce_html .= "         Payment Mode : Cheque";
                    $responce_html .= "     </td>";
                    $responce_html .= " </tr>";
                    $responce_html .= "</table>";
                    $responce_html .= "<table width='100%' border=1 class='customers'>";
                    $responce_html .= " <tr>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Receipt No.";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         STD";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Month";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         GR No.";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Name";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Cheque No.";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Bank Name";
                    $responce_html .= "     </th>";

                    foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                        $responce_html .= "     <th>";
                        $responce_html .= $fees_title[$head_val];
                        $responce_html .= "     </th>";
                    }

                    $responce_html .= "     <th>";
                    $responce_html .= "         Fees Mafi";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Amount";
                    $responce_html .= "     </th>";
                    $responce_html .= " </tr>";
                }
                $responce_html .= " <tr>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['receipt_no'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['standard_name'].'/'.$arr['division_name'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $months[$arr['term_id']];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['enrollment_no'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['first_name']." ".$arr['last_name'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['cheque_no'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['cheque_bank_name'];
                $responce_html .= "     </td>";

                foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                    $responce_html .= "     <td>";
                    if ($arr[$head_val] == '' || $arr[$head_val] == null) {
                        $arr[$head_val] = 0;
                    }
                    $responce_html .= $arr[$head_val];
                    if (isset($total_arr[$test_id])) {
                        $total_arr[$test_id] = $arr[$head_val] + $total_arr[$test_id];
                    } else {
                        $total_arr[$test_id] = 0;
                        $total_arr[$test_id] = $arr[$head_val] + $total_arr[$test_id];
                    }
                    $responce_html .= "     </td>";
                }

                $responce_html .= "     <td>";
                $responce_html .= $arr['fees_discount'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['amount'];
                $responce_html .= "     </td>";

                $responce_html .= " </tr>";
                if (isset($total_arr['fees_discount'])) {
                    $total_arr['fees_discount'] += $arr['fees_discount'];
                } else {
                    $total_arr['fees_discount'] = 0;
                    $total_arr['fees_discount'] += $arr['fees_discount'];
                }
                if (isset($total_arr['amount'])) {
                    $total_arr['amount'] += $arr['amount'];
                } else {
                    $total_arr['amount'] = 0;
                    $total_arr['amount'] += $arr['amount'];
                }
            }

            $responce_html .= " <tr>";
            $responce_html .= "     <td align=right colspan=7>";
            $responce_html .= "<b>"."         Date Wise Total :"."</b>";
            $responce_html .= "     </td>";

            foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                $responce_html .= "     <td>";
                $responce_html .= "<b>".$total_arr[$test_id]."</b>";
                $responce_html .= "     </td>";
            }
            $grand_total_cheque = $total_cheque + $total_arr['amount'];
            $responce_html .= "     <td>";
            $responce_html .= "<b>".$total_arr['fees_discount']."</b>";
            $responce_html .= "     </td>";
            $responce_html .= "     <td>";
            $responce_html .= "<b>".$total_arr['amount']."</b>";
            $responce_html .= "     </td>";
            $responce_html .= "</table>";

        }

        $old_date = "";

        if (count($cash_arr) > 0) {
            $responce_html .= "<center><h3>Cash Data</h3></center>";

            foreach ($cash_arr as $id => $arr) {
                $cur_date = date("Y-m-d", strtotime($arr['created_date']));
                if ($old_date != $cur_date) {
                    $old_date = $cur_date;

                    if ($id != 0) {
                        $responce_html .= " <tr>";
                        $responce_html .= "     <td align=right colspan=7>";
                        $responce_html .= "<b>"."         Date Wise Total :"."</b>";
                        $responce_html .= "     </td>";
                        foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                            $responce_html .= "     <td>";
                            $responce_html .= "<b>".$total_arr[$test_id]."</b>";
                            $responce_html .= "     </td>";
                        }
                        $responce_html .= "     <td>";
                        $responce_html .= "<b>".$total_arr['fees_discount']."</b>";
                        $responce_html .= "     </td>";
                        $responce_html .= "     <td>";
                        $responce_html .= "<b>".$total_arr['amount']."</b>";
                        $responce_html .= "     </td>";
                        $responce_html .= "</table><br>";
                        $total_cash = $total_cash + $total_arr['amount'];
                    }
                    $total_arr = [];
                    $responce_html .= "<table width='100%' class='customers'>";
                    $responce_html .= " <tr>";
                    $responce_html .= "     <td>";
                    $responce_html .= "Date : ".$cur_date;
                    $responce_html .= "     </td>";
                    $responce_html .= "     <td>";
                    $responce_html .= "         Payment Mode : Cash";
                    $responce_html .= "     </td>";
                    $responce_html .= " </tr>";
                    $responce_html .= "</table>";
                    $responce_html .= "<table width='100%' class='customers'>";
                    $responce_html .= " <tr>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Receipt No.";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         STD";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Month";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         GR No.";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Name";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Cheque No.";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Bank Name";
                    $responce_html .= "     </th>";

                    foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                        $responce_html .= "     <th>";
                        $responce_html .= $fees_title[$head_val];
                        $responce_html .= "     </th>";
                    }

                    $responce_html .= "     <th>";
                    $responce_html .= "         Fees Mafi";
                    $responce_html .= "     </th>";
                    $responce_html .= "     <th>";
                    $responce_html .= "         Amount";
                    $responce_html .= "     </th>";
                    $responce_html .= " </tr>";
                }
                $responce_html .= " <tr>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['receipt_no'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['standard_name'].'/'.$arr['division_name'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";

                if (isset($arr['term_id']) && $arr['term_id'] != '') {
                    $month_term = $months[$arr['term_id']];
                } else {
                    $month_term = '';
                }
                $responce_html .= $month_term;
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['enrollment_no'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['first_name']." ".$arr['last_name'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= "NA";
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= "NA";
                $responce_html .= "     </td>";

                foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                    $responce_html .= "     <td>";
                    if ($arr[$head_val] == '' || $arr[$head_val] == null) {
                        $arr[$head_val] = 0;
                    }
                    $responce_html .= $arr[$head_val];
                    if (isset($total_arr[$test_id])) {
                        $total_arr[$test_id] = $arr[$head_val] + $total_arr[$test_id];
                    } else {
                        $total_arr[$test_id] = 0;
                        $total_arr[$test_id] = $arr[$head_val] + $total_arr[$test_id];
                    }
                    $responce_html .= "     </td>";
                }

                $responce_html .= "     <td>";
                $responce_html .= $arr['fees_discount'];
                $responce_html .= "     </td>";
                $responce_html .= "     <td>";
                $responce_html .= $arr['amount'];
                $responce_html .= "     </td>";

                $responce_html .= " </tr>";
                if (isset($total_arr['fees_discount'])) {
                    $total_arr['fees_discount'] += $arr['fees_discount'];
                } else {
                    $total_arr['fees_discount'] = 0;
                    $total_arr['fees_discount'] += $arr['fees_discount'];
                }
                if (isset($total_arr['amount'])) {
                    $total_arr['amount'] += $arr['amount'];
                } else {
                    $total_arr['amount'] = 0;
                    $total_arr['amount'] += $arr['amount'];
                }
            }

            $responce_html .= " <tr>";
            $responce_html .= "     <td align=right colspan=7>";
            $responce_html .= "<b>"."         Date Wise Total :"."</b>";
            $responce_html .= "     </td>";
            foreach ($_REQUEST['fees_heads'] as $test_id => $head_val) {
                $responce_html .= "     <td>";
                $responce_html .= "<b>".$total_arr[$test_id]."</b>";
                $responce_html .= "     </td>";
            }
            $grand_total_cash = $total_cash + $total_arr['amount'];
            $responce_html .= "     <td>";
            $responce_html .= "<b>".$total_arr['fees_discount']."</b>";
            $responce_html .= "     </td>";
            $responce_html .= "     <td>";
            $responce_html .= "<b>".$total_arr['amount']."</b>";
            $responce_html .= "     </td>";
            $responce_html .= "</table>";
        }

        $responce_html .= "<center><h3>Total Fees</h3></center><center>";
        $responce_html .= "<table width='50%' class='customers'>";
        $responce_html .= " <tr>";
        $responce_html .= "     <th>";
        $responce_html .= "         Sr No.";
        $responce_html .= "     </th>";
        $responce_html .= "     <th>";
        $responce_html .= "         Payment Mode";
        $responce_html .= "     </th>";
        $responce_html .= "     <th>";
        $responce_html .= "         Amount";
        $responce_html .= "     </th>";
        $responce_html .= " </tr>";
        $responce_html .= " <tr class='font-weight-bold'>";
        $responce_html .= "     <td>";
        $responce_html .= "         1";
        $responce_html .= "     </td>";
        $responce_html .= "     <td>";
        $responce_html .= "         Cash";
        $responce_html .= "     </td>";
        $responce_html .= "     <td>";
        $responce_html .= $grand_total_cash;
        $responce_html .= "     </td>";
        $responce_html .= " </tr>";
        $responce_html .= " <tr class='font-weight-bold'>";
        $responce_html .= "     <td>";
        $responce_html .= "         2";
        $responce_html .= "     </td>";
        $responce_html .= "     <td>";
        $responce_html .= "         Cheque";
        $responce_html .= "     </td>";
        $responce_html .= "     <td>";
        $responce_html .= $grand_total_cheque;
        $responce_html .= "     </td>";
        $responce_html .= " </tr>";
        $responce_html .= "</table></center>";


        $responce_arr['stu_data'] = $responce_html;

        if (isset($_REQUEST['type'])) {
            $type = $_REQUEST['type'];
        } else {
            $type = "";
        }

        return is_mobile($type, "fees/cheque_cash/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        //
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
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
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

}
