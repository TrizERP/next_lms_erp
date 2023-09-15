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
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Schema;

class feesTypewiseReportController extends Controller
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

        return is_mobile($type, "fees/fees_report/show_fees_type_wise_report", $res, "view");
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
        $admission_year = $request->input('admission_year');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        $extraSearchArrayRaw = " 1=1 ";
        $extraSearchArrayRawfp = " 1=1 ";        

        if ($grade != '') {
            $extraSearchArrayRaw .= "  AND se.grade_id = " . $grade;
        }

        if ($standard != '') {
            $extraSearchArrayRaw .= "  AND se.standard_id = " . $standard;
        }

        if ($division != '') {
            $extraSearchArrayRaw .= "  AND se.section_id = " . $division;
        }

        if ($enrollment_no != '') {
            $extraSearchArrayRaw .= "  AND ts.enrollment_no = " . $enrollment_no;
        }

        if ($mobile_no != '') {
            $extraSearchArrayRaw .= "  AND ts.mobile = " . $mobile_no;
        }

        if ($uniqueid != '') {
            $extraSearchArrayRaw .= "  AND ts.uniqueid = " . $uniqueid;
        }

        if ($first_name != '') {
            $extraSearchArrayRaw .= "  AND ts.first_name like '%" . $first_name . "%' ";
        }

        if ($last_name != '') {
            $extraSearchArrayRaw .= "  AND ts.last_name like '%" . $last_name . "%' ";
        }

        if ($admission_year != '' && $admission_year != '--Select Admission Year--') {
            $extraSearchArrayRaw .= "  AND ts.admission_year  = '" . $admission_year . "'";
        }

        if ($from_date != '') {
            $extraSearchArrayRaw .= "  AND fc.receiptdate >= '" . $from_date . "'";
            $extraSearchArrayRawfp .= "  AND fp.receiptdate >= '" . $from_date . "'";            
        }

        if ($to_date != '') {
            $extraSearchArrayRaw .= "  AND fc.receiptdate <= '" . $to_date . "'";
            $extraSearchArrayRawfp .= "  AND fp.receiptdate <= '" . $to_date . "'";            
        }

        $fees_heads = DB::table('fees_title as FT')
            ->where('FT.sub_institute_id', $sub_institute_id)
            // ->where('FT.other_fee_id', '=', 0)
            ->where('FT.syear', $syear)->orderBy('FT.sort_order')->get()->toArray();
        $fees_heads = array_map(function ($value) {
            return (array)$value;
        }, $fees_heads);

        $fees_head_sum = "";
        
        $fees_columns = "";
        $other_columns = "";
        $columns = "";  
        foreach ($fees_heads as $key => $value) {
            $feesTitleColumnExistsInCollect = Schema::hasColumn('fees_collect', $value['fees_title']);
            $feesTitleColumnExistsInPaidOther = Schema::hasColumn('fees_paid_other', $value['fees_title']);
            $columnAlias = is_numeric($value['fees_title']) ? $value['fees_title'] : $value['fees_title'];

            if ($feesTitleColumnExistsInCollect) {
                $fees_columns .= "sum(fc.`". $value['fees_title'] . "`) as total_" . $value['fees_title'] . ",";
                if($value['fees_title'] == "tution_fee"){
                    $columns .= "IFNULL(SUM(total_" . $value['fees_title'] . "),0) as total_" . $value['fees_title'] . ",";
                }else{
                    $columns .= "IFNULL(SUM(total_" . $value['fees_title'] . "),0) as total_" . $value['fees_title'] . ",";
                }
            } else {
                $fees_columns .= "NULL as total_" . $columnAlias . ",";
            }

            if ($feesTitleColumnExistsInPaidOther) {
                $other_columns .="sum(fp.`". $value['fees_title'] . "`) as  total_" . $value['fees_title'] . ",";
                $columns .="IFNULL(SUM(`total_" . $value['fees_title'] . "`),0) as  total_" . $value['fees_title'] . ",";
            } else {
                $other_columns .= "NULL as total_" . $columnAlias . ",";
            }
            
         //echo "<pre>";print_r($columns);
        
            // $fees_head_sum .= " SUM(fc." . $value['fees_title'] . ") AS " . $value['fees_title'] . ",";
        }

        // $data = DB::table(function ($query) use ($sub_institute_id, $syear, $extraSearchArrayRaw, $extraSearchArrayRawfp,$columns) {
        //     $query->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
        //         . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', g.title as grade, s.name as standard_name, d.name as division_name, fc.created_date, '
        //         . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fc.term_id, fc.receiptdate, fc.receipt_no, fc.payment_mode, '
        //         . 'fc.cheque_bank_name, fc.bank_branch, fc.cheque_no, fc.cheque_date, b.title as batch, sq.title as quota, '
        //         . $columns))
        //         ->from('tblstudent as t')
        //         ->join('tblstudent_enrollment as te', function ($join) use($syear){
        //             $join->on('te.student_id', '=', 't.id')->where('te.syear',$syear);
        //         })
        //         ->leftJoin('academic_section as g', 'g.id', '=', 'te.grade_id')
        //         ->leftJoin('standard as s', 's.id', '=', 'te.standard_id')
        //         ->leftJoin('division as d', 'd.id', '=', 'te.section_id')
        //         ->leftJoin('student_quota as sq', 'sq.id', '=', 'te.student_quota')
        //         ->leftjoin('batch as b', function ($join) {
        //             $join->on('b.standard_id', '=', 'te.standard_id')
        //                 ->whereRaw('b.division_id = te.section_id')
        //                 ->whereRaw('b.id = t.studentbatch')
        //                 ->whereRaw('b.syear = te.syear');
        //         })
        //         ->Join('fees_collect as fc', 'fc.student_id', '=', 'te.student_id')
        //         ->leftJoin('tbluser as u', 'fc.created_by', '=', 'u.id')
        //         ->whereRaw($extraSearchArrayRaw)

        //         ->unionAll(function ($query) use ($sub_institute_id, $syear, $extraSearchArrayRaw, $extraSearchArrayRawfp,$columns) {
        //             $query->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
        //                 . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', g.title as grade, s.name as standard_name, d.name as division_name, NULL AS created_date, '
        //                 . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fp.month_id AS term_id, fp.receiptdate AS receiptdate, fp.reciept_id AS receipt_no, fp.payment_mode AS payment_mode, '
        //                 . 'fp.bank_name as cheque_bank_name, fp.bank_branch, fp.cheque_dd_no as cheque_no, fp.cheque_dd_date AS cheque_date, b.title as batch, sq.title as quota, '
        //                 . $columns))->from('tblstudent as t')
        //                 ->join('tblstudent_enrollment as te', function ($join) use($syear){
        //                     $join->on('te.student_id', '=', 't.id')->where('te.syear',$syear);
        //                 })
        //                 ->leftJoin('academic_section as g', 'g.id', '=', 'te.grade_id')
        //                 ->leftJoin('standard as s', 's.id', '=', 'te.standard_id')
        //                 ->leftJoin('division as d', 'd.id', '=', 'te.section_id')
        //                 ->leftJoin('student_quota as sq', 'sq.id', '=', 'te.student_quota')
        //                 ->leftjoin('batch as b', function ($join) {
        //                     $join->on('b.standard_id', '=', 'te.standard_id')
        //                         ->whereRaw('b.division_id = te.section_id')
        //                         ->whereRaw('b.id = t.studentbatch')
        //                         ->whereRaw('b.syear = te.syear');
        //                 })
        //                 ->leftJoin('fees_paid_other as fp', 'fp.student_id', '=', 'te.student_id')
        //                 ->leftJoin('tbluser as u', 'fp.created_by', '=', 'u.id')
        //                 ->whereRaw( $extraSearchArrayRawfp);
        //         });
        // })
        //     ->selectRaw('student_id, enrollment_no, roll_no, uniqueid, place_of_birth, student_name, grade,standard_name, division_name,created_date, user_name, term_id, receiptdate, receipt_no,  payment_mode, cheque_bank_name, bank_branch, cheque_no, cheque_date, batch,  quota')
        //     ->groupBy('receipt_no');

            // $data = $data->get()->toArray();

       

        $fees_data = DB::table(function ($query)  use($extraSearchArrayRaw,$extraSearchArrayRawfp,$fees_columns,$other_columns,$sub_institute_id,$syear) {
            $query->from('fees_collect as fc')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = fc.student_id AND ts.sub_institute_id = fc.sub_institute_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = ts.id');
            })->join('student_quota as sq', function ($join) {
                $join->whereRaw('sq.id = se.student_quota');
            })->join('academic_section as a', function ($join) {
                $join->whereRaw('a.id = se.grade_id');
            })->join('standard as s', function ($join) {
                $join->whereRaw('s.id = se.standard_id');
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id');
            })->leftjoin('batch as b', function ($join) {
                $join->whereRaw('b.id = ts.studentbatch');
            })
            ->selectRaw("fc.id,fc.student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                ts.enrollment_no,ts.admission_year,ts.mobile,ts.email,date_format(ts.dob,'%d-%m-%Y') AS dob,a.title AS section,
                s.name AS std_name,d.name AS div_name,sq.title AS stu_qouta, $fees_columns
                fc.fine AS total_fine,fc.fees_discount AS tot_disc,fc.receipt_no,sum(fc.amount) as total_amt,b.title as student_batch_name,date_format(fc.receiptdate,'%d-%m-%Y') AS receipt_date")
            ->whereRaw($extraSearchArrayRaw)
            ->where('se.syear', $syear)
            ->where('fc.syear', $syear)
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('fc.is_deleted','N')->groupBy(['fc.student_id', 'fc.receipt_no'])
            ->unionAll(function ($query)  use($extraSearchArrayRawfp,$other_columns,$sub_institute_id,$syear){
                $query->selectRaw("fp.id,fp.student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                ts.enrollment_no,ts.admission_year,ts.mobile,ts.email,date_format(ts.dob,'%d-%m-%Y') AS dob,a.title AS section,
                s.name AS std_name,d.name AS div_name,sq.title AS stu_qouta, $other_columns
                fp.fine AS total_fine,fp.fees_discount AS tot_disc,fp.reciept_id as receipt_no,sum(fp.actual_amountpaid) as total_amt,b.title as student_batch_name,date_format(fp.receiptdate,'%d-%m-%Y') AS receipt_date")
                    ->from('fees_paid_other as fp')
                    ->join('tblstudent as ts', function ($join) {
                        $join->whereRaw('ts.id = fp.student_id AND ts.sub_institute_id = fp.sub_institute_id');
                    })->join('tblstudent_enrollment as se', function ($join) {
                        $join->whereRaw('se.student_id = ts.id');
                    })->join('student_quota as sq', function ($join) {
                        $join->whereRaw('sq.id = se.student_quota');
                    })->join('academic_section as a', function ($join) {
                        $join->whereRaw('a.id = se.grade_id');
                    })->join('standard as s', function ($join){
                        $join->whereRaw('s.id = se.standard_id');
                    })->join('division as d', function ($join) {
                        $join->whereRaw('d.id = se.section_id');
                    })->leftjoin('batch as b', function ($join) {
                        $join->whereRaw('b.id = ts.studentbatch');
                    })
                    ->whereRaw($extraSearchArrayRawfp)
                    ->where('se.syear', $syear)
                    ->where('fp.syear', $syear)
                    ->where('s.sub_institute_id', $sub_institute_id)
                    ->where('fp.is_deleted','N')->groupBy(['fp.student_id','fp.reciept_id']);
            });
        })
        ->selectRaw("id,student_id,student_name,
        enrollment_no,admission_year,mobile,email,dob,section,
       std_name,div_name,stu_qouta, ".$columns."
       total_fine,tot_disc,receipt_no,sum(total_amt) as amount,student_batch_name,receipt_date")
            ->groupBy(['student_id', 'receipt_no'])->get()->toArray();
            // 7050
            // echo "<pre>";print_r($fees_data);exit;
        $fees_data = array_map(function ($value) {
            return (array)$value;
        }, $fees_data);
        
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $fees_data;
        $res['fees_heads'] = $fees_heads;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile_no'] = $mobile_no;
        $res['uniqueid'] = $uniqueid;
        $res['admission_year'] = $admission_year;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        // return $fees_data;exit;
        return is_mobile($type, "fees/fees_report/show_fees_type_wise_report", $res, "view");
    }

}
