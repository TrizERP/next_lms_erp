<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_category_masterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class inventory_item_category_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $item_data['message'] = $data_arr['message'];
            }
        }
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get(); //'syear'=>$syear]
        $item_data['status_code'] = 1;
        $item_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_item_category", $item_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('inventory/add_inventory_item_category_master', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $item_category = new inventory_item_category_masterModel([
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'status'           => $request->get('status'),
            'sub_institute_id' => $sub_institute_id,
        ]);

        $item_category->save();

        $message['status_code'] = "1";
//        $message = array(
//            "message" => "Item Category Added Succesfully",
//        );
        $message = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_item_category_master.index", $message, "redirect");

    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = inventory_item_category_masterModel::find($id);

        return is_mobile($type, "inventory/add_inventory_item_category_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = [
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'status'           => $request->get('status'),
            'sub_institute_id' => $sub_institute_id,
        ];


        inventory_item_category_masterModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1";
        $message = [
            "message" => "Item Category Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_inventory_item_category_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_item_category_masterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Item Category Deleted successfully",
        ];

        return is_mobile($type, "add_inventory_item_category_master.index", $message, "redirect");
    }
}
