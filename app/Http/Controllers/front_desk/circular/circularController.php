<?php

namespace App\Http\Controllers\front_desk\circular;

use App\Http\Controllers\Controller;
use App\Models\front_desk\circular\circular;
use App\Models\school_setup\SchoolModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;
use Illuminate\Pagination\Paginator;

class circularController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function index(Request $request)
    {
        if (session()->has('data')) {
            // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $data = $this->getData();
        $school_data['data'] = $data['data'];
        $school_data['circular_type'] = $data['circular_type'];

        $type = $request->input('type');

        return is_mobile($type, "front_desk/circular/show", $school_data, "view");
    }

    function getData()
    {
        $result['data'] = DB::table("circular as c")
            ->join('standard as s', function ($join) {
                $join->whereRaw("s.id = c.standard_id");
            })
            ->join('circular_type as t', function ($join) {
                $join->whereRaw("t.id = c.type");
            })
            ->join('division as d', function ($join) {
                $join->whereRaw("d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id");
            })
            ->selectRaw('c.*,s.name as std_name,t.type as circular_type,d.name as div_name')
            ->where("c.syear", "=", session()->get('syear'))
            ->where("c.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->orderBy('c.id', 'DESC')->limit(400)
            ->get()->toArray();

        $result['circular_type'] = DB::table('circular_type')->get()->toArray();

        return $result;
    }

    public function fetchData(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());

            return response()->json($response, 401);
        }
        $payload = $this->jwtPayload();

        $response = ['status' => '0', 'message' => '', 'data' => []];

        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'action'           => 'required',
        ]);

        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = isset($_REQUEST['student_id']) ? $_REQUEST['student_id'] : '0';
            $action = $_REQUEST['action'];

            if (isset($payload['student_id']) && $student_id == $payload['student_id'] && $sub_institute_id == $payload['sub_institute_id']) {

                $result = DB::table("tblstudent as s")
                    ->join('tblstudent_enrollment as se', function ($join) {
                        $join->whereRaw("se.student_id = s.id");
                    })
                    ->join('academic_section as g', function ($join) {
                        $join->whereRaw("g.id = se.grade_id");
                    })
                    ->join('standard as st', function ($join) {
                        $join->whereRaw("st.id = se.standard_id");
                    })
                    ->join('division as d', function ($join) {
                        $join->whereRaw("d.id = se.section_id");
                    })
                    ->join('school_setup as ss', function ($join) {
                        $join->whereRaw("s.sub_institute_id = ss.Id");
                    })
                    ->selectRaw('se.standard_id,se.section_id,se.grade_id,d.id as division_id')
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->where("se.syear", "=", $syear)
                    ->where("se.student_id", "=", $student_id)
                    ->groupBy('s.id')
                    ->get()->toarray();

                $standard_id = $result[0]->standard_id;
                $division_id = $result[0]->division_id;
                $extra_condition = "";
                if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                    $extra_condition = " AND event_type = '".$_REQUEST["type"]."'";
                }

                $result_data = DB::table("circular as c")
                    ->join('circular_type as t', function ($join) {
                        $join->whereRaw("t.id = c.type");
                    })
                    ->selectRaw("c.*,if(c.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/circular/',c.file_name))
                        as file_name,t.type as circular_type,DATE_FORMAT(c.date_,'%d-%m-%Y') AS date_")
                    ->where("standard_id", "=", $standard_id)
                    ->where("division_id", "=", $division_id)
                    ->where("syear", "=", $syear)
                    ->where("sub_institute_id", "=", $sub_institute_id)
                    ->where("t.type", "=", $action)
                    ->orderBy('c.date_', 'DESC')
                    ->get()->toArray();

                $response['data'] = $result_data;
                $response['message'] = "Success";
                $response['status'] = 1;
            } else {
                $response['message'] = array("Token Error" => "You are not authorized to view this data.");
            }
        }

        return json_encode($response);

    }

    public function TeacherFetchData(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());

            return response()->json($response, 401);
        }
        $payload = $this->jwtPayload();

        $response = array('status' => '0', 'message' => '', 'data' => array());

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'standard_id'      => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $_REQUEST['standard_id'];


            $result_data = DB::table("circular as c")
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id");
                })
                ->selectRaw("*,if(c.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/circular/',c.file_name)) as file_name,s.name as std_name")
                ->where("c.standard_id", "=", $standard_id)
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->get()->toArray();

            $response['data'] = $result_data;
            $response['message'] = "Success";
            $response['status'] = "1";
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

        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "API") {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $user_id = $_REQUEST['user_id'];
        } else {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $user_id = session()->get('user_id');
        }

        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $key => $file_data) {
                $file_name = "";
                $originalname = $file_data->getClientOriginalName();
                $name = 'circular_'.$key.'_'.date('YmdHis');
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
                $path = $file_data->storeAs('public/circular/', $file_name);

                if (isset($_REQUEST['standard'])) {
                    foreach ($_REQUEST['standard'] as $id => $std) {
                        foreach ($_REQUEST['division'] as $ids => $div_id) {

                            $values = [
                                'syear'            => $syear,
                                'standard_id'      => $std,
                                'division_id'      => $div_id,
                                'title'            => $_REQUEST['title'],
                                'type'             => $_REQUEST['type'],
                                'message'          => $_REQUEST['message'],
                                'file_name'        => $file_name,
                                'date_'            => $_REQUEST['date_'],
                                'sub_institute_id' => $sub_institute_id,
                                'created_at'       => now(),
                                'updated_at'       => now(),
                            ];
                            DB::table('circular')->insert($values);

                            //START Send Notification Code
                            $student_data = DB::table("tblstudent_enrollment as se")
                                ->join('tblstudent as s', function ($join) {
                                    $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                                })
                                ->selectRaw("*,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name")
                                ->where("se.standard_id", "=", $std)
                                ->where("se.section_id", "=", $div_id)
                                ->where("se.syear", "=", $syear)
                                ->whereNull("se.end_date")
                                ->where("se.sub_institute_id", "=", $sub_institute_id)
                                ->get()->toArray();

                            $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
                            $schoolName = $schoolData[0]['SchoolName'];
                            $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

                            if (count($student_data) > 0) {
                                foreach ($student_data as $key => $val) {
                                    $student_id = $val->student_id;
                                    $mobile_no = $val->mobile;
                                    $student_name = $val->student_name;

                                    $pushMessage = $student_name. " - ".$_REQUEST['title']." has been added in Circular for date : ".date('d-m-Y',
                                            strtotime($_REQUEST['date_']));

                                    $app_notification_content = [
                                        'NOTIFICATION_TYPE'        => 'Circular',
                                        'NOTIFICATION_DATE'        => $_REQUEST['date_'],
                                        'STUDENT_ID'               => $student_id,
                                        'NOTIFICATION_DESCRIPTION' => $pushMessage,
                                        'STATUS'                   => 0,
                                        'SUB_INSTITUTE_ID'         => $sub_institute_id,
                                        'SYEAR'                    => $syear,
                                        'SCREEN_NAME'              => 'circular_events',
                                        'CREATED_BY'               => $user_id,
                                        'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                                    ];

                                    $gcm_data = DB::table("gcm_users")
                                        ->where("mobile_no", "=", $mobile_no)
                                        ->where("sub_institute_id", "=", $sub_institute_id)
                                        ->groupBy("gcm_regid")
                                        ->get()->toArray();
                                    //new end

                                    $gcmRegIds = [];
                                    if (count($gcm_data) > 0) {
                                        foreach ($gcm_data as $key1 => $val1) {
                                            $gcmRegIds[] = $val1->gcm_regid;
                                        }
                                    }

                                    $bunch_arr = array_chunk($gcmRegIds, 1000);
                                    if (! empty($bunch_arr)) {
                                        foreach ($bunch_arr as $val) {
                                            if (isset($val) && isset($pushMessage)) {
                                                $type = 'Circular';
                                                $message = array(
                                                    'body'    => $pushMessage, 'TYPE' => $type,
                                                    'USER_ID' => $student_id, 'title' => $schoolName.' - '.$type,
                                                    'image'   => $schoolLogo,
                                                );
                                                $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                                                sendNotification($app_notification_content);
                                            }
                                        }
                                    }

                                }
                            }
                            //END Send Notification Code
                        }
                    }
                }
            }
        } else {
            if (isset($_REQUEST['standard'])) {
                foreach ($_REQUEST['standard'] as $id => $std) {
                    foreach ($_REQUEST['division'] as $ids => $div_id) {
                        $values = [
                            'syear'            => $syear,
                            'standard_id'      => $std,
                            'division_id'      => $div_id,
                            'title'            => $_REQUEST['title'],
                            'type'             => $_REQUEST['type'],
                            'message'          => $_REQUEST['message'],
                            'date_'            => $_REQUEST['date_'],
                            'sub_institute_id' => $sub_institute_id,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                        DB::table('circular')->insert($values);

                        //START Send Notification Code

                        $student_data = DB::table("tblstudent_enrollment as se")
                            ->join('tblstudent as s', function ($join) {
                                $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                            })
                            ->selectRaw("*,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name")
                            ->where("se.standard_id", "=", $std)
                            ->where("se.section_id", "=", $div_id)
                            ->where("se.syear", "=", $syear)
                            ->whereNull("se.end_date")
                            ->where("se.sub_institute_id", "=", $sub_institute_id)
                            ->get()->toArray();

                        $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
                        $schoolName = $schoolData[0]['SchoolName'];
                        $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

                        if (count($student_data) > 0) {
                            foreach ($student_data as $key => $val) {
                                $student_id = $val->student_id;
                                $mobile_no = $val->mobile;
                                $student_name = $val->student_name;

                                $pushMessage = $student_name. " - ".$_REQUEST['title']." has been added in Circular for date : ".
                                    date('d-m-Y', strtotime($_REQUEST['date_']));

                                $app_notification_content = [
                                    'NOTIFICATION_TYPE'        => 'Circular',
                                    'NOTIFICATION_DATE'        => $_REQUEST['date_'],
                                    'STUDENT_ID'               => $student_id,
                                    'NOTIFICATION_DESCRIPTION' => $pushMessage,
                                    'STATUS'                   => 0,
                                    'SUB_INSTITUTE_ID'         => $sub_institute_id,
                                    'SYEAR'                    => $syear,
                                    'SCREEN_NAME'              => 'circular_events',
                                    'CREATED_BY'               => $user_id,
                                    'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                                ];

                                $gcm_data = DB::table("gcm_users")
                                    ->where("mobile_no", "=", $mobile_no)
                                    ->where("sub_institute_id", "=", $sub_institute_id)
                                    ->groupBy("gcm_regid")
                                    ->get()->toArray();
                                $gcmRegIds = [];
                                if (count($gcm_data) > 0) {
                                    foreach ($gcm_data as $key1 => $val1) {
                                        $gcmRegIds[] = $val1->gcm_regid;
                                    }
                                }

                                $bunch_arr = array_chunk($gcmRegIds, 1000);
                                if (! empty($bunch_arr)) {
                                    foreach ($bunch_arr as $val) {
                                        if (isset($val) && isset($pushMessage)) {
                                            $type = 'Circular';
                                            $message = array(
                                                'body'    => $pushMessage, 'TYPE' => $type,
                                                'USER_ID' => $student_id, 'title' => $schoolName.' - '.$type,
                                                'image'   => $schoolLogo,
                                            );
                                            $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                                            sendNotification($app_notification_content);
                                        }
                                    }
                                }

                            }
                        }
                        //END Send Notification Code
                    }
                }
            }
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action'] == "API") {
            return 1;
        } else {
            $res = [
                "status"  => 1,
                "message" => "Circular Added Successfully.",
            ];
            $type = $request->input('type');

            return is_mobile($type, "circular.index", $res, "redirect");
        }
    }

 
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('circular')->where(["Id" => $id])->delete();

        $res = [
            "status" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "circular.index", $res, "redirect");
    }

    public function searchCircularTitle(Request $request)
    {
        $searchValue = $request->input('value');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $extraSearchArray = [];
        $extraSearchArray['circular.sub_institute_id'] = $sub_institute_id;

        return circular::selectRaw('title')
            ->whereRaw('circular.title LIKE "%'.$searchValue.'%"')
            ->where($extraSearchArray)
            ->groupby('circular.title')
            ->get()
            ->toArray();
    }
}
