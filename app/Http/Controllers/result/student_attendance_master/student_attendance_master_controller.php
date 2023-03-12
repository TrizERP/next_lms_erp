<?php

namespace App\Http\Controllers\result\student_attendance_master;

use App\Http\Controllers\Controller;
use App\Models\result\result_remark_mater\result_remark_master;
use App\Models\result\student_attendance_master\student_attendance_master;
use App\Models\result\working_day_master\working_day_master;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class student_attendance_master_controller extends Controller
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
                $data['message'] = $data_arr['message'];
            }
            if (isset($data_arr['class'])) {
                $data['class'] = $data_arr['class'];
            }
        }

        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "result/student_attendance_master/show", $data, "view");
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
        if (! isset($_REQUEST["term"]) || $_REQUEST["term"] == '' || ! isset($_REQUEST["standard"]) || $_REQUEST["standard"] == '' ||
            ! isset($_REQUEST["grade"]) || $_REQUEST['grade'] == '') {

            $res = [
                "status_code" => 1,
                "message"     => "Please Select All Fields.",
                "class"       => "danger",
            ];

            $type = $request->input('type');

            return is_mobile($type, "result/student_attendance_master/show", $res, "view");
        }
        $type = $request->input('type');

        $where = [
            'term_id'          => $_REQUEST["term"],
            'standard'         => $_REQUEST["standard"],
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ];

        $where_days_master = [
            'term_id'          => $_REQUEST["term"],
            'standard'         => $_REQUEST["standard"],
            'sub_institute_id' => session()->get('sub_institute_id'),
        ];

        $where_result_remark = [
            'marking_period_id' => $_REQUEST["term"],
            'sub_institute_id'  => session()->get('sub_institute_id'),
            'syear'             => session()->get('syear'),
        ];

        $responce_arr['term_id'] = $_REQUEST["term"];
        $responce_arr['standard'] = $_REQUEST["standard"];
        $responce_arr['grade'] = $_REQUEST['grade'];

        $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard']);

        $attendance_data = student_attendance_master::select('student_id', 'attendance', 'percentage', 'remark_id',
            'teacher_remark')
            ->where($where)->get()->toArray();

        $working_day = working_day_master::select('total_working_day')
            ->where($where_days_master)->get()->toArray();

        if (count($working_day) == 0) {
            $res = [
                "status_code" => 1,
                "message"     => "Please Add Total Working Days For This Standard.",
                "class"       => "danger",
            ];

            $type = $request->input('type');

            return is_mobile($type, "result/student_attendance_master/show", $res, "view");
        }

        $remark_data = result_remark_master::where($where_result_remark)->pluck("title", "id");

        if (count($remark_data) == 0) {
            $res = [
                "status_code" => 1,
                "message"     => "Please Add Remarks From Remark Master For This Standard.",
                "class"       => "danger",
            ];

            $type = $request->input('type');

            return is_mobile($type, "result/student_attendance_master/show", $res, "view");
        }

        $responce_arr['remark_data'] = $remark_data;
        foreach ($student_data as $id => $arr) {
            $temp_arr = [];
            foreach ($attendance_data as $data_id => $data_arr) {
                if ($data_arr['student_id'] == $arr['student_id']) {
                    $temp_arr = $data_arr;
                }
            }

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            if (count($temp_arr) > 0) {
                $responce_arr['stu_data'][$id]['att'] = $temp_arr["attendance"];
                $responce_arr['stu_data'][$id]['per'] = $temp_arr["percentage"];
                $responce_arr['stu_data'][$id]['remark'] = $temp_arr["remark_id"];
                $responce_arr['stu_data'][$id]['teacher_remark'] = $temp_arr["teacher_remark"];
            } else {
                $responce_arr['stu_data'][$id]['att'] = 0;
                $responce_arr['stu_data'][$id]['per'] = 0;
                $responce_arr['stu_data'][$id]['remark'] = "";
                $responce_arr['stu_data'][$id]['teacher_remark'] = "";
            }
            $responce_arr['stu_data'][$id]['att_out'] = $working_day[0]['total_working_day'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
        }

        return is_mobile($type, "result/student_attendance_master/add", $responce_arr, "view");
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
        foreach ($_REQUEST['values'] as $student_id => $arr) {
            student_attendance_master::where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'term_id'          => $arr['term_id'],
                'standard'         => $arr['standard'],
                'syear'            => session()->get('syear'),
                'student_id'       => $student_id,
            ])->delete();
            if ($arr['attendance'] != '' || $arr['remark_id'] != '' || $arr['teacher_remark']) {
                $arr['per'] = rtrim($arr['per'], '%');
                $data = new student_attendance_master([
                    'term_id'          => $arr['term_id'],
                    'standard'         => $arr['standard'],
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'syear'            => session()->get('syear'),
                    'student_id'       => $student_id,
                    'attendance'       => $arr['attendance'],
                    'percentage'       => $arr['per'],
                    'remark_id'        => $arr['remark_id'],
                    'teacher_remark'   => $arr['teacher_remark'],
                ]);
                $data->save();
            }
        }
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
            "class"       => "success",
        ];

        $type = $request->input('type');

        return is_mobile($type, "student_attendance_master.index", $res, "redirect");
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
