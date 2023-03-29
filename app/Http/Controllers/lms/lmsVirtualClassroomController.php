<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\virtualclassroomModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lmsVirtualClassroomController extends Controller
{
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
        $res['data'] = $data['virtualclassroom_data'];

        return is_mobile($type, 'lms/show_lmsVirtualClassroom', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $user_profile_name = $request->session()->get('user_profile_name');
        $data['virtualclassroom_data'] = [];

        if (strtoupper($user_profile_name) == 'TEACHER') {
            $data['virtualclassroom_data'] = virtualclassroomModel::select('lms_virtual_classroom.*',
                DB::raw('sub_std_map.display_name as subject_name,chapter_master.chapter_name AS chapter_name,
                topic_master.name AS topic_name,concat_ws(" ",tbluser.first_name,tbluser.last_name) as teacher_name,concat(lms_virtual_classroom.event_date," ",lms_virtual_classroom.from_time) as event_datetime'))
                ->join('sub_std_map', 'sub_std_map.subject_id', '=', 'lms_virtual_classroom.subject_id')
                ->join("timetable", function ($join) {
                    $join->on("timetable.standard_id", "=", "sub_std_map.standard_id")
                        ->on("timetable.subject_id", "=", "sub_std_map.subject_id")
                        ->on("timetable.sub_institute_id", "=", "sub_std_map.sub_institute_id");
                })
                ->join('chapter_master', 'chapter_master.id', '=', 'lms_virtual_classroom.chapter_id')
                ->join('topic_master', 'topic_master.id', '=', 'lms_virtual_classroom.topic_id')
                ->join('tbluser', 'tbluser.id', '=', 'lms_virtual_classroom.created_by')
                ->where([
                    'lms_virtual_classroom.sub_institute_id' => $sub_institute_id,
                    'lms_virtual_classroom.syear'            => $syear, 'timetable.teacher_id' => $user_id,
                ])
                ->orderBy('lms_virtual_classroom.sort_order')
                ->groupBy('lms_virtual_classroom.id')
                ->get();
        } else {
            $data['virtualclassroom_data'] = virtualclassroomModel::select('lms_virtual_classroom.*',
                DB::raw('sub_std_map.display_name as subject_name,chapter_master.chapter_name AS chapter_name,
                topic_master.name AS topic_name,concat_ws(" ",tbluser.first_name,tbluser.last_name) as teacher_name,concat(lms_virtual_classroom.event_date," ",lms_virtual_classroom.from_time) as event_datetime'))
                ->join('sub_std_map', 'sub_std_map.subject_id', '=', 'lms_virtual_classroom.subject_id')
                ->join('chapter_master', 'chapter_master.id', '=', 'lms_virtual_classroom.chapter_id')
                ->join('topic_master', 'topic_master.id', '=', 'lms_virtual_classroom.topic_id')
                ->join('tbluser', 'tbluser.id', '=', 'lms_virtual_classroom.created_by')
                ->where([
                    'lms_virtual_classroom.sub_institute_id' => $sub_institute_id,
                    'lms_virtual_classroom.syear'            => $syear,
                ])
                ->orderBy('lms_virtual_classroom.sort_order')
                ->groupBy('lms_virtual_classroom.id')
                ->get();
        }

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {

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
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['virtualclassroom_data'] = virtualclassroomModel::find($id)->toArray();

        return is_mobile($type, "lms/edit_virtualclassroom", $data, "view");
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
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $data = [
            'room_name'   => $request->get('room_name'),
            'description' => $request->get('description'),
            'event_date'  => $request->get('event_date'),
            'from_time'   => $request->get('from_time'),
            'to_time'     => $request->get('to_time'),
            'recurring'   => $request->get('recurring'),
            'url'         => $request->get('url'),
            'password'    => $request->get('password'),
            'status'      => $request->get('status'),
            'sort_order'  => $request->get('sort_order'),
        ];

        virtualclassroomModel::where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Virtual Classroom Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lmsVirtualClassroom.index", $res);
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

        virtualclassroomModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Virtual Classroom Deleted Successfully";

        return is_mobile($type, "lmsVirtualClassroom.index", $res);
    }

}
