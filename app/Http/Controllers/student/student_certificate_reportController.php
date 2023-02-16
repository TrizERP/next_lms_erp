<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class student_certificate_reportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/student_certificate/show", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $extra_query = '';

        if ($from_date != '') {
            $extra_query .= " AND sr.CREATED_AT >= '" . $from_date . "' ";
        }

        if ($to_date != '') {
            $extra_query .= " AND sr.CREATED_AT <= '" . $to_date . "' ";
        }

        $sql = "SELECT sr.*,ts.enrollment_no, CONCAT_WS(' ',ts.first_name,ts.last_name) AS student_name,
                s.name AS standard,d.name AS division,sr.certificate_type AS REQUEST
        FROM certificate_history sr
        INNER JOIN tblstudent ts ON sr.STUDENT_ID = ts.id
        INNER JOIN tblstudent_enrollment se on se.student_id = ts.id
        INNER JOIN standard s ON s.id = se.STANDARD_ID
        INNER JOIN division d ON d.id = se.SECTION_ID
        WHERE ts.sub_institute_id = '" . $sub_institute_id . "' AND sr.SYEAR = '" . $syear . "' $extra_query 
        GROUP BY sr.id";
        $sql = $sql;
        // echo $sql;
        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql . $extra_query);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['result_report'] = $result;

        return is_mobile($type, "student/student_certificate/show", $res, "view");
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
