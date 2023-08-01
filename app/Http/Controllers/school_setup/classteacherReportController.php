<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\classteacherModel;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class classteacherReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status'] = "1";
        $res['message'] = "Success";

        return is_mobile($type, 'school_setup/show_classteacherReport', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        return classteacherModel::from("class_teacher as ct")
            ->select('ct.*', 'a.title as academic_section_name', 's.name as standard_name', 'd.name as division_name',
                DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as teacher_name')
            )
            ->join('academic_section as a', 'a.id', '=', 'ct.grade_id')
            ->join('standard as s', function($join) use($marking_period_id){
                $join->on('s.id', '=', 'ct.standard_id')->when($marking_period_id,function($query) use($marking_period_id){
                    $query->where('s.marking_period_id',$marking_period_id);
                });
            })
            ->join('division as d', 'd.id', '=', 'ct.division_id')
            ->join('tbluser as u', 'u.id', '=', 'ct.teacher_id')
            ->where(['ct.sub_institute_id' => $sub_institute_id])
            ->get();
    }

    public function getusers(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return tbluserModel::select('tbluser.*',
            DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher'])
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {

        $data = $this->getData($request);
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $teacher_id = $request->input('teacher_id');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        $extraSearchArray = [];
        $extraSearchArrayRaw = " 1=1 ";

        if ($grade != '') {
            $extraSearchArray['ct.grade_id'] = $grade;
        }

        if ($standard != '') {
            $extraSearchArray['ct.standard_id'] = $standard;
        }

        if ($division != '') {
            $extraSearchArray['ct.division_id'] = $division;
        }

        if ($teacher_id != '') {
            $extraSearchArrayRaw .= "  AND ct.teacher_id = ".$teacher_id;
        }

        $extraSearchArray['ct.syear'] = $syear;
        $extraSearchArray['ct.sub_institute_id'] = $sub_institute_id;

        $data = classteacherModel::from("class_teacher as ct")
            ->select('ct.*', 'a.title as academic_section_name', 's.name as standard_name', 'd.name as division_name',
                DB::raw('concat(u.first_name," ",u.middle_name," ",u.last_name) as teacher_name')
            )
            ->join('academic_section as a', 'a.id', '=', 'ct.grade_id')
            ->join('standard as s', function($join) use($marking_period_id){
                $join->on('s.id', '=', 'ct.standard_id')->when($marking_period_id,function($query) use($marking_period_id){
                    $query->where('s.marking_period_id',$marking_period_id);
                });
            })
            ->join('division as d', 'd.id', '=', 'ct.division_id')
            ->join('tbluser as u', 'u.id', '=', 'ct.teacher_id')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->get()
            ->toArray();

        $res['status'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['teacher_id'] = $teacher_id;
        $res['teacher_data'] = $this->getusers($request);

        return is_mobile($type, "school_setup/show_classteacherReport", $res, "view");

    }
}
