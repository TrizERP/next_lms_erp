<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Schema;

class feesMonthlyReportController extends Controller
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
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type, 'fees/fees_report/fees_monthly_report', $res, "view");
    }


    public function getfeesMonthlyReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $fees_title_result = DB::table('fees_title')
            ->where('sub_institute_id', session()->get('sub_institute_id'))->where('syear',session()->get('syear'))->orderBy('sort_order')->get()->toArray();
        $fees_title_result = json_decode(json_encode($fees_title_result), true);

        $fees_columns = "";
        $other_columns = "";
        $columns = "";        
        
        $heading_arr = $report_data = array();
        // foreach ($fees_title_result as $key => $val) {
            // $columns .= " SUM(`" . $val['fees_title'] . "`) as total_" . $val['fees_title'] . ",";
        //     $heading_arr[$val['fees_title']] = $val['display_name'];
        // }

            $discountAdded = false;
            $fineAdded = false;

            foreach ($fees_title_result as $key => $val) {
                // $columns .= "IFNULL(SUM(`" . $val['fees_title'] . "`),0) as total_" . $val['fees_title'] . ",";
               $feesTitleColumnExistsInCollect = Schema::hasColumn('fees_collect', $val['fees_title']);
               $feesTitleColumnExistsInPaidOther = Schema::hasColumn('fees_paid_other', $val['fees_title']);
           
                $columnAlias = is_numeric($val['fees_title']) ? $val['fees_title'] : $val['fees_title'];

                if ($feesTitleColumnExistsInCollect) {
                    $fees_columns .= "fees_collect.`". $val['fees_title'] . "` as total_" . $val['fees_title'] . ",";
                    if($val['fees_title'] == "tution_fee"){
                        $columns .= "IFNULL(SUM(total_" . $val['fees_title'] . "),0),";
                    }else{
                        $columns .= "IFNULL(SUM(total_" . $val['fees_title'] . "),0),";
                    }
                } else {
                    $fees_columns .= "NULL as total_" . $columnAlias . ",";
                }

                if ($feesTitleColumnExistsInPaidOther) {
                    $other_columns .="fees_paid_other.`". $val['fees_title'] . "` as total_" . $val['fees_title'] . ",";
                    $columns .="IFNULL(SUM(total_" . $val['fees_title'] . "),0) ,";                    
                } else {
                    $other_columns .= "NULL as total_" . $columnAlias . ",";
                }
                $heading_arr[$val['fees_title']] = $val['display_name'];
                
                if (!$discountAdded) {
                    $fees_columns .="fees_collect.fees_discount as total_discount,";
                    $other_columns .="fees_paid_other.fees_discount as total_discount,";
                    $heading_arr['discount'] = "Discount";
                    $discountAdded = true;
                }
                if (!$fineAdded) {
                    $heading_arr['fine'] = "Fine";
                    $fees_columns .="fees_collect.fine as total_fine,";
                    $other_columns .="fees_paid_other.fine as total_fine,";
                    $fineAdded = true;
                }

                $columns .= "IFNULL(SUM(total_discount),0) AS total_discount,";
                $columns .= "IFNULL(SUM(total_fine),0) AS total_fine,";                
            }

        $extra_query = "";
        if ($grade != "") {
            $extra_query = " AND s.grade_id = '" . $grade . "'";
        }
        if ($standard != "") {
            $extra_query = " AND s.standard_id = '" . $standard . "'";
        }
        if ($division != "") {
            $extra_query = " AND s.section_id = '" . $division . "'";
        }

        $final_data = array();
        $query = "SELECT " . $fees_columns . "
		DATE_FORMAT(f.receiptdate,'%Y-%m-%d') AS fees_date
		FROM fees_collect f
		INNER JOIN tblstudent_enrollment s ON s.sub_institute_id = f.sub_institute_id AND f.student_id = s.student_id
		LEFT JOIN fees_paid_other fo ON fo.sub_institute_id = f.sub_institute_id and fo.student_id = f.student_id and fo.month_id = f.term_id
		WHERE f.is_deleted='N' AND f.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND DATE_FORMAT(f.receiptdate,'%Y-%m-%d') between '" . $from_date . "'
		AND '" . $to_date . "'"
            . $extra_query . "
		GROUP BY DATE_FORMAT(f.receiptdate,'%Y-%m-%d')";

        // $data = DB::table('fees_collect as f')
        //     ->join('tblstudent_enrollment as s', function ($join) {
        //         $join->whereRaw('s.sub_institute_id = f.sub_institute_id AND f.student_id = s.student_id');
        //     })->leftJoin('fees_paid_other as fo', function ($join) {
        //         $join->whereRaw('fo.sub_institute_id = f.sub_institute_id AND f.student_id = fo.student_id AND fo.is_deleted = "N" ');
        //     })
        //     ->selectRaw("" . $columns . "IFNULL(SUM(f.fees_discount),0) as total_tution_fee_discount,IFNULL(SUM(f.fine),0) as total_tution_fee_fine, DATE_FORMAT(f.receiptdate,'%Y-%m-%d') AS fees_date")
        //     ->whereRaw('f.is_deleted = "N"')
        //     ->where('f.sub_institute_id', session()->get('sub_institute_id'))
        //     ->where('f.syear',session()->get('syear'))
        //     ->whereRaw("DATE_FORMAT(f.receiptdate,'%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "'");
       
            $fees_columns .= "fees_collect.receiptdate as fees_date";
            $other_columns .= "fees_paid_other.receiptdate as fees_date";
            $std = $_REQUEST['standard'] ?? '';
        $data = DB::table(function ($query) use($columns,$fees_columns,$other_columns,$sub_institute_id,$syear,$from_date,$to_date,$std) {
            $query->selectRaw($fees_columns)
                ->from('fees_collect')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->whereBetween('receiptdate', [$from_date,$to_date])
                ->where('is_deleted', 'N')
                ->when($std,function($query) use($std){
                    $query->where('standard_id',$std);
                })
                ->unionAll(function ($query)  use($columns,$fees_columns,$other_columns,$sub_institute_id,$syear,$from_date,$to_date,$std){
                    $query->selectRaw($other_columns)
                        ->from('fees_paid_other')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $syear)
                        ->whereBetween('receiptdate',[$from_date,$to_date])
                        ->where('is_deleted', 'N');
                });
        })
        ->selectRaw( $columns .' fees_date')
        ->groupBy('fees_date');

        // if ($grade != "") {
        //     $data = $data->where('s.grade_id', $grade);
        // }
        // if ($standard != "") {
        //     $data = $data->where('fees_collect.standard_id', $standard);
        //     $data = $data->where('fees_paid_other.standard_id', $standard);            
        // }
        // if ($division != "") {
        //     $data = $data->where('s.section_id', $division);
        // }
        // dd($data->toSql());exit;
        
        $data = $data->get()->toArray();
        $data = json_decode(json_encode($data), true);
        $processed_data = [];
        foreach ($data as $key => $val) {
            $processed_val = [];
            foreach ($val as $column_key => $column_value) {
                $column_key = str_replace(['IFNULL(SUM(', '),0)'], '', $column_key);
                $processed_val[$column_key] = $column_value;
            }
            $processed_data[$val['fees_date']] = $processed_val;
        }
        
        $final_data = $processed_data;
        
        $i = 0;
        $from_date_new = $from_date;
        while (strtotime($from_date_new) <= strtotime($to_date)) {
            $i++;

            if (array_key_exists($from_date_new, $final_data)) {
                $report_data[$from_date_new] = $final_data[$from_date_new];
            } else {
                $report_data[$from_date_new] = array();
            }
            $from_date_new = date("Y-m-d", strtotime("+1 day", strtotime($from_date_new)));
        }
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['heading_arr'] = $heading_arr;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['report_data'] = $report_data;

        return is_mobile($type, "fees/fees_report/fees_monthly_report", $res, "view");
    }

    // for dashboard cn 
    public function getfeesMonthlyReportCN(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        $fees_title_result = DB::table('fees_title')
            ->where('sub_institute_id', session()->get('sub_institute_id'))->where('syear',session()->get('syear'))->orderBy('sort_order')->get()->toArray();
        $fees_title_result = json_decode(json_encode($fees_title_result), true);

        $fees_columns = "";
        $other_columns = "";
        $columns = "";        
        
        $heading_arr = $report_data = array();
        
            $discountAdded = false;
            $fineAdded = false;

            foreach ($fees_title_result as $key => $val) {
               
               $feesTitleColumnExistsInCollect = Schema::hasColumn('fees_collect', $val['fees_title']);
               $feesTitleColumnExistsInPaidOther = Schema::hasColumn('fees_paid_other', $val['fees_title']);
           
                $columnAlias = is_numeric($val['fees_title']) ? $val['fees_title'] : $val['fees_title'];

                if ($feesTitleColumnExistsInCollect) {
                    $fees_columns .= "fees_collect.`". $val['fees_title'] . "` as total_" . $val['fees_title'] . ",";
                    if($val['fees_title'] == "tution_fee"){
                        $columns .= "IFNULL(SUM(total_" . $val['fees_title'] . "),0),";
                    }else{
                        $columns .= "IFNULL(SUM(total_" . $val['fees_title'] . "),0),";
                    }
                } else {
                    $fees_columns .= "NULL as total_" . $columnAlias . ",";
                }

                if ($feesTitleColumnExistsInPaidOther) {
                    $other_columns .="fees_paid_other.`". $val['fees_title'] . "` as total_" . $val['fees_title'] . ",";
                    $columns .="IFNULL(SUM(total_" . $val['fees_title'] . "),0) ,";                    
                } else {
                    $other_columns .= "NULL as total_" . $columnAlias . ",";
                }
                $heading_arr[$val['fees_title']] = $val['display_name'];
                
                if (!$discountAdded) {
                    $fees_columns .="fees_collect.fees_discount as total_discount,";
                    $other_columns .="fees_paid_other.fees_discount as total_discount,";
                    $heading_arr['discount'] = "Discount";
                    $discountAdded = true;
                }
                if (!$fineAdded) {
                    $heading_arr['fine'] = "Fine";
                    $fees_columns .="fees_collect.fine as total_fine,";
                    $other_columns .="fees_paid_other.fine as total_fine,";
                    $fineAdded = true;
                }

                $columns .= "IFNULL(SUM(total_discount),0) AS total_discount,";
                $columns .= "IFNULL(SUM(total_fine),0) AS total_fine,"; 
            }

        $final_data = array();
       
            $fees_columns .= "fees_collect.receiptdate as fees_date";
            $other_columns .= "fees_paid_other.receiptdate as fees_date";
            $std = $_REQUEST['standard'] ?? '';

        $data = DB::table(function ($query) use($columns,$fees_columns,$other_columns,$sub_institute_id,$syear) {
            $query->selectRaw($fees_columns.',term_id as month_id')
                ->from('fees_collect')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->where('is_deleted', 'N')
                ->unionAll(function ($query)  use($columns,$fees_columns,$other_columns,$sub_institute_id,$syear){
                    $query->selectRaw($other_columns.',month_id')
                        ->from('fees_paid_other')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $syear)
                        ->where('is_deleted', 'N');
                });
        })
        ->selectRaw( $columns .' fees_date,month_id')
        ->groupBy('month_id');
        
        $data = $data->get()->toArray();
        $data = json_decode(json_encode($data), true);
        $processed_data = [];
        foreach ($data as $key => $val) {
            $processed_val = [];
            foreach ($val as $column_key => $column_value) {
                $column_key = str_replace(['IFNULL(SUM(', '),0)'], '', $column_key);
                $processed_val[$column_key] = $column_value;
            }
            $processed_data[$val['month_id']] = $processed_val;
        }
        
        $final_data = $processed_data;
    
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['heading_arr'] = $heading_arr;
        $res['report_data'] = $final_data;

        return is_mobile($type, "fees/fees_report/fees_monthly_report", $res, "view");
    }
}
