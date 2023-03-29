<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\admission_category_masterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class admission_category_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $admission_data['message'] = $data_arr['message'];
            }
        }
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = admission_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $admission_data['status_code'] = 1;
        $admission_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_admission_category", $admission_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = admission_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('hostel_management/add_admission_category_master', ['menu' => $data]);
    }

    public function getData()
    {
        return admission_category_masterModel::orderBy('id')->get();
    }

    public function store(Request $request)
    {

        ValidateInsertData('admission_category_master', $request);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $admission = new admission_category_masterModel([
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'sub_institute_id' => $sub_institute_id,
        ]);
        $admission->save();
        $message['status_code'] = "1";
//        $message = array(
//            "message" => "Admission category Added Succesfully",
//        );

        $message = admission_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_admission_category_master.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = admission_category_masterModel::find($id);

        return is_mobile($type, "hostel_management/add_admission_category_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('admission_category_master', 'update');

        $data = [
            'title'       => $request->get('title'),
            'description' => $request->get('description'),
        ];

        admission_category_masterModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1";
        $message = [
            "message" => "Data Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_admission_category_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        admission_category_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_admission_category_master.index", $message, "redirect");
    }
}
