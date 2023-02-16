<?php

namespace App\Http\Controllers\easy_com\send_notification_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\sendNotification;
use function App\Helpers\send_FCM_Notification;
use App\Models\school_setup\SchoolModel;
use function App\Helpers\is_mobile;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class register_parents_report_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    use GetsJwtToken;
    
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";

        return is_mobile($type, "easy_comm/send_notification_report/show_register_parents_report", $res , "view");
    }

    //13.46
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        $type = $request->input("type");
        $mobile_no = $request->input('mobile_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $extraSearchArrayRaw = " 1=1 ";

        if($mobile_no != '')
        {
            $extraSearchArrayRaw .= "  AND s.mobile = ".$mobile_no;            
        }

        if($from_date != '')
        {
            $extraSearchArrayRaw .= "  AND gu.created_on >= '".$from_date."'";
        }

        if($to_date != '')
        {
            $extraSearchArrayRaw .= "  AND gu.created_on <= '".$to_date."'";
        }

        $sql = "SELECT s.id AS student_id,
                CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS stu_name, 
                ss.name AS std_name,dd.name AS div_name,aa.title as aca_sec,gu.imei_no,
                gu.curr_version,gu.new_version,
                gu.mobile_no, DATE_FORMAT(gu.created_on,'%d-%m-%Y %r') AS CREATED_ON,s.enrollment_no
                FROM gcm_users gu
                INNER JOIN tblstudent s ON s.mobile=gu.mobile_no
                INNER JOIN tblstudent_enrollment se ON se.student_id=s.id
                INNER JOIN standard ss ON ss.id = se.standard_id
                INNER JOIN academic_section aa ON aa.id=ss.grade_id
                LEFT JOIN division dd ON dd.id=se.section_id
                WHERE $extraSearchArrayRaw AND se.SYEAR='".$syear."' AND gu.sub_institute_id = '".$sub_institute_id."' ";

        $data = DB::select($sql);
        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        // dd($fees_data);
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['mobile_no'] = $mobile_no;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        return is_mobile($type, "easy_comm/send_notification_report/show_register_parents_report", $res , "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
