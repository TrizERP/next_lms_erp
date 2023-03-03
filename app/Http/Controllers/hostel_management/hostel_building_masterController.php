<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\hostel_building_masterModel;
use App\Models\hostel_management\hosteltypemasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class hostel_building_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $building_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $building = DB::table('hostel_building_master')
            ->join('hostel_type_master', 'hostel_building_master.hostel_type_id', '=', 'hostel_type_master.id')
            ->join('hostel_master', 'hostel_building_master.hostel_id', '=', 'hostel_master.id')
            ->select('hostel_building_master.*', 'hostel_type_master.hostel_type as hostel_type_id',
                'hostel_master.name as hostel_name')
            ->where('hostel_building_master.sub_institute_id', '=', $sub_institute_id)->get();
        $building_data['status_code'] = 1;
        $building_data['data'] = $building;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_hostel_building", $building_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('hostel_management/add_hostel_building_master', ['hosteltype' => $data]);
    }

    public function store(Request $request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $building = new hostel_building_masterModel([
            'building_name'    => $request->get('building_name'),
            'hostel_type_id'   => $request->get('hostel_type_id'),
            'hostel_id'        => $request->get('hostel_id'),
            'sub_institute_id' => $sub_institute_id,
        ]);

        $building->save();
        $message['status_code'] = "1";
        $message['message'] = "Building Added Succesfully";

        $message = hostel_building_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_building_master.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = hostel_building_masterModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        view()->share('hosteltype', $editdata);

        return view('hostel_management/add_hostel_building_master', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = [
            'building_name'  => $request->get('building_name'),
            'hostel_type_id' => $request->get('hostel_type_id'),
            'hostel_id'      => $request->get('hostel_id'),
        ];

        hostel_building_masterModel::where(["id" => $id])->update($data);

        $message['status_code'] = "1";
        $message['message'] = "Building Updated Succesfully";
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_building_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        hostel_building_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message['message'] = "Building Deleted Succesfully";

        return is_mobile($type, "add_hostel_building_master.index", $message, "redirect");
    }
}
