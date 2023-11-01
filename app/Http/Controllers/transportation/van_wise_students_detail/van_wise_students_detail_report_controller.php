<?php

namespace App\Http\Controllers\transportation\van_wise_students_detail;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class van_wise_students_detail_report_controller extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $student_datas = DB::table("transport_vehicle as tv")
            ->join('transport_school_shift as tss', function ($join) {
                $join->whereRaw("tss.id = tv.school_shift");
            })
            ->join('transport_map_student as tms', function ($join) {
                $join->whereRaw("tms.from_shift_id = tss.id");
                $join->whereRaw("tms.from_bus_id = tv.id");
            })
            ->select('tv.id as transport_vehicle_id', 'tss.id as transport_school_shift_id', 'tv.title as bus_name', 'tss.shift_title', DB::raw('count(tms.student_id) as student_count'))
            ->where('tv.sub_institute_id', $sub_institute_id)
            ->groupBy('tv.title', 'tss.shift_title')
            ->get()->toarray();
           /* echo "<pre>";
            print_r($student_datas);
            echo "</pre>";
            die; */
 
        $res['student_datas'] = $student_datas;

        return is_mobile($type, "transportation/van_wise_students_detail/show", $res, "view");
    }

    public function retrieveDataByUserId(Request $request, $transport_vehicle_id, $transport_school_shift_id)
{
    $transport_vehicle_id = $transport_vehicle_id;
    $transport_school_shift_id = $transport_school_shift_id;
    $syear = $request->session()->get('syear');
    $sub_institute_id = $request->session()->get('sub_institute_id');

    $result = DB::table('tblstudent as s')
        ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
        ->join('academic_section as g', 'g.id', '=', 'se.grade_id')
        ->join('standard as st', 'st.id', '=', 'se.standard_id')
        ->join('division as d', 'd.id', '=', 'se.section_id')
        ->join('transport_map_student as tms', function ($join) use ($transport_vehicle_id, $transport_school_shift_id) {
            $join->on('tms.student_id', '=', 's.id')
                ->where('tms.from_bus_id', $transport_vehicle_id)
                ->where('tms.from_shift_id', $transport_school_shift_id);
        })
        ->join('transport_vehicle as tv', 'tv.id', '=', 'tms.from_bus_id')
        ->selectRaw('CONCAT_WS(" ", s.first_name, s.last_name) as student_name, s.enrollment_no, s.mobile, s.address, se.syear, se.student_id, se.grade_id, se.standard_id, se.section_id, st.name as standard_name, d.name as division_name, tv.title as bus_name')
        ->where('s.sub_institute_id', $sub_institute_id)
        ->where('se.syear', $syear)
        ->where('tms.sub_institute_id', $sub_institute_id)
        ->where('tms.syear', $syear)
        ->get()->toArray();

        /* echo "<pre>";
print_r($result);
echo "</pre>";
die; */

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $result;

        return $result;
    }

    /* public function ddShift()
    {
        return DB::table('transport_school_shift')
            ->select('transport_school_shift.shift_title', 'transport_school_shift.id')
            ->where("transport_school_shift.sub_institute_id", session()->get('sub_institute_id'))
            ->pluck('shift_title', 'id');
    }

    public function ddVan()
    {
        return DB::table('transport_vehicle')
            ->select('transport_vehicle.title', 'transport_vehicle.id')
            ->where("transport_vehicle.sub_institute_id", session()->get('sub_institute_id'))
            ->pluck('title', 'id');
    }

    public function ddRoute()
    {
        $where = [
            "transport_route.sub_institute_id" => session()->get('sub_institute_id'),
            "transport_route.syear"            => session()->get('syear'),
        ];

        return DB::table('transport_route')
            ->select('transport_route.route_name', 'transport_route.id')
            ->where($where)
            ->pluck('route_name', 'id');
    }

    public function ddStop()
    {
        $where = [
            "transport_stop.sub_institute_id" => session()->get('sub_institute_id'),
            "transport_stop.syear"            => session()->get('syear'),
        ];

        return DB::table('transport_stop')
            ->select('transport_stop.stop_name', 'transport_stop.id')
            ->where($where)
            ->pluck('stop_name', 'id');
    } */

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {

        $search = "";
        $marking_period_id = session()->get('term_id');

        if ($_REQUEST['pickup'] == 'drop') {
            $search = "to";
        } else {
            $search = "from";
        }

        $where = "tm.sub_institute_id = '".session()->get('sub_institute_id')."'
                and tm.syear = '".session()->get('syear')."' ";

        if (isset($_REQUEST['van']) && $_REQUEST['van'] != '') {
            $where .= " and tm.".$search."_bus_id = '".$_REQUEST['van']."' ";
        }
        if (isset($_REQUEST['shift']) && $_REQUEST['shift'] != '') {
            $where .= " and tm.".$search."_shift_id = '".$_REQUEST['shift']."' ";
        }
        if (isset($_REQUEST['grno']) && $_REQUEST['grno'] != '') {
            $where .= " and ts.enrollment_no = '".$_REQUEST['grno']."' ";
        }
        if (isset($_REQUEST['route']) && $_REQUEST['route'] != '') {
            $where .= " and tr.id ='".$_REQUEST['route']."' ";
        }
        if (isset($_REQUEST['stop']) && $_REQUEST['stop'] != '') {
            $where .= " and st.id ='".$_REQUEST['stop']."'  ";
        }

        $student_data = DB::table("tblstudent as ts")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = ts.id AND se.syear = '".session()->get('syear')."' AND se.end_date IS NULL");
            })
            ->join('standard as s', function ($join) use($marking_period_id){
                $join->whereRaw("s.id = se.standard_id");
                // ->when($marking_period_id,function($uqery) use($marking_period_id){
                //     $uqery->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division as d', function ($join) {
                $join->whereRaw("d.id = se.section_id");
            })
            ->join('transport_map_student as tm', function ($join) {
                $join->whereRaw("tm.student_id = ts.id");
            })
            ->join('transport_vehicle as tv', function ($join) use ($search) {
                $join->whereRaw("tv.id = tm.".$search."_bus_id");
            })
            ->join('transport_school_shift as ss', function ($join) {
                $join->whereRaw("ss.id = tv.school_shift");
            })
            ->join('transport_stop as st', function ($join) use ($search) {
                $join->whereRaw("st.id = tm.".$search."_stop");
            })
            ->join('transport_route_bus as rb', function ($join) {
                $join->whereRaw("rb.bus_id = tv.id");
            })
            ->join('transport_route as tr', function ($join) {
                $join->whereRaw("tr.id = rb.route_id");
            })
            ->join('transport_driver_detail as dd', function ($join) {
                $join->whereRaw("dd.id = tv.driver");
            })
            ->leftJoin('transport_driver_detail as cd', function ($join) {
                $join->whereRaw("cd.id = tv.conductor");
            })
            ->selectRaw("ts.id AS student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) name, 
    concat(s.name,'/',d.name) as stddiv,ts.mobile,ts.enrollment_no,ts.address, tr.route_name, tv.title as bus_name, st.stop_name,
    dd.first_name driver, cd.first_name conductor")
            ->whereRaw($where)
            ->groupBy('student_id')
            ->get()->toarray();

        $data['data'] = $student_data;

        $type = $request->input('type');

        return is_mobile($type, "transportation/van_wise_report/add", $data, "view");
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
