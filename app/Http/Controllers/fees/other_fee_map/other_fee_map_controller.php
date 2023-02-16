<?php

namespace App\Http\Controllers\fees\other_fee_map;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\fees\fees_title\fees_title;
use DB;
use function App\Helpers\FeeMonthId;

class other_fee_map_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $fee_month = FeeMonthId();
        // echo ('<pre>');print_r($fee_month);exit;
        $data['data']['ddMonth'] = $fee_month;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "fees/other_fee_map/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) 
    {
        session(['month_id' => $_REQUEST['month_id']]);

        $type = $request->input('type');
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $mp_id = session()->get('month_id');
        $query = "SELECT * FROM fees_breakoff_other 
                  WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."' AND month_id = '".$mp_id."' ";
        $query = preg_replace('/\n+/', '', $query);

        $fees_breckoff = DB::select(DB::raw($query));

        $fees_title = fees_title::
                        select('id', 'display_name', 'fees_title', 'mandatory', 'syear', 'other_fee_id')
                        ->where([
                            'sub_institute_id' => session()->get('sub_institute_id'),
                            'syear' => session()->get('syear'),
                            'fees_title_id' => 1
                        ])->get()->toArray();

        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['standard'] = $_REQUEST['standard'];
       $responce_arr['division'] = $_REQUEST['division'];
        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['std'] = $arr['standard_name'];
            $responce_arr['stu_data'][$id]['div'] = $arr['division_name'];
            foreach ($fees_title as $temp_id => $vals) {
                $amount = 0;
                foreach ($fees_breckoff as $bk_temp_id => $bk_vals) {
                    if ($arr['student_id'] == $bk_vals->student_id &&
                            $bk_vals->fee_type_id == $vals['other_fee_id']
                    ) {
                        $amount = $bk_vals->amount;
                    }
                }
                $responce_arr['stu_data'][$id][$vals['other_fee_id']] = $amount;
            }
        }
        $responce_arr['fees_title'] = $fees_title;

        return \App\Helpers\is_mobile($type, "fees/other_fee_map/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) 
    {
        if (isset($_REQUEST['student_id'])) 
        {
            foreach ($_REQUEST['student_id'] as $student_id => $val) {
                foreach ($_REQUEST['values'] as $student_id1 => $arr) {
                    if ($student_id == $student_id1) {

                        foreach ($arr as $fee_type_id => $value) {
                            DB::table('fees_breakoff_other')
                                    ->where([
                                        'syear' => session()->get('syear'),
                                        'student_id' => $student_id,
                                        'fee_type_id' => $fee_type_id,
                                        // 'grade_id' => $_REQUEST['grade'],
                                        // 'standard_id' => $_REQUEST['standard'],
                                        // 'section_id' => $_REQUEST['division'],
                                        'month_id' => session()->get('month_id'),
                                        'sub_institute_id' => session()->get('sub_institute_id')
                                    ])->delete();
                            DB::table('fees_breakoff_other')->insert(
                                    array(
                                        'syear' => session()->get('syear'),
                                        'student_id' => $student_id,
                                        'fee_type_id' => $fee_type_id,
                                        // 'grade_id' => $_REQUEST['grade'],
                                        // 'standard_id' => $_REQUEST['standard'],
                                        // 'section_id' => $_REQUEST['division'],
                                        'month_id' => session()->get('month_id'),
                                        'amount' => $value,
                                        'sub_institute_id' => session()->get('sub_institute_id')
                                    )
                            );
                        }
                    }
                }
            }
            $res = array(
                "status_code" => 1,
                "message" => "Other Fees Breakoff Added Successfully.",
            );
        }else{
            $res = array(
                "status_code" => 0,
                "message" => "Please select minimum one student",
            );
        }
        
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "other_fee_map.index", $res, "redirect");
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

}
