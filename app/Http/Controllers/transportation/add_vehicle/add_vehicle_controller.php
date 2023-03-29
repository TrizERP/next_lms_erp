<?php

namespace App\Http\Controllers\transportation\add_vehicle;

use App\Http\Controllers\Controller;
use App\Models\transportation\add_vehicle\add_vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class add_vehicle_controller extends Controller
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

        return is_mobile($type, "transportation/add_vehicle/show", $school_data, "view");
    }

    public function getData()
    {
        return add_vehicle::join('transport_school_shift', 'transport_school_shift.id', '=',
            'transport_vehicle.school_shift')
            ->join('transport_driver_detail as td', 'td.id', '=', 'transport_vehicle.driver')
            ->leftjoin('transport_driver_detail as tc', 'tc.id', '=', 'transport_vehicle.conductor')
            ->where([
                'transport_vehicle.sub_institute_id' => session()->get('sub_institute_id'),
            ])
            ->select('transport_vehicle.id', 'title', 'vehicle_number', 'vehicle_type', 'sitting_capacity',
                'shift_title', 'vehicle_identity_number', 'td.first_name', 'tc.first_name as cond')
            ->get();
    }

    public function getDD()
    {
        return DB::table('transport_school_shift')
            ->where("sub_institute_id", session()->get('sub_institute_id'))
            ->pluck('shift_title', 'id');
    }

    public function getDriverDD()
    {
        return DB::table('transport_driver_detail')
            ->where([
                "sub_institute_id" => session()->get('sub_institute_id'),
                "type"             => 'Driver',
            ])
            ->pluck('first_name', 'id');
    }

    public function getConductorDD()
    {
        return DB::table('transport_driver_detail')
            ->where([
                "sub_institute_id" => session()->get('sub_institute_id'),
                "type"             => 'Conductor',
            ])
            ->pluck('first_name', 'id');
    }

    public function getVehicleType()
    {
        return DB::table('transport_vehicle_type')
            ->pluck('name', 'id');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore['vehicle_type_data'] = $this->getVehicleType();
        $dataStore['ddValue'] = $this->getDD();
        $dataStore['Driverdd'] = $this->getDriverDD();
        $dataStore['Conductordd'] = $this->getConductorDD();

        return is_mobile($type, 'transportation/add_vehicle/add', $dataStore, "view");
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
        $exam = new add_vehicle([
            "title"                   => $request->get('title'),
            "vehicle_number"          => $request->get('vehicle_number'),
            "vehicle_type"            => $request->get('vehicle_type'),
            "sitting_capacity"        => $request->get('sitting_capacity'),
            "school_shift"            => $request->get('school_shift'),
            "vehicle_identity_number" => $request->get('vehicle_identity_number'),
            "driver"                  => $request->get('driver'),
            "conductor"               => $request->get('conductor'),
            'sub_institute_id'        => session()->get('sub_institute_id'),
        ]);
        $exam->save();

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_vehicle.index", $res, "redirect");
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
        $data = add_vehicle::find($id)->toArray();
        $data['vehicle_type_data'] = $this->getVehicleType();
        $data['ddValue'] = $this->getDD();
        $data['Driverdd'] = $this->getDriverDD();
        $data['Conductordd'] = $this->getConductorDD();

        return is_mobile($type, "transportation/add_vehicle/edit", $data, "view");
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
        $data1 = array(
            [
                "title"                   => $request->get('title'),
                "vehicle_number"          => $request->get('vehicle_number'),
                "vehicle_type"            => $request->get('vehicle_type'),
                "sitting_capacity"        => $request->get('sitting_capacity'),
                "school_shift"            => $request->get('school_shift'),
                "vehicle_identity_number" => $request->get('vehicle_identity_number'),
                "driver"                  => $request->get('driver'),
                "conductor"               => $request->get('conductor'),
                'sub_institute_id'        => session()->get('sub_institute_id'),
            ],
        );

        $data1 = $data1[0];

        add_vehicle::where(["id" => $id])->update($data1);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_vehicle.index", $res, "redirect");
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
        add_vehicle::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "add_vehicle.index", $res, "redirect");
    }

}
