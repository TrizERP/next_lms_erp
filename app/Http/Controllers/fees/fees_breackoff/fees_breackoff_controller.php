<?php

namespace App\Http\Controllers\fees\fees_breackoff;

use App\Http\Controllers\Controller;
use App\Models\fees\fees_breackoff\fees_breackoff;
use App\Models\fees\map_year\map_year;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class fees_breackoff_controller extends Controller
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
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "fees/fees_breackoff/show", $school_data, "view");
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

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');

        $data = map_year::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ])->get()->toArray();

        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = [
            1  => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $months_arr = [];
        $syear = session()->get('syear');

        if($data[0]['type'] == "yearly_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
        }
        else if($data[0]['type'] == "half_year_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
            $sixmonths = ($start_month+6);
            $months_arr[$sixmonths.$syear] = $months[$sixmonths].'/'.$syear;

        }
        else if($data[0]['type'] == "quarterly_fees")
        {
            for ($i = $start_month; $i <= 12; $i++)
            {
                if ($start_month <= 12)
                {
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    $start_month = ($start_month+3);
                }
                else
                {
                    $start_month = 1;
                    ++$syear;
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    break;
                }
            }
        }
        else
        {
            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                if ($start_month == 12) {
                    $start_month = 0;
                    ++$syear;
                }
                ++$start_month;
            }
        }

        /* for ($i = 1; $i <= 12; $i++) {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
            if ($start_month == 12) {
                $start_month = 0;
                ++$syear;
            }
            ++$start_month;
        } */

        $dataStore['data']['ddMonth'] = $months_arr;

        return is_mobile($type, 'fees/fees_breackoff/add', $dataStore, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'insert')
        {
            $all_data = $_REQUEST['NewValues'];

            foreach ($all_data as $id => $arr) {
                foreach ($arr as $ids => $val) {
                    if ($val == '' || $val == null) {
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

            foreach ($req['grade'] as $grade_id => $grade) {
                foreach ($req['standard'] as $std_id => $std) {
                    foreach ($all_data as $quota_id => $arr) {
                        foreach ($arr as $title_id => $amount) {
                            foreach ($req['month'] as $month_id => $on) {
                                $syear = session()->get('syear');
                                $admission_year = session()->get('syear');
                                $sub_institute_id = session()->get('sub_institute_id');

                                $checkNewfeesBreakoff = fees_breackoff::where([
                                    'syear'            => $syear, 'admission_year' => $admission_year,
                                    'fee_type_id'      => $title_id, 'quota' => $quota_id, 'grade_id' => $grade,
                                    'standard_id'      => $std, 'month_id' => $month_id,
                                    'sub_institute_id' => $sub_institute_id,
                                ])->get()->toArray();

                                if (count($checkNewfeesBreakoff) == 0) {
                                    DB::table('fees_breackoff')->insert([
                                        'syear'            => session()->get('syear'),
                                        'admission_year'   => session()->get('syear'),
                                        'fee_type_id'      => $title_id,
                                        'quota'            => $quota_id,
                                        'grade_id'         => $grade,
                                        'standard_id'      => $std,
                                        'month_id'         => $month_id,
                                        'amount'           => $amount,
                                        'sub_institute_id' => session()->get('sub_institute_id'),
                                        'created_at'       => date('Y-m-d H:i:s'),
                                    ]);
                                }

                            }
                        }
                    }
                }
            }

            $cur_syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');

            $old_year = DB::table('tblstudent')
                ->selectRaw('distinct(admission_year)')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('admission_year', '<', $cur_syear)->get()->toArray();

            $all_data = $_REQUEST['OldValues'];
            foreach ($all_data as $id => $arr) {
                foreach ($arr as $ids => $val) {
                    if ($val == '' || $val == null) {
                        unset($all_data[$id][$ids]);
                    }
                }
            }

            foreach ($all_data as $id => $arr) {
                if (count($arr) == 0) {
                    unset($all_data[$id]);
                }
            }

            foreach ($old_year as $year_id => $year_arr) {
                foreach ($req['grade'] as $grade_id => $grade) {
                    foreach ($req['standard'] as $std_id => $std) {
                        foreach ($all_data as $quota_id => $arr) {
                            foreach ($arr as $title_id => $amount) {
                                foreach ($req['month'] as $month_id => $on) {
                                    $syear = session()->get('syear');
                                    $admission_year = $year_arr->admission_year;
                                    $sub_institute_id = session()->get('sub_institute_id');

                                    $checkOldfeesBreakoff = fees_breackoff::where([
                                        'syear'            => $syear, 'admission_year' => $admission_year,
                                        'fee_type_id'      => $title_id, 'quota' => $quota_id, 'grade_id' => $grade,
                                        'standard_id'      => $std, 'month_id' => $month_id,
                                        'sub_institute_id' => $sub_institute_id,
                                    ])->get()->toArray();

                                    if (count($checkOldfeesBreakoff) == 0) {
                                        DB::table('fees_breackoff')->insert([
                                            'syear'            => session()->get('syear'),
                                            'admission_year'   => $year_arr->admission_year,
                                            'fee_type_id'      => $title_id,
                                            'quota'            => $quota_id,
                                            'grade_id'         => $grade,
                                            'standard_id'      => $std,
                                            'month_id'         => $month_id,
                                            'amount'           => $amount,
                                            'sub_institute_id' => session()->get('sub_institute_id'),
                                            'created_at'       => date('Y-m-d H:i:s'),
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }


            $res = [
                "status"  => 1,
                "message" => "Fees Structure Saved Successfully",
            ];

            $type = $request->input('type');

            return is_mobile($type, "fees_breackoff.index", $res, "redirect");
        }
        else
        {

            $grade = DB::table('academic_section')
                ->whereIn('id', $_REQUEST['grade'])->get();
            $grade_arr = [];

            foreach ($grade as $id => $arr) {
                $grade_arr[] = $arr->title;
            }

            $standard = DB::table('standard')
                ->whereIn('id', $_REQUEST['standard'])->get();
            $standard_arr = [];
            foreach ($standard as $id => $arr) {
                $standard_arr[] = $arr->name;
            }

            $months = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            ];
            $ReqMonths = $_REQUEST["month"] ?? [];
            $months_arr = [];

            foreach ($ReqMonths as $id => $on) {
                $y = $id / 10000;
                $month = (int) $y;
                $year = substr($id, -4);
                $months_arr[] = $months[$month]."/".$year;
            }

            $where_arr = [
                'other_fee_id'     => 0,
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear'            => session()->get('syear'),
            ];

            $fees_title = DB::table('fees_title')
                ->where($where_arr)->orderBy('sort_order')->get();
            $title_arr = [];
            foreach ($fees_title as $id => $arr) {
                $title_arr[$arr->id] = $arr->display_name;
            }

            $where_arr = [
                'sub_institute_id' => session()->get('sub_institute_id'),
            ];
            $student_quota = DB::table('student_quota')
                ->where($where_arr)->get();
            $quota_arr = [];
            foreach ($student_quota as $id => $arr) {
                $quota_arr[$arr->id] = $arr->title;
            }

            $req = [
                "grade"    => $_REQUEST['grade'],
                "standard" => $_REQUEST['standard'],
                "month"    => $_REQUEST['month'],
            ];

            $request->session()->put('req', $req);

            $school_data['data']['grade_arr'] = $grade_arr;
            $school_data['data']['std_arr'] = $standard_arr;
            $school_data['data']['month_arr'] = $months_arr;
            $school_data['data']['title_arr'] = $title_arr;
            $school_data['data']['quota_arr'] = $quota_arr;
            $type = $request->input('type');
            // echo "<pre>";print_r($school_data);exit;
            return is_mobile($type, "fees/fees_breackoff/edit", $school_data, "view");
        }
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

    public function ajax_checkFeesStructure(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $month_values = $request->input('month_values');
        $month_arr = explode(",", $month_values);

        foreach ($month_arr as $key => $val) {
            $fees_breakoff_data = fees_breackoff::select(DB::raw('count(*) as total'))
                ->where([
                    'sub_institute_id' => $sub_institute_id,
                    'syear' => $syear,
                    'month_id' => $val,
                    'grade_id' => $grade,
                    'standard_id' => $standard,
                ])->get()->toArray();

            $fees_breakoff_data = $fees_breakoff_data[0];

            if ($fees_breakoff_data['total'] > 0) {
                $month_name = $this->getMonthName($val);
                $final_array[$key]['Month'] = $month_name;
                $final_array[$key]['Total'] = $fees_breakoff_data['total'];
            }
        }

        return $final_array;
    }


    public function getMonthName($month)
    {

        $months = [
            1  => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $month_name = '';
        $y = $month / 10000;
        $year = substr($month, -4);
        $month = (int) $y;

        $month_name .= $months[$month]."/".$year.',';

        return $month_name;
    }
}
