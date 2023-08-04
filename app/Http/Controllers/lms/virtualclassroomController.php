<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\contentmappingtypeModel;
use App\Models\lms\contentModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\topicModel;
use App\Models\lms\virtualclassroomModel;
use App\Models\school_setup\sub_std_mapModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\sendNotification;

class virtualclassroomController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['content_data'];

        return is_mobile($type, 'lms/show_lmsVirtualClassroom', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['content_data'] = array();
        $marking_period_id = session()->get('term_id');
        $data['content_data'] = contentModel::select('content_master.*', 'standard.name as standard_name',
            'academic_section.title as grade_name',
            'subject_name', 'chapter_name', 'tm.name as topic_name', 'stm.name as sub_topic_name')
            ->join('standard',function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'content_master.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('academic_section', 'academic_section.id', '=', 'content_master.grade_id')
            ->join('subject', 'subject.id', '=', 'content_master.subject_id')
            ->join('chapter_master as cm', 'cm.id', '=', 'content_master.chapter_id')
            ->leftjoin('topic_master as tm', 'tm.id', '=', 'content_master.topic_id')
            ->leftjoin('topic_master as stm', 'stm.id', '=', 'content_master.sub_topic_id')
            ->where('content_master.sub_institute_id', $sub_institute_id)
            ->get();

        return $data;
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('chapter_id'),
            $request->get('topic_id'));

        return is_mobile($type, 'lms/add_virtualclassroom', $data, "view");
    }

    public function getBreadcrum($sub_institute_id, $chapter_id, $topic_id)
    {
        $marking_period_id=session()->get('term_id');
        $breadcrum_data = DB::table('chapter_master as c')
            ->join('sub_std_map as s', function ($join) use($main_topic_id){
                $join->whereRaw('s.subject_id = c.subject_id AND s.standard_id = c.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = c.standard_id');
            })->join('topic_master as t', function ($join) {
                $join->whereRaw('t.chapter_id = c.id');
            })->selectRaw("c.subject_id,s.display_name AS subject_name,c.standard_id,st.name AS standard_name,
                c.id AS chapter_id,c.chapter_name,t.id as topic_id,t.name as topic_name")
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.id', $chapter_id)
            ->where('t.id', $topic_id)->get()->toArray();

        return $breadcrum_data[0];
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        $chapter_data = chapterModel::select('*')
            ->where([
                'chapter_master.sub_institute_id' => $sub_institute_id,
                'chapter_master.id'               => $request->get('hid_chapter_id'),
            ])
            ->get()->toArray();
        $chapter_data = $chapter_data[0];

        $content = array(
            'grade_id'         => $chapter_data['grade_id'],
            'standard_id'      => $chapter_data['standard_id'],
            'subject_id'       => $chapter_data['subject_id'],
            'chapter_id'       => $request->get('hid_chapter_id'),
            'topic_id'         => $request->get('hid_topic_id'),
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
            'syear'            => $syear,
            'sub_institute_id' => $sub_institute_id,
            'created_by'       => $user_id,
            'created_ip'       => $created_ip,
        );
        virtualclassroomModel::insert($content);

        $app_notification_content = array(
            'NOTIFICATION_TYPE'        => 'Virtual Classroom',
            'NOTIFICATION_DATE'        => date('Y-m-d'),
            'NOTIFICATION_DESCRIPTION' => $request->get('room_name'),
            'STATUS'                   => 0,
            'SUB_INSTITUTE_ID'         => $sub_institute_id,
            'SYEAR'                    => $syear,
            'SCREEN_NAME'              => 'virtual_classroom',
            'CREATED_BY'               => $user_id,
            'CREATED_IP'               => $created_ip,
        );
        sendNotification($app_notification_content);
        //appNotificationModel::insert($app_notification_content);

        $res = array(
            "status_code" => 1,
            "message"     => "Virtual Classroom Added Successfully",
        );
        $type = $request->input('type');

        //return is_mobile($type, "content_master.index", $res, "redirect");
        return redirect()->route('topic_master.index', ['id' => $request->get('hid_chapter_id')]);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['content_data'] = contentModel::find($id)->toArray();

        $content_mapping_type = contentmappingtypeModel::where(['content_id' => $id])->get()->toArray();
        $i = 1;
        foreach ($content_mapping_type as $key => $val) {
            $final_content_mapping_type[$i]['TYPE_ID'] = $val['mapping_type_id'];
            $final_content_mapping_type[$i]['VALUE_ID'] = $val['mapping_value_id'];
            $i++;
        }

        //dd($final_content_mapping_type);

        $lms_mapping_type = lmsmappingtypeModel::where(['parent_id' => '0'])->get()->toArray();
        foreach ($lms_mapping_type as $lkey => $lval) {
            $arr = lmsmappingtypeModel::where(['parent_id' => $lval['id']])->get()->toArray();
            foreach ($arr as $k => $v) {
                $lms_mapping_value[$lval['id']][$v['id']] = $v['name'];
            }
        }

        $data['lms_mapping_value'] = $lms_mapping_value;
        $data['lms_mapping_type'] = $lms_mapping_type;
        $data['content_mapping_type'] = $final_content_mapping_type;

        return is_mobile($type, "lms/edit_content", $data, "view");
    }

    public function update(Request $request, $id)
    {
        //ValidateInsertData('subject','update');        

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $show_hide = $request->get('show_hide');
        $show_hide_val = isset($show_hide) ? $show_hide : '';

        $image_data = array();
        if ($request->hasFile('filename')) {
            if ($request->has('hid_filename')) {
                unlink('storage'.$request->input('hid_filename'));
            }
            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            $img->storeAs('public/lms_content_file/', $newfilename);

            $image_data = array(
                'file_folder' => '/lms_content_file',
                'filename'    => $newfilename,
                'file_type'   => $ext,
                'file_size'   => $size,
            );
        }

        $data = array(
            'grade_id'         => $request->get('grade'),
            'standard_id'      => $request->get('standard'),
            'subject_id'       => $request->get('subject'),
            'chapter_id'       => $request->get('chapter'),
            'topic_id'         => $request->get('topic'),
            'sub_topic_id'     => $request->get('subtopic'),
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'show_hide'        => $show_hide_val,
            'sort_order'       => $request->get('sort_order'),
            'meta_tags'        => $request->get('meta_tags'),
            'content_category' => $request->get('content_category'),
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'restrict_date'    => $request->get('restrict_date'),
            'syear'            => $syear,
        );

        $data = array_merge($data, $image_data);

        contentModel::where(["id" => $id])->update($data);

        //START Delete and insert into content_mapping_Data
        contentmappingtypeModel::where(["content_id" => $id])->delete();

        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');

        foreach ($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $contentmappingtype = array(
                    'content_id'       => $id,
                    'mapping_type_id'  => $val,
                    'mapping_value_id' => $mapping_value[$key],
                );
                contentmappingtypeModel::insert($contentmappingtype);
            }
        }
        //END Delete and insert into content_mapping_Data

        $res = array(
            "status_code" => 1,
            "message"     => "Content Updated Successfully",
        );
        $type = $request->input('type');

        //return is_mobile($type, "content_master.index", $res, "redirect");
        return redirect()->route('topic_master.index', ['id' => $request->get('chapter')]);
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        $contentdata = contentModel::where(["id" => $id])->get()->toArray();
        $chapter_id = $contentdata[0]['chapter_id'];

        contentModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Content Deleted Successfully";

        //return is_mobile($type, "content_master.index", $res);
        return redirect()->route('topic_master.index', ['id' => $chapter_id]);
    }

    public function ajax_LMS_MappingValue(Request $request)
    {
        $mapping_type = $request->input("mapping_type");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return DB::table('lms_mapping_type')
            ->select(['id', 'name'])
            ->where(['parent_id' => $mapping_type, 'status' => '1'])
            ->get()->toArray();
    }

    public function StandardwiseSubject(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();

        return $stdData;
    }

    public function chapter_search(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $subject = $request->input('subject');
        $marking_period_id=session()->get('term_id');

        $search_arr = array(
            'chapter_master.grade_id'         => $grade,
            'chapter_master.standard_id'      => $standard,
            'chapter_master.subject_id'       => $subject,
            'chapter_master.sub_institute_id' => $sub_institute_id,
        );
        $data = array();
        $data['data'] = chapterModel::select('chapter_master.*', 'standard.name as standard_name'
            , 'academic_section.title as grade_name', 'subject_name')
            ->join('standard', function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'chapter_master.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('academic_section', 'academic_section.id', '=', 'chapter_master.grade_id')
            ->join('subject', 'subject.id', '=', 'chapter_master.subject_id')
            ->where($search_arr)
            ->orderBy('chapter_master.standard_id', 'asc')
            ->get();

        if (count($data['data']) > 0) {
            $topic_data = topicModel::select('*')
                ->where(['sub_institute_id' => $sub_institute_id])
                ->where('main_topic_id', '=', '0')
                ->get()->toArray();

            foreach ($topic_data as $key => $val) {
                $data['topic_data'][$val['chapter_id']][] = $val;
            }

            $subtopic_data = topicModel::select('*')
                ->where(['sub_institute_id' => $sub_institute_id])
                ->where('main_topic_id', '!=', '0')
                ->get()->toArray();

            foreach ($subtopic_data as $subkey => $subval) {
                $data['subtopic_data'][$subval['main_topic_id']][] = $subval;
            }

        }

        $subject_data = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $standard])
            ->orderBy('display_name')->get()->toArray();

        $data['subject_arr'] = $subject_data;
        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";
        $data['grade'] = $grade;
        $data['standard'] = $standard;
        $data['subject'] = $subject;


        return is_mobile($type, "lms/show_chapter", $data, "view");
    }

    public function ajax_SubjectwiseChapter(Request $request)
    {
        $sub_id = $request->input("sub_id");
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $chapterData = chapterModel::where(
            [
                'sub_institute_id' => $sub_institute_id,
                'subject_id'       => $sub_id,
                'standard_id'      => $std_id,
                'availability'     => 1,
            ]
        )
            ->get()->toArray();

        return $chapterData;
    }

    public function ajax_ChapterwiseTopic(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $main_topic_id = $request->input("main_topic_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $topicData = topicModel::where(
            [
                'sub_institute_id' => $sub_institute_id,
                'chapter_id'       => $chapter_id,
                'main_topic_id'    => $main_topic_id,
            ]
        )
            ->get()->toArray();

        return $topicData;
    }

    public function studentVirtualClassroomAPI(Request $request)
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

        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $marking_period_id = session()->get('term_id');

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('tblstudent_enrollment as s')
                ->join('lms_virtual_classroom as v', function ($join) {
                    $join->whereRaw('s.standard_id = v.standard_id AND s.sub_institute_id = v.sub_institute_id');
                })->join('standard as st', function ($join) use($marking_period_id) {
                    $join->whereRaw('st.id = v.standard_id AND st.sub_institute_id = s.sub_institute_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('s.marking_period_id',$marking_period_id);
                    // });
                })->join('subject as sub', function ($join) {
                    $join->whereRaw('sub.id = v.subject_id AND sub.sub_institute_id = s.sub_institute_id');
                })->join('chapter_master as c', function ($join) {
                    $join->whereRaw('c.id = v.chapter_id AND c.sub_institute_id = v.sub_institute_id');
                })->join('topic_master as t', function ($join) {
                    $join->whereRaw('t.id = v.topic_id AND t.sub_institute_id = v.sub_institute_id');
                })->join('tbluser as u', function ($join) {
                    $join->whereRaw('u.id = v.created_by');
                })->selectRaw("st.name AS standard_name,sub.subject_name,c.chapter_name,t.name AS topic_name,s.syear,
                    s.sub_institute_id,v.room_name,v.description,v.event_date,v.from_time,v.to_time,v.recurring,v.url,v.password,
                    CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name")
                ->where('s.student_id', $student_id)
                ->where('s.syear', $syear)
                ->where('s.sub_institute_id', $sub_institute_id)->get()->toArray();


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
