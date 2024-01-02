<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\periodModel;
use App\Models\school_setup\timetableModel;
use App\Models\user\tbluserModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class facultywisetimetableController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $user_data = $this->getusers($request);
        $res['teacher_data'] = $user_data;

        return is_mobile($type, 'school_setup/show_facultywisetimetable', $res, "view");
    }

    public function getFacultywiseTimetable(Request $request)
    {
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $res = $this->getTimetable_data($request, $teacher_id, $sub_institute_id, $syear);

        return is_mobile($type, 'school_setup/show_facultywisetimetable', $res, "view");
    }

    public function getTimetable_data(Request $request, $teacher_id, $sub_institute_id, $syear)
    {
        $html = "";
        $marking_priod_id=session()->get('term_id');
        $get_teacher_name = DB::table('tbluser')
            ->selectRaw("id,CONCAT_WS(' ',first_name,middle_name,last_name) as teacher_name")
            ->where('id', $teacher_id)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $timetable_data_arr = timetableModel::select('timetable.*',
            'subject.subject_name', 'subject.subject_code', 'batch.title as batch_name', 'period.title as period_name',
            'standard.name as standard_name', 'division.name as division_name')
            ->join('standard',function($join) use($marking_priod_id){
                $join->on('standard.id', "=", 'timetable.standard_id');
                // ->when($marking_priod_id,function($query) use($marking_priod_id){
                //     $query->where('standard.marking_period_id',$marking_priod_id);
                // });
            })
            ->join('subject', 'subject.id', "=", 'timetable.subject_id')
            ->leftjoin('division', 'division.id', "=", 'timetable.division_id')
            ->join('period', 'period.id', "=", 'timetable.period_id')
            ->leftJoin('batch', 'batch.id', "=", 'timetable.batch_id')
            ->where([
                'timetable.sub_institute_id' => $sub_institute_id,
                'timetable.teacher_id'       => $teacher_id,
                'timetable.syear'            => $syear,
            ])
            ->orderby('period.sort_order')
            ->get()->toArray();

        foreach ($timetable_data_arr as $k => $p) {
            $period_data[$p['period_id']]["title"] = $p['period_name'];
            $period_data[$p['period_id']]["id"] = $p['period_id'];
            $timetable_data[$p['week_day']][$p['period_id']]['SUBJECT'][] = $p['subject_name'].' / '.$p['subject_code'];
            $timetable_data[$p['week_day']][$p['period_id']]['STANDARD'][] = $p['standard_name'].' / '.$p['division_name'];;
            if (isset($p['batch_name'])) {
                $timetable_data[$p['week_day']][$p['period_id']]['BATCH'][] = $p['batch_name'];
            }
        }

        $result = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('syear', session()->get('syear'))
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->groupByRaw("receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number")
            ->get()->toArray();

        $receipt_book_arr = array();
        foreach ($result as $temp_id => $receipt_detail) {
            $receipt_book_arr = $receipt_detail;
        }
if(isset($receipt_book_arr->receipt_logo) && $receipt_book_arr->receipt_logo!=''){
        $image_path = "http://".$_SERVER['HTTP_HOST']."/storage/fees/".$receipt_book_arr->receipt_logo;
}
else{
    $image_path = '';
}
        $period_data = periodModel::select('period.*', DB::raw('date_format(period.start_time,"%H:%i") as s_time,
            date_format(period.end_time,"%H:%i") as e_time'))
            ->where(['sub_institute_id' => $sub_institute_id])//, 'academic_section_id' => $academic_section_id
            ->orderby('sort_order')
            ->get()
            ->toArray();

        $week_data = $this->getweeks();


        $html = '<table style="margin:0 auto;" width="80%">
                        <tbody>
                            <tr>
                                <td style=" width: 165px;text-align: center;" align="left">';
        // $html .= '    <img style="width: 100px;height: 90px;margin: 0;" src="' . $image_path . '" alt="SCHOOL LOGO">';
        $html .= '</td>';
        $html .= '<td colspan="3" style="text-align:center !important;" align="center"> ';
        if (isset($receipt_book_arr->receipt_line_1) && $receipt_book_arr->receipt_line_1 != '') {
            $html .= '<span style=" font-size: 26px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important;">'.$receipt_book_arr->receipt_line_1.'</span><br>';
        }
        if (isset($receipt_book_arr->receipt_line_2) && $receipt_book_arr->receipt_line_2 != '') {
            $html .= '<span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">'.$receipt_book_arr->receipt_line_2.'</span><br>';
        }
        if (isset($receipt_book_arr->receipt_line_3) && $receipt_book_arr->receipt_line_3 != '') {
            $html .= '<span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">'.$receipt_book_arr->receipt_line_3.'</span><br>';
        }
        if (isset($receipt_book_arr->receipt_line_4) && $receipt_book_arr->receipt_line_4 != '') {
            $html .= '<span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important;">'.$receipt_book_arr->receipt_line_4.'</span><br>';
        }
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '<tr><td>&nbsp;</td></tr>';
        $html .= '<tr><td>&nbsp;</td><td style="text-align:center !important;" align="center"><span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">Teacher Name : ' . $get_teacher_name[0]->teacher_name . '</span><br></td><td>&nbsp;</td></tr>';
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '<br>';

        $html .= "<table class='table table-bordered table-center' border=1>";
        $week_data = $this->getweeks();
        if (!empty($timetable_data) && count($timetable_data) > 0) {
            $html .= "<tr>
                <td style='display: table-cell;width:30px;'><span class='label label-info'>Days - Lectures</span></td>";
            foreach ($period_data as $pkey => $pval) {
                $html .= "<td style='display: table-cell;' align='center'><span class='label label-info'>" . $pval['title'] . "</span>";
                $html .= "<br>";
                $html .= "( " . $pval['s_time'] . "-" . $pval['e_time'] . " )" . "</span></td>";
            }
            $html .= "</tr>";
            foreach ($week_data as $wkey => $wval) {
                $html .= "<tr>";
                $html .= "<td style='display: table-cell;'><span class='label label-warning'>" . $wkey . "</span></td>";
                foreach ($period_data as $pkey => $pval) {
                    $html .= "<td align='center' style='font-size:10px;color: black;'>";
                    if (isset($timetable_data[$wval][$pval['id']]['SUBJECT'])) {

                        if (count($timetable_data[$wval][$pval['id']]['SUBJECT']) > 0) {
                            foreach ($timetable_data[$wval][$pval['id']]['SUBJECT'] as $k => $v) {
                                $subject_name = $timetable_data[$wval][$pval['id']]['SUBJECT'][$k];
                                $batch_name = isset($timetable_data[$wval][$pval['id']]['BATCH'][$k]) ? " / ".$timetable_data[$wval][$pval['id']]['BATCH'][$k] : "";

                                $html .= $subject_name.$batch_name;
                                if (isset($timetable_data[$wval][$pval['id']]['STANDARD'][$k])) {
                                    $html .= "<br>".$timetable_data[$wval][$pval['id']]['STANDARD'][$k];
                                }
                                if ($k != (count($timetable_data[$wval][$pval['id']]['SUBJECT']) - 1)) {
                                    $html .= "<br><hr>";
                                }
                            }
                        } else {
                            $html .= $timetable_data[$wval][$pval['id']]['SUBJECT'][0].'-'.$timetable_data[$wval][$pval['id']]['SUBJECT_CODE'][0];
                        }
                    } else {
                        $html .= "<font color='red' style='font-size:10px;'>--No Period--</font>";
                    }
                    $html .= "</td>";
                }
                $html .= "</tr>";
            }
        } else {
            $html .= "<tr><td align='center' style='text-align: center;'>No Records Found!</td></tr>";
        }
        $html .= "</table>";

        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['HTML'] = $html;
        $res['teacher_id'] = $teacher_id;
        $user_data = $this->getusers($request);
        $res['teacher_data'] = $user_data;

        return $res;
    }

    public function getweeks()
    {
        $week_days = array(
            "Monday"   => "M", "Tuesday" => "T", "Wednesday" => "W", "Thursday" => "H", "Friday" => "F",
            "Saturday" => "S",
        );

        return $week_days;
    }

    public function getusers(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return tbluserModel::select('tbluser.*',
            DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher', 'tbluser.status' => 1])
            ->orderby('tbluser.first_name')
            ->get();
    }

    public function teacherTimetableAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $marking_priod_id = session()->get('term_id');
        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('timetable as t')
                ->join('standard as s', function ($join) use($marking_priod_id){
                    $join->whereRaw('s.id = t.standard_id and s.sub_institute_id = t.sub_institute_id');
                    // ->when($marking_priod_id,function($query) use($marking_priod_id){
                    //     $query->where('s.marking_period_id',$marking_priod_id);
                    // });
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = t.division_id and d.sub_institute_id = t.sub_institute_id');
                })->join('subject as sub', function ($join) {
                    $join->whereRaw('sub.id = t.subject_id and sub.sub_institute_id = t.sub_institute_id');
                })->join('period as p', function ($join) {
                    $join->whereRaw('p.id = t.period_id');
                })->selectRaw("t.week_day,GROUP_CONCAT(CONCAT_WS('/',s.name,d.name,sub.subject_name)) as lectures,
						p.title as periodname")
                ->where('t.teacher_id', $teacher_id)
                ->where('t.syear', $syear)
                ->where('t.sub_institute_id', $sub_institute_id)
                ->groupByRaw('week_day,t.period_id')->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
