<?php

namespace App\Http\Controllers\transportation\add_route;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\transportation\add_route\add_route;
use DB;

class add_route_controller extends Controller {

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
        return \App\Helpers\is_mobile($type, "transportation/add_route/show", $school_data, "view");
    }

    public function getData() {
        $data = add_route::
                where([
                    'sub_institute_id' => session()->get('sub_institute_id'),
                        ]
                )
                ->get();
        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $type = $request->input('type');
        $dataStore = array();
        return \App\Helpers\is_mobile($type, 'transportation/add_route/add', $dataStore, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
//        $max_id = add_route::max('map_id');

        $exam = new add_route([
            "route_name" => $request->get('route_name'),
            "from_time" => $request->get('from_time'),
            "to_time" => $request->get('to_time'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear' => session()->get('syear'),
        ]);
        $exam->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_route.index", $res, "redirect");
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
        $data = add_route::find($id)->toArray();

        return \App\Helpers\is_mobile($type, "transportation/add_route/edit", $data, "view");
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
                "route_name" => $request->get('route_name'),
                "from_time" => $request->get('from_time'),
                "to_time" => $request->get('to_time'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
        ]);

        $data1 = $data1[0];

        add_route::where(["id" => $id])->update($data1);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "add_route.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        add_route::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "add_route.index", $res, "redirect");
    }

}
