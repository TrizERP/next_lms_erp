<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\inventory\requisitionController;
use App\Models\frontdesk\complaintModel;
use App\Models\frontdesk\taskModel;
use App\Models\inventory\requisitionModel;
use App\Models\lms\answermasterModel;
use App\Models\lms\chapterModel;
use App\Models\lms\contentModel;
use App\Models\lms\doubtModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\teacherResourceModel;
use App\Models\lms\virtualclassroomModel;
use App\Models\ptm\ptmattenedstatusModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\lessonplanning_executionModel;
use App\Models\school_setup\lessonplanningModel;
use App\Models\school_setup\subjectModel;
use App\Models\school_setup\timetableModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use App\Models\user\tbluserModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class teacherapiController extends Controller
{
    use GetsJwtToken;

    public function teacher_homescreen(Request $request)
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

        $payload = $this->jwtPayload();

        $response = ['status' => '1', 'message' => 'Success', 'data' => []];

        $user_profile_id = $request->input("user_profile_id");
        $user_profile_name = $request->input("user_profile_name");
        $sub_institute_id = $request->input("sub_institute_id");


        $validator = Validator::make($request->all(), [
            'sub_institute_id'  => 'required|numeric',
            'user_profile_id'   => 'required|numeric',
            'user_profile_name' => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table("teacher_mobile_homescreen")
                ->where([
                    "status"            => "Yes",
                    'user_profile_id'   => $user_profile_id,
                    'user_profile_name' => $user_profile_name,
                    'sub_institute_id'  => $sub_institute_id,
                ])
                ->orderBy('main_sort_order', 'ASC')
                ->orderBy('sub_title_sort_order', 'ASC')
                ->get();

            $data = json_encode($data);
            $data = json_decode($data, 1);

            $send_data = [];
            $i = 0;
            foreach ($data as $id => $arr) {
                if (($i != 0) && isset($send_data[$i - 1]["main_title"]) && $send_data[$i - 1]["main_title"] == $arr['main_title']) {
                    continue;
                }
                if ($arr['menu_type'] == 'Banner') {
                    $send_data[$i] = [
                        "main_title"                  => $arr['main_title'],
                        "menu_type"                   => $arr['menu_type'],
                        "main_itle_color"             => $arr['main_title_color_code'],
                        "main_title_background_image" => $arr['main_title_background_image'],
                        "api"                         => $arr['sub_title_api'],
                        "api_param"                   => $arr['sub_title_api_param'],
                        "screen_name"                 => $arr['screen_name'],
                    ];
                    $i++;
                    continue;
                } else {
                    $send_data[$i] = [
                        "main_title"                  => $arr['main_title'],
                        "menu_type"                   => $arr['menu_type'],
                        "main_itle_color"             => $arr['main_title_color_code'],
                        "main_title_background_image" => $arr['main_title_background_image'],
                        "contents"                    => [],
                    ];
                }

                foreach ($data as $id1 => $arr1) {
                    if ($arr['main_title'] == $arr1['main_title']) {
                        $send_data[$i]["contents"][] = [
                            "sub_title"           => $arr1["sub_title_of_main"],
                            "sub_title_icon"      => $arr1["sub_title_icon"],
                            "sub_title_api"       => $arr1["sub_title_api"],
                            "sub_title_api_param" => $arr1["sub_title_api_param"],
                            "screen_name"         => $arr1["screen_name"],

                        ];
                    }
                }
                $i++;
            }
            $response["data"] = $send_data;
        }

        return json_encode($response);
    }

    public function teacherSocialCollabrativeAPI(Request $request)
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

        $teacher_id = $request->input("teacher_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {
            $path = "https://".$_SERVER['SERVER_NAME']."/storage/";

            $doubtdata = doubtModel::select('lms_doubt.*', db::raw('
                CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) as student_name,
                CONCAT("'.$path.'student/",IFNULL(s.image,"no-image.jpg")) as image,
                DATEDIFF(now(),lms_doubt.created_at) as totaldays,
                CONCAT("(",CONCAT_WS("/",st.name,d.name),")") as standard_division,
                DATE_FORMAT(lms_doubt.created_at,"%M %d, %Y") as doubt_date'))
                ->leftjoin("tblstudent_enrollment as se", function ($join) {
                    $join->on('se.student_id', '=', 'lms_doubt.user_id')
                        ->on('se.syear', '=', 'lms_doubt.syear');
                })
                ->join('tblstudent as s', 's.id', 'se.student_id')
                ->join('standard as st', 'st.id', 'se.standard_id')
                ->join('division as d', 'd.id', 'se.section_id')
                ->where(['lms_doubt.sub_institute_id' => $sub_institute_id, 'lms_doubt.syear' => $syear])
                ->get()->toArray();

            $finaldata = [];
            if (count($doubtdata) > 0) {
                foreach ($doubtdata as $key => $val) {
                    $unionQuery = DB::table('lms_doubt_conversation as l')
                        ->join('tbluser as u', function ($join) {
                            $join->whereRaw('u.id = l.user_id');
                        })
                        ->selectRaw('l.*,CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) AS student_name,                    
                            CONCAT("'.$path.'user/",IFNULL(u.image,"no-image.jpg")) as image,
                            DATE_FORMAT(l.created_at,"%M %d, %Y") AS comment_date," " as standard_division')
                        ->where('l.sub_institute_id', $sub_institute_id)
                        ->where('l.doubt_id', $val['id']);
                    $conversationData = DB::table('lms_doubt_conversation as l')
                        ->leftJoin('tblstudent_enrollment AS se', function ($join) {
                            $join->whereRaw('se.student_id = l.user_id and se.syear = l.syear AND se.end_date is NULL');
                        })->join('tblstudent AS s', function ($join) {
                            $join->whereRaw('s.id = se.student_id');
                        })->join('standard AS st', function ($join) {
                            $join->whereRaw('st.id = se.standard_id');
                        })->join('division AS d', function ($join) {
                            $join->whereRaw('d.id = se.section_id');
                        })->selectRaw('l.*, CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) AS student_name,                     
                        CONCAT("'.$path.'student/",IFNULL(s.image,"no-image.jpg")) as image,DATE_FORMAT(l.created_at,"%M %d, %Y") AS comment_date, 
                        CONCAT("(",CONCAT_WS("/",st.name,d.name),")") as standard_division')
                        ->where('l.sub_institute_id', $sub_institute_id)
                        ->union($unionQuery)->get()->toArray();

                    $conversationData = json_decode(json_encode($conversationData), true);

                    $finaldata[$val['id']] = $val;
                    $finaldata[$val['id']]['ConversationData'] = $conversationData;
                }
            }

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $finaldata;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function add_teacherSocialCollabrativeAPI(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'doubt_id'         => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'message'          => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $teacher_id = $request->input("teacher_id");
            $user_data = tbluserModel::select("*")->where('id', "=", $teacher_id)->get()->toArray();
            $user_profile_id = $user_data[0]['user_profile_id'];

            $data = [
                'user_id'          => $teacher_id,
                'user_profile_id'  => $user_profile_id,
                'doubt_id'         => $_REQUEST['doubt_id'],
                'message'          => $_REQUEST['message'],
                'sub_institute_id' => $_REQUEST['sub_institute_id'],
                'syear'            => $_REQUEST['syear'],
            ];

            DB::table('lms_doubt_conversation')->insert($data);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function add_teacherContentAPI(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'chapter_id'       => 'required|numeric',
            'topic_id'         => 'required|numeric',
            'file_type'        => 'required|in:pdf,mp3,mp4,html,jpg,jpeg,png,link',
            'filename'         => 'required',
            'title'            => 'required',
            'restrict_date'    => 'date',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $chapter_data = chapterModel::select('*')
                ->where([
                    'chapter_master.sub_institute_id' => $request->get('sub_institute_id'),
                    'chapter_master.id'               => $request->get('chapter_id'),
                ])
                ->get()->toArray();
            $chapter_data = $chapter_data[0];

            $file_folder = $ext = $size = $newfilename = "";
            if ($request->hasFile('filename')) {
                $img = $request->file('filename');
                $filename = $img->getClientOriginalName();
                $ext = $img->getClientOriginalExtension();
                $size = $img->getSize();
                $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
                $file_folder = '/lms_content_file';
                $img->storeAs('public/lms_content_file/', $newfilename);
            }

            if ($request->get('file_type') == "link") {
                $newfilename = $request->get('filename');
                $ext = "link";
            }

            //Basic means 1 and advance means 0 in basic advance
            $content = [
                'grade_id'         => $chapter_data['grade_id'],
                'standard_id'      => $chapter_data['standard_id'],
                'subject_id'       => $chapter_data['subject_id'],
                'chapter_id'       => $request->get('chapter_id'),
                'topic_id'         => $request->get('topic_id'),
                'title'            => $request->get('title'),
                'description'      => $request->get('description'),
                'file_folder'      => $file_folder,
                'filename'         => $newfilename,
                'file_type'        => $ext,
                'file_size'        => $size,
                'show_hide'        => "1",
                'sort_order'       => $request->get('sort_order'),
                'meta_tags'        => $request->get('meta_tags'),
                'created_by'       => $request->get('teacher_id'),
                'sub_institute_id' => $request->get('sub_institute_id'),
                'restrict_date'    => $request->get('restrict_date'),
                'basic_advance'    => "1",
                'syear'            => $request->get('syear'),
            ];

            contentModel::insert($content);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function add_teacherVirtualClassroomAPI(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'chapter_id'       => 'required|numeric',
            'topic_id'         => 'required|numeric',
            'room_name'        => 'required',
            'recurring'        => 'required:in:yes,no',
            'url'              => 'required',
            'password'         => 'required',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $chapter_data = chapterModel::select('*')
                ->where([
                    'chapter_master.sub_institute_id' => $request->get('sub_institute_id'),
                    'chapter_master.id'               => $request->get('chapter_id'),
                ])
                ->get()->toArray();

            $chapter_data = $chapter_data[0];

            $created_ip = $_SERVER['REMOTE_ADDR'];

            $content = [
                'grade_id'         => $chapter_data['grade_id'],
                'standard_id'      => $chapter_data['standard_id'],
                'subject_id'       => $chapter_data['subject_id'],
                'chapter_id'       => $request->get('chapter_id'),
                'topic_id'         => $request->get('topic_id'),
                'room_name'        => $request->get('room_name'),
                'description'      => $request->get('description'),
                'event_date'       => $request->get('event_date'),
                'from_time'        => $request->get('from_time'),
                'to_time'          => $request->get('to_time'),
                'recurring'        => $request->get('recurring'),
                'url'              => $request->get('url'),
                'password'         => $request->get('password'),
                'status'           => $request->get('status'),
                'notification'     => $request->get('notification'),
                'sort_order'       => $request->get('sort_order'),
                'syear'            => $request->get('syear'),
                'sub_institute_id' => $request->get('sub_institute_id'),
                'created_by'       => $request->get('teacher_id'),
                'created_ip'       => $created_ip,
            ];

            virtualclassroomModel::insert($content);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_teacherVirtualClassroomAPI(Request $request)
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

        $teacher_id = $request->input("teacher_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('lms_virtual_classroom as v')
                ->join('standard as st', function ($join) {
                    $join->whereRaw('st.id = v.standard_id AND st.sub_institute_id = v.sub_institute_id');
                })->join('subject as sub', function ($join) {
                    $join->whereRaw('sub.id = v.subject_id AND sub.sub_institute_id = v.sub_institute_id');
                })->join('chapter_master as c', function ($join) {
                    $join->whereRaw('c.id = v.chapter_id AND c.sub_institute_id = v.sub_institute_id');
                })->join('topic_master as t', function ($join) {
                    $join->whereRaw('t.id = v.topic_id AND t.sub_institute_id = v.sub_institute_id');
                })->join('tbluser as u', function ($join) {
                    $join->whereRaw('u.id = v.created_by');
                })->selectRaw("st.name AS standard_name,sub.subject_name,c.chapter_name,t.name AS topic_name,v.syear,
                    v.sub_institute_id,v.room_name,v.description,v.event_date,v.from_time,v.to_time,v.recurring,v.url,v.password,
                    CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name")
                ->where('v.created_by', $teacher_id)
                ->where('v.syear', $syear)
                ->where('v.sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $data;
            } else {
                $res['status'] = 0;
                $res['message'] = "No Record";
            }

        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function get_teacherResourceFieldAPI(Request $request)
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

        $sub_institute_id = $request->get('sub_institute_id');

        //START Columns from field setting
        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "lms_teacher_resource"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get();
        //END Columns from field setting

        //START Columns from field setting for combo checkbox
        if (count($dataCustomFields) > 0) {
            foreach ($dataCustomFields as $key => $val) {
                $field_id = $val['id'];
                $data[$field_id] = $val;

                $fieldsData = tblfields_dataModel::where("field_id", "=", $field_id)->get()->toArray();
                $i = 0;
                $finalfieldsData = [];

                foreach ($fieldsData as $key => $value) {
                    $finalfieldsData[$i]['display_text'] = $value['display_text'];
                    $finalfieldsData[$i]['display_value'] = $value['display_value'];
                    $i++;
                }
                $data[$field_id]['FIELD_VALUE'] = $finalfieldsData;
            }

            $res['status'] = 1;
            $res['data'] = $data;
            $res['message'] = "Success";
        } else {
            $res['status'] = 0;
            $res['message'] = "Not Field Found";
        }

        //END Columns from field setting for combo checkbox            

        return json_encode($res);
    }

    public function get_teacherResourceAPI(Request $request)
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

        $teacher_id = $request->input("teacher_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('lms_teacher_resource as v')
                ->join('standard as st', function ($join) {
                    $join->whereRaw('st.id = v.standard_id AND st.sub_institute_id = v.sub_institute_id');
                })->join('subject as sub', function ($join) {
                    $join->whereRaw('sub.id = v.subject_id AND sub.sub_institute_id = v.sub_institute_id');
                })->join('chapter_master as c', function ($join) {
                    $join->whereRaw('c.id = v.chapter_id AND c.sub_institute_id = v.sub_institute_id');
                })->join('topic_master as t', function ($join) {
                    $join->whereRaw('t.id = v.topic_id AND t.sub_institute_id = v.sub_institute_id');
                })->join('tbluser as u', function ($join) {
                    $join->whereRaw('u.id = v.created_by');
                })->selectRaw("v.syear,v.sub_institute_id,st.name AS standard_name,sub.subject_name,c.chapter_name,
                    t.name AS topic_name,v.title,if(v.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/lms_teacher_resource/',
                    v.file_name)) as file_name,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name")
                ->where('v.status', 1)
                ->where('v.created_by', $teacher_id)
                ->where('v.syear', $syear)
                ->where('v.sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($data) > 0) {
                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $data;
            } else {
                $res['status'] = 0;
                $res['message'] = "No Record";
            }

        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function add_teacherResourceAPI(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'chapter_id'       => 'required|numeric',
            'title'            => 'required',
            'file_name'        => 'required',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $chapter_data = chapterModel::select('*')
                ->where([
                    'chapter_master.sub_institute_id' => $request->get('sub_institute_id'),
                    'chapter_master.id'               => $request->get('chapter_id'),
                ])
                ->get()->toArray();
            $chapter_data = $chapter_data[0];

            $created_ip = $_SERVER['REMOTE_ADDR'];

            $file_folder = $ext = $size = $newfilename = "";
            if ($request->hasFile('file_name')) {
                $img = $request->file('file_name');
                $filename = $img->getClientOriginalName();
                $ext = $img->getClientOriginalExtension();
                $size = $img->getSize();
                $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
                $file_folder = '/lms_teacher_resource';
                $img->storeAs('public/lms_teacher_resource/', $newfilename);
            }

            $TR_data = [
                'standard_id'      => $chapter_data['standard_id'],
                'subject_id'       => $chapter_data['subject_id'],
                'chapter_id'       => $request->get('chapter_id'),
                'topic_id'         => $request->get('topic_id'),
                'syear'            => $request->get('syear'),
                'title'            => $request->get('title'),
                'file_name'        => $newfilename,
                'file_type'        => $ext,
                'file_size'        => $size,
                'status'           => "1",
                'sub_institute_id' => $request->get('sub_institute_id'),
                'created_by'       => $request->get('teacher_id'),
            ];

            //START Add Dynamic Field data
            $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "lms_teacher_resource"])
                ->whereRaw('(sub_institute_id = '.$request->get('sub_institute_id').' OR common_to_all = 1)')
                ->get();

            foreach ($dataCustomFields as $key => $val) {
                $TR_data[$val['field_name']] = $request->get($val['field_name']);
            }
            //END Add Dynamic Field data

            teacherResourceModel::insert($TR_data);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function add_teacherQuestionAnswerAPI(Request $request)
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

        $response = [];

        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'chapter_id'       => 'required|numeric',
            'topic_id'         => 'required|numeric',
            'question_title'   => 'required',
            'question_marks'   => 'required',
            'options'          => 'required',
            'correct_answer'   => 'required',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $chapter_data = chapterModel::select('*')
                ->where([
                    'chapter_master.sub_institute_id' => $request->get('sub_institute_id'),
                    'chapter_master.id'               => $request->get('chapter_id'),
                ])
                ->get()->toArray();
            $chapter_data = $chapter_data[0];

            $created_ip = $_SERVER['REMOTE_ADDR'];

            $QA_data = [
                'question_type_id' => "1", // Multiple Questio Type             
                'grade_id'         => $chapter_data['grade_id'],
                'standard_id'      => $chapter_data['standard_id'],
                'subject_id'       => $chapter_data['subject_id'],
                'chapter_id'       => $request->get('chapter_id'),
                'topic_id'         => $request->get('topic_id'),
                'question_title'   => $request->get('question_title'),
                'description'      => $request->get('description'),
                'points'           => $request->get('question_marks'),
                'multiple_answer'  => "0",
                'sub_institute_id' => $request->get('sub_institute_id'),
                'status'           => "1",
                'created_by'       => $request->get('teacher_id'),
                'hint_text'        => $request->get('question_hint'),
            ];

            $question_id = lmsQuestionMasterModel::insertGetId($QA_data);

            //START Insert into answer_master
            $option_arr = $request->get('options');

            foreach ($option_arr as $key => $val) {
                $correct_answer_val = 0;
                if ($request->has('correct_answer')) {
                    $correct_answer[] = $request->get('correct_answer');
                    $correct_answer_val = in_array($key, $correct_answer) ? 1 : 0;
                }

                $answer = [
                    'question_id'      => $question_id,
                    'answer'           => $val,
                    'correct_answer'   => $correct_answer_val,
                    'created_by'       => $request->get('teacher_id'),
                    'sub_institute_id' => $request->get('sub_institute_id'),
                ];

                answermasterModel::insert($answer);
            }
            //END Insert into answer_master

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function add_teacherStudentDisciplineAPI(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'data'             => 'required',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $result = DB::table('tbluser')->selectRaw("CONCAT(first_name,' ',last_name) as name")
                ->where('id', $request->get('teacher_id'))->get()->toArray();

            $teacher_name = $result[0]->name;

            $data = json_decode($request->get('data'), true);

            foreach ($data as $key => $val) {
                DB::table('dicipline')->insert([
                    'syear'            => $request->get('syear'),
                    'student_id'       => $val['student_id'],
                    'name'             => $teacher_name,
                    'dicipline'        => $val['dicipline'],
                    'message'          => $val['message'],
                    'date_'            => date('Y-m-d'),
                    'sub_institute_id' => $request->get('sub_institute_id'),
                    'created_by'       => $request->get('teacher_id'),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_teacherSubjectAPI(Request $request)
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

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($sub_institute_id != "" && $syear != "") {
            $data = DB::table('sub_std_map as s')
                ->join('standard as STD', function ($join) {
                    $join->whereRaw('STD.id = s.standard_id');
                })->selectRaw("STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,
                    if(s.display_image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage',s.display_image)) as display_image,
                    ifnull(s.subject_category,'My Course') AS content_category")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('allow_content', 'Yes')->groupByRaw('s.subject_id,s.standard_id,s.subject_category')
                ->orderBy('s.sort_order')->get()->toArray();
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;

        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function get_teacherContentAPI(Request $request)
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

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $standard_id = $request->input("standard_id");
        $subject_id = $request->input("subject_id");

        if ($standard_id != "" && $sub_institute_id != "" && $syear != "" && $subject_id != "") {
            $chapterdata = DB::table('chapter_master')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('subject_id', $subject_id)
                ->where('syear', $syear)
                ->where('standard_id', $standard_id)->get()->toArray();

            $chapterdata = json_decode(json_encode($chapterdata), true);
            $finaldata = [];

            if (count($chapterdata) > 0) {
                foreach ($chapterdata as $key => $val) {
                    $chapter_id = $val['id'];

                    $topicData = DB::table('topic_master')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('chapter_id', $chapter_id)
                        ->where('syear', $syear)
                        ->get()->toArray();

                    $topicData = json_decode(json_encode($topicData), true);

                    $finaldata[$chapter_id] = $val;
                    $finaldata[$chapter_id]['topicData'] = $topicData;

                    if (count($topicData) > 0) {
                        foreach ($topicData as $tkey => $tval) {
                            $contentData = DB::table('content_master')
                                ->selectRaw("*,if(filename = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage',
                                    file_folder,'/',filename)) as full_path ")
                                ->where('sub_institute_id', $sub_institute_id)
                                ->where('chapter_id', $chapter_id)
                                ->where('syear', $syear)
                                ->where('topic_id', $tval['id'])
                                ->where('subject_id', $subject_id)
                                ->get()->toArray();

                            $contentData = json_decode(json_encode($contentData), true);
                            $finaldata[$chapter_id]['topicData'][$tkey]['contentData'] = $contentData;
                        }
                    }
                }
            }

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $finaldata;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }


    public function get_teacher_timetablewiseStandard(Request $request)
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

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $teacher_id = $request->input("teacher_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = timetableModel::from("timetable as t")
                ->select(DB::raw('distinct(t.standard_id) as std_id'), 's.name as std_name', 's.grade_id')
                ->join('standard as s', 's.id', '=', 't.standard_id')
                ->where(['t.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $teacher_id])
                ->get()->toArray();

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $data;
        }

        return json_encode($response);

    }

    public function get_teacher_timetablewiseSubject(Request $request)
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

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $teacher_id = $request->input("teacher_id");
        $standard_id = $request->input("standard_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'standard_id'      => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = subjectModel::from("timetable as t")
                ->select(DB::raw('distinct(t.subject_id) as sub_id'), 's.subject_name as sub_name')
                ->join('subject as s', 's.id', '=', 't.subject_id')
                ->where([
                    't.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $teacher_id,
                    't.standard_id'      => $standard_id,
                ])
                ->get()->toArray();

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $data;
        }

        return json_encode($response);
    }

    public function get_teacher_timetablewiseDivision(Request $request)
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

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $teacher_id = $request->input("teacher_id");
        $standard_id = $request->input("standard_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'standard_id'      => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
//            $data = subjectModel::from("timetable as t")
//                ->select(DB::raw('distinct(t.subject_id) as sub_id'), 's.subject_name as sub_name')
//                ->join('subject as s', 's.id', '=', 't.subject_id')
//                ->where([
//                    't.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $teacher_id,
//                    't.standard_id'      => $standard_id,
//                ])
//                ->get()->toArray();

            $data = divisionModel::from("timetable as t")
                ->select(DB::raw('distinct(t.division_id) as div_id'), 'd.name as div_name')
                ->join('division as d', 'd.id', '=', 't.division_id')
                ->where([
                    't.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $teacher_id,
                    't.standard_id'      => $standard_id,
                ])
                ->orderBy('d.name', 'asc')
                ->get()->toArray();

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $data;
        }

        return json_encode($response);
    }

    public function add_teacherLessonPlanning(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'user_profile_id'  => 'required|numeric',
            'standard_id'      => 'required|numeric',
            'subject_id'       => 'required|numeric',
            'division_id'      => 'required|numeric',
            'date'             => 'required|date',
            'title'            => 'required',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $finalArray[] = [
                'title'            => $request->get('title'),
                'description'      => $request->get('description'),
                'standard_id'      => $request->get('standard_id'),
                'subject_id'       => $request->get('subject_id'),
                'school_date'      => $request->get('date'),
                'division_id'      => $request->get('division_id'),
                'grade_id'         => '1',
                'user_group_id'    => $request->get('user_profile_id'),
                'teacher_id'       => $request->get('teacher_id'),
                'syear'            => $request->get('syear'),
                'sub_institute_id' => $request->get('sub_institute_id'),
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
            lessonplanningModel::insert($finalArray);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function add_teacherLessonPlanningExecution(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'user_profile_id'  => 'required|numeric',
            'lessonplan_id'    => 'required|numeric',
            'status'           => 'required|in:YES,NO',
            'reason'           => 'required',
            'date'             => 'required|date',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);
        $response['response'] = $validator->messages();

        if ($validator->fails()) {
            //
        } else {

            $lessonplan_DATA = lessonplanningModel::select('*')->where("id",
                $request->get('lessonplan_id'))->get()->toArray();
            $lessonplan_DATA = $lessonplan_DATA[0];

            $finalArray_Exec = [
                'syear'             => $request->get('syear'),
                'sub_institute_id'  => $request->get('sub_institute_id'),
                'user_group_id'     => $request->get('user_profile_id'),
                'school_date'       => $request->get('date'),
                'standard_id'       => $lessonplan_DATA['standard_id'],
                'division_id'       => $lessonplan_DATA['division_id'],
                'subject_id'        => $lessonplan_DATA['subject_id'],
                'teacher_id'        => $request->get('teacher_id'),
                'lessonplan_id'     => $request->get('lessonplan_id'),
                'lessonplan_status' => $request->get('status'),
                'lessonplan_reason' => $request->get('reason'),
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
            lessonplanning_executionModel::insert($finalArray_Exec);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    /* START PTM Module Teacher API*/
    public function get_teacherPTMBookingList(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table('ptm_booking_master as pb')
                ->join('ptm_time_slots_master as ps', function ($join) {
                    $join->whereRaw('ps.id= pb.TIME_SLOT_ID');
                })->join('standard as cs', function ($join) {
                    $join->whereRaw('cs.id = ps.standard_id');
                })->join('tblstudent as s', function ($join) {
                    $join->whereRaw('s.id = pb.STUDENT_ID and s.sub_institute_id = pb.SUB_INSTITUTE_ID');
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id and se.sub_institute_id = s.sub_institute_id AND se.end_date is NULL');
                })
                ->selectRaw("pb.ID,pb.DATE,pb.TEACHER_ID,pb.TIME_SLOT_ID,pb.CONFIRM_STATUS,pb.STUDENT_ID,pb.CREATED_ON,
                    pb.SUB_INSTITUTE_ID,pb.PTM_ATTENDED_STATUS,pb.PTM_ATTENDED_REMARKS,pb.PTM_ATTENDED_ENTRY_DATE,
                    ps.from_time AS FROM_TIME,ps.to_time AS TO_TIME,ps.ptm_date AS PTM_DATE")
                ->where('pb.SUB_INSTITUTE_ID', $request->get('sub_institute_id'))
                ->where('pb.TEACHER_ID', $request->get('teacher_id'))
                ->where('se.syear', $request->get('syear'))
                ->get()->toArray();

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $data;
        }

        return json_encode($response);
    }

    public function add_teacherPTMStatus(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'booking_id'       => 'required|numeric',
            'status'           => 'required|in:Yes,No',
            'remarks'          => 'required',
            'ip_address'       => 'required',
            'entry_date'       => 'required|date',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $PTMArray['PTM_ATTENDED_REMARKS'] = $request->get('remarks');
            $PTMArray['PTM_ATTENDED_STATUS'] = $request->get('status');
            $PTMArray['PTM_ATTENDED_BY'] = $request->get('teacher_id');
            $PTMArray['PTM_ATTENDED_ENTRY_DATE'] = $request->get('entry_date');
            $PTMArray['PTM_ATTENDED_CREATED_IP'] = $request->get('ip_address');
            $booking_id = $request->get('booking_id');
            $sub_institute_id = $request->get('sub_institute_id');

            ptmattenedstatusModel::where([
                "ID" => $booking_id, "SUB_INSTITUTE_ID" => $sub_institute_id,
            ])->update($PTMArray);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    /* END PTM Module Teacher API*/


    public function get_teacherResultExamList(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'term_id'          => 'required|numeric',
            'grade_id'         => 'required|numeric',
            'standard_id'      => 'required|numeric',
            'subject_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('result_create_exam')
                ->where('sub_institute_id', $request->get('sub_institute_id'))
                ->where('syear', $request->get('syear'))
                ->where('term_id', $request->get('term_id'))
                ->where('standard_id', $request->get('standard_id'))
                ->where('subject_id', $request->get('subject_id'))
                ->get()->toArray();

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $data;
        }

        return json_encode($response);
    }

    public function get_teacherResultCoscholasticParentList(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('result_co_scholastic_parent')
                ->where('sub_institute_id', $request->get('sub_institute_id'))->get()->toArray();

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $data;
        }

        return json_encode($response);
    }

    public function get_teacherResultCoscholasticList(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id'        => 'required|numeric',
            'term_id'                 => 'required|numeric',
            'co_scholastic_parent_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table('result_co_scholastic')
                ->where('sub_institute_id', $request->get('sub_institute_id'))
                ->where('parent_id', $request->get('co_scholastic_parent_id'))
                ->where('term_id', $request->get('term_id'))
                ->get()->toArray();

            $response['status'] = 1;
            $response['message'] = "Success";
            $response['data'] = $data;
        }

        return json_encode($response);
    }

    public function add_teacherExamSchedule(Request $request)
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

        $response = [];
        $validator = Validator::make($request->all(), [
            'title'            => 'required',
            'date'             => 'required|date',
            'standard_id'      => 'required|numeric',
            'division_id'      => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $file_folder = $ext = $size = $newfilename = "";
            if ($request->hasFile('filename')) {
                $img = $request->file('filename');
                $filename = $img->getClientOriginalName();
                $ext = $img->getClientOriginalExtension();
                $size = $img->getSize();
                $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
                $file_folder = '/lms_content_file';
                $img->storeAs('public/lms_content_file/', $newfilename);
            }

            if ($request->get('file_type') == "link") {
                $newfilename = $request->get('filename');
                $ext = "link";
            }

            $finalArray[] = [
                'standard_id'      => $request->get('standard_id'),
                'division_id'      => $request->get('division_id'),
                'title'            => $request->get('title'),
                'date_'            => $request->get('date'),
                'file_name'        => $newfilename,
                'syear'            => $request->get('syear'),
                'sub_institute_id' => $request->get('sub_institute_id'),
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            DB::table('exam_schedule')->insert($finalArray);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_teachertaskAPI(Request $request)
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

        $sub_institute_id = $request->input("sub_institute_id");
        $teacher_id = $request->input("teacher_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'teacher_id'       => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table('task as t')
                ->join('tbluser as u', function ($join) {
                    $join->whereRaw('t.TASK_ALLOCATED = u.id AND u.sub_institute_id = t.sub_institute_id');
                })->join('tbluser as u2', function ($join) {
                    $join->whereRaw('t.TASK_ALLOCATED_TO = u2.id AND u2.sub_institute_id = t.sub_institute_id');
                })->join('tbluser as u3', function ($join) {
                    $join->whereRaw('t.approved_by = u3.id AND u3.sub_institute_id = t.sub_institute_id');
                })
                ->selectRaw("t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS ALLOCATOR, 
                    CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS ALLOCATED_TO,
                    CONCAT_WS(' ',u3.first_name,u3.middle_name,u3.last_name) AS approved_by,
                    if(t.TASK_ATTACHMENT = '','',CONCAT('https://".$_SERVER['SERVER_NAME']."/storage/frontdesk/',t.TASK_ATTACHMENT)) as TASK_ATTACHMENT")
                ->where('t.sub_institute_id', $sub_institute_id)
                ->where('t.syear', $syear)
                ->where('t.TASK_ALLOCATED_TO', $teacher_id)
                ->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_teachertaskAPI(Request $request)
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

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $teacher_id = $request->get('teacher_id');
        $title = $request->get('title');
        $date = $request->get('date');
        $allocated_to = $request->get('allocated_to');
        $description = $request->get('description');

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'teacher_id'       => 'required|numeric',
            'title'            => 'required',
            'date'             => 'required|date',
            'allocated_to'     => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $file_folder = $ext = $size = $newfilename = "";
            if ($request->hasFile('attachment')) {
                $img = $request->file('attachment');
                $filename = $img->getClientOriginalName();
                $ext = $img->getClientOriginalExtension();
                $size = $img->getSize();
                $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
                $file_folder = '/frontdesk';
                $img->storeAs('public/frontdesk/', $newfilename);
            }

            $data['SYEAR'] = $syear;
            $data['CREATED_BY'] = $teacher_id;
            $data['TASK_ALLOCATED'] = $teacher_id;
            $data['CREATED_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
            $data['CREATED_ON'] = date('Y-m-d H:i:s');
            $data['sub_institute_id'] = $sub_institute_id;
            $data['TASK_ALLOCATED_TO'] = $allocated_to;
            $data['TASK_TITLE'] = $title;
            $data['TASK_DESCRIPTION'] = $description;
            $data['TASK_DATE'] = $date;
            $data['TASK_ATTACHMENT'] = $newfilename;

            taskModel::insert($data);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_teacherRequisitionAPI(Request $request)
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

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $teacher_id = $request->input("teacher_id");

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'teacher_id'       => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $data = DB::table('inventory_requisition_details as ir')
                ->join('tbluser as tu', function ($join) {
                    $join->whereRaw('tu.id = ir.requisition_by');
                })->leftJoin('tbluser as ira', function ($join) {
                    $join->whereRaw('ira.id = ir.requisition_approved_by');
                })->join('inventory_item_master as i', function ($join) {
                    $join->whereRaw('i.id = ir.item_id');
                })->join('inventory_requisition_status_master as irs', function ($join) {
                    $join->whereRaw('irs.id = ir.requisition_status');
                })->selectRaw("ir.id,CONCAT_WS(' ',tu.first_name,tu.middle_name,tu.last_name) AS requisition_by,ir.requisition_no,
                    ir.requisition_date,i.title AS item_name,ir.item_qty,ir.item_unit,ir.expected_delivery_time,ir.remarks,
                    irs.title AS requisition_status,CONCAT_WS(' ',ira.first_name,ira.middle_name,ira.last_name) AS requisition_approved_by,
                    ir.approved_qty,ir.requisition_approved_remarks,ir.requisition_approved_date")
                ->where('ir.sub_institute_id', $sub_institute_id)
                ->where('ir.syear', $syear)
                ->where('ir.requisition_by', $teacher_id)
                ->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_teacherRequisitionAPI(Request $request)
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


        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $requisition_by = $request->get('requisition_by');
        $requisition_date = date('Y-m-d H:i:s');
        $item_id = $request->get('item_id');
        $item_unit = $request->get('item_unit');
        $item_qty = $request->get('item_qty');
        $expected_delivery_time = $request->get('expected_delivery_time');
        $remarks = $request->get('remarks');
        $created_by = $request->get('created_by');
        $created_ip_address = $request->get('created_ip_address');

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'                  => 'required|numeric',
            'sub_institute_id'       => 'required|numeric',
            'requisition_by'         => 'required|numeric',
            'item_id'                => 'required|numeric',
            'item_qty'               => 'required|numeric',
            'expected_delivery_time' => 'required|date',
            'remarks'                => 'required',
            'created_ip_address'     => 'required',
            'created_by'             => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $requisition_controller = new requisitionController;

            $FORM_NO = $requisition_controller->generate_requisition_no($sub_institute_id, $syear);

            $requisition = new requisitionModel([
                'syear'                  => $syear,
                'sub_institute_id'       => $sub_institute_id,
                'requisition_no'         => $FORM_NO,
                'requisition_by'         => $requisition_by,
                'requisition_date'       => $requisition_date,
                'item_id'                => $item_id,
                'item_qty'               => $item_qty,
                'item_unit'              => $item_unit,
                'expected_delivery_time' => $expected_delivery_time,
                'requisition_status'     => 1,
                'remarks'                => $remarks,
                'created_by'             => $created_by,
                'created_ip_address'     => $created_ip_address,
            ]);

            $result = $requisition->save();

            if ($result == 1) {
                $response['status'] = 1;
                $response['message'] = "Requisition Added Successfully.";
            } else {
                $response['status'] = 0;
                $response['message'] = "Record Not Added";
            }
        }

        return json_encode($response);
    }

    public function get_teachercomplaintAPI(Request $request)
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

        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        $response = [];
        $validator = Validator::make($request->all(), [
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'teacher_id'       => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $data = DB::table('complaint as t')
                ->join('tbluser as u', function ($join) {
                    $join->whereRaw('t.COMPLAINT_BY = u.id AND u.sub_institute_id = t.sub_institute_id');
                })
                ->leftJoin('tbluser as u3', function ($join) {
                    $join->whereRaw('t.COMPLAINT_SOLUTION_BY = u3.id AND u3.sub_institute_id = t.sub_institute_id');
                })
                ->selectRaw("t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS COMPLAINT_BY,
                    CONCAT_WS(' ',u3.first_name,u3.middle_name,u3.last_name) AS COMPLAINT_SOLUTION_BY,
                    if(t.ATTACHEMENT = '','',CONCAT('https://".$_SERVER['SERVER_NAME']."/storage/frontdesk/',t.ATTACHEMENT)) as
                    COMPLAINT_ATTACHMENT")
                ->where('t.syear', $syear)
                ->where('t.sub_institute_id', $sub_institute_id)
                ->where('t.COMPLAINT_BY', $teacher_id)
                ->get()->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        }

        return json_encode($response);
    }

    public function add_teachercomplaintAPI(Request $request)
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

        $syear = $request->get('syear');
        $sub_institute_id = $request->get('sub_institute_id');
        $teacher_id = $request->get('teacher_id');
        $title = $request->get('title');
        $date = $request->get('date');
        $allocated_to = $request->get('allocated_to');
        $description = $request->get('description');

        $response = [];
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'teacher_id'       => 'required|numeric',
            'title'            => 'required',
            'date'             => 'required|date',
            'allocated_to'     => 'required',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            $file_folder = $ext = $size = $newfilename = "";
            if ($request->hasFile('attachment')) {
                $img = $request->file('attachment');
                $filename = $img->getClientOriginalName();
                $ext = $img->getClientOriginalExtension();
                $size = $img->getSize();
                $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
                $file_folder = '/frontdesk';
                $img->storeAs('public/frontdesk/', $newfilename);
            }

            $data['SYEAR'] = $syear;
            $data['COMPLAINT_BY'] = $teacher_id;
            $data['SUB_INSTITUTE_ID'] = $sub_institute_id;
            $data['COMPLAINT_SOLUTION'] = "PENDING";
            $data['CREATED_IP'] = $_SERVER['REMOTE_ADDR'];
            $data['CREATED_DATE'] = date('Y-m-d H:i:s');
            $data['TITLE'] = $title;
            $data['DESCRIPTION'] = $description;
            $data['ATTACHEMENT'] = $newfilename;
            $data['DATE'] = $date;

            complaintModel::insert($data);

            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
    }

    public function get_teacherExamSchedule(Request $request)
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

        $type = $request->input("type");
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {

            $data = DB::table('exam_schedule as e')
                ->join('timetable as t', function ($join) {
                    $join->on('t.standard_id', '=', 'e.standard_id')
                         ->on('t.division_id', '=', 'e.division_id')
                         ->on('t.sub_institute_id', '=', 'e.sub_institute_id')
                         ->on('t.syear', '=', 'e.syear');
                })
                ->join('standard as s', function ($join) {
                    $join->on('s.id', '=', 't.standard_id')
                         ->on('s.sub_institute_id', '=', 't.sub_institute_id')
                         ->on('s.grade_id', '=', 't.academic_section_id');
                })
                ->selectRaw("CONCAT_WS(' - ', s.name, e.title) as title, e.date_,
                    IF(e.file_name = '', '', concat('https://".$_SERVER['SERVER_NAME']."/storage/exam_schedule/', e.file_name)) as file_name")
                ->where('e.syear', $syear)
                ->where('e.sub_institute_id', $sub_institute_id)
                ->where('t.teacher_id', $teacher_id)
                ->groupBy('e.id')
                ->orderBy('e.date_', 'desc')
                ->get()
                ->toArray();

            if (count($data) > 0) {
                $response['status'] = 1;
                $response['message'] = "Success";
                $response['data'] = $data;
            } else {
                $response['status'] = 0;
                $response['message'] = "No Record";
            }
        } else {
            $response['status'] = 0;
            $response['message'] = "Parameter Missing";
        }

        return json_encode($response);
    }
}
