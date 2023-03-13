<?php

namespace App\Http\Controllers\student\fees_graph;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class student_fees_graphController extends Controller
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

        $date = "2019-08-22";
        // return is_mobile($type, "student/fees_graph/view", $res, "view");


        // SUM(CASE WHEN s.gender = 'M' THEN 1 ELSE 0 END) AS BOY,
        // SUM(CASE WHEN s.gender = 'F' THEN 1 ELSE 0 END) AS GIRL,
        // SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'P' THEN 1 ELSE 0 END) TBP,
        // SUM(CASE WHEN s.gender = 'F' AND a.attendance_code = 'P' THEN 1 ELSE 0 END) TGP,
        // SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TBA,
        // SUM(CASE WHEN s.gender = 'F' AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TGA
        $query = "SELECT acs.title,sm.name AS standard_name, 
        dm.name AS division_name,
        se.standard_id,se.section_id,count(se.student_id) tot_amount,
        sum(fb.amount) tot_amount,
        tot_paid
		FROM tblstudent s
		INNER JOIN tblstudent_enrollment se ON s.id = se.student_id AND se.syear = '".$syear."'
        INNER JOIN academic_section acs on acs.id = se.grade_id
		INNER JOIN standard sm ON se.standard_id = sm.id
		INNER JOIN division dm ON se.section_id = dm.id
        INNER JOIN fees_breackoff fb ON (  
            fb.syear = se.syear AND
            fb.admission_year = s.admission_year AND
            fb.quota = se.student_quota AND
            fb.grade_id = se.grade_id AND
            fb.standard_id = se.standard_id
        )
        LEFT JOIN (
            select if(ifnull(sum(fp.amount),0)='',0,ifnull(sum(fp.amount),0)) tot_paid,se.grade_id,se.standard_id,se.section_id
            from fees_collect fp
            INNER JOIN tblstudent_enrollment se ON fp.student_id = se.student_id AND se.syear = '".$syear."'
            group by se.grade_id,se.standard_id,se.section_id
        ) fp ON (
            fp.grade_id = se.grade_id AND
            fp.standard_id = se.standard_id AND
            fp.section_id = se.section_id 
        )
		WHERE s.sub_institute_id = '".$sub_institute_id."'
        GROUP BY se.grade_id,se.standard_id,se.section_id";
        $query = trim(preg_replace('/\s\s+/', ' ', $query));

        $data = DB::select($query);

        foreach ($data as $id => $arr) {
            if (
                $arr->tot_paid == '' ||
                $arr->tot_paid == ' ' ||
                $arr->tot_paid == null
            ) {
                $data[$id]->tot_paid = 0;
            }
        }

        $chart_data = "[{
            id: '0.0',
            parent: '',
            name: 'Fees Chart'
        },";

        $grades = [];
        foreach ($data as $id => $arr) {
            if (! in_array($arr->title, $grades)) {
                $grades[] = $arr->title;
                $chart_data .= "{";
                $chart_data .= "id: "."'1.".count($grades)."',";
                $chart_data .= "parent: '0.0',";
                $chart_data .= "name: "."'".$arr->title."'";
                $chart_data .= "},";
            }
        }
        // $chart_data = rtrim($chart_data, ",");


        $i = 1;
        $standards = array();
        foreach ($grades as $id => $val) {
            foreach ($data as $key => $arr) {
                if ($arr->title == $val) {
                    if (! in_array($arr->standard_name, $standards)) {
                        $standards[] = $arr->standard_name;
                        $chart_data .= "{";
                        $chart_data .= "id: "."'2.".count($standards)."',";
                        $chart_data .= "parent: '1.".$i."',";
                        $chart_data .= "name: "."'".$arr->standard_name."'";
                        $chart_data .= "},";
                    }
                }
            }
            $i++;
        }

        $divisioin = [];
        $temp = 0;
        foreach ($standards as $id => $val) {
            foreach ($data as $key => $arr) {
                if ($arr->standard_name == $val) {
                    // if (!in_array($arr->division_name, $divisioin)) {
                    $divisioin[] = $arr->division_name;
                    $chart_data .= "{";
                    $chart_data .= "id: "."'3.".count($divisioin)."',";
                    $chart_data .= "parent: '2.".($id + 1)."',";
                    $chart_data .= "name: "."'".$arr->division_name."',";
                    $chart_data .= "value: ".$arr->tot_amount;
                    $chart_data .= "},";

                    // if ($arr->tot_paid != 0) {
                    $temp++;
                    $chart_data .= "{";
                    $chart_data .= "id: "."'4.".$temp."',";
                    $chart_data .= "parent: '3.".count($divisioin)."',";
                    $chart_data .= "name: 'Paid',";
                    $chart_data .= "value: ".$arr->tot_paid;
                    $chart_data .= "},";

                    $temp++;
                    $chart_data .= "{";
                    $chart_data .= "id: "."'4.".$temp."',";
                    $chart_data .= "parent: '3.".count($divisioin)."',";
                    $chart_data .= "name: 'UnPaid',";
                    $chart_data .= "value: ".($arr->tot_amount - $arr->tot_paid);
                    $chart_data .= "},";

                    // }else{

                    // }
                }
            }
            // $i++;
        }


        $chart_data = rtrim($chart_data, ",");
        $chart_data .= "];";

        $res['chartData'] = $chart_data;

        return is_mobile($type, "student/fees_graph/view", $res, "view");
    }
}
