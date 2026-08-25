<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\inventory\inventory_tax_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

class inventory_tax_masterController extends Controller
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
        $data = inventory_tax_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $item_data['status_code'] = 1;
        $item_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_item_tax", $item_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = inventory_tax_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('inventory/add_inventory_item_tax_master', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            'amount_percentage' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $message['status_code'] = "0";
            $message['message'] = $validator->messages()->first();
            $type = $request->input('type');

            return is_mobile($type, "add_inventory_item_tax_master.index", $message, "redirect");
        }

        $item_category = new inventory_tax_masterModel([
            'syear'              => $syear,
            'title'              => $request->get('title'),
            'amount_percentage'  => $request->get('amount_percentage'),
            'description_1'      => $request->get('description_1'),
            'status'             => $request->get('status'),
            'sort_order'         => $request->get('sort_order'),
            'created_by'         => $created_by,
            'created_on'         => date('Y-m-d'),
            'created_ip_address' => $_SERVER['REMOTE_ADDR'],
            'sub_institute_id'   => $sub_institute_id,
        ]);
        $item_category->save();

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'inventory_tax_master_store',
            'entity_type' => 'inventory_tax_master',
            'entity_id'   => $item_category->id,
            'new_values'  => $request->except(['_token']),
        ]);

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Item Tax Added Succesfully",
//        ];
        $message = inventory_tax_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_item_tax_master.index", $message, "redirect");

    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = inventory_tax_masterModel::find($id);

        return is_mobile($type, "inventory/add_inventory_item_tax_master", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            'amount_percentage' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $message['status_code'] = "0";
            $message['message'] = $validator->messages()->first();
            $type = $request->input('type');

            return is_mobile($type, "add_inventory_item_tax_master.index", $message, "redirect");
        }

        $data = [
            'syear'              => $syear,
            'title'              => $request->get('title'),
            'amount_percentage'  => $request->get('amount_percentage'),
            'description_1'      => $request->get('description_1'),
            'status'             => $request->get('status'),
            'sort_order'         => $request->get('sort_order'),
            'created_by'         => $created_by,
            'created_on'         => date('Y-m-d'),
            'created_ip_address' => $_SERVER['REMOTE_ADDR'],
            'sub_institute_id'   => $sub_institute_id,
        ];

        inventory_tax_masterModel::where(["id" => $id])->update($data);

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'inventory_tax_master_update',
            'entity_type' => 'inventory_tax_master',
            'entity_id'   => $id,
            'new_values'  => $data,
        ]);

        $message['status_code'] = "1";
        $message = [
            "message" => "Item Tax Updated Successfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_item_tax_master.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_tax_masterModel::where(["id" => $id])->delete();

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'inventory_tax_master_delete',
            'entity_type' => 'inventory_tax_master',
            'entity_id'   => $id,
        ]);

        $message['status_code'] = "1";
        $message = [
            "message" => "Item Tax Deleted successfully",
        ];

        return is_mobile($type, "add_inventory_item_tax_master.index", $message, "redirect");

    }
}
