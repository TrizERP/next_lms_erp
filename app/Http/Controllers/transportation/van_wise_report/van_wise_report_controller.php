<?php

namespace App\Http\Controllers\transportation\van_wise_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class van_wise_report_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) {
            // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $data['data'] = [];
        $data['data']['ddShift'] = $this->ddShift();
        $data['data']['ddVan'] = $this->ddVan();
        $data['data']['ddRoute'] = $this->ddRoute();
        $data['data']['ddStop'] = $this->ddStop();

        $type = $request->input('type');

        return is_mobile($type, "transportation/van_wise_report/show", $data, "view");
    }

    public function ddShift()
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
    }

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
