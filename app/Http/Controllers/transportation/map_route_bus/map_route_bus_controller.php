<?php

namespace App\Http\Controllers\transportation\map_route_bus;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\transportation\map_route_bus\map_route_bus;
use DB;

class map_route_bus_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
//        $school_data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "transportation/map_route_bus/show", $school_data, "view");
    }

    public function getData() {
        $data = map_route_bus::
                join('transport_vehicle', 'transport_vehicle.id', '=', 'transport_route_bus.bus_id')
                ->join('transport_school_shift', 'transport_school_shift.id', '=', 'transport_vehicle.school_shift')
                ->join('transport_route', 'transport_route.id', '=', 'transport_route_bus.route_id')
                ->where([
                    'transport_route_bus.sub_institute_id' => session()->get('sub_institute_id'),
                        ]
                )
                ->select(
                        DB::raw("CONCAT(title,'[',shift_title,']') AS bus_name"), 'transport_route_bus.id', 'transport_route.route_name')
                ->get();
//        echo "<pre>";
//        print_r($data);
//        exit;

        return $data;
    }

    public function ddBus() {
        $std_div_map = DB::table('transport_vehicle')
                ->select(
                        DB::raw("CONCAT(title,'[',shift_title,']') AS name"), 'transport_vehicle.id')
                ->join('transport_school_shift', 'transport_school_shift.id', '=', 'transport_vehicle.school_shift')
                ->where("transport_vehicle.sub_institute_id", session()->get('sub_institute_id'))
                ->pluck('name', 'id');
//        echo "<pre>";
//        print_r($std_div_map);
//        exit;

        return $std_div_map;
    }

    public function ddRoute() {
        $std_div_map = DB::table('transport_route')
                ->where([
                    "sub_institute_id" => session()->get('sub_institute_id'),
                ])
                ->pluck('route_name', 'id');
        return $std_div_map;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $type = $request->input('type');
        $dataStore['ddBus'] = $this->ddBus();
        $dataStore['ddRoute'] = $this->ddRoute();
        return \App\Helpers\is_mobile($type, 'transportation/map_route_bus/add', $dataStore, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
//        $max_id = add_driver::max('map_id');

        $exam = new map_route_bus([
            "route_id" => $request->get('route'),
            "bus_id" => $request->get('bus'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear' => session()->get('syear'),
        ]);
        $exam->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "map_route_bus.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $type = $request->input('type');
        $data = map_route_bus::find($id)->toArray();

        $data['ddBus'] = $this->ddBus();
        $data['ddRoute'] = $this->ddRoute();

        return \App\Helpers\is_mobile($type, "transportation/map_route_bus/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
//        echo "<pre>";
//        dd($request);

        $data1 = array([
                "route_id" => $request->get('route'),
                "bus_id" => $request->get('bus'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
        ]);

        $data1 = $data1[0];

        map_route_bus::where(["id" => $id])->update($data1);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "map_route_bus.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        map_route_bus::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "map_route_bus.index", $res, "redirect");
    }

}
