<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class erpstatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['status_code'] = 1;
        $data['message'] = "Success";

        $new_data = $this->getData($request);
        $data['erp_status'] = $new_data['erp_status'];
        $data['total_student'] = $new_data['total_student'];
        $data['total_staff'] = $new_data['total_staff'];
        $data['total_standard'] = $new_data['total_standard'];
        $data['percentage'] = $new_data['percentage'];

        return is_mobile($type, '/erpstatus', $data, 'view');
    }

    public function getData($request)
    {
        $tablename_arr = [
            "Student"           => "tblstudent",
            "Staff"             => "tbluser",
            "Fees"              => "fees_collect",
            "Circular"          => "circular",
            "Homework"          => "homework",
            "Result"            => "result_marks",
            "Transporation"     => "transport_map_student",
            "Attendance"        => "attendance_student",
            "Certificate"       => "certificate_history",
            "Lesson Plan"       => "lessonplan",
            "Timetable"         => "timetable",
            "Hostel"            => "hostel_room_allocation",
            "Academic Calendar" => "calendar_events",
            "SMS"               => "sms_sent_parents",
        ];

        $sub_institute_id = $request->session()->get('sub_institute_id');
        if ($request->input('percentage') != "") {
            $percentage = $request->input('percentage');
        } else {
            $percentage = 50;
        }

        $client_data = DB::table('school_setup as s')
            ->join('tblclient as c', function ($join) {
                $join->whereRaw('s.client_id = c.id');
            })->selectRaw("c.total_student,c.total_staff,s.SchoolName,s.Id")
            ->where('s.Id', $sub_institute_id)->get()->toArray();

        $total_student = $client_data[0]->total_student;
        $expected_student_data = ($total_student * $percentage) / 100;
        $total_staff = $client_data[0]->total_staff;
        $expected_staff_data = ($total_staff * $percentage) / 100;

        $std_data = DB::table('standard')->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $total_standard = count($std_data);
        $expected_standard_data = ($total_standard * $percentage) / 100;

        $data = DB::table('erp_status')->where('status', '=', 1)->get()->toArray();

        foreach ($data as $key => $val) {
            $modulename = $val->modulename;
            $erp_status[$modulename]['STATUS'] = "no";
            $erp_status[$modulename]['DATA'] = "-";

            /* START Student Comparing Data */
            if ($modulename == "Student" || $modulename == "Staff" || $modulename == "Fees" ||
                $modulename == "Transporation" || $modulename == "Attendance" || $modulename == "Certificate" ||
                $modulename == "Result" || $modulename == "Hostel" || $modulename == "Academic Calendar" ||
                $modulename == "SMS"
            ) {

                $extrasql = "";
                if ($modulename == "Attendance" || $modulename == "Certificate" || $modulename == "Result" ||
                    $modulename == "SMS") {
                    $extrasql = "GROUP BY student_id";
                }

                $data1 = DB::select("select * from ".$tablename_arr[$modulename]."
						where sub_institute_id = $sub_institute_id $extrasql");

                $original_data = count($data1);

                if ($original_data >= $expected_student_data) {
                    $erp_status[$modulename]['STATUS'] = "yes";
                } else {
                    $erp_status[$modulename]['STATUS'] = "no";
                }

                /*START Show Direct Data*/
                if ($modulename == "Hostel" || $modulename == "Academic Calendar" || $modulename == "SMS") {
                    $erp_status[$modulename]['STATUS'] = "yes";
                }
                /*END Show Direct Data*/

                $erp_status[$modulename]['DATA'] = $original_data;
            }
            /* END Student Comparing Data */

            /* START Staff Comparing Data */
            if ($modulename == "Staff") {

                $data1 = DB::select("select * from ".$tablename_arr[$modulename]."
						where sub_institute_id = $sub_institute_id $extrasql");

                $original_data = count($data1);

                if ($original_data >= $expected_staff_data) {
                    $erp_status[$modulename]['STATUS'] = "yes";
                } else {
                    $erp_status[$modulename]['STATUS'] = "no";
                }
                $erp_status[$modulename]['DATA'] = $original_data;
            }
            /* END Staff Comparing Data */

            /* START Standard Comparing Data */
            if ($modulename == "Circular" || $modulename == "Homework" || $modulename == "Timetable" ||
                $modulename == "Lesson Plan"
            ) {
                $data1 = DB::table($tablename_arr[$modulename])
                    ->where('sub_institute_id', $sub_institute_id)->groupBy('standard_id')->get()->toArray();

                $original_data = count($data1);

                if ($original_data >= $expected_standard_data) {
                    $erp_status[$modulename]['STATUS'] = "yes";
                } else {
                    $erp_status[$modulename]['STATUS'] = "no";
                }
                $erp_status[$modulename]['DATA'] = $original_data;
            }
            /* END Standard Comparing Data */
        }
        /*
            select * from hs_hr_attendance a
            group by a.employee_id

            select * from hs_hr_leave a
            group by a.employee_id

            select * from hs_hr_emp_payroll a
            group by emp_id
        */

        $new_data['erp_status'] = $erp_status;
        $new_data['total_student'] = $total_student;
        $new_data['total_staff'] = $total_staff;
        $new_data['total_standard'] = $total_standard;
        $new_data['percentage'] = $percentage;

        return $new_data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $data['status_code'] = 1;
        $data['message'] = "Success";

        $new_data = $this->getData($request);
        $data['erp_status'] = $new_data['erp_status'];
        $data['total_student'] = $new_data['total_student'];
        $data['total_staff'] = $new_data['total_staff'];
        $data['total_standard'] = $new_data['total_standard'];
        $data['percentage'] = $new_data['percentage'];

        return is_mobile($type, '/erpstatus', $data, 'view');
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

    public function device_check(request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, '/device_check', $res, 'view');

    }
}
