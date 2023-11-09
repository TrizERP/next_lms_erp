<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\proxyModel;
use App\Models\school_setup\timetableModel;
use App\Models\user\tbluserModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

class proxyController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;

        return is_mobile($type, 'school_setup/show_proxy', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        if($request->type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        return proxyModel::select(
            'proxy_master.*',
            's.name as standard_name',
            'd.name as division_name',
            DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as teacher_name'),
            DB::raw('concat(u1.first_name," ",u1.middle_name," ",u1.last_name) as proxy_teacher_name'),
            'p.title as period_name',
            DB::raw('concat(sub.subject_name,"(",sub.subject_code,")") as sub_name')
        )->join('standard as s',function($join) use($marking_period_id){
                $join->on( 's.id', '=', 'proxy_master.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division as d', 'd.id', '=', 'proxy_master.division_id')
            ->join('tbluser as u', 'u.id', '=', 'proxy_master.teacher_id')
            ->join('tbluser as u1', 'u1.id', '=', 'proxy_master.proxy_teacher_id')
            ->join('period as p', 'p.id', '=', 'proxy_master.period_id')
            ->join('subject as sub', 'sub.id', '=', 'proxy_master.subject_id')
            ->where(['proxy_master.sub_institute_id' => $sub_institute_id])
            ->get();
    }

    public function getproxydata(Request $request)
    {
        $marking_period_id = session()->get('term_id');
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 200);
        }
        $response = ['data' => '', 'status' => '0', 'message' => 'Data Not Found.'];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'from_date'        => 'required|date_format:Y-m-d',
            'to_date'          => 'required|date_format:Y-m-d',
        ]);
        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $from_date = $_REQUEST["from_date"];
            $to_date = $_REQUEST["to_date"];
            $teacher_id = $_REQUEST["teacher_id"];
            $data = proxyModel::select(
                'proxy_master.*',
                's.name as standard_name',
                'd.name as division_name',
                DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as teacher_name'),
                DB::raw('concat(u1.first_name," ",u1.middle_name," ",u1.last_name) as proxy_teacher_name'),
                'p.title as period_name',
                DB::raw('concat(sub.subject_name,"(",sub.subject_code,")") as sub_name')
            )
            ->join('standard as s',function($join) use($marking_period_id){
                $join->on( 's.id', '=', 'proxy_master.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
                ->join('division as d', 'd.id', '=', 'proxy_master.division_id')
                ->join('tbluser as u', 'u.id', '=', 'proxy_master.teacher_id')
                ->join('tbluser as u1', 'u1.id', '=', 'proxy_master.proxy_teacher_id')
                ->join('period as p', 'p.id', '=', 'proxy_master.period_id')
                ->join('subject as sub', 'sub.id', '=', 'proxy_master.subject_id')
                ->where(['proxy_master.sub_institute_id' => $sub_institute_id])
                ->where('proxy_master.proxy_date', '>=', "$from_date")
                ->where('proxy_master.proxy_date', '<=', "$to_date")
                ->where('proxy_master.proxy_teacher_id', '=', "$teacher_id")
                ->get();
            $response['data'] = $data;
            $response['message'] = "Sucsess";
            $response['status'] = '1';
        }

        return json_encode($response);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($request->type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        $user_data = tbluserModel::select(
            'tbluser.*',
            DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name')
        )
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher'])
            ->orderBy('tbluser.first_name')
            ->get();
        $data['teacher_data'] = $user_data;

        return is_mobile($type, 'school_setup/add_proxy', $data, "view");
    }

    public function getproxyperiod(Request $request)
    {
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $proxy_teacher_id = $request->get('proxy_teacher_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id= session()->get('term_id');
        if($request->type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        $days_arr = $this->getcountdays($from_date, $to_date);
        $days = array_keys($days_arr);
        $timetable_data = timetableModel::select(
            'timetable.*',
            DB::raw('group_concat(DISTINCT s.name) as standard_name'),
            'b.title as batch_name',
            DB::raw('group_concat(DISTINCT d.name) as division_name'),
            'su.subject_name',
            'p.title as period_name',
            'timetable.id as timetable_id'
        )
            ->join('standard AS s', function ($join)  use($marking_period_id){
                $join->on('s.id', '=', 'timetable.standard_id');
                $join->on('s.sub_institute_id', '=', 'timetable.sub_institute_id');
                // $join->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division AS d', function ($join) {
                $join->on('d.id', '=', 'timetable.division_id');
                $join->on('d.sub_institute_id', '=', 'timetable.sub_institute_id');
            })
            ->join('period AS p', function ($join) {
                $join->on('p.id', '=', 'timetable.period_id');
                $join->on('p.sub_institute_id', '=', 'timetable.sub_institute_id');
            })
            ->join('subject AS su', function ($join) {
                $join->on('su.id', '=', 'timetable.subject_id');
                $join->on('su.sub_institute_id', '=', 'timetable.sub_institute_id');
            })
            ->leftjoin('batch AS b', function ($join) {
                $join->on('b.id', '=', 'timetable.batch_id');
                $join->on('b.sub_institute_id', '=', 'timetable.sub_institute_id');
            })
            ->where([
                'timetable.sub_institute_id' => $sub_institute_id, 'teacher_id' => $proxy_teacher_id,
                'timetable.syear'            => $syear,
            ])
            ->whereIn('week_day', $days)
            /* ->whereNotIn('timetable.id', function ($query) use ($sub_institute_id, $proxy_teacher_id, $from_date, $to_date) {
                $query->select(DB::raw('ifnull(group_concat(timetable_id),0)'))
                    ->from('proxy_master')
                    ->whereRaw("sub_institute_id = $sub_institute_id  and teacher_id = $proxy_teacher_id")
                    ->whereBetween('proxy_date', [$from_date, $to_date]);
            }) */
            ->groupby('p.id')
            ->orderBy('week_day', 'asc')
            ->get()->toArray();
        $proxydata = array();
        foreach ($timetable_data as $tkey => $tval) {
            $dates = $days_arr[$tval['week_day']];
            $daysarr = array(
                'M' => 'Monday', 'T' => 'Tuesday', 'W' => 'Wednesday', 'H' => 'Thursday', 'F' => 'Friday',
                'S' => 'Saturday',
            );
            foreach ($dates as $key => $val) {
                //Get free teacher according to period and day

            $teacher_data = DB::table('tbluser as t')
                ->select(DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as teacher_name, t.id"))
                ->join('tbluserprofilemaster as u', function ($join) use ($syear,$sub_institute_id) {
                    $join->on('u.id', '=', 't.user_profile_id')
                         ->where('u.name', '=', 'Teacher')
                         ->where('u.sub_institute_id', '=', $sub_institute_id);
                })
                ->leftJoin('timetable as ti', 't.id', '=', 'ti.teacher_id')
                // ->where('ti.period_id', '<>', $tval["period_id"])
                ->where('ti.teacher_id', '<>', $proxy_teacher_id)
                ->where('ti.syear', '<=', $syear)
                ->where('ti.sub_institute_id', '=', $sub_institute_id)
                ->where('t.status', '=', 1)
                ->whereNotIn('ti.teacher_id', function($query) use ($tval, $syear,$sub_institute_id) {
                    $query->select('tt.teacher_id')
                          ->from('timetable as tt')
                          ->where('tt.syear', '=', $syear)
                          ->where('tt.sub_institute_id', '=', $sub_institute_id)
                          // ->whereNull('tt.week_day')
                          ->where('tt.period_id', '=', $tval["period_id"])
                          ->where('tt.week_day', '=', $tval["week_day"]);
                })
                ->orWhere("ti.week_day",null)
                ->groupBy('ti.teacher_id')
                ->orderBy('t.first_name')
                ->get();
                
                $proxydata[] = [
                    'date'          => $val,
                    'standard_id'   => $tval['standard_id'],
                    'division_id'   => $tval['division_id'],
                    'subject_id'    => $tval['subject_id'],
                    'period_id'     => $tval['period_id'],
                    'subject_name'  => $tval['subject_name'],
                    'standard_name' => $tval['standard_name'],
                    'division_name' => $tval['division_name'],
                    'period_name'   => $tval['period_name'],
                    'timetable_id'  => $tval['timetable_id'],
                    'week_day'      => $daysarr[$tval['week_day']],
                    'batch_name'    => $tval['batch_name'],
                    'teacher_data'  => $teacher_data,
                ];
            }
        }

        $user_data = tbluserModel::select(
            'tbluser.*',
            DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name')
        )
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher'])
            ->orderBy('tbluser.first_name')
            ->get();

        $data['teacher_data'] = $user_data;

        $type = $request->input('type');
        $data['proxydata'] = $proxydata;
        $data['teacher'] = $proxy_teacher_id;
        // echo "<pre>";print_r($data['teacher_data']);exit;
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
// exit;
        return is_mobile($type, 'school_setup/add_proxy', $data, "view");
    }

    public function getcountdays($from_date, $to_date)
    {
        //5 for count Friday, 6 for Saturday , 7 for Sunday
        $days = array('M' => '1', 'T' => '2', 'W' => '3', 'H' => '4', 'F' => '5', 'S' => '6');
        foreach ($days as $key => $day) {
            $i = 0;
            $from_date1 = $from_date;
            while (strtotime($from_date1) <= strtotime($to_date)) {
                if (date("N", strtotime($from_date1)) == $day) {
                    $i++;
                    $counter[$key][] = $from_date1;
                }
                $from_date1 = date("Y-m-d", strtotime("+1 day", strtotime($from_date1)));
            }
        }

        return $counter;
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // return $request;exit;
        $selected_data = $request->get('proxy_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        if($request->type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        foreach ($selected_data as $key => $val) {
            $arr = explode("/", str_replace("'", "", $key));
            $date = $arr[0];
            $timetable_id = $arr[1];

            $t_data = timetableModel::where(['sub_institute_id' => $sub_institute_id, 'id' => $timetable_id])
                ->get()->toArray();
            $t_data = $t_data[0];

            $insertarr[] = [
                "syear"            => $syear,
                "proxy_date"       => $date,
                "sub_institute_id" => $sub_institute_id,
                "grade_id"         => $t_data['academic_section_id'],
                "standard_id"      => $t_data['standard_id'],
                "division_id"      => $t_data['division_id'],
                "batch_id"         => $t_data['batch_id'],
                "period_id"        => $t_data['period_id'],
                "subject_id"       => $t_data['subject_id'],
                "week_day"         => $t_data['week_day'],
                "teacher_id"       => $t_data['teacher_id'],
                "proxy_teacher_id" => $request->teacher_id[$key],
                "timetable_id"     => $timetable_id,
            ];
        }

        proxyModel::insert($insertarr);
        $res = [
            "status_code" => 1,
            "message"     => "Proxy Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "proxy_master.index", $res, "redirect");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        if($request->type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        $timetable_data = proxyModel::select(
            'proxy_master.*',
            's.name as standard_name',
            'b.title as batch_name',
            'd.name as division_name',
            'su.subject_name',
            'p.title as period_name',
            'proxy_master.id as proxy_master_id'
        )
            ->join('standard AS s', function ($join) use($marking_period_id) {
                $join->on('s.id', '=', 'proxy_master.standard_id');
                $join->on('s.sub_institute_id', '=', 'proxy_master.sub_institute_id');
                // $join->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division AS d', function ($join) {
                $join->on('d.id', '=', 'proxy_master.division_id');
                $join->on('d.sub_institute_id', '=', 'proxy_master.sub_institute_id');
            })
            ->join('period AS p', function ($join) {
                $join->on('p.id', '=', 'proxy_master.period_id');
                $join->on('p.sub_institute_id', '=', 'proxy_master.sub_institute_id');
            })
            ->join('subject AS su', function ($join) {
                $join->on('su.id', '=', 'proxy_master.subject_id');
                $join->on('su.sub_institute_id', '=', 'proxy_master.sub_institute_id');
            })
            ->leftjoin('batch AS b', function ($join) {
                $join->on('b.id', '=', 'proxy_master.batch_id');
                $join->on('b.sub_institute_id', '=', 'proxy_master.sub_institute_id');
            })
            ->where(['proxy_master.sub_institute_id' => $sub_institute_id, 'proxy_master.id' => $id])
            ->get()->toArray();

        //Get free teacher according to period and day
        // $user_data = timetableModel::select(
        //     'timetable.teacher_id as id',
        //     DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name')
        // )
        //     ->join('tbluser', 'tbluser.id', "=", 'timetable.teacher_id')
        //     ->where([
        //         'timetable.sub_institute_id' => $sub_institute_id,
        //         'timetable.week_day'         => $timetable_data[0]['week_day'],
        //     ])
        //     ->where('timetable.teacher_id', '<>', $timetable_data[0]['teacher_id'])
        //     ->where('timetable.period_id', '<>', $timetable_data[0]['period_id'])
        //     ->groupBy('timetable.teacher_id')
        //     ->orderBy('tbluser.first_name')
        //     ->get();
        $user_data = tbluserModel::select('tbluser.*',
        DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name'))
        ->join('tbluserprofilemaster','tbluserprofilemaster.id' ,"=", 'tbluser.user_profile_id')
        ->where(['tbluser.sub_institute_id'=>$sub_institute_id,'tbluserprofilemaster.name' => 'Teacher'])
        ->whereNotIn('tbluser.id',array($timetable_data[0]['teacher_id']))
        ->get();

        $data['teacher_data'] = $user_data;

        $type = $request->input('type');
        $data['proxydata'] = $timetable_data[0];

        return is_mobile($type, 'school_setup/edit_proxy', $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if($request->type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        $data = [
            'proxy_teacher_id' => $request->get('proxy_teacher_id'),
        ];
        proxyModel::where(["id" => $id])->update($data);
        $res = [
            "status_code" => 1,
            "message"     => "Proxy Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "proxy_master.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        proxyModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Proxy Deleted Successfully";

        return is_mobile($type, "proxy_master.index", $res);
    }
}
