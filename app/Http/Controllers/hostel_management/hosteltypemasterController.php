<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\hosteltypemasterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class hosteltypemasterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $hostel_data['message'] = $data_arr['message'];
            }
        }
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $hostel_data['status_code'] = 1;
        $hostel_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_hostel_type", $hostel_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('hostel_management/add_hostel_type_master', ['menu' => $data]);
    }

    public function getData(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();
    }

    public function store(Request $request)
    {
        ValidateInsertData('hostel_type_master', $request);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $hostel_type = new hosteltypemasterModel([
            'hostel_type'      => $request->get('hostel_type'),
            'status'           => $request->get('status'),
            'description'      => $request->get('description'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $hostel_type->save();

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Hostel type Added Succesfully",
//        ];
        $message = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $type = $request->input('type');

        return is_mobile($type, "add_hostel_type_master.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = hosteltypemasterModel::find($id);

        return is_mobile($type, "hostel_management/add_hostel_type_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('hostel_type_master', 'update');

        $data = [
            'hostel_type' => $request->get('hostel_type'),
            'status'      => $request->get('status'),
            'description' => $request->get('description'),
        ];

        hosteltypemasterModel::where(["id" => $id])->update($data);

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_hostel_type_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        hosteltypemasterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_hostel_type_master.index", $message, "redirect");
    }
}
