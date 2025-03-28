<?php

namespace App\Http\Controllers\transportation\van_wise_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;
use App\Models\school_setup\SchoolModel;
use Validator;

class van_wise_report_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) {
            // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $data['data'] = [];
        $data['data']['ddShift'] = $this->ddShift();
        $data['data']['ddVan'] = $this->ddVan();
        $data['data']['ddRoute'] = $this->ddRoute();
        $data['data']['ddStop'] = $this->ddStop();

        $type = $request->input('type');

        return is_mobile($type, "transportation/van_wise_report/show", $data, "view");
    }

    public function ddShift()
    {
        return DB::table('transport_school_shift')
            ->select('transport_school_shift.shift_title', 'transport_school_shift.id')
            ->where("transport_school_shift.sub_institute_id", session()->get('sub_institute_id'))
            ->pluck('shift_title', 'id');
    }

    public function ddVan()
    {
        return DB::table('transport_vehicle')
            ->select('transport_vehicle.title', 'transport_vehicle.id')
            ->where("transport_vehicle.sub_institute_id", session()->get('sub_institute_id'))
            ->orderBy('transport_vehicle.title')
            ->pluck('title', 'id');
    }

    public function ddRoute()
    {
        $where = [
            "transport_route.sub_institute_id" => session()->get('sub_institute_id'),
            "transport_route.syear"            => session()->get('syear'),
        ];

        return DB::table('transport_route')
            ->select('transport_route.route_name', 'transport_route.id')
            ->where($where)
            ->pluck('route_name', 'id');
    }

    public function ddStop()
    {
        $where = [
            "transport_stop.sub_institute_id" => session()->get('sub_institute_id'),
            "transport_stop.syear"            => session()->get('syear'),
        ];

        return DB::table('transport_stop')
            ->select('transport_stop.stop_name', 'transport_stop.id')
            ->where($where)
            ->pluck('stop_name', 'id');
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

        $search = "";
        $marking_period_id = session()->get('term_id');

        if ($_REQUEST['pickup'] == 'drop') {
            $search = "to";
        } else {
            $search = "from";
        }

        $where = "tm.sub_institute_id = '".session()->get('sub_institute_id')."'
                and tm.syear = '".session()->get('syear')."' ";

        if (isset($_REQUEST['van']) && $_REQUEST['van'] != '') {
            $where .= " and tm.".$search."_bus_id = '".$_REQUEST['van']."' ";
        }
        if (isset($_REQUEST['shift']) && $_REQUEST['shift'] != '') {
            $where .= " and tm.".$search."_shift_id = '".$_REQUEST['shift']."' ";
        }
        if (isset($_REQUEST['grno']) && $_REQUEST['grno'] != '') {
            $where .= " and ts.enrollment_no = '".$_REQUEST['grno']."' ";
        }
        if (isset($_REQUEST['route']) && $_REQUEST['route'] != '') {
            $where .= " and tr.id ='".$_REQUEST['route']."' ";
        }
        if (isset($_REQUEST['stop']) && $_REQUEST['stop'] != '') {
            $where .= " and st.id ='".$_REQUEST['stop']."'  ";
        }

        $student_data = DB::table("tblstudent as ts")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = ts.id AND se.syear = '".session()->get('syear')."' AND se.end_date IS NULL");
            })
            ->join('standard as s', function ($join) use($marking_period_id){
                $join->whereRaw("s.id = se.standard_id");
                // ->when($marking_period_id,function($uqery) use($marking_period_id){
                //     $uqery->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division as d', function ($join) {
                $join->whereRaw("d.id = se.section_id");
            })
            ->join('transport_map_student as tm', function ($join) {
                $join->whereRaw("tm.student_id = ts.id");
            })
            ->join('transport_vehicle as tv', function ($join) use ($search) {
                $join->whereRaw("tv.id = tm.".$search."_bus_id");
            })
            ->join('transport_school_shift as ss', function ($join) use ($search) {
                $join->whereRaw("ss.id = tm.".$search."_shift_id");
            })
            ->join('transport_stop as st', function ($join) use ($search) {
                $join->whereRaw("st.id = tm.".$search."_stop");
            })
            ->join('transport_route_bus as rb', function ($join) {
                $join->whereRaw("rb.bus_id = tv.id");
            })
            ->join('transport_route as tr', function ($join) {
                $join->whereRaw("tr.id = rb.route_id");
            })
            ->join('transport_driver_detail as dd', function ($join) {
                $join->whereRaw("dd.id = tv.driver")->where('dd.status', 'Active');
            })
            ->leftJoin('transport_driver_detail as cd', function ($join) {
                $join->whereRaw("cd.id = tv.conductor")->where('cd.status', 'Active');
            })
            ->selectRaw("ts.id AS student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) name, 
    concat(s.name,'/',d.name) as stddiv,ts.mobile,ts.enrollment_no,ts.address, tr.route_name, ss.shift_title,tv.title as bus_name, st.stop_name,
    dd.first_name driver, cd.first_name conductor, tm.amount as van_vise_amount")
            ->whereRaw($where)
            ->groupBy('student_id')
            ->get()->toarray();

        $data['data'] = $student_data;

        $type = $request->input('type');

        return is_mobile($type, "transportation/van_wise_report/add", $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');

        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $message = $request->notificationText;

        if($type=="API"){
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
            $user_id = $request->get('user_id');     

            $validator = validator::make($request->all,[
                'sub_institute_id'=>'required|number',
                'syear'=>'require|number',
                'user_id'=>'require|number',
                'notificationText'=>'require',
            ]);     
            if ($validator->fails()) {
                $response['status'] = '0';
                $response['message'] = $validator->messages()->first();
                return response()->json($response, 401);
            }
        }
        $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
        $schoolName = $schoolData[0]['SchoolName'];
        $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

        foreach ($request->studentData as $studentId => $mobile) {
            $app_notification_content = [
                'NOTIFICATION_TYPE'        => 'TansportNotification',
                'NOTIFICATION_DATE'        => now(),
                'STUDENT_ID'               => $studentId,
                'NOTIFICATION_DESCRIPTION' => $message,
                'STATUS'                   => 0,
                'SUB_INSTITUTE_ID'         => $sub_institute_id,
                'SYEAR'                    => $syear,
                'SCREEN_NAME'              => 'general',
                'CREATED_BY'               => $user_id,
                'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
            ];
    
            $gcm_data = DB::table('gcm_users')->where('mobile_no', $mobile)
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
    
            $gcmRegIds = [];
            if (count($gcm_data) > 0) {
                foreach ($gcm_data as $key1 => $val1) {
                    $gcmRegIds[] = $val1->gcm_regid;
                }
            }
    
            $pushMessage = $message;
    
            $bunch_arr = array_chunk($gcmRegIds, 1000);
            sendNotification($app_notification_content);
            
            if (! empty($bunch_arr)) {
                $i++;
                foreach ($bunch_arr as $val) {
                    if (isset($val, $pushMessage)) {
                        $type1 = 'TansportNotification';
                        $message = [
                            'body'  => $pushMessage, 'TYPE' => $type1, 'USER_ID' => $studentId,
                            'title' => $schoolName, 'image' => $schoolLogo,
                        ];
                        $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                        // sendNotification($app_notification_content);
                    }
                }
                
            }
        }
        
        $response = ['status_code' => 200, 'message' => 'Notification sent successfull.'];
        return is_mobile($type, "van_wise_report.index", $response);
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
}
