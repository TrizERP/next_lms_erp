<?php

namespace App\Http\Controllers\easy_com\send_notification_report;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class notification_report_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    use GetsJwtToken;

    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";

        return is_mobile($type, "easy_comm/send_notification_report/show_notification_report", $res, "view");
    }

    //13.46

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input("type");
        $mobile_no = $request->input('mobile_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        $data = DB::table('app_notification as an')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('s.id=an.STUDENT_ID');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id=s.id');
            })->join('standard as ss', function ($join) use($marking_period_id){
                $join->whereRaw('ss.id = se.standard_id')->when($marking_period_id,function($query) use($marking_period_id){
                    $query->where('marking_period_id',$marking_period_id);
                });
            })->join('academic_section as aa', function ($join) {
                $join->whereRaw('aa.id=ss.grade_id');
            })->join('gcm_users as gu', function ($join) {
                $join->whereRaw('gu.mobile_no = s.mobile');
            })->join('division as dd', function ($join) {
                $join->whereRaw('dd.id=se.section_id');
            })->selectRaw("s.id AS student_id,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS stu_name, 
                ss.name AS std_name,dd.name AS div_name,aa.title as aca_sec,gu.imei_no,gu.curr_version,gu.new_version,
                gu.mobile_no, DATE_FORMAT(an.CREATED_AT,'%d-%m-%Y %r') AS CREATED_ON,s.enrollment_no,an.NOTIFICATION_TYPE, 
                DATE_FORMAT(an.NOTIFICATION_DATE,'%d-%m-%Y') AS NOTOFICATION_DATE,an.NOTIFICATION_DESCRIPTION, 
                CASE WHEN an.Status = 1 THEN 'Read' WHEN an.Status =0 THEN 'Un-Read' ELSE 'N/A' END AS NOTIFICATION_STATUS")
            ->where('se.SYEAR', $syear)
            ->where('gu.sub_institute_id', $sub_institute_id)
            ->where('an.sub_institute_id', $sub_institute_id)
            ->where(function ($q) use ($mobile_no, $from_date, $to_date) {
                if ($mobile_no != '') {
                    $q->where('s.mobile', $mobile_no);
                }
                if ($from_date != '') {
                    $q->where('an.NOTIFICATION_DATE', '>=', $from_date);
                }

                if ($to_date != '') {
                    $q->where('an.NOTIFICATION_DATE', '<=', $to_date);
                }
            })->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['mobile_no'] = $mobile_no;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "easy_comm/send_notification_report/show_notification_report", $res, "view");
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
}
