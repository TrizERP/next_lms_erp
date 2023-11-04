<?php

namespace App\Http\Controllers\calendar\calendar;

use App\Http\Controllers\Controller;
use App\Models\calendar\calendar\calendar;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

class calendar_controller extends Controller
{

    use GetsJwtToken;

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

        $data['data'] = [];

        $data = $this->getData($request);

        $calendarData = [];
        if (count($data) > 0) {
            foreach ($data as $key => $val) {
                $std = "";
                if ($val['standard'] != "") {
                    $std_arr = explode(",", $val['standard']);
                    $std = json_encode($std_arr);
                }

                $color_bg = [
                    'vacation' => 'bg-warning',
                    'event'    => 'bg-success',
                    'holiday'  => 'bg-danger',
                ];

                $calendarData[] = [
                    'id'               => $val['id'],
                    'title'            => $val['title'],
                    'start'            => $val['school_date'],
                    'description'      => $val['description'],
                    'event_type'       => $val['event_type'],
                    'standard'         => $std,
                    'sub_institute_id' => $val['sub_institute_id'],
                    'className'        => $color_bg[$val['event_type']],
                ];
            }
        }
        $calendarData = json_encode($calendarData, true);

        $standard = DB::table("standard")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->pluck("name", "id");

        $res['calendarData'] = $calendarData;
        $res['standardData'] = $standard;
        $type = $request->input('type');

        return is_mobile($type, "calendar/calendar/show", $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $extra = ['lp.sub_institute_id' => $sub_institute_id, 'lp.syear' => $syear];

        return calendar::from("calendar_events as lp")
            ->where($extra)
            ->get()->toArray();
    }

    public function fetchData(Request $request)
    {
        $response = ['response' => '', 'success' => false];

        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type'             => ["in:holiday,event,vacation,"],
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

            $result = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id');
                })->join('academic_section as g', function ($join) {
                    $join->whereRaw('g.id = se.grade_id');
                })->join('standard as st', function ($join) use ($marking_period_id) {
                    $join->on('st.id', '=', 'se.standard_id');
                        // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                        //     $query->where('st.marking_period_id', $marking_period_id);
                        // });
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->join('school_setup as ss', function ($join) {
                    $join->whereRaw('s.sub_institute_id = ss.Id');
                })->select(['se.standard_id', 'se.section_id', 'se.grade_id'])
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                ->where('se.student_id', $student_id)
                ->groupBy('s.id')->get()->toArray();

            if ($result) {
                $standard_id = $result[0]->standard_id;
                $extra_condition = "";
                if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                    $extra_condition = " AND event_type = '".$_REQUEST["type"]."'";
                }

                $result_data = DB::table('calendar_events')
                    ->whereRaw("FIND_IN_SET(".$standard_id.",standard)".$extra_condition)->get()->toArray();
                $response['response'] = $result_data;
                $response['success'] = true;
            } else {
                $response['response'] = ["student_id" => ["No student found."]];
            }
        }

        return json_encode($response);
    }

    public function TeacherFetchData(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 200);
        }

        $response = ['status' => '0', 'message' => '', 'data' => []];

        $validator = Validator::make($request->all(), [
            'standard_id'      => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type'             => ["in:holiday,event,vacation,"],
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $_REQUEST['standard_id'];


            $extra_condition = "";
            if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                $extra_condition = " AND event_type = '".$_REQUEST["type"]."'";
            }

            $result_data = DB::table('calendar_events')
                ->whereRaw("FIND_IN_SET(".$standard_id.",standard)".$extra_condition)->get()->toArray();
            $response['data'] = $result_data;
            $response['status'] = '1';
            $response['message'] = 'Sucsses';
        }

        return json_encode($response);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $syear = $request->session()->get('syear');

        $finalArray[] = [
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'event_type'       => $request->get('event_type'),
            'standard'         => implode(",", $request->get('standard')),
            'school_date'      => date("Y-m-d", $request->get('school_date') / 1000),
            'syear'            => $syear,
            'sub_institute_id' => $sub_institute_id,
        ];

        calendar::insert($finalArray);
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $standard = $request->get('standard');
        if (is_string($standard)) {
            // Convert the string to an array
            $standard = explode(',', $standard);
        }
        
        $finalArray = [
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'event_type'       => $request->get('event_type'),
            'standard'         => is_array($standard) ? implode(',', $standard) : null,
            'school_date'      => date("Y-m-d", $request->get('school_date') / 1000),
            'syear'            => $syear,
            'sub_institute_id' => $sub_institute_id,
        ];
        
        calendar::where(["id" => $id])->update($finalArray);

        return;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        calendar::where(["id" => $id])->delete();
    }

    /**
     * @param  Request  $request
     *
     *
     * @return false|JsonResponse|string
     */
    public function studentCalenderAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $action = $request->input("action");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {

            $data = DB::table('tblstudent_enrollment as s')
                ->leftJoin('calendar_events as c', function ($join) {
                    $join->whereRaw('find_in_set (s.standard_id,c.standard) AND c.syear=s.syear');
                })
                ->selectRaw('c.school_date AS school_date,c.title,c.description,c.event_type')
                ->where('s.student_id', $student_id)
                ->where('s.syear', $syear)
                ->where('s.sub_institute_id', $sub_institute_id)
                ->when($action != '', function ($q) use ($action) {
                    $q->where('c.event_type', $action);
                })->orderBy('c.school_date')->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
