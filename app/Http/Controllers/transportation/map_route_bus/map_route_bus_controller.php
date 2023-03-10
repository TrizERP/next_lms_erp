<?php

namespace App\Http\Controllers\transportation\map_route_bus;

use App\Http\Controllers\Controller;
use App\Models\transportation\map_route_bus\map_route_bus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class map_route_bus_controller extends Controller
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

        return is_mobile($type, "transportation/map_route_bus/show", $school_data, "view");
    }

    public function getData()
    {

        return map_route_bus::join('transport_vehicle', 'transport_vehicle.id', '=', 'transport_route_bus.bus_id')
            ->join('transport_school_shift', 'transport_school_shift.id', '=', 'transport_vehicle.school_shift')
            ->join('transport_route', 'transport_route.id', '=', 'transport_route_bus.route_id')
            ->where([
                'transport_route_bus.sub_institute_id' => session()->get('sub_institute_id'),
            ])
            ->select(DB::raw("CONCAT(title,'[',shift_title,']') AS bus_name"), 'transport_route_bus.id',
                'transport_route.route_name')
            ->get();
    }

    public function ddBus()
    {
        return DB::table('transport_vehicle')
            ->select(DB::raw("CONCAT(title,'[',shift_title,']') AS name"), 'transport_vehicle.id')
            ->join('transport_school_shift', 'transport_school_shift.id', '=', 'transport_vehicle.school_shift')
            ->where("transport_vehicle.sub_institute_id", session()->get('sub_institute_id'))
            ->pluck('name', 'id');
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
        $dataStore['ddBus'] = $this->ddBus();
        $dataStore['ddRoute'] = $this->ddRoute();

        return is_mobile($type, 'transportation/map_route_bus/add', $dataStore, "view");
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
        $exam = new map_route_bus([
            "route_id"         => $request->get('route'),
            "bus_id"           => $request->get('bus'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ]);
        $exam->save();

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "map_route_bus.index", $res, "redirect");
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
        $data = map_route_bus::find($id)->toArray();

        $data['ddBus'] = $this->ddBus();
        $data['ddRoute'] = $this->ddRoute();

        return is_mobile($type, "transportation/map_route_bus/edit", $data, "view");
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
            "route_id"         => $request->get('route'),
            "bus_id"           => $request->get('bus'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ];

        map_route_bus::where(["id" => $id])->update($data1);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "map_route_bus.index", $res, "redirect");
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
        map_route_bus::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "map_route_bus.index", $res, "redirect");
    }

}
