<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\hostel_building_masterModel;
use App\Models\hostel_management\hostel_floor_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class hostel_floor_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $floor_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $floor = DB::table('hostel_floor_master')
            ->join('hostel_building_master', 'hostel_floor_master.building_id', '=', 'hostel_building_master.id')
            ->select('hostel_floor_master.*', 'hostel_building_master.building_name as building_id')
            ->where('hostel_floor_master.sub_institute_id', '=', $sub_institute_id)->get();
        $floor_data['status_code'] = 1;
        $floor_data['data'] = $floor;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_hostel_floor", $floor_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hostel_building_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('hostel_management/add_hostel_floor_master', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $floor = new hostel_floor_masterModel([
            'floor_name'       => $request->get('floor_name'),
            'building_id'      => $request->get('building_id'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $floor->save();
        $message['status_code'] = "1";
//        $message = array(
//            "message" => "Building's Floor Added Succesfully",
//        );

        $message = hostel_floor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_floor_master.index", $message, "redirect");

    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = hostel_floor_masterModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = hostel_building_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        view()->share('menu', $editdata);

        return view('hostel_management/add_hostel_floor_master', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = [
            'floor_name'  => $request->get('floor_name'),
            'building_id' => $request->get('building_id'),
        ];

        hostel_floor_masterModel::where(["id" => $id])->update($data);

        $message['status_code'] = "1";
        $message = [
            "message" => "Building's Floor Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_floor_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        hostel_floor_masterModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message = [
            "message" => "Building's Floor Deleted successfully",
        ];

        return is_mobile($type, "add_hostel_floor_master.index", $message, "redirect");
    }
}
