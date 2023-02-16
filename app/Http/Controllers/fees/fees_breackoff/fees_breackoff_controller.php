<?php

namespace App\Http\Controllers\fees\fees_breackoff;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\fees\map_year\map_year;
use App\Models\fees\fees_breackoff\fees_breackoff;
use DB;

class fees_breackoff_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
//        $school_data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "fees/fees_breackoff/show", $school_data, "view");
    }

    function getData() {
        $query = "select fb.syear,fb.admission_year,ft.display_name fees_head,sq.title quota,acs.title grade_name,
                st.name sta_name,d.name div_name,fb.month_id,fb.amount
                from fees_breackoff fb
                inner join fees_title ft on ft.id = fb.fee_type_id
                inner join student_quota sq on sq.id = fb.quota
                inner join academic_section acs on acs.id = fb.grade_id
                inner join standard st on st.id = fb.standard_id
                left join division d on d.id = fb.section_id
                where fb.sub_institute_id = '" . session()->get('sub_institute_id') . "' and fb.syear = '" . session()->get('syear') . "'";
                // echo $query;
                // exit;
        $result = DB::select($query);

        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');

        foreach ($result as $id => $arr) {
            $y = $arr->month_id / 10000;
            $month = (int) $y;
            // $year_arr = explode('.', $y);
            // $year = $year_arr[1];
            $year = substr($arr->month_id,-4);
            $result[$id]->month_id = $months[$month] . "/" . $year;
        }

//        echo "<pre>";
//        print_r($result);
//        exit;
        return $result;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $type = $request->input('type');

        $data = map_year::
                where([
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'syear' => session()->get('syear')
                ])->get()->toArray();
        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
        $months_arr = array();
        $syear = session()->get('syear');

        for ($i = 1; $i <= 12; $i++) {
            $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
            if ($start_month == 12) {
                $start_month = 0;
                $syear = $syear + 1;
            }
            $start_month = $start_month + 1;
        }

        $dataStore['data']['ddMonth'] = $months_arr;
        return \App\Helpers\is_mobile($type, 'fees/fees_breackoff/add', $dataStore, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'insert') 
        {
            $all_data = $_REQUEST['NewValues'];
            foreach ($all_data as $id => $arr) {
                foreach ($arr as $ids => $val) {
                    if ($val == '' || $val == NULL) {
                        unset($all_data[$id][$ids]);
                    }
                }
            }
            foreach ($all_data as $id => $arr) {
                if (count($arr) == 0) {
                    unset($all_data[$id]);
                }
            }
            $req = session()->get('req');
          
            foreach ($req['grade'] as $grade_id => $grade) 
            {
                foreach ($req['standard'] as $std_id => $std) 
                {
                    foreach ($all_data as $quota_id => $arr) 
                    {
                        foreach ($arr as $title_id => $amount) 
                        {
                            foreach ($req['month'] as $month_id => $on) 
                            {
                                $syear = session()->get('syear');
                                $admission_year = session()->get('syear');
                                $sub_institute_id = session()->get('sub_institute_id');

                                $checkNewfeesBreakoff = fees_breackoff::where(['syear' => $syear,'admission_year' => $admission_year,'fee_type_id' => $title_id,'quota' => $quota_id,'grade_id' => $grade,'standard_id' => $std,'month_id' => $month_id,'sub_institute_id' => $sub_institute_id])->get()->toArray();

                                if (count($checkNewfeesBreakoff) == 0) 
                                {
                                    DB::table('fees_breackoff')->insert(
                                            array(
                                                'syear' => session()->get('syear'),
                                                'admission_year' => session()->get('syear'),
                                                'fee_type_id' => $title_id,
                                                'quota' => $quota_id,
                                                'grade_id' => $grade,
                                                'standard_id' => $std,    
                                                'month_id' => $month_id,
                                                'amount' => $amount,
                                                'sub_institute_id' => session()->get('sub_institute_id'),
                                                'created_at' => date('Y-m-d H:i:s')
                                            )
                                    );
                                }

                            }
                        }
                    }
                }
            }

            $cur_syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $query = "select distinct(admission_year)
                    from tblstudent
                    where sub_institute_id = $sub_institute_id AND admission_year < $cur_syear"; //admission_year != $cur_syear 
            $old_year = DB::select($query);


            $all_data = $_REQUEST['OldValues'];
            foreach ($all_data as $id => $arr) {
                foreach ($arr as $ids => $val) {
                    if ($val == '' || $val == NULL) {
                        unset($all_data[$id][$ids]);
                    }
                }
            }
            foreach ($all_data as $id => $arr) {
                if (count($arr) == 0) {
                    unset($all_data[$id]);
                }
            }

            foreach ($old_year as $year_id => $year_arr) 
            {
                foreach ($req['grade'] as $grade_id => $grade) 
                {
                    foreach ($req['standard'] as $std_id => $std) 
                    {
                        foreach ($all_data as $quota_id => $arr) 
                        {
                            foreach ($arr as $title_id => $amount) 
                            {
                                foreach ($req['month'] as $month_id => $on) 
                                {
                                    $syear = session()->get('syear');
                                    $admission_year = $year_arr->admission_year;
                                    $sub_institute_id = session()->get('sub_institute_id');

                                    $checkOldfeesBreakoff = fees_breackoff::where(['syear' => $syear,'admission_year' => $admission_year,'fee_type_id' => $title_id,'quota' => $quota_id,'grade_id' => $grade,'standard_id' => $std,'month_id' => $month_id,'sub_institute_id' => $sub_institute_id])->get()->toArray();

                                    if (count($checkOldfeesBreakoff) == 0) 
                                    {
                                        DB::table('fees_breackoff')->insert(
                                                array(
                                                    'syear' => session()->get('syear'),
                                                    'admission_year' => $year_arr->admission_year,
                                                    'fee_type_id' => $title_id,
                                                    'quota' => $quota_id,
                                                    'grade_id' => $grade,
                                                    'standard_id' => $std,
                                                    'month_id' => $month_id,
                                                    'amount' => $amount,
                                                    'sub_institute_id' => session()->get('sub_institute_id'),
                                                    'created_at' => date('Y-m-d H:i:s')
                                                )
                                        );
                                    }    
                                }
                            }
                        }
                    }
                }
            }


            $res = array(
                "status" => 1,
                "message" => "Fees Structure Saved Successfully",
            );

            $type = $request->input('type');

            return \App\Helpers\is_mobile($type, "fees_breackoff.index", $res, "redirect");
        } else {
            $grade = DB::table('academic_section')
                            ->whereIn('id', $_REQUEST['grade'])->get();
            $grade_arr = array();
            foreach ($grade as $id => $arr) {
                $grade_arr[] = $arr->title;
            }

            $standard = DB::table('standard')
                            ->whereIn('id', $_REQUEST['standard'])->get();
            $standard_arr = array();
            foreach ($standard as $id => $arr) {
                $standard_arr[] = $arr->name;
            }

//            $division = DB::table('division')
//                            ->whereIn('id', $_REQUEST['division'])->get();
//            $division_arr = array();
//            foreach ($division as $id => $arr) {
//                $division_arr[] = $arr->name;
//            }

            $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
            $ReqMonths = $_REQUEST["month"];
            $months_arr = array();
            foreach ($ReqMonths as $id => $on) {
                $y = $id / 10000;
                $month = (int) $y;
                // $year_arr = explode('.', $y);
                // $year = $year_arr[1];
                $year = substr($id,-4);
                $months_arr[] = $months[$month] . "/" . $year;
            }

            $where_arr = array(
                'other_fee_id' => 0,
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear')
            );
            $fees_title = DB::table('fees_title')
                            ->where($where_arr)->get();
            $title_arr = array();
            foreach ($fees_title as $id => $arr) {
                $title_arr[$arr->id] = $arr->display_name;
            }

            $where_arr = array(
                'sub_institute_id' => session()->get('sub_institute_id'),
            );
            $student_quota = DB::table('student_quota')
                            ->where($where_arr)->get();
            $quota_arr = array();
            foreach ($student_quota as $id => $arr) {
                $quota_arr[$arr->id] = $arr->title;
            }

            $req = array(
                "grade" => $_REQUEST['grade'],
                "standard" => $_REQUEST['standard'],
//                "division" => $_REQUEST['division'],
                "month" => $_REQUEST['month'],
            );
            $request->session()->put('req', $req);

            $school_data['data']['grade_arr'] = $grade_arr;
            $school_data['data']['std_arr'] = $standard_arr;
//            $school_data['data']['div_arr'] = $division_arr;
            $school_data['data']['month_arr'] = $months_arr;
            $school_data['data']['title_arr'] = $title_arr;
            $school_data['data']['quota_arr'] = $quota_arr;
//            $school_data['data']['req'] = $req;
            $type = $request->input('type');            
            return \App\Helpers\is_mobile($type, "fees/fees_breackoff/edit", $school_data, "view");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }

    public function ajax_checkFeesStructure(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $grade = $request->input('grade');       
        $standard = $request->input('standard');       
        $month_values = $request->input('month_values');   
        $month_arr = explode(",",$month_values);
        
        //$final_array = array();
        foreach($month_arr as $key => $val)
        {      
            $fees_breakoff_data = fees_breackoff::select(db::raw('count(*) as total'))
            ->where(['sub_institute_id'=>$sub_institute_id,
                    'syear'=>$syear,
                    'month_id'=>$val,
                    'grade_id'=>$grade,
                    'standard_id'=>$standard
                    ])
            ->get()
            ->toArray();
            
            $fees_breakoff_data = $fees_breakoff_data[0];
            
            if($fees_breakoff_data['total'] > 0)
            {
                $month_name = $this->getMonthName($val);
                $final_array[$key]['Month'] = $month_name;
                $final_array[$key]['Total'] = $fees_breakoff_data['total'];
            }            
        }  
          
        return $final_array;
    }


    public function getMonthName($month)
    {
        
        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
        
        $month_name = '';        
        $y = $month / 10000;        
        $year = substr($month,-4);
        $month = (int) $y;
        
        $month_name .= $months[$month]. "/".$year.',';
        
        //echo $month_name = substr($month_name, 0, -1);

        return $month_name;
    }
}
