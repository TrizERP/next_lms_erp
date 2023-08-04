<?php

namespace App\Http\Controllers\consent;

use App\Http\Controllers\Controller;
use App\Models\consent\consent_masterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class delete_consent_masterController extends Controller
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "front_desk/consent/delete_consent_master", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');

        $result = DB::table('consent_master as CM')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw("s.id = CM.student_id AND s.sub_institute_id = CM.sub_institute_id");
            })->join('tblstudent_enrollment as SE', function ($join) use ($syear) {
                $join->whereRaw("SE.student_id = s.id AND SE.syear = '" . $syear . "'");
            })->join('standard as CS', function ($join) use($marking_period_id) {
                $join->whereRaw("CS.id = SE.standard_id");
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('CS.marking_period_id',$marking_period_id);
                // });
            })->join('academic_section as SG', function ($join) use ($sub_institute_id) {
                $join->whereRaw("SG.id = CS.grade_id AND SG.sub_institute_id = '" . $sub_institute_id . "'");
            })->join('division as SS', function ($join) {
                $join->whereRaw("SS.id = SE.section_id");
            })->join('tbluser as ta', function ($join) {
                $join->whereRaw("ta.id = CM.created_by");
            })->selectRaw("CM.ID AS CHECKBOX,CM.*,s.enrollment_no, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name)
                AS FULL_NAME,s.mobile AS SMS_NO, SG.title AS GRADE_ID, CONCAT_WS('/',CS.name,SS.name) AS STANDARD,
		        CONCAT_WS(' ',ta.first_name,ta.last_name) AS created_by, DATE_FORMAT(CM.date,'%d-%m-%Y') AS consent_date,
		        IF(CM.accountable_status = 'Accountable','Account','Not Account') AS account_status")
            ->where('CM.syear', $syear)
            ->where('CM.sub_institute_id', $sub_institute_id)
            ->where(function ($q) use ($standard, $division, $from_date, $to_date) {
                if ($standard != '') {
                    $q->where('CM.standard_id', $standard);
                }

                if ($division != '') {
                    $q->where('CM.division_id', $division);
                }
                if ($from_date != '' && $to_date != '') {
                    $q->whereRaw("(DATE_FORMAT(CM.date,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."')");
                }
            })->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $result;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "front_desk/consent/delete_consent_master", $res, "view");
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

        foreach ($students as $key => $student_id) {
            consent_masterModel::where(["id" => $student_id])->delete();
        }

        $res['status_code'] = "1";
        $res['message'] = "Consent Deleted successfully";

        return is_mobile($type, "delete_consent_master.index", $res);
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
