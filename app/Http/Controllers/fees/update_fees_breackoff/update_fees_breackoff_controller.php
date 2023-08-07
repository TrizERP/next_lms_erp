<?php

namespace App\Http\Controllers\fees\update_fees_breackoff;

use App\Http\Controllers\Controller;
use App\Models\fees\map_year\map_year;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class update_fees_breackoff_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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

        $school_data['data']['ddMonth'] = $months_arr;
        $type = $request->input('type');
        return is_mobile($type, "fees/update_fees_breackoff/show", $school_data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
//        echo "<pre>";
//        print_r($_REQUEST);
//        exit;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|Response|string
     */
    public function store(Request $request)
    {
        //dd($request->all());
        if ($request->has('action') && $request->input('action') == 'insert'){
            
            if($request->has('NewValues'))
            {
                $all_data = $request->input('NewValues');
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
    //            foreach ($req['grade'] as $grade_id => $grade) {
    //                foreach ($req['standard'] as $std_id => $std) {
    //                    foreach ($req['division'] as $div_id => $div) {
                foreach ($all_data as $quota_id => $arr) {
                    foreach ($arr as $title_id => $amount) {
    //                                foreach ($req['month_id'] as $month_id => $on) {
                        DB::table('fees_breackoff')->where(
                            array(
                                'syear' => session()->get('syear'),
                                'admission_year' => session()->get('syear'),
                                'fee_type_id' => $title_id,
                                'quota' => $quota_id,
                                'grade_id' => $req['grade'],
                                'standard_id' => $req['standard'],
                                // 'section_id' => $req['division'],
                                'month_id' => $req['month'],
                                'sub_institute_id' => session()->get('sub_institute_id')
                            )
                        )->delete();
                        if ($amount != 0 && $amount != '') {
                            DB::table('fees_breackoff')->insert([
                                'syear' => session()->get('syear'),
                                'admission_year' => session()->get('syear'),
                                'fee_type_id' => $title_id,
                                'quota' => $quota_id,
                                'grade_id' => $req['grade'],
                                'standard_id' => $req['standard'],
                                // 'section_id' => $req['division'],
                                'month_id' => $req['month'],
                                'amount' => $amount,
                                'sub_institute_id' => session()->get('sub_institute_id'),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
    //                                }
                    }
                }
            }
//                    }
//                }
//            }

           
            if ($request->has('OldValues')) {
                $old_all_data = $request->input('OldValues');
                foreach ($old_all_data as $id => $arr) {
                    foreach ($arr as $ids => $val) {
                        if ($val == '' || $val == NULL) {
                            unset($old_all_data[$id][$ids]);
                        }
                    }
                }
                foreach ($old_all_data as $id => $arr) {
                    if (count($arr) == 0) {
                        unset($old_all_data[$id]);
                    }
                }

                $old_year = DB::table('fees_breackoff')->selectRaw('distinct(admission_year)')
                    ->where('sub_institute_id', session()->get('sub_institute_id'))
                    ->where('admission_year', "<", session()->get('syear'))->get()->toArray();

                foreach ($old_year as $year_id => $year_arr) {
    //                foreach ($req['grade'] as $grade_id => $grade) {
    //                    foreach ($req['standard'] as $std_id => $std) {
    //                        foreach ($req['division'] as $div_id => $div) {
                    foreach ($old_all_data as $quota_id => $arr) {
                        foreach ($arr as $title_id => $amount) {
    //                        foreach ($req['month_id'] as $month_id => $on) {

                            DB::table('fees_breackoff')->where([
                                'syear' => session()->get('syear'),
                                'admission_year' => $year_arr->admission_year,
                                'fee_type_id' => $title_id,
                                'quota' => $quota_id,
                                'grade_id' => $req['grade'],
                                'standard_id' => $req['standard'],
                                // 'section_id' => $req['division'],
                                'month_id' => $req['month'],
                                'sub_institute_id' => session()->get('sub_institute_id')
                            ])->delete();
                            if ($amount != 0 && $amount != '') {
                                DB::table('fees_breackoff')->insert([
                                    'syear' => session()->get('syear'),
                                    'admission_year' => $year_arr->admission_year,
                                    'fee_type_id' => $title_id,
                                    'quota' => $quota_id,
                                    'grade_id' => $req['grade'],
                                    'standard_id' => $req['standard'],
                                    // 'section_id' => $req['division'],
                                    'month_id' => $req['month'],
                                    'amount' => $amount,
                                    'sub_institute_id' => session()->get('sub_institute_id'),
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
    //                        }
    //                                }
    //                            }
    //                        }
                        }
                    }
                }
            }


            $res = array(
                "status_code" => 1,
                "message" => "Data Update Successfully",
            );

            $type = $request->input('type');

            return is_mobile($type, "update_fees_breackoff.index", $res, "redirect");
        } else {
            $result = DB::table('fees_breackoff')
                ->where('sub_institute_id', session()->get('sub_institute_id'))
                ->where('grade_id', $_REQUEST['grade'])
                ->where('standard_id', $_REQUEST['standard'])
                ->where('month_id', $_REQUEST['month_id'])->get()->toArray();


            $bk_arr = array();
            foreach ($result as $id => $arr) {
                if ($arr->admission_year == session()->get('syear')) {
                    $bk_arr['new'][$arr->quota][$arr->fee_type_id] = $arr->amount;
                } else {
                    $bk_arr['old'][$arr->quota][$arr->fee_type_id] = $arr->amount;
                }
            }

            $where_arr = array(
                'other_fee_id' => 0,
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear')
            );
            $fees_title = DB::table('fees_title')
                ->where($where_arr)->orderBy('sort_order')->get();
            $title_arr = [];
            foreach ($fees_title as $id => $arr) {
                $title_arr[$arr->id] = $arr->display_name;
            }

            $where_arr = [
                'sub_institute_id' => session()->get('sub_institute_id'),
            ];
            // $student_quota = DB::table('tblstudent_quota')
            $student_quota = DB::table('student_quota')
                ->where($where_arr)->get();
            $quota_arr = [];
            foreach ($student_quota as $id => $arr) {
                $quota_arr[$arr->id] = $arr->title;
            }


            $req = [
                "grade" => $_REQUEST['grade'],
                "standard" => $_REQUEST['standard'],
                // "division" => $_REQUEST['division'],
                "month" => $_REQUEST['month_id'],
            ];
            $request->session()->put('req', $req);

            $school_data['data']['title_arr'] = $title_arr;
            $school_data['data']['quota_arr'] = $quota_arr;
            $school_data['data']['bk_arr'] = $bk_arr;

            $type = $request->input('type');

            //START If fees collected breakoff cant be edited
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');

            $paid_result = DB::table('fees_collect as f')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('f.student_id = se.student_id AND f.sub_institute_id = se.sub_institute_id AND f.syear = se.syear');
                })->join('tblstudent as s', function ($join) {
                    $join->whereRaw('s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id');
                })->selectRaw('se.student_quota,s.admission_year')
                ->where('f.sub_institute_id', $sub_institute_id)
                ->where('f.syear', $syear)
                ->where('f.term_id', $_REQUEST['month_id'])
                ->where('se.grade_id', $_REQUEST['grade'])
                ->where('se.standard_id', $_REQUEST['standard'])
                ->where('f.is_deleted', '=', 'N')->get()->toArray();

            $paid_arr = array();

            foreach ($paid_result as $p_id => $p_arr) {
                if ($p_arr->admission_year == session()->get('syear')) {
                    $paid_arr['new'][$p_arr->student_quota] = 'Y';
                } else {
                    $paid_arr['old'][$p_arr->student_quota] = 'Y';
                }
            }
            $next_syear = ($syear+1);
            $month_name = [
                "1".$syear => 'Jan', "2".$syear => 'Feb', "3".$syear => 'Mar',"4".$syear => 'Apr', "5".$syear => 'May', "6".$syear => 'Jun', "7".$syear => 'Jul', "8".$syear => 'Aug',
                "9".$syear => 'Sep', "10".$syear => 'Oct', "11".$syear => 'Nov', "12".$syear => 'Dec', "1".$next_syear => 'Jan', "2".$next_syear => 'Feb', "3".$next_syear => 'Mar',
            ];

            $grade_name = DB::table('academic_section')
            ->where('id', $_REQUEST['grade'])->get();

            $std_name = DB::table('standard')
            ->where('id', $_REQUEST['standard'])->get();

            $school_data['data']['paid_arr'] = $paid_arr;
            $school_data['grade']= $grade_name[0]->title;
            $school_data['standard']=$std_name[0]->name;
            $school_data['month']=$month_name[$_REQUEST['month_id']];
            //END If fees collected breakoff cant be edited

            return is_mobile($type, "fees/update_fees_breackoff/edit", $school_data, "view");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

}
