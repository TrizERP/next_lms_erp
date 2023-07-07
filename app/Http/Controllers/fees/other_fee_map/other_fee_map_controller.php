<?php

namespace App\Http\Controllers\fees\other_fee_map;

use App\Http\Controllers\Controller;
use App\Models\fees\fees_title\fees_title;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;

class other_fee_map_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $fee_month = FeeMonthId();
        $fees_title = fees_title::select('id', 'display_name', 'fees_title', 'mandatory', 'syear', 'other_fee_id')
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
                'fees_title_id' => 1
            ])->orderBy('sort_order')->get();
        $data['data']['ddMonth'] = $fee_month;
        $data['data']['heads'] = $fees_title;
        
        $type = $request->input('type');
        return is_mobile($type, "fees/other_fee_map/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|string
     */
    public function create(Request $request)
    {
        // session(['month_id' => $_REQUEST['month_id']]);
        $type = $request->input('type');
        $fees_heads = $request->fees_heads;
        
     // controller.php
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'],$_REQUEST['standard'],$_REQUEST['division'],'', '','', $_REQUEST['stu_name'],$_REQUEST['uniqueid'],$_REQUEST['mobile'],$_REQUEST['grno']);
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $mp_id = $request->month_id;

        $fees_breckoff = DB::table('fees_breakoff_other')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->whereRaw('month_id IN ("'.implode('","',$mp_id).'")')->get()->toArray();
        // echo "<pre>";print_r($fees_breckoff);exit;
        $fees_title['data'] = fees_title::select('id', 'display_name', 'fees_title', 'mandatory', 'syear', 'other_fee_id')
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
                'fees_title_id' => 1
            ])
            ->whereRaw('id IN ("'.implode('","',$fees_heads).'")')
            ->orderBy('sort_order')->get()->toArray();
        $responce_arr['months_id'] = $request->month_id;
              
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['standard'] = $_REQUEST['standard'];
        $responce_arr['division'] = $_REQUEST['division'];
  
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $fees_breckoff_grp = DB::table('fees_breakoff_other')
        ->where('sub_institute_id', $sub_institute_id)
        ->where('syear', $syear)
        ->groupBy('month_id')->get()->toArray();
        $month_names = [];
        foreach ($mp_id as $id => $arr) {
            $y = $arr / 10000;
            $month = (int)$y;
            $year = substr($arr, -4);
            $month_names[$arr] = $months[$month] . "-" . $year;
        }

        foreach ($fees_title['data'] as $fees_title_item) {
            $fee_type_id = $fees_title_item['fees_title'];

            foreach ($mp_id as $arr) {
                $month_id = $arr;
                $month_name = $month_names[$month_id];
                $fees_title['month'][] = $month_name . '/' . $fees_title_item['display_name'];
                $fees_title['month_id'][] = $month_id;                
            }
        }

        $responce_arr['month_head'] = $fees_title['month'];
        // return $fees_title;exit;
        foreach ($student_data as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['std'] = $arr['standard_name'];
            $responce_arr['stu_data'][$id]['div'] = $arr['division_name'];
            foreach ($mp_id as $key=>$month_id){   
            foreach ($fees_title['data'] as $temp_id => $vals) {
                $amount = 0;
                $month_name = $fees_title['month'][$temp_id];
                foreach ($fees_breckoff as $bk_temp_id => $bk_vals) {
                    if ($arr['student_id'] == $bk_vals->student_id && $bk_vals->fee_type_id == $vals['other_fee_id'] && $month_id == $bk_vals->month_id) {
                        $amount = $bk_vals->amount;
                    }
                }
                
                $responce_arr['stu_data'][$id][$month_id][$vals['display_name']]['amount'] = $amount;
            }
        }
        }
        $responce_arr['fees_title'] = $fees_title;
        
        return is_mobile($type, "fees/other_fee_map/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|Response|string
     */
    public function store(Request $request)
    {
        if (isset($_REQUEST['student_id'])) {
            foreach ($_REQUEST['student_id'] as $student_id => $val) {
                foreach ($_REQUEST['values'] as $student_id1 => $arr) {
                    if ($student_id == $student_id1) {
                        foreach ($arr as $month_id => $value) {
                        foreach ($value as $fee_type_id => $amount) {
                            // if exists then delete
                           DB::table('fees_breakoff_other')
                                ->where([
                                    'syear' => session()->get('syear'),
                                    'student_id' => $student_id,
                                    'fee_type_id' => $fee_type_id,
                                    // 'grade_id' => $_REQUEST['grade'],
                                    // 'standard_id' => $_REQUEST['standard'],
                                    // 'section_id' => $_REQUEST['division'],
                                    'month_id' => $month_id,
                                    'sub_institute_id' => session()->get('sub_institute_id')
                                ])->delete();
                                //insert
                            DB::table('fees_breakoff_other')->insert(
                                array(
                                    'syear' => session()->get('syear'),
                                    'student_id' => $student_id,
                                    'fee_type_id' => $fee_type_id,
                                    // 'grade_id' => $_REQUEST['grade'],
                                    // 'standard_id' => $_REQUEST['standard'],
                                    // 'section_id' => $_REQUEST['division'],
                                    'month_id' => $month_id,
                                    'amount' => $amount,
                                    'sub_institute_id' => session()->get('sub_institute_id')
                                )
                            );
                        
                        }
                        }
                    }
                }
            }
            $res = array(
                "status_code" => 1,
                "message" => "Other Fees Breakoff Added Successfully.",
            );
        } else {
            $res = array(
                "status_code" => 0,
                "message" => "Please select minimum one student",
            );
        }

        $type = $request->input('type');
        $fee_month = FeeMonthId();
        $fees_title = fees_title::select('id', 'display_name', 'fees_title', 'mandatory', 'syear', 'other_fee_id')
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
                'fees_title_id' => 1
            ])->orderBy('sort_order')->get();
        $res['data']['ddMonth'] = $fee_month;
        $res['data']['heads'] = $fees_title;
        
        $type = $request->input('type');
        // return \App\Helpers\is_mobile($type, "other_fee_map.index", $res, "redirect");
        return is_mobile($type, "fees/other_fee_map/show", $res, "view");
    }

}
