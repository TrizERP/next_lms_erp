<?php

namespace App\Http\Controllers\lms\assignment;

use App\Http\Controllers\Controller;
use App\Models\lms\assignment\lms_assignmentModel;
use App\Models\lms\questionpaperModel;
use App\Models\school_setup\subjectModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\sendNotification;


class assignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['status_code'] = 1;
        $res['message'] = "Success";

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['subjects'] = $subjects;

        return is_mobile($type, "lms/assignment/show_assignment", $res, "view");
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
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $subject = $request->input('subject');
        $type = $request->input('type');
        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $exam_arr = questionpaperModel::select('*',
            DB::raw('concat(concat_ws("_",id,sub_institute_id,syear),".pdf") as pdf_name'))
            ->where(['sub_institute_id' => $sub_institute_id, 'exam_type' => "offline", 'subject_id' => $subject])
            ->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $data;
        $res['subjects'] = $subjects;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['exam_arr'] = $exam_arr;
        $res['subject'] = $subject;

        return is_mobile($type, "lms/assignment/show_assignment", $res, "view");
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
        $type = $request->get('type');
        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $students = $request->get('students');
        $student_details = getStudents($students, $sub_institute_id, $syear);

        $title = $request->get('title');
        $description = $request->get('description');
        $submission_date = $request->get('submission_date');
        $division_id = $request->get('division_id');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $arr = explode("####", $request->get('exam_pdf'));
        $exam_pdf = "QuestionPaper/".$arr[0];
        $exam_id = $arr[1];
        $created_by = $request->session()->get('user_id');

        foreach ($student_details as $id => $arr) {
            $student_id = $arr['id'];
            $standard_id = $arr['standard_id'];
            $division_id = $arr['section_id'];
            $assignment_arr = [];
            $assignment_arr['student_id'] = $student_id;
            $assignment_arr['sub_institute_id'] = $sub_institute_id;
            $assignment_arr['title'] = $title;
            $assignment_arr['description'] = $description;
            $assignment_arr['standard_id'] = $standard_id;
            $assignment_arr['division_id'] = $division_id;
            $assignment_arr['subject_id'] = $subject_id;
            $assignment_arr['exam_id'] = $exam_id;
            $assignment_arr['exam_pdf'] = $exam_pdf;
            $assignment_arr['created_date'] = date('Y-m-d');
            $assignment_arr['submission_date'] = $submission_date;
            $assignment_arr['syear'] = $syear;
            $assignment_arr['created_ip'] = $_SERVER['REMOTE_ADDR'];
            $assignment_arr['created_by'] = $created_by;
            lms_assignmentModel::insert($assignment_arr);

            //START Send Notification Code
            $app_notification_content = [
                'NOTIFICATION_TYPE'        => 'LMS Assignment',
                'NOTIFICATION_DATE'        => date('Y-m-d'),
                'STUDENT_ID'               => $student_id,
                'NOTIFICATION_DESCRIPTION' => $title,
                'STATUS'                   => 0,
                'SUB_INSTITUTE_ID'         => $sub_institute_id,
                'SYEAR'                    => $syear,
                'CREATED_BY'               => $created_by,
                'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
            ];
            sendNotification($app_notification_content);
            //END Send Notification Code
        }

        $res['status_code'] = "1";
        $res['message'] = "Assignment Added successfully";

        return is_mobile($type, "lmsAssignment.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show(Request $request, $id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit(Request $request, $id)
    {
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
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy(Request $request, $id)
    {
    }

}
