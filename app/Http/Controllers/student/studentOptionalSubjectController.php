<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
//use Illuminate\Support\Facades\Session;

class studentOptionalSubjectController extends Controller
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

        return is_mobile($type, "student/show_student_optional", $res, "view");
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

    /**
     * @param  Request  $request
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @return false|Application|Factory|View|RedirectResponse|string|void
     */
    public function searchStudentOptionalSubject(Request $request)
    {
        // return $request;exit;
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');
        $user_profile_name = session()->get('user_profile_name');

        $last_name = $request->input('last_name');
        $first_name = $request->input('first_name');
        $mobile = $request->input('mobile');
        $gr_no = $request->input('gr_no');
        $including_inactive = $request->input('including_inactive');
        $unique_id = $request->input('unique_id');

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

        $extraRaw = " 1 = 1 ";

        if ($user_profile_name == 'Student') {
            $extraRaw .= " AND tblstudent.id = '".$user_id."' ";
        }

        if ($including_inactive != 'Yes') {
            $extraRaw .= " AND tblstudent_enrollment.end_date is NULL";
        }
        if ($including_inactive == 'Yes') {
            $inactive_colour = ' if(tblstudent_enrollment.end_date != "","pink","") as inactive_colour ';
        } else {
            $inactive_colour = ' "" as inactive_colour ';
        }
        if ($last_name != '') {
            $extraRaw .= " AND tblstudent.last_name LIKE '%$last_name%'";
        }
        if ($first_name != '') {
            $extraRaw .= " AND tblstudent.first_name LIKE '%$first_name%'";
        }
        if ($mobile != '') {
            $extraSearchArray['tblstudent.mobile'] = $mobile;
        }
        if ($gr_no != '') {
            $extraSearchArray['tblstudent.enrollment_no'] = $gr_no;
        }
        if ($unique_id != '') {
            $extraRaw .= " AND tblstudent.uniqueid = '".$unique_id."'";
        }


        //START Check for class teacher assigned standards

        $classTeacherStdArr = session()->get('classTeacherStdArr');
        if (isset($classTeacherStdArr)) {
            if (count($classTeacherStdArr) > 0) {
                $extraRaw = "standard.id IN (".implode(",", $classTeacherStdArr).")";
            } 
        }

        $classTeacherDivArr = session()->get('classTeacherDivArr');
        if (isset($classTeacherStdArr)) {
            if (count($classTeacherDivArr) > 0) {
                $extraRaw .= " and division.id IN (".implode(",", $classTeacherDivArr).")";
            }
        }

        //END Check for class teacher assigned standards
        // DB::enableQueryLog();		
        $student_data = tblstudentModel::select('tblstudent.*', 'tblstudent_enrollment.*', 'standard.name as standard', 'tblstudent.id as stu_id',
            'division.name as division',
            'academic_section.title as grade', DB::raw($inactive_colour))
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->where($extraSearchArray)
            ->whereRaw($extraRaw)
            ->get();
            // dd(DB::getQueryLog($student_data));

        $optional_subject_data = sub_std_mapModel::select('sub_std_map.*', 'subject.subject_name',
        'subject.subject_code')
        ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
        ->where([
            'sub_std_map.sub_institute_id' => $sub_institute_id,
            'sub_std_map.standard_id'      => $standard_id, 
            'sub_std_map.elective_subject' => 'Yes',
        ])
        ->get()->toArray();
        
        $res['status_code'] = 1;
        $res['message'] = "Student List";
        $res['data'] = $student_data;
        $res['optional_subject_data'] = $optional_subject_data;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile'] = $mobile;
        $res['gr_no'] = $gr_no;
        $res['unique_id'] = $unique_id;
        $res['including_inactive'] = $including_inactive;

        return is_mobile($type, "student/show_student_optional", $res, "view");
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $subjects = $request->input('subjects');
        $student_ids = $request->input('students');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');

        //$data = getStudents($student_ids);
        
        // Insert the data into the database
        foreach ($student_ids as $student_id) {
            foreach ($subjects as $subject) {
                // Check if the combination of subject_id and student_id already exists
                $data = DB::table('student_optional_subject')
                    ->where('subject_id', $subject)
                    ->where('student_id', $student_id)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('syear', $syear)
                    ->first();
    
                // If the record does not exist, insert it
                if (!$data) {
                    DB::table('student_optional_subject')->insert([
                        'subject_id' => $subject,
                        'student_id' => $student_id,
                        'sub_institute_id' => $sub_institute_id,
                        'syear' => $syear,
                    ]);
                }
            }
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        //$res['data'] = $data;
       /*  $res['template'] = $template;
        $res['str'] = $new_html;
        $res['insert_ids'] = $insert_ids;
        if ($certificate_reason != '') {
            $res['certificate_reason'] = $certificate_reason;
        } */

        return is_mobile($type, "student_optional_subject.index", $res);
        //return is_mobile($type, "student/show_student_optional", $res, "view");
    }

    public function searchStudentName(Request $request)
    {
        $searchValue = $request->input('value');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $extraSearchArray = [];
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent.status'] = 1;

        return tblstudentModel::selectRaw('CONCAT(tblstudent.enrollment_no, " / ",CONCAT_WS(" ",tblstudent.first_name,
            tblstudent.middle_name,tblstudent.last_name)) as student,tblstudent.id')
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->whereRaw('tblstudent.enrollment_no LIKE "%'.$searchValue.'%" OR CONCAT_WS(" ",tblstudent.first_name,
            tblstudent.middle_name,tblstudent.last_name) LIKE "%'.$searchValue.'%"')
            ->where($extraSearchArray)
            ->get()
            ->toArray();
    }

    public function searchStudentLastName(Request $request)
    {
        $searchValue = $request->input('value');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $extraSearchArray = [];
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent.status'] = 1;

        return tblstudentModel::selectRaw('last_name')
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->whereRaw('tblstudent.last_name LIKE "%'.$searchValue.'%"')
            ->where($extraSearchArray)
            ->groupby('tblstudent.last_name')
            ->get()
            ->toArray();
    }

    public function searchStudentFirstName(Request $request)
    {
        $searchValue = $request->input('value');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $extraSearchArray = [];
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent.status'] = 1;

        return tblstudentModel::selectRaw('first_name')
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->whereRaw('tblstudent.first_name LIKE "%'.$searchValue.'%"')
            ->where($extraSearchArray)
            ->groupby('tblstudent.first_name')
            ->get()
            ->toArray();
    }

    public function searchStudentId(Request $request)
    {
        $searchValue = $request->input('student_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $extraSearchArray = [];
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent.status'] = 1;

        return tblstudentModel::select('standard.name as standard', 'division.name as division',
            'academic_section.title as grade')
            ->selectRaw('tblstudent.enrollment_no,CONCAT_WS(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) 
            as student_name,tblstudent.id')
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->whereRaw('tblstudent.id IN ('.$searchValue.')')
            ->where($extraSearchArray)
            ->get()
            ->toArray();
    }

    public function addStudentSiblings(Request $request)
    {
        $student_id = $request->input('student_id');
        $sibling_id = $request->input('sibling_id');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $checkStudentSiblings = DB::table('tblstudent_siblings')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->whereRaw("FIND_IN_SET(".$student_id.",siblings_id)")
            ->get()
            ->toArray();
        $dataStudentSiblingsjson = json_encode($checkStudentSiblings, true);
        $dataStudentSiblings = json_decode($dataStudentSiblingsjson, true);


        if (count($checkStudentSiblings) > 0) {
            if ($type == 'Add') {
                $finalInsert['siblings_id'] = $dataStudentSiblings[0]['siblings_id'].",".$sibling_id;
            } else {
                $explodeSiblings = explode(',', $dataStudentSiblings[0]['siblings_id']);
                foreach ($explodeSiblings as $skey => $svalue) {
                    if ($sibling_id == $svalue) {
                        unset($explodeSiblings[$skey]);
                    }
                }
                $finalInsert['siblings_id'] = implode(",", $explodeSiblings);
            }

            $checkStudentSiblings = DB::table('tblstudent_siblings')
                ->where(['sub_institute_id' => $sub_institute_id])
                ->whereRaw("FIND_IN_SET(".$student_id.",siblings_id)")
                ->update(['siblings_id' => $finalInsert['siblings_id']]);

            //START Delete if only student id is there in sibling table
            $checklastrecord = DB::table('tblstudent_siblings')
                ->where(['sub_institute_id' => $sub_institute_id, 'siblings_id' => $student_id])
                ->get()
                ->toArray();
            if (count($checklastrecord) > 0) {
                DB::table('tblstudent_siblings')->where('sub_institute_id', $sub_institute_id)
                    ->where('siblings_id', $student_id)->delete();
            }

            //END Delete if only student id is there in sibling table
        } else {
            $finalInsert['siblings_id'] = $student_id.",".$sibling_id;
            $finalInsert['sub_institute_id'] = $sub_institute_id;
            $finalInsert['created_by'] = $user_id;
            DB::table('tblstudent_siblings')->insert($finalInsert);
        }
    }
}
