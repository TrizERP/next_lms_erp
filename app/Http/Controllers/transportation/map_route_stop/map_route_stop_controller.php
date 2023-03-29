<?php

namespace App\Http\Controllers\transportation\map_route_stop;

use App\Http\Controllers\Controller;
use App\Models\transportation\map_route_stop\map_route_stop;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class map_route_stop_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "transportation/map_route_stop/show", $school_data, "view");
    }

    public function getData()
    {
        return map_route_stop::
        join('transport_stop', 'transport_stop.id', '=', 'transport_route_stop.stop_id')
            ->join('transport_route', 'transport_route.id', '=', 'transport_route_stop.route_id')
            ->where([
                'transport_route_stop.sub_institute_id' => session()->get('sub_institute_id'),
            ])
            ->select('transport_stop.stop_name', 'transport_route_stop.id', 'transport_route.route_name',
                'transport_route_stop.pickuptime', 'transport_route_stop.droptime')
            ->get();
    }

    public function ddStop()
    {
        return DB::table('transport_stop')
            ->select('transport_stop.stop_name', 'transport_stop.id')
            ->where("transport_stop.sub_institute_id", session()->get('sub_institute_id'))
            ->where("transport_stop.syear", session()->get('syear'))
            ->pluck('stop_name', 'id');
    }

    public function ddRoute()
    {
        return DB::table('transport_route')
            ->where([
                "sub_institute_id" => session()->get('sub_institute_id'),
            ])
            ->pluck('route_name', 'id');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore['ddStop'] = $this->ddStop();
        $dataStore['ddRoute'] = $this->ddRoute();

        return is_mobile($type, 'transportation/map_route_stop/add', $dataStore, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        $stop_arr = $request->get('stop_arr');
        $pickuptime = $request->get('pickuptime');
        $droptime = $request->get('droptime');

        foreach ($stop_arr as $key => $val) {
            $routestop_arr = new map_route_stop([
                "route_id"         => $request->get('route'),
                "stop_id"          => $val,
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear'            => session()->get('syear'),
                'pickuptime'       => $pickuptime[$val],
                'droptime'         => $droptime[$val],
            ]);

            $routestop_arr->save();
        }

        $res = [
            "status_code" => 1,
            "message"     => "Route-Stop Mapped Succesfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "map_route_stop.index", $res, "redirect");
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
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = map_route_stop::find($id)->toArray();

        $data['ddStop'] = $this->ddStop();
        $data['ddRoute'] = $this->ddRoute();

        return is_mobile($type, "transportation/map_route_stop/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $data1 = [
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
            'pickuptime'       => $request->get('pickuptime'),
            'droptime'         => $request->get('droptime'),
        ];

        map_route_stop::where(["id" => $id])->update($data1);

        $res = [
            "status_code" => 1,
            "message"     => "Route-Stop Mapping Updated Succesfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "map_route_stop.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        map_route_stop::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Route-Stop Mapping Deleted Succesfully",
        ];

        return is_mobile($type, "map_route_stop.index", $res, "redirect");
    }

}
