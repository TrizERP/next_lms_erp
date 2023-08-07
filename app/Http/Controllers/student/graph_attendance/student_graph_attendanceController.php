<?php

namespace App\Http\Controllers\student\graph_attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class student_graph_attendanceController extends Controller
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

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $syear = $request->session()->get('syear');
        $term_id = $request->session()->get('term_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');
        $date = "2019-08-22";


        // SUM(CASE WHEN s.gender = 'M' THEN 1 ELSE 0 END) AS BOY,
        // SUM(CASE WHEN s.gender = 'F' THEN 1 ELSE 0 END) AS GIRL,
        // SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'P' THEN 1 ELSE 0 END) TBP,
        // SUM(CASE WHEN s.gender = 'F' AND a.attendance_code = 'P' THEN 1 ELSE 0 END) TGP,
        // SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TBA,
        // SUM(CASE WHEN s.gender = 'F' AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TGA

        $data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->whereRaw("s.id = se.student_id AND se.syear = '".$syear."'");
            })->join('academic_section as acs', function ($join) {
                $join->whereRaw("acs.id = se.grade_id");
            })->join('standard as sm', function ($join) use($marking_period_id) {
                $join->whereRaw("se.standard_id = sm.id");
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('sm.marking_period_id',$marking_period_id);
                // });
            })->join('division as dm', function ($join) {
                $join->whereRaw("se.section_id = dm.id");
            })->leftJoin('attendance_student as a', function ($join) use ($date) {
                $join->whereRaw("a.student_id = s.id and a.attendance_date = '" . $date . "'");
            })
            ->selectRaw("acs.title,sm.name AS standard_name,dm.name AS division_name,
                se.standard_id,se.section_id,count(se.student_id) total_student,
                SUM(CASE WHEN a.attendance_code = 'A' THEN 1 ELSE 0 END) TA,
                SUM(CASE WHEN a.attendance_code = 'P' THEN 1 ELSE 0 END) TP")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->groupByRaw('se.grade_id,se.standard_id,se.section_id')
            ->get()->toArray();

        $chart_data = "[{
            id: '0.0',
            parent: '',
            name: 'Attendance Chart'
        },";

        $grades = [];
        foreach ($data as $id => $arr) {
            if (! in_array($arr->title, $grades)) {
                $grades[] = $arr->title;
                $chart_data .= "{";
                $chart_data .= "id: "."'1." . count($grades)."',";
                $chart_data .= "parent: '0.0',";
                $chart_data .= "name: "."'".$arr->title."'";
                $chart_data .= "},";
            }
        }
        // $chart_data = rtrim($chart_data, ",");


        $i = 1;
        $standards = [];
        foreach ($grades as $id=>$val) {
            foreach ($data as $key=>$arr) {
                if ($arr->title == $val) {
                    if (!in_array($arr->standard_name, $standards)) {
                        $standards[] = $arr->standard_name;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'2." . count($standards)."',";
                        $chart_data .= "parent: '1.".$i."',";
                        $chart_data .= "name: "."'".$arr->standard_name."'";
                        $chart_data .= "},";
                    }
                }
            }
            $i++;
        }

        // $i = 1;
        $divisioin = [];
        $temp = 0;
        foreach ($standards as $id=>$val) {
            foreach ($data as $key=>$arr) {
                if ($arr->standard_name == $val) {
                    // if (!in_array($arr->division_name, $divisioin)) {
                    $divisioin[] = $arr->division_name;
                    $chart_data .= "{";
                    $chart_data .= "id: "."'3." . count($divisioin)."',";
                    $chart_data .= "parent: '2.".($id+1)."',";
                    $chart_data .= "name: "."'".$arr->division_name."',";
                    $chart_data .= "value: ".$arr->total_student;
                    $chart_data .= "},";

                    if ($arr->TA != 0 || $arr->TP != 0) {
                        $temp++;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'4." . $temp."',";
                        $chart_data .= "parent: '3.".count($divisioin)."',";
                        $chart_data .= "name: 'Present',";
                        $chart_data .= "value: ".$arr->TP;
                        $chart_data .= "},";
                        $temp++;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'4." . $temp."',";
                        $chart_data .= "parent: '3.".count($divisioin)."',";
                        $chart_data .= "name: 'Absent',";
                        $chart_data .= "value: ".$arr->TA;
                        $chart_data .= "},";
                    }
                }
            }
            // $i++;
        }

        $chart_data = rtrim($chart_data, ",");
        $chart_data .= "];";

        $res['chartData'] = $chart_data;

        return is_mobile($type, "student/graph_attendance/view", $res, "view");
    }
}
