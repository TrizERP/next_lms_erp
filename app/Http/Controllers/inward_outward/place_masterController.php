<?php

namespace App\Http\Controllers\inward_outward;

use App\Http\Controllers\Controller;
use App\Models\inward_outward\place_masterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class place_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $place_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $place_data['status_code'] = 1;
        $place_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inward_outward/show_place_master", $place_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('inward_outward/add_place_master', ['menu' => $data]);
    }

    public function store(Request $request)
    {

        ValidateInsertData('place_master', $request);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $place = new place_masterModel([
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $place->save();

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Place Master Added Succesfully",
//        ];
        $message = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_place_master.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = place_masterModel::find($id);

        return is_mobile($type, "inward_outward/add_place_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('place_master', 'update');

        $data = [
            'title'       => $request->get('title'),
            'description' => $request->get('description'),
            // 'sub_institute_id' => "1",
        ];
        place_masterModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1";
        $message = [
            "message" => "Data Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_place_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        place_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_place_master.index", $message, "redirect");
    }
}
