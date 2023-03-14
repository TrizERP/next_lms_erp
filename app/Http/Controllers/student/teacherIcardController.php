<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class teacherIcardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";

        $res['teacher_type'] = $this->teacher_types($request->session()->get('sub_institute_id'));

        return is_mobile($type, "student/teacher_icard/show_teacher", $res, "view");
    }

    public function teacher_types($SUB_INSTITUTE_ID)
    {
        return DB::table("tbluserprofilemaster")
            ->where("sub_institute_id", $SUB_INSTITUTE_ID)
            ->where('name', '<>', 'Student')
            ->pluck("name", "id");
    }

    public function showTeacher(Request $request)
    {
        $type = $request->input('type');
        $teacher_type = $request->input('teacher_type');
        // $grade = $request->input('grade');
        // $standard = $request->input('standard');
        // $division = $request->input('division');

        $user_type = DB::table("tbluser")
            ->select(DB::raw("CONCAT(first_name,' ',last_name) AS name"), 'id')
            ->where("sub_institute_id", $request->session()->get('sub_institute_id'))
            ->where('user_profile_id', $teacher_type)
            ->pluck("name", "id");

        // $studentData = SearchStudent($grade, $standard, $division);
        if (count($user_type) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No profile user found please check your search panel";

            return is_mobile($type, "teacher_icard.index", $res);
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $user_type;
        $res['teacher_type'] = $this->teacher_types($request->session()->get('sub_institute_id'));
        $res['teacher_type_selected'] = $teacher_type;

        // $res['grade_id'] = $grade;
        // $res['standard_id'] = $standard;
        // $res['division_id'] = $division;

        return is_mobile($type, "student/teacher_icard/show_teacher", $res, "view");
    }

    public function showteacherIcard(Request $request)
    {
        $type = $request->input('type');
        $template = $request->input('template');
        $row = $request->input('row');
        $column = $request->input('column');
        $student_ids = $request->input('students');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        // $grade_id = $request->input('grade_id');
        // $standard_id = $request->input('standard_id');

        // $data = getStudents($student_ids);

        $data = DB::table("tbluser")
            // ->select(DB::raw('first_name','last_name','email','mobile','gender','address'))
            ->select('first_name', 'last_name', 'email', 'mobile', 'gender', 'address')
            ->where("sub_institute_id", $request->session()->get('sub_institute_id'))
            ->whereIn('id', $student_ids)
            ->get()
            ->toArray();


        // $data = array_map(function ($value) {
        //     return (array)$value;
        // }, $data);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['column'] = $column;
        $res['row'] = $row;
        $res['template'] = $template;

        return is_mobile($type, "student/teacher_icard/show_teacher_icard", $res, "view");
    }

    public function viewSamples(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "success";

        return is_mobile($type, "student/teacher_icard/view_samples", $res, "view");
    }
}
