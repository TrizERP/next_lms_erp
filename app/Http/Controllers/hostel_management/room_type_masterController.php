<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\room_type_masterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class room_type_masterController extends Controller
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
        $data = room_type_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $room_data['status_code'] = 1;
        $room_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_room_type", $room_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = room_type_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('hostel_management/add_room_type_master', ['menu' => $data]);
    }

    public function getData()
    {
        return room_type_masterModel::orderBy('id')->get();
    }

    public function store(Request $request)
    {
        ValidateInsertData('room_type_master', $request);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $room_type = new room_type_masterModel([
            'room_type'        => $request->get('room_type'),
            'status'           => $request->get('status'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $room_type->save();

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Room type Added Succesfully",
//        ];
//        
        $message = room_type_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_room_type_master.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = room_type_masterModel::find($id);

        return is_mobile($type, "hostel_management/add_room_type_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('room_type_master', 'update');

        $data = [
            'room_type' => $request->get('room_type'),
            'status'    => $request->get('status'),
        ];

        room_type_masterModel::where(["id" => $id])->update($data);

        $message['status_code'] = "1";
        $message['message'] = "Data Updated Successfully";
        $type = $request->input('type');

        return is_mobile($type, "add_room_type_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        room_type_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_room_type_master.index", $message, "redirect");
    }
}
