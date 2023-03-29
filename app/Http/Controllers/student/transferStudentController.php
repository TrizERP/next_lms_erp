<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class transferStudentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/transfer_student", $res, "view");
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
     * @return void
     */
    public function store(Request $request)
    {
        //
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

    public function searchStudent(Request $request)
    {
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $next_syear = $syear + 1;
        $type = $request->input('type');

        $extraSearchArray = [];
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.status'] = 1;
        if ($grade_id != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade_id;
        }
        if ($standard_id != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard_id;
        }
        if ($division_id != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division_id;
        }

        $student_data = tblstudentModel::select('tblstudent.id as student_id', 'enrollment_no',
            'academic_section.title as grade',
            'standard.name as standard_name', 'division.name as division_name', 'gender')
            ->selectRaw("Concat_ws(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) as student_name")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->leftjoin("tblstudent_enrollment as se", function ($join) use ($next_syear) {
                $join->on("se.syear", "=", DB::raw("'".$next_syear."'"))
                    ->on("tblstudent.id", "=", "se.student_id");
            })
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->where($extraSearchArray)
            ->whereRaw('tblstudent_enrollment.end_date is NULL and se.standard_id is NULL')
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Student List";
        $res['student_data'] = $student_data;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;

        return is_mobile($type, "student/transfer_student", $res, "view");
    }

    public function transferStudent(Request $request)
    {

        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade_id = $request->input("hid_gradeid");
        $standard_id = $request->input("hid_standardid");
        $division_id = $request->input("hid_divisionid");
        $stud_ids = $request->input("stud_ids");
        $type = $request->input('type');

        $standard_data = DB::select("select a.rollover_id,b.grade_id from (
		select rollover_id from standard where sub_institute_id = '".$sub_institute_id."' and id = '".$standard_id."') as  a
		inner join standard as b on b.id = a.rollover_id");

        $next_standard_id = $standard_data[0]->rollover_id;
        $next_grade_id = $standard_data[0]->grade_id;
        $next_syear = $syear + 1;

        if ($next_standard_id != "" && $next_grade_id != "") {
            foreach ($stud_ids as $key => $student_id) {
                DB::select("INSERT INTO tblstudent_enrollment
				(syear,student_id,grade_id,standard_id,section_id,student_quota,start_date,end_date,enrollment_code,
				    drop_code,drop_remarks,term_id,remarks,admission_fees,house_id,lc_number,sub_institute_id)
				SELECT '".$next_syear."' AS syear,student_id,'".$next_grade_id."' AS grade_id,'".$next_standard_id."' AS standard_id,
                    section_id,student_quota,start_date,end_date,enrollment_code,
    				drop_code,drop_remarks,term_id,remarks,admission_fees,house_id,lc_number,sub_institute_id
				FROM tblstudent_enrollment
				WHERE sub_institute_id = '".$sub_institute_id."' AND student_id = '".$student_id."'");
            }
        }

        $res['status_code'] = 1;
        $res['message'] = "Student transfered successfully.";

        return is_mobile($type, "transfer_student.index", $res);

    }
}
