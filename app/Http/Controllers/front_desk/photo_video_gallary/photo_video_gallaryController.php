<?php

namespace App\Http\Controllers\front_desk\photo_video_gallary;

use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use GenTux\Jwt\GetsJwtToken;
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

class photo_video_gallaryController extends Controller
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
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();

        $type = $request->input('type');

        return is_mobile($type, "front_desk/photo_video_gallary/show", $school_data, "view");
    }

    public function getData()
    {
        return DB::table("photo_video_gallary as c")
            ->join('standard as s', function ($join) {
                $join->whereRaw("s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id");
            })
            ->leftJoin('division as d', function ($join) {
                $join->whereRaw("d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id");
            })
            ->selectRaw('c.*,s.name std_name,d.name div_name')
            ->where("c.syear", "=", session()->get('syear'))
            ->where("c.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->orderBy('id', 'DESC')
            ->limit(1000)
            ->get()->toArray();
    }

    public function fetchData(Request $request)
    {
        $response = ['response' => '', 'success' => false];

        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type'             => ["in:Photo,Video,"],
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

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
                ->selectRaw('se.standard_id,se.section_id,se.grade_id')
                ->where("s.sub_institute_id", "=", $sub_institute_id)
                ->where("se.syear", "=", $syear)
                ->where("se.student_id", "=", $student_id)
                ->groupBy('s.id')
                ->get()->toarray();

            if ($result) {
                $standard_id = $result[0]->standard_id;

                $server = "https://".$_SERVER['HTTP_HOST'];

                $result_data = DB::table("photo_video_gallary as pvg")
                    ->selectRaw("pvg.id,pvg.syear,pvg.standard_id,pvg.title,pvg.`type`,pvg.ai,
                    if(pvg.file_name IS NULL OR pvg.file_name = '','-',if(pvg.`type` = 'Video', pvg.file_name, 
                    CONCAT('$server/storage/photo_video_gallary/',pvg.file_name))) file_name,
                    pvg.date_,pvg.sub_institute_id,pvg.created_at,pvg.updated_at")
                    ->where("pvg.standard_id", "=", $standard_id)
                    ->where("pvg.syear", "=", $syear)
                    ->where("pvg.sub_institute_id", "=", $sub_institute_id)
                    ->where(function ($q) {
                        if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                            $q->whereRaw('type', $_REQUEST["type"]);
                        }
                    })
                    ->get()->toArray();

                $response['response'] = $result_data;
                $response['success'] = true;
            } else {
                $response['response'] = ["student_id" => ["No data found."]];
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
            'type'             => ["in:Photo,Video,"],
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $_REQUEST['standard_id'];

            $server = "https://".$_SERVER['HTTP_HOST'];

            $result_data = DB::table("photo_video_gallary as pvg")
                ->selectRaw("pvg.id,pvg.syear,pvg.standard_id,pvg.album_title,pvg.title,pvg.`type`,pvg.ai,
                    if(pvg.file_name IS NULL OR pvg.file_name = '','-',if(pvg.`type` = 'Video', pvg.file_name, 
                    CONCAT('$server/storage/photo_video_gallary/',pvg.file_name))) file_name,
                    pvg.date_,pvg.sub_institute_id,pvg.created_at,pvg.updated_at")
                ->where("pvg.standard_id", "=", $standard_id)
                ->where("pvg.syear", "=", $syear)
                ->where("pvg.sub_institute_id", "=", $sub_institute_id)
                ->where(function ($q) {
                    if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                        $q->whereRaw('type', $_REQUEST["type"]);
                    }
                })
                ->get()->toArray();

            foreach ($result_data as $key => $val) {
                $new_data[$val->album_title][] = $val;
            }

            $response['data'] = $new_data;
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

        if ($_REQUEST['type'] == 'Photo') {
            if ($request->hasFile('attachment')) {
                foreach ($request->file('attachment') as $key => $file_data) {
                    $file_name = $file_size = $ext = "";
                    $originalname = $file_data->getClientOriginalName();
                    $file_size = $file_data->getSize();
                    $name = 'attachment_'.$key.'_'.date('YmdHis');
                    $ext = File::extension($originalname);
                    $file_name = $name.'.'.$ext;
                    $path = $file_data->storeAs('public/photo_video_gallary/', $file_name);

                    if (isset($_REQUEST['standard'])) {
                        foreach ($_REQUEST['standard'] as $id => $std) {
                            foreach ($_REQUEST['division'] as $ids => $div_id) {
                                $values = [
                                    'syear'            => $syear,
                                    'standard_id'      => $std,
                                    'division_id'      => $div_id,
                                    'title'            => $_REQUEST['title'],
                                    'album_title'      => $_REQUEST['album_title'],
                                    'type'             => $_REQUEST['type'],
                                    'file_name'        => $file_name,
                                    'file_size'        => $file_size,
                                    'file_type'        => $ext,
                                    'date_'            => $_REQUEST['date_'],
                                    'sub_institute_id' => $sub_institute_id,
                                    'created_at'       => now(),
                                    'updated_at'       => now(),
                                ];
                                DB::table('photo_video_gallary')->insert($values);

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

                                        if ($_REQUEST['type'] == 'Photo') {
                                            $screen_name = 'photos_gallery';
                                            $noti_type = 'Photo Gallery';
                                        } else {
                                            $screen_name = 'video_gallery';
                                            $noti_type = 'Video Gallery';
                                        }


                                        $pushMessage = "Dear Parents, ".$_REQUEST['title']." has been added in Photo Video Gallary for date : ".date('d-m-Y',
                                                strtotime($_REQUEST['date_']));

                                        $app_notification_content = [
                                            'NOTIFICATION_TYPE'        => $noti_type,
                                            'NOTIFICATION_DATE'        => $_REQUEST['date_'],
                                            'STUDENT_ID'               => $student_id,
                                            'NOTIFICATION_DESCRIPTION' => $_REQUEST['title'].' - '.$pushMessage,
                                            'STATUS'                   => 0,
                                            'SUB_INSTITUTE_ID'         => $sub_institute_id,
                                            'SYEAR'                    => $syear,
                                            'SCREEN_NAME'              => $screen_name,
                                            'CREATED_BY'               => $user_id,
                                            'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                                        ];

                                        $gcm_data = DB::table("gcm_users")
                                            ->where("mobile_no", "=", $mobile_no)
                                            ->where("sub_institute_id", "=", $sub_institute_id)
                                            ->get()->toArray();

                                        $gcmRegIds = [];
                                        if (count($gcm_data) > 0) {
                                            foreach ($gcm_data as $key1 => $val1) {
                                                array_push($gcmRegIds, $val1->gcm_regid);
                                            }
                                        }

                                        $bunch_arr = array_chunk($gcmRegIds, 1000);
                                        if (! empty($bunch_arr)) {
                                            foreach ($bunch_arr as $val) {
                                                if (isset($val, $pushMessage)) {
                                                    $type = $noti_type;
                                                    $message = [
                                                        'body'    => $pushMessage, 'TYPE' => $type,
                                                        'USER_ID' => $student_id, 'title' => $schoolName,
                                                        'image'   => $schoolLogo,
                                                    ];
                                                    $pushStatus = send_FCM_Notification($val, $message);
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
            }
        } else {
            if (isset($_REQUEST['standard'])) {
                foreach ($_REQUEST['standard'] as $id => $std) {
                    foreach ($_REQUEST['division'] as $ids => $div_id) {
                        $file_name = $_REQUEST['attachment'];
                        $values = [
                            'syear'            => $syear,
                            'standard_id'      => $std,
                            'division_id'      => $div_id,
                            'title'            => $_REQUEST['title'],
                            'album_title'      => $_REQUEST['album_title'],
                            'type'             => $_REQUEST['type'],
                            'file_name'        => $file_name,
                            'date_'            => $_REQUEST['date_'],
                            'sub_institute_id' => $sub_institute_id,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                        DB::table('photo_video_gallary')->insert($values);

                        //START Send Notification Code
                        $student_data = DB::table("tblstudent_enrollment as se")
                            ->join('tblstudent as s', function ($join) {
                                $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                            })
                            ->selectRaw("*,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name ")
                            ->where("se.standard_id", "=", $std)
                            ->where("se.section_id", "=", $div_id)
                            ->where("se.syear", "=", $syear)
                            ->whereNull('se.end_date')
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

                                if ($_REQUEST['type'] == 'Photo') {
                                    $screen_name = 'photos_gallery';
                                    $noti_type = 'Photo Gallery';
                                } else {
                                    $screen_name = 'video_gallery';
                                    $noti_type = 'Video Gallery';
                                }


                                $pushMessage = "Dear Parents, ".$_REQUEST['title']." has been added in Photo Video Gallary for date : ".date('d-m-Y',
                                        strtotime($_REQUEST['date_']));

                                $app_notification_content = [
                                    'NOTIFICATION_TYPE'        => $noti_type,
                                    'NOTIFICATION_DATE'        => $_REQUEST['date_'],
                                    'STUDENT_ID'               => $student_id,
                                    'NOTIFICATION_DESCRIPTION' => $_REQUEST['title'].' - '.$pushMessage,
                                    'STATUS'                   => 0,
                                    'SUB_INSTITUTE_ID'         => $sub_institute_id,
                                    'SYEAR'                    => $syear,
                                    'SCREEN_NAME'              => $screen_name,
                                    'CREATED_BY'               => $user_id,
                                    'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                                ];

                                $gcm_data = DB::table("gcm_users")
                                    ->where("mobile_no", "=", $mobile_no)
                                    ->where("sub_institute_id", "=", $sub_institute_id)
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
                                        if (isset($val, $pushMessage)) {
                                            $type = $noti_type;
                                            $message = [
                                                'body'    => $pushMessage, 'TYPE' => $type,
                                                'USER_ID' => $student_id, 'title' => $schoolName,
                                                'image'   => $schoolLogo,
                                            ];
                                            $pushStatus = send_FCM_Notification($val, $message);
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
                "message" => "Photo Video Gallery Added Successfully.",
            ];

            $type = $request->input('type');

            return is_mobile($type, "photo_video_gallary.index", $res, "redirect");
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
     * @return Response
     */
    public function edit($id)
    {
        $result = DB::table("photo_video_gallary")
            ->select('ai')
            ->where("id", "=", $id)
            ->get()->toArray();

        $active_status = $result[0]->ai;

        $change_status = "InActive";
        if ($active_status == 'InActive') {
            $change_status = "Active";
        }

        DB::table('photo_video_gallary')->where('id', $id)
            ->update(['ai' => $change_status]);
        $res = [
            "status"  => 1,
            "message" => "Status Changed",
        ];

        $type = "web";

        return is_mobile($type, "photo_video_gallary.index", $res, "redirect");
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
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('photo_video_gallary')->where(["id" => $id])->delete();

        $res = [
            "status"  => 1,
            "message" => "Data Deleted",
        ];

        return is_mobile($type, "photo_video_gallary.index", $res, "redirect");
    }

    public function studentPhotoVideoGalleryAPI(Request $request)
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

        if ($student_id != "" && $sub_institute_id != "" && $syear != "" && $action != "") {
            $data = DB::table("tblstudent_enrollment as s")
                ->join('photo_video_gallary as p', function ($join) {
                    $join->whereRaw("p.standard_id = s.standard_id AND p.division_id = s.section_id AND p.syear = s.syear AND ai = 'Active'");
                })
                ->selectRaw("p.album_title,p.title,if(p.type = 'Video',p.file_name,concat('https://".$_SERVER['SERVER_NAME']
                    ."/storage/photo_video_gallary/',p.file_name)) as file_name,p.date_ ,ai,`type`")
                ->where("s.student_id", "=", $student_id)
                ->where("s.syear", "=", $syear)
                ->where("s.sub_institute_id", "=", $sub_institute_id)
                ->where("type", "=", $action)
                ->orderBy('p.date_', 'DESC')
                ->get()->toArray();

            if(!empty($data))
            {
                $new_data = [];
                foreach ($data as $key => $val) {
                    $new_data[$val->album_title]['album_title'] = $val->album_title;
                    $new_data[$val->album_title]['album'][] = [
                        "album_title" => $val->album_title,
                        "title" => $val->title,
                        "file_name" => $val->file_name,
                        "date_" => $val->date_,
                        "ai" => $val->ai,
                        "type" => $val->type
                    ];
                }

                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = array_values($new_data);
            }else{
                $res['status'] = 0;
                $res['message'] = "No Data Found";
            }
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
