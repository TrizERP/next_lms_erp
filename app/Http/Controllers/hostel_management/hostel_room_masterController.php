<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\hostel_floor_masterModel;
use App\Models\hostel_management\hostel_room_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class hostel_room_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $room_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $room = DB::table('hostel_room_master')
            ->join('hostel_floor_master', 'hostel_room_master.floor_id', '=', 'hostel_floor_master.id')
            ->join('hostel_building_master', 'hostel_building_master.id', '=', 'hostel_floor_master.building_id')
            ->join('hostel_master', 'hostel_master.id', '=', 'hostel_building_master.hostel_id')
            ->select('hostel_room_master.*')
            ->selectRaw('CONCAT_WS(" - ",hostel_floor_master.floor_name,hostel_master.name) as floor_id')
            ->where('hostel_room_master.sub_institute_id', '=', $sub_institute_id)->get();
        $room_data['status_code'] = 1;
        $room_data['data'] = $room;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_hostel_room", $room_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hostel_floor_masterModel::select('hostel_floor_master.id')
            ->selectRaw('CONCAT_WS(" - ",hostel_floor_master.floor_name,hostel_master.name) as floor_name')
            ->join('hostel_building_master', 'hostel_building_master.id', '=', 'hostel_floor_master.building_id')
            ->join('hostel_master', 'hostel_master.id', '=', 'hostel_building_master.hostel_id')
            ->where(['hostel_floor_master.sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('hostel_management/add_hostel_room_master', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $room = new hostel_room_masterModel([
            'room_name'        => $request->get('room_name'),
            'floor_id'         => $request->get('floor_id'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $room->save();
        $message['status_code'] = "1";
//        $message = [
//            "message" => "Floor's Room Added Succesfully",
//        ];
        $message = hostel_room_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_room_master.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = hostel_room_masterModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = hostel_floor_masterModel::select('hostel_floor_master.id')
            ->selectRaw('CONCAT_WS(" - ",hostel_floor_master.floor_name,hostel_master.name) as floor_name')
            ->join('hostel_building_master', 'hostel_building_master.id', '=', 'hostel_floor_master.building_id')
            ->join('hostel_master', 'hostel_master.id', '=', 'hostel_building_master.hostel_id')
            ->where(['hostel_floor_master.sub_institute_id' => $sub_institute_id])->get()->toArray();

        view()->share('menu', $editdata);

        return view('hostel_management/add_hostel_room_master', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = [
            'room_name' => $request->get('room_name'),
            'floor_id'  => $request->get('floor_id'),
        ];

        hostel_room_masterModel::where(["id" => $id])->update($data);

        $message['status_code'] = "1";
        $message = [
            "message" => "Floor's Room Updated Successfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_hostel_room_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        hostel_room_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Room Deleted successfully",
        ];

        return is_mobile($type, "add_hostel_room_master.index", $message, "redirect");
    }

    public function hostelWiseRoomList(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $hostel_id = $request->input('hostel_id');

        $extraSearchArray = [];

        $extraSearchArray['hostel_room_master.sub_institute_id'] = $sub_institute_id;
        if ($hostel_id != '') {
            $extraSearchArray['hostel_building_master.hostel_id'] = $hostel_id;
        }

        return hostel_room_masterModel::select("hostel_room_master.id as id",
            "hostel_room_master.room_name as room_name")
            ->join("hostel_floor_master", "hostel_floor_master.id", "=", "hostel_room_master.floor_id")
            ->join("hostel_building_master", "hostel_building_master.id", "=", "hostel_floor_master.building_id")
            ->where($extraSearchArray)
            ->get()->toArray();
    }
}
