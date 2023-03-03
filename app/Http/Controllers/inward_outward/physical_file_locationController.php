<?php

namespace App\Http\Controllers\inward_outward;

use App\Http\Controllers\Controller;
use App\Models\inward_outward\physical_file_locationModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class physical_file_locationController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $physical_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $physical_data['status_code'] = 1;
        $physical_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inward_outward/show_physical_file_location", $physical_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('inward_outward/add_physical_file_location', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        ValidateInsertData('physical_file_location', $request);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $physical = new physical_file_locationModel([
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'file_code'        => $request->get('file_code'),
            'file_location'    => $request->get('file_location'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $physical->save();

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Physical file location Added Succesfully",
//        ];

        $message = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_physical_file_location.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = physical_file_locationModel::find($id);

        return is_mobile($type, "inward_outward/add_physical_file_location", $data, "view");
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('physical_file_location', 'update');

        $data = [
            'title'         => $request->get('title'),
            'description'   => $request->get('description'),
            'file_code'     => $request->get('file_code'),
            'file_location' => $request->get('file_location'),
        ];

        physical_file_locationModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1";
        $message = [
            "message" => "Data Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_physical_file_location.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        physical_file_locationModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_physical_file_location.index", $message, "redirect");
    }
}
