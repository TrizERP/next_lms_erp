<?php

namespace App\Http\Controllers\ptm;

use App\Http\Controllers\Controller;
use App\Models\ptm\ptmattenedstatusModel;
use App\Models\school_setup\standardModel;
use App\Models\user\tbluserModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class ptmattenedstatusController extends Controller
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
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $marking_period_id = session()->get('term_id');

        $standard = standardModel::select('id', 'name')
            ->where(['sub_institute_id' => $sub_institute_id])
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->get()->toArray();

        $user_data = tbluserModel::select('tbluser.*', DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",
                tbluser.last_name) as teacher_name'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher', 'tbluser.status' => 1])
            ->get();
        
        $res['standards'] = $standard;
        $res['users'] = $user_data;

        return is_mobile($type, "ptm/show_ptm_attened_status", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $grade = $_REQUEST['grade'];
        $standard = $_REQUEST['standard'];
        $division_id = $_REQUEST['division'];
        $date = $request->input('date');
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');

        $result = DB::table('ptm_time_slots_master as PTS')
            ->selectRaw("s.id AS CHECKBOX, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS STUDENT,
                        CONCAT_WS(' - ', cs.name, ss.name) AS std_div, s.mobile, PTS.title, 
                        DATE_FORMAT(PTS.ptm_date, '%d-%m-%Y') AS PTM_DATE, 
                        CONCAT_WS('-', PTS.from_time, PTS.to_time) AS TIME_SLOT, PTS.id as ptm_time_slot_id")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('PTS.standard_id', '=', 'se.standard_id')
                     ->on('PTS.division_id', '=', 'se.section_id')
                     ->on('PTS.sub_institute_id', '=', 'se.sub_institute_id')
                     ->on('PTS.syear', '=', 'se.syear');
            })
            ->join('standard as cs', 'cs.id', '=', 'se.standard_id')
            ->join('division as ss', 'ss.id', '=', 'se.section_id')
            ->join('tblstudent as s', 's.id', '=', 'se.student_id')
            ->where('PTS.standard_id', '=', $standard)
            ->where('PTS.division_id', '=', $division_id)
            ->where('PTS.sub_institute_id', '=', $sub_institute_id)
            ->where('PTS.syear', '=', $syear)
            ->whereRaw("DATE_FORMAT(PTS.ptm_date, '%Y-%m-%d') = ?", [$date])
            ->get()
            ->toArray();
        
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $result;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division_id;
        $res['date'] = $date;

        return is_mobile($type, "ptm/show_ptm_attened_status", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $students = $request->get('students');
        $type = $request->get('type');
        $date = $request->get('date');
        $standard_id = $request->get('standard_id');
        $attened_remarks = $request->input('attened_remarks');
        $attened_status = $request->input('attened_status');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $created_by = $request->session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];
        $stdDivs = $request->input('std_div');
        $mobiles = $request->input('mobile');
        $titles = $request->input('title');
        $ptmDates = $request->input('PTM_DATE');
        $timeSlots = $request->input('TIME_SLOT');
        $ptm_time_slot_id = $request->input('ptm_time_slot_id');
        $selectedStudents = array_keys($request->input('attened_status'));  // Get the IDs of selected students
      
        foreach ($students as $student_id) {
            // Check if the current student is in the selected students array
            if (in_array($student_id, $selectedStudents)) {
                $formattedDate = \Carbon\Carbon::createFromFormat('d-m-Y', $ptmDates[$student_id])->format('Y-m-d');
                DB::table('ptm_booking_master')->insert([
                    'DATE' => $formattedDate,
                    'TIME_SLOT_ID' => $ptm_time_slot_id[$student_id],
                    'CONFIRM_STATUS' => "CONFIRM",
                    'CREATED_ON' => now(),
                    'STUDENT_ID' => $student_id,
                    'SUB_INSTITUTE_ID' => $sub_institute_id,
                    'PTM_ATTENDED_REMARKS' => $request->input('attened_remarks')[$student_id],
                    'PTM_ATTENDED_STATUS' => $request->input('attened_status')[$student_id],
                    'PTM_ATTENDED_BY' => $created_by,
                    'PTM_ATTENDED_ENTRY_DATE' => date('Y-m-d'),
                    'PTM_ATTENDED_CREATED_IP' => $created_ip,
                ]);
            }
        }      

        $res['status_code'] = "1";
        $res['message'] = "Record Added Successfully";

        return is_mobile($type, "add_ptm_attened_status.index", $res);
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

    public function ptmBookAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $booking_id = $request->input("booking_id");
        $booking_status = $request->input("booking_status");
        $teacher_id = $request->input("teacher_id");
        $date = $request->input("date");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "" && $booking_id != "" && $teacher_id != "") {
            $data[] = [
                'CONFIRM_STATUS'   => 'Confirm',
                'TEACHER_ID'       => $teacher_id,
                'STUDENT_ID'       => $student_id,
                'TIME_SLOT_ID'     => $booking_id,
                'DATE'             => $date,
                'SUB_INSTITUTE_ID' => $sub_institute_id,
                'CREATED_ON'       => date('Y-m-d'),
            ];

            ptmattenedstatusModel::insert($data);

            $res['status_code'] = 1;
            $res['message'] = "Success";
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function ptmBookingStatusAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $time_slot_id = $request->input("time_slot_id");
        $marking_period_id = session()->get('term_id');

        if ($student_id != "" && $sub_institute_id != "" && $syear != "" && $time_slot_id != "") {

            $data = DB::table("ptm_booking_master as pb")
                ->join('ptm_time_slots_master as ps', function ($join) {
                    $join->whereRaw("ps.id= pb.TIME_SLOT_ID");
                })
                ->join('standard as cs', function ($join) use($marking_period_id) {
                    $join->whereRaw("cs.id = ps.standard_id");
                    // ->when($marking_period_id,function($query) use ($marking_period_id){
                    //     $query->where('cs.marking_period_id',$marking_period_id);
                    // });
                })
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.standard_id = cs.id");
                })
                ->join('tblstudent as s', function ($join) use ($syear) {
                    $join->whereRaw("s.id = se.student_id AND se.syear='".$syear."'");
                })
                ->selectRaw('pb.ID,pb.DATE,pb.TEACHER_ID,pb.TIME_SLOT_ID,pb.CONFIRM_STATUS,pb.STUDENT_ID,pb.CREATED_ON,
                    pb.SUB_INSTITUTE_ID,pb.PTM_ATTENDED_STATUS,pb.PTM_ATTENDED_REMARKS,pb.PTM_ATTENDED_ENTRY_DATE,
                    ps.from_time as FROM_TIME,ps.to_time as TO_TIME,ps.ptm_date as PTM_DATE')
                ->where("s.id", "=", $student_id)
                ->where("ps.id", "=", $time_slot_id)
                ->where("ps.sub_institute_id", "=", $sub_institute_id)
                ->groupBy('pb.ID')
                ->get()->toarray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function ptmBookingTimeAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $date = $request->input("date");
        $marking_period_id = session()->get('term_id');

        if ($student_id != "" && $sub_institute_id != "" && $syear != "" && $date != "") {
            $data = DB::table("ptm_time_slots_master as ps")
                ->join('standard as cs', function ($join) use($marking_period_id){
                    $join->whereRaw("cs.id = ps.standard_id");
                    // ->when($marking_period_id,function($query) use ($marking_period_id){
                    //     $query->where('cs.marking_period_id',$marking_period_id);
                    // });
                })
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.standard_id = cs.id");
                })
                ->join('tblstudent as s', function ($join) use ($syear) {
                    $join->whereRaw("s.id = se.student_id AND se.syear='".$syear."'");
                })
                ->join('ptm_booking_master as pt', function ($join) {
                    $join->whereRaw("pt.TIME_SLOT_ID= ps.id AND s.id=pt.STUDENT_ID");
                })
                ->selectRaw("ps.id,ps.syear,ps.sub_institute_id,ps.ptm_date,ps.standard_id,ps.title, DATE_FORMAT(ps.from_time,'%h:%i') 
                    AS from_time, DATE_FORMAT(ps.to_time,'%h:%i') AS to_time,ps.created_by,ps.created_on, IFNULL(pt.CONFIRM_STATUS,'Pending') 
                    AS CONFIRM_STATUS,pt.ID AS booking_id,pt.STUDENT_ID")
                ->where("s.id", "=", $student_id)
                ->where("ps.ptm_date", "=", $date)
                ->orderBy('ps.id')
                ->get()->toarray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function ptmTeacherListAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table("tblstudent_enrollment as se")
                ->join('timetable as cp', function ($join) {
                    $join->whereRaw("cp.standard_id = se.standard_id");
                })
                ->join('tbluser as s', function ($join) use ($sub_institute_id) {
                    $join->whereRaw("s.id= cp.teacher_id AND s.sub_institute_id = '".$sub_institute_id."'");
                })
                ->join('tbluserprofilemaster as tup', function ($join) {
                    $join->whereRaw("tup.id = s.user_profile_id AND tup.name = 'Teacher'");
                })
                ->selectRaw("s.id, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS teacher, s.sub_institute_id")
                ->where("se.student_id", "=", $student_id)
                ->where("se.syear", "=", $syear)
                ->groupBy('cp.teacher_id')
                ->get()->toarray();

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
