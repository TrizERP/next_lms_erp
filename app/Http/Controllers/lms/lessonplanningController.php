<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\lessonplanning_executionModel;
use App\Models\school_setup\lessonplanningModel;
use App\Models\school_setup\subjectModel;
use App\Models\school_setup\timetableModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lessonplanningController extends Controller
{
    public function index(Request $request)
    {
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_profile_name = $request->session()->get('user_profile_name');

        $user_id = $request->session()->get('user_id');
        if ($user_profile_name != 'Admin') {
            $res['editable'] = "true";
        } else {
            $res['editable'] = "false";
        }
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $calendarData = array();
        if (count($data) > 0) {
            foreach ($data as $key => $val) {
                $calendarData[] = [
                    'id'                => $val['id'],
                    'title'             => $val['title'],
                    'description'       => $val['description'],
                    'standard_id'       => $val['standard_id'],
                    'standard_name'     => $val['standard_name'],
                    'division_id'       => $val['division_id'],
                    'division_name'     => $val['division_name'],
                    'subject_id'        => $val['subject_id'],
                    'subject_name'      => $val['subject_name'],
                    'start'             => $val['school_date'],
                    'teacher_name'      => $val['teacher_name'],
                    'lessonplan_date'   => $val['lessonplan_date'],
                    'lessonplan_status' => $val['lessonplan_status'],
                    'lessonplan_reason' => $val['lessonplan_reason'],
                    'lessonplan_id'     => $val['lessonplan_id'],
                    'className'         => 'bg-info',
                ];
            }
        }
        $calendarData = json_encode($calendarData, true);
        $res['calendarData'] = $calendarData;
        $res['standard_data'] = json_encode($this->getStandardData($request), true);
        $res['subject_data'] = json_encode($this->getSubjectData($request), true);
        $res['division_data'] = json_encode($this->getDivisionData($request), true);

        return is_mobile($type, 'school_setup/show_lessonplanning', $res, "view");
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_id = $request->session()->get('user_id');
        $syear = $request->session()->get('syear');

        $finalArray[] = [
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'standard_id'      => $request->get('standard_id'),
            'subject_id'       => $request->get('subject_id'),
            'school_date'      => date("Y-m-d", $request->get('school_date') / 1000),
            'division_id'      => $request->get('division_id'),
            'grade_id'         => '1',
            'user_group_id'    => $user_profile_id,
            'teacher_id'       => $user_id,
            'syear'            => $syear,
            'sub_institute_id' => $sub_institute_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
        lessonplanningModel::insert($finalArray);
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_id = $request->session()->get('user_id');
        $syear = $request->session()->get('syear');

        $finalArray = [
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'standard_id'      => $request->get('standard_id'),
            'subject_id'       => $request->get('subject_id'),
            'school_date'      => date("Y-m-d", $request->get('school_date') / 1000),
            'division_id'      => $request->get('division_id'),
            'grade_id'         => '1',
            'user_group_id'    => $user_profile_id,
            'teacher_id'       => $user_id,
            'syear'            => $syear,
            'sub_institute_id' => $sub_institute_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
        lessonplanningModel::where(["id" => $id])->update($finalArray);

        if ($request->get('lessonplan_reason') != "" && $request->get('lessonplan_status') != "" &&
            $request->get('lessonplan_date') != "") {
            $finalArray_Exec = [
                'syear'             => $syear,
                'sub_institute_id'  => $sub_institute_id,
                'user_group_id'     => $user_profile_id,
                'school_date'       => $request->get('lessonplan_date'),
                'standard_id'       => $request->get('standard_id'),
                'division_id'       => $request->get('division_id'),
                'subject_id'        => $request->get('subject_id'),
                'teacher_id'        => $user_id,
                'lessonplan_id'     => $id,
                'lessonplan_status' => $request->get('lessonplan_status'),
                'lessonplan_reason' => $request->get('lessonplan_reason'),
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
            if ($request->get('lessonplan_id') != "" || $request->get('lessonplan_id') != null) {
                lessonplanning_executionModel::where(["id" => $request->get('lessonplan_id')])->update($finalArray_Exec);
            } else {
                lessonplanning_executionModel::insert($finalArray_Exec);
            }
        }
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        lessonplanningModel::where(["id" => $id])->delete();
        lessonplanning_executionModel::where(["lessonplan_id" => $id])->delete();
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_profile_name = $request->session()->get('user_profile_name');
        $user_id = $request->session()->get('user_id');
        $extra = ['lp.sub_institute_id' => $sub_institute_id, 'lp.syear' => $syear];
        if ($user_profile_name != 'Admin') {
            $extra['lp.teacher_id'] = $user_id;
        }

        return lessonplanningModel::from("lessonplan as lp")
            ->select('lp.*', 's.name as standard_name', 'd.name as division_name', 'sub.subject_name',
                'l.lessonplan_reason', 'l.school_date as lessonplan_date', 'l.lessonplan_status',
                'l.id as lessonplan_id',
                DB::raw('concat(t.first_name," ",t.middle_name," ",t.last_name) as teacher_name'))
            ->join('standard as s', 's.id', '=', 'lp.standard_id')
            ->join('division as d', 'd.id', '=', 'lp.division_id')
            ->join('subject as sub', 'sub.id', '=', 'lp.subject_id')
            ->join('tbluser as t', 't.id', '=', 'lp.teacher_id')
            ->leftjoin('lessonplan_execution as l', 'l.lessonplan_id', '=', 'lp.id')
            ->where($extra)
            ->get()->toArray();
    }

    public function getStandardData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_profile_name = $request->session()->get('user_profile_name');
        $user_id = $request->session()->get('user_id');

        return timetableModel::from("timetable as t")
            ->select(DB::raw('distinct(t.standard_id) as std_id'), 's.name as std_name', 's.grade_id')
            ->join('standard as s', 's.id', '=', 't.standard_id')
            ->where(['t.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $user_id])
            ->get()->toArray();
    }

    public function getSubjectData(Request $request, $standard_id = "")
    {
        $standard_id = $request->get('standard_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_profile_name = $request->session()->get('user_profile_name');
        $user_id = $request->session()->get('user_id');

        $wherecondition = array('t.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $user_id);
        if ($standard_id != "") {
            $wherecondition['t.standard_id'] = $standard_id;
        }

        return subjectModel::from("timetable as t")
            ->select(DB::raw('distinct(t.subject_id) as sub_id'), 's.subject_name as sub_name')
            ->join('subject as s', 's.id', '=', 't.subject_id')
            ->where($wherecondition)
            ->get()->toArray();
    }

    public function getDivisionData(Request $request, $standard_id = "")
    {
        $standard_id = $request->get('standard_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_profile_name = $request->session()->get('user_profile_name');
        $user_id = $request->session()->get('user_id');

        $wherecondition = array('t.sub_institute_id' => $sub_institute_id, 't.teacher_id' => $user_id);
        if ($standard_id != "") {
            $wherecondition['t.standard_id'] = $standard_id;
        }

        return divisionModel::from("timetable as t")
            ->select(DB::raw('distinct(t.division_id) as div_id'), 'd.name as div_name')
            ->join('division as d', 'd.id', '=', 't.division_id')
            ->where($wherecondition)
            ->orderBy('d.name', 'asc')
            ->get()->toArray();
    }
}
