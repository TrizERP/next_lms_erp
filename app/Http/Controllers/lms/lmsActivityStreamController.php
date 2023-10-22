<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lmsActivityStreamController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['activitystream_today_data'] = $data['activitystream_today_data'];
        $res['activitystream_upcoming_data'] = $data['activitystream_upcoming_data'];

        return is_mobile($type, 'lms/show_lmsActivityStream', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data['activitystream_upcoming_data'] = $data['activitystream_today_data'] = array();

        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $student_id = session()->get('user_id');
            $stu_data = tblstudentEnrollmentModel::select('standard_id')->where([
                'student_id' => $student_id, "syear" => $syear,
            ])->get()->toArray();

            //START Today's Event Query
           /* $data['activitystream_today_data'] = DB::select("
                SELECT * FROM (
                SELECT 'Virtual Classroom' AS action,v.id,v.standard_id,room_name AS title,description,
                url,event_date,v.subject_id,s.display_name AS subject_name
                FROM lms_virtual_classroom v
                INNER JOIN sub_std_map s ON s.subject_id = v.subject_id and s.standard_id = v.standard_id
                WHERE event_date = CURRENT_DATE() AND v.sub_institute_id = '".$sub_institute_id."' AND v.standard_id = '".$stu_data[0]['standard_id']."'

                UNION

                SELECT 'Homework' as action,h.id,h.standard_id,title,description,'',submission_date AS event_date,h.subject_id,s.display_name AS subject_name
                FROM homework h
                INNER JOIN sub_std_map s ON s.subject_id = h.subject_id and s.standard_id = h.standard_id
                WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.standard_id = '".$stu_data[0]['standard_id']."'  AND submission_date = CURRENT_DATE()
                ) AS a
                ORDER BY event_date
                ");*/

            $data['activitystream_today_data'] = DB::table('lms_virtual_classroom as v')
                ->select(
                    DB::raw("'Virtual Classroom' AS action"),
                    'v.id',
                    'v.standard_id',
                    'room_name AS title',
                    'description',
                    'url',
                    'event_date',
                    'v.subject_id',
                    DB::raw('s.display_name AS subject_name')
                )
                ->join('sub_std_map as s', function ($join) {
                    $join->on('s.subject_id', '=', 'v.subject_id')
                        ->on('s.standard_id', '=', 'v.standard_id');
                })
                ->whereDate('event_date', now())
                ->where('v.sub_institute_id', $sub_institute_id)
                ->where('v.standard_id', $stu_data[0]['standard_id'])
                ->unionAll(
                    DB::table('homework as h')
                        ->select(
                            DB::raw("'Homework' AS action"),
                            'h.id',
                            'h.standard_id',
                            'title',
                            'description',
                            DB::raw("'' AS url"),
                            'submission_date AS event_date',
                            'h.subject_id',
                            DB::raw('s.display_name AS subject_name')
                        )
                        ->join('sub_std_map as s', function ($join) {
                            $join->on('s.subject_id', '=', 'h.subject_id')
                                ->on('s.standard_id', '=', 'h.standard_id');
                        })
                        ->where('h.sub_institute_id', $sub_institute_id)
                        ->where('h.standard_id', $stu_data[0]['standard_id'])
                        ->whereDate('submission_date', now())
                )
                ->orderBy('event_date')
                ->get();

            //END Today's Event Query

            //START Upcoming Event Query
            $data['activitystream_upcoming_data'] = DB::select("
                SELECT * FROM (
                SELECT 'Virtual Classroom' AS action,v.id,v.standard_id,room_name AS title,description,
                url,event_date,v.subject_id,s.display_name AS subject_name
                FROM lms_virtual_classroom v
                INNER JOIN sub_std_map s ON s.subject_id = v.subject_id and s.standard_id = v.standard_id
                WHERE event_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND v.sub_institute_id = '".$sub_institute_id."' AND v.standard_id = '".$stu_data[0]['standard_id']."'

                UNION

                SELECT 'Homework' as action,h.id,h.standard_id,title,description,'',submission_date AS event_date,h.subject_id,s.display_name as subject_name
                FROM homework h
                INNER JOIN sub_std_map s ON s.subject_id = h.subject_id and s.standard_id = h.standard_id
                WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.standard_id = '".$stu_data[0]['standard_id']."'
                AND submission_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                ) AS a
                ORDER BY event_date
                ");

            //END Upcoming Event Query
        }

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {

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
    public function edit(Request $request, $id)
    {

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

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy(Request $request, $id)
    {

    }

}
