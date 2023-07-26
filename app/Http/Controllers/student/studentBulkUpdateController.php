<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setupModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Str;

class studentBulkUpdateController extends Controller
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
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $get_student_enrollments = tblstudentEnrollmentModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereNull('end_date')->get()->toArray();

        $res['get_student_enrollments'] = $get_student_enrollments;

        return is_mobile($type, "student/student_bluk_update", $res, "view");
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
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $type = $request->get('type');

        $get_student_enrollments = tblstudentEnrollmentModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereNull('end_date')->get();

        foreach ($get_student_enrollments as $get_student_enrollment) {
            $get_student_enrollment->end_date = date('Y-m-d');
            $get_student_enrollment->save();
        }

        $res['status'] = "1";
        $res['message'] = "Data Student Bulk Updated Successfully.";

        return is_mobile($type, "student_bulk_update.index", $res, "redirect");
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

    public function ajax_toAcademicSections(Request $request)
    {
        $to_sub_institute_id = $request->input("to_sub_institute_id");

        return academic_sectionModel::where(['sub_institute_id' => $to_sub_institute_id])->get()->toArray();
    }

    public function ajax_toStandards(Request $request)
    {
        $to_academic_section = $request->input("to_academic_section");

        return standardModel::where(['grade_id' => $to_academic_section])->get()->toArray();
    }

    public function ajax_toDivisions(Request $request)
    {
        $to_standard = $request->input("to_standard");

        return std_div_mappingModel::select('division.*')
            ->join("division", function ($join) {
                $join->on("division.id", "=", "std_div_map.division_id")
                    ->on("division.sub_institute_id", "=", "std_div_map.sub_institute_id");
            })
            ->where(['std_div_map.standard_id' => $to_standard])
            ->get()->toArray();
    }

    public function selected_student_view()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $from_institute_details = school_setupModel::where(['id' => $sub_institute_id])->get()->toArray();
        $from_institute_name = '';
        if (count($from_institute_details) > 0) {
            $from_institute_name = $from_institute_details[0]['SchoolName'];
        }
        $type='';
        $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status'] = 1;
        $res['message'] = "Success";
        $res['to_academic_sections'] = $to_academic_sections;
        $res['from_institute_name'] = $from_institute_name;
        return is_mobile($type, "student.show_rollover_selected_students", $res, "view");

        // return view('student/show_rollover_selected_students', $res);
    }

}
