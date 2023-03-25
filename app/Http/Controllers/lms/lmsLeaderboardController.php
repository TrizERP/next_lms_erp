<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lmsLeaderboardController extends Controller
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
        // $res['total_points'] = $data['total_points'];
        // $res['modulewise_points'] = $data['modulewise_points'];
        $res['lb_Data'] = $data;

        return is_mobile($type, 'lms/show_lmsLeaderboard', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_id = $request->session()->get('user_id');
        $syear = $request->session()->get('syear');

        $data = $modulewise_points = [];

        //Get Student Current Standard and Leader board Points
        $studData = DB::table('lb_points AS l')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('l.user_id = s.id and l.sub_institute_id = s.sub_institute_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id and se.sub_institute_id = s.sub_institute_id');
            })->join('lb_master as m', function ($join) {
                $join->whereRaw('l.module_name = m.module_name and m.standard_id = se.standard_id');
            })->selectRaw('l.*,m.icon,se.standard_id,se.section_id')
            ->where('l.sub_institute_id', $sub_institute_id)
            ->where('l.user_id', $user_id)
            ->where('l.user_profile_id', $user_profile_id)
            ->where('l.syear', $syear)->get()->toArray();

        if (count($studData) > 0) {
            $studData = json_decode(json_encode($studData), true);

            $total_points = 0;

            //Make Studen Module wise points array
            foreach ($studData as $key => $val) {
                $total_points += $val['points'];
                $modulewise_points[$val['module_name']]['ICON'] = $val['icon'];
                $modulewise_points[$val['module_name']]['DATA'][$val['inserted_date']] = $val['points'];
                $standard_id = $val['standard_id'];
            }

            //Get Class wise Rank and Class data
            //$statement = DB::statement("SET @a=0");
            $classdata = DB::table('lb_points AS l')
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw('l.user_id = s.id and l.sub_institute_id = s.sub_institute_id');
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id and se.sub_institute_id = s.sub_institute_id');
                })->selectRaw("SUM(points) as total_points,l.user_id,CONCAT_WS(' ' ,s.first_name,
                    s.middle_name,s.last_name) as student_name")
                ->where('l.sub_institute_id', $sub_institute_id)
                ->where('se.standard_id', $standard_id)
                ->where('se.syear', $syear)
                ->groupBy('user_id')
                ->orderBY('total_points', 'DESC')->limit(5)
                ->get()->toArray();

            $classdata = json_decode(json_encode($classdata), true);

            $data['total_points'] = $total_points;
            $data['modulewise_points'] = $modulewise_points;
            $data['student_rank'] = (array_search($user_id, array_column($classdata, 'user_id')) + 1);
            $data['classdata'] = $classdata;
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
