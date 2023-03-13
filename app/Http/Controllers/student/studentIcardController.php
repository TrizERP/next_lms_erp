<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\transportation\add_driver\add_driver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class studentIcardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        $driver_data = add_driver::where([
            'sub_institute_id' => $sub_institute_id, 'type' => 'Driver',
        ])->get()->toArray();

        $res['status_code'] = "1";
        $res['driver'] = $driver_data;
        $res['message'] = "Success";

        return is_mobile($type, "student/student_icard/show_student", $res, "view");
    }

    public function showStudent(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $driver_id = $request->input('driver_id');

        $driver_data = add_driver::where([
            'sub_institute_id' => $sub_institute_id, 'type' => 'Driver',
        ])->get()->toArray();

        $studentData = SearchStudent($grade, $standard, $division);

        if ($driver_id != '') {
            foreach ($studentData as $key => $value) {
                $student_driver_map = DB::table('transport_map_student as tm')
                    ->join('transport_vehicle as tv', function ($join) {
                        $join->whereRaw("tv.id = tm.from_bus_id AND tv.sub_institute_id = tm.sub_institute_id");
                    })->selectRaw('COUNT(*) AS total,tm.student_id')
                    ->where('tm.sub_institute_id', $sub_institute_id)
                    ->where('tv.driver', $driver_id)
                    ->where('tm.student_id', $value['id'])->get()->toArray();

                if ($value['id'] != $student_driver_map[0]->student_id) {
                    unset($studentData[$key]);
                }
            }
        }
        if (count($studentData) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";

            return is_mobile($type, "student_icard.index", $res);
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $studentData;
        $res['driver'] = $driver_data;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['driver_id'] = $driver_id;

        return is_mobile($type, "student/student_icard/show_student", $res, "view");
    }

    public function showStudentIcard(Request $request)
    {
        $type = $request->input('type');
        $template = $request->input('template');
        $row = $request->input('row');
        $column = $request->input('column');
        $student_ids = $request->input('students');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');

        $data = getStudents($student_ids);
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['column'] = $column;
        $res['row'] = $row;
        $res['template'] = $template;

        return is_mobile($type, "student/student_icard/show_student_icard", $res, "view");
    }

    public function viewSamples(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "success";

        return is_mobile($type, "student/student_icard/view_samples", $res, "view");
    }
}
