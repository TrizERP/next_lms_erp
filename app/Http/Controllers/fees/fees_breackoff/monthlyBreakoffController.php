<?php

namespace App\Http\Controllers\fees\fees_breackoff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\FeeMonthId;

class monthlyBreakoffController extends Controller
{
    //
    public function index(Request $request){
        $school_data =[];
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
                $school_data['status_code'] = $data_arr['status_code'];
                $school_data['bk_months'] = $data_arr['bk_months'] ?? '';
                $school_data['next_bk_months'] = $data_arr['next_bk_months'] ?? '';                
            }
        }
       $current_bk = DB::table('fees_breackoff')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->groupBy('month_id')->pluck('month_id');
       $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
        10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $month_name = [];

        foreach ($current_bk as $id => $arr) {
            $y = $arr / 10000;
            $month = (int)$y;
            $year = substr($arr, -4);
            $month_name[$arr] = '';
            $month_name[$arr] .= $months[$month] . "/" . $year;
        }
        $school_data['bk_month'] =$month_name;
        $school_data['next_month'] = FeeMonthId(); 
        $school_data['today_data'] = $this->getData();                       

        $type = $request->input('type');
        // return $school_data['today_data'];exit;
        return is_mobile($type, "fees/fees_breackoff/monthly_breakoff/show", $school_data, "view");
    }

    function getData()
    {
        $marking_period_id=session()->get('term_id');
        $result = DB::table('fees_breackoff as fb')
            ->join('fees_title as ft', function ($join) {
                $join->whereRaw('ft.id = fb.fee_type_id');
            })->join('student_quota as sq', function ($join) {
                $join->whereRaw('sq.id = fb.quota');
            })->join('academic_section as acs', function ($join) {
                $join->whereRaw('acs.id = fb.grade_id');
            })->join('standard as st', function ($join) use($marking_period_id) {
                $join->whereRaw('st.id = fb.standard_id');
                // ->when($marking_period_id,function($join) use ($marking_period_id){
                //     $join->where('st.marking_period_id',$marking_period_id);
                // });
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = fb.section_id');
            })->selectRaw('fb.syear,fb.admission_year,ft.display_name fees_head,sq.title quota,acs.title grade_name,
                st.name sta_name,d.name div_name,fb.month_id,fb.amount')
            ->where('fb.sub_institute_id', session()->get('sub_institute_id'))
            ->where('fb.syear', session()->get('syear'))
            ->whereRaw('fb.created_at LIKE "'.date('Y-m-d').'%"')            
            ->orderByRaw('ft.sort_order')
            ->get()->toArray();

        $months = [
            1  => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        foreach ($result as $id => $arr) {
            $y = $arr->month_id / 10000;
            $month = (int) $y;

            $year = substr($arr->month_id, -4);
            $result[$id]->month_id = $months[$month]."/".$year;
        }

        return $result;
    }

    public function store(Request $request){
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $bk_month = $request->bk_month;
        $next_bk = $request->next_bk ?? [];
        $type=$request->input('type');
        
        $check_exist = DB::table('fees_breackoff')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->where('month_id',$bk_month)->get()->toArray();

        if (!empty($check_exist)) {
            $check_all = DB::table('fees_breackoff')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->get()->toArray();
            $add_data = 0;                        

            $existing_month_ids = array_column($check_all, 'month_id');
            foreach ($next_bk as $next_month_id) {
                if (in_array($next_month_id, $existing_month_ids)) {
                    $next_bk_exist = DB::table('fees_breackoff')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->where('month_id',$next_month_id)->get()->toArray();
                   
                    if(empty($next_bk_exist)){
                        $arr = [];
                       foreach($check_exist as $key=>$val){
                            $amount = $val->amount;
                            $admission_year =$val->admission_year;  
                            $standard_id = $val->standard_id;    
                            $fee_type_id = $val->fee_type_id;
                            $student_quota = $val->quota;   
                            $grade_id = $val->grade_id;

                            $next_month_bk_exist = DB::table('fees_breackoff')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->where('month_id',$next_month_id)->where(['amount'=>$amount,'admission_year'=>$admission_year,'standard_id'=>$standard_id,'fee_type_id'=>$fee_type_id])->get()->toArray();
                            if(empty($next_month_bk_exist)){
                                $insert = DB::table()->insert([
                                    'syear'=>$syear,
                                    'sub_institute_id'=>$sub_institute_id,
                                    'admission_year'=>$admission_year,
                                    'fee_type_id'=>$fee_type_id,
                                    'quota'=>$student_quota,
                                    'grade_id'=>$grade_id,
                                    'standard_id'=>$standard_id,                                    
                                    'section_id'=>0,
                                    'month_id'=>$next_month_id,
                                    'amount'=>$amount,
                                    'sub_institute_id'=>$sub_institute_id,
                                    'created_at'=>now()
                                ]);
                                $add_data++;
                            }
                        }
                       
                }
                }else{
                    //done
                    foreach($check_exist as $key=>$val){
                        $amount = $val->amount;
                        $admission_year =$val->admission_year;  
                        $standard_id = $val->standard_id;    
                        $fee_type_id = $val->fee_type_id;
                        $student_quota = $val->quota;   
                        $grade_id = $val->grade_id;
                        $arr=[
                            'syear'=>$syear,
                            'sub_institute_id'=>$sub_institute_id,
                            'admission_year'=>$admission_year,
                            'fee_type_id'=>$fee_type_id,
                            'quota'=>$student_quota,
                            'grade_id'=>$grade_id,
                            'standard_id'=>$standard_id,                                    
                            'section_id'=>0,
                            'month_id'=>$next_month_id,
                            'amount'=>$amount,
                            'sub_institute_id'=>$sub_institute_id,
                        ];
                            $arr['created_at'] = now();                      
                            $insert = DB::table('fees_breackoff')->insert($arr);
                            $add_data++;
                            
                    }
                }
                if($add_data==0){
                    $res['status_code']=0;
                    $res['message']="Already Exists";
                }else{
                    $res['status_code']=1;
                    $res['message']="Added Successfully";
                }
            }
           
        }
       
        $res['bk_months'] = $bk_month;
        $res['next_bk_months']=$next_bk;
        return is_mobile($type, "monthly_breakoff.index", $res, "redirect");
    }
   
}
