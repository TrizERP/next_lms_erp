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
        $marking_period_id = session()->get('term_id');
        $date = "2019-08-22";

        $query = DB::table('tblstudent as s')
        ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
            $join->on('s.id', '=', 'se.student_id')
                ->where('se.syear', '=', $syear);
        })
        ->join('academic_section as acs', 'acs.id', '=', 'se.grade_id')
        ->join('standard as sm', function($join) use($marking_period_id) {
            $join->on('se.standard_id', '=', 'sm.id');
            // ->when($marking_period_id,function($query) use($marking_period_id){
            //     $query->where('sm.marking_period_id',$marking_period_id);
            // } );
        })
        ->join('division as dm', 'se.section_id', '=', 'dm.id')
        ->join('fees_breackoff as fb', function ($join) use ($syear) {
            $join->on('fb.syear', '=', 'se.syear')
                ->on('fb.admission_year', '=', 's.admission_year')
                ->on('fb.quota', '=', 'se.student_quota')
                ->on('fb.grade_id', '=', 'se.grade_id')
                ->on('fb.standard_id', '=', 'se.standard_id');
        })
        ->leftJoin(DB::raw('(SELECT if(ifnull(sum(fp.amount),0)="",0,ifnull(sum(fp.amount),0)) as tot_paid, se.grade_id, se.standard_id, se.section_id
                    FROM fees_collect as fp
                    INNER JOIN tblstudent_enrollment as se ON fp.student_id = se.student_id AND se.syear = "' . $syear . '"
                    GROUP BY se.grade_id, se.standard_id, se.section_id) as fp'), function ($join) {
            $join->on('fp.grade_id', '=', 'se.grade_id')
                ->on('fp.standard_id', '=', 'se.standard_id')
                ->on('fp.section_id', '=', 'se.section_id');
        })
        ->where('s.sub_institute_id', '=', $sub_institute_id)
        ->groupBy('se.grade_id', 'se.standard_id', 'se.section_id')
        ->select('acs.title', 'sm.name as standard_name', 'dm.name as division_name', 'se.standard_id', 'se.section_id', DB::raw('count(se.student_id) as tot_amount'), DB::raw('sum(fb.amount) as tot_amount'), 'fp.tot_paid');
    
    $data = $query->get();
    
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
