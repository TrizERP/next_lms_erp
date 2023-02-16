<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\standardModel;
use App\Models\student\studentRequestModel;
use App\Models\student\studentChangeRequestTypeModel;
use App\Models\student\tblstudentModel;
use App\Models\student\documentTypeModel;

use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

use Illuminate\Support\Facades\DB;

class missingDocumentReportController extends Controller
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
        
        return is_mobile($type, "student/missing_document_report", $res, "view");
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
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");        
        
        $extraRaw = "";
        if($grade_id != ''){
            $extraRaw .= " AND se.grade_id  = '".$grade_id."'";
        }
        if($standard_id != ''){
            $extraRaw .= " AND  se.standard_id = '".$standard_id."'";
        }
        if($division_id != ''){
            $extraRaw .= " AND  se.section_id = '".$division_id."'";
        }

        $sql = "SELECT s.enrollment_no, CONCAT_WS(' ',s.first_name,s.last_name) AS student_name,
        st.name as standard_name,d.name as division_name,GROUP_CONCAT(sd.document_type_id) as document_list
        FROM tblstudent s
        INNER JOIN tblstudent_enrollment se ON s.id = se.student_id
        INNER JOIN standard st ON st.id = se.standard_id
        INNER JOIN division d ON d.id = se.section_id
        left join tblstudent_document sd on sd.student_id = se.student_id
        WHERE se.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."' $extraRaw
        GROUP BY se.student_id";

        $result = DB::select($sql);

        $docment_type_data = documentTypeModel::select('*')->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['docment_type_data'] = $docment_type_data;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;

        return is_mobile($type, "student/missing_document_report", $res, "view");
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
