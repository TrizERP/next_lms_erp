<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\standardModel;
use App\Models\student\studentRequestModel;
use App\Models\student\studentChangeRequestTypeModel;
use App\Models\student\tblstudentModel;

use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

use Illuminate\Support\Facades\DB;

class studentRequestReportController extends Controller
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
        
        return is_mobile($type, "student/student_request_report", $res, "view");
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

        if($from_date != '')
        {
            $extra_query .= " AND sr.CREATED_ON >= '".$from_date."' ";
        }

        if($to_date != '')
        {
            $extra_query .= " AND sr.CREATED_ON <= '".$to_date."' ";
        }

        $sql = "SELECT sr.*,ts.enrollment_no, CONCAT_WS(' ',ts.first_name,ts.last_name) AS student_name,s.name AS standard,d.name AS division,srt.REQUEST_TITLE AS REQUEST
        FROM student_change_request sr
        INNER JOIN tblstudent ts ON sr.STUDENT_ID = ts.id
        INNER JOIN standard s ON s.id = sr.STANDARD_ID
        INNER JOIN division d ON d.id = sr.SECTION_ID
        INNER JOIN STUDENT_CHANGE_REQ_TYPE srt ON srt.ID = sr.CHANGE_REQUEST_ID
        WHERE ts.sub_institute_id = '".$sub_institute_id."' AND sr.SYEAR = '".$syear."' ";


        $result = DB::select($sql.$extra_query);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;

        return is_mobile($type, "student/student_request_report", $res, "view");
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
