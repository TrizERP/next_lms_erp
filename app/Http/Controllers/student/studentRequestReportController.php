<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class studentRequestReportController extends Controller
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
        $syear = $request->session()->get('syear');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/student_request_report", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $marking_period_id = session()->get('term_id');

        $result = DB::table('student_change_request as sr')
            ->join('tblstudent as ts', function ($join)use($marking_period_id) {
                $join->whereRaw('sr.STUDENT_ID = ts.id')->when($marking_period_id,function($query) use($marking_period_id){
                    $query->where('ts.marking_period_id',$marking_period_id);
                });
            })->join('standard as s', function ($join) {
                $join->whereRaw('s.id = sr.STANDARD_ID');
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = sr.SECTION_ID');
            })->join('STUDENT_CHANGE_REQ_TYPE as srt', function ($join) {
                $join->whereRaw('srt.ID = sr.CHANGE_REQUEST_ID');
            })->selectRaw("sr.*,ts.enrollment_no, CONCAT_WS(' ',ts.first_name,ts.last_name) AS student_name,
                s.name AS standard,d.name AS division,srt.REQUEST_TITLE AS REQUEST")
            ->where('ts.sub_institute_id', $sub_institute_id)
            ->where('sr.SYEAR', $syear);

        if ($from_date != '') {
            $result = $result->where('sr.CREATED_ON', '>=', $from_date);
        }

        if ($to_date != '') {
            $result = $result->where('sr.CREATED_ON', '<=', $to_date);
        }

        $result = $result->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['from_date']=$from_date;
        $res['to_date']=$to_date;
        $res['result_report'] = $result;

        return is_mobile($type, "student/student_request_report", $res, "view");
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
