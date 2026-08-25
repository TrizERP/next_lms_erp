<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\inventory\inventory_master_setupModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

class inventory_master_setupController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $inventory_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = inventory_master_setupModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get();

        $inventory_data['status_code'] = 1;
        $inventory_data['data'] = $data;

        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_master_setup", $inventory_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = inventory_master_setupModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        return view('inventory/add_inventory_master_setup', ['menu' => $data]);
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $validator = Validator::make($request->all(), [
            'PO_NO_PREFIX' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            $message['status_code'] = "0";
            $message['message'] = $validator->messages()->first();
            $type = $request->input('type');

            return is_mobile($type, "add_inventory_master_setup.index", $message, "redirect");
        }

        $newfilename = "";
        if ($request->hasFile('LOGO')) {
            $img = $request->file('LOGO');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'inventorymaster_'.date('Y-m-d_h-i-s').'.'.$ext;
            $img->storeAs('public/inventory_master/', $newfilename);
        }

        $inventory = new inventory_master_setupModel([
            'SYEAR'                        => $syear,
            'GST_REGISTRATION_NO'          => $request->get('GST_REGISTRATION_NO'),
            'GST_REGISTRATION_DATE'        => $request->get('GST_REGISTRATION_DATE'),
            'CST_REGISTRATION_NO'          => $request->get('CST_REGISTRATION_NO'),
            'CST_REGISTRATION_DATE'        => $request->get('CST_REGISTRATION_DATE'),
            'LOGO'                         => $newfilename,
            'PO_NO_PREFIX'                 => $request->get('PO_NO_PREFIX'),
            'ITEM_SETTING_FOR_REQUISITION' => $request->get('ITEM_SETTING_FOR_REQUISITION'),
            'SUB_INSTITUTE_ID'             => $sub_institute_id,
        ]);
        $inventory->save();

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'inventory_master_setup_store',
            'entity_type' => 'inventory_master_setup',
            'entity_id'   => $inventory->id,
            'new_values'  => $request->except(['_token', 'LOGO']),
        ]);

        $message['status_code'] = "1";
//        $message = [
//            "message" => "Inventory Setup Details Added Succesfully",
//        ];
        $message = inventory_master_setupModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');

        return is_mobile($type, "add_inventory_master_setup.index", $message, "redirect");

    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = inventory_master_setupModel::find($id);

        return is_mobile($type, "inventory/add_inventory_master_setup", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $validator = Validator::make($request->all(), [
            'PO_NO_PREFIX' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            $message['status_code'] = "0";
            $message['message'] = $validator->messages()->first();
            $type = $request->input('type');

            return is_mobile($type, "add_inventory_master_setup.index", $message, "redirect");
        }

        $inventory = [
            'GST_REGISTRATION_NO'          => $request->get('GST_REGISTRATION_NO'),
            'GST_REGISTRATION_DATE'        => $request->get('GST_REGISTRATION_DATE'),
            'CST_REGISTRATION_NO'          => $request->get('CST_REGISTRATION_NO'),
            'CST_REGISTRATION_DATE'        => $request->get('CST_REGISTRATION_DATE'),
            'PO_NO_PREFIX'                 => $request->get('PO_NO_PREFIX'),
            'ITEM_SETTING_FOR_REQUISITION' => $request->get('ITEM_SETTING_FOR_REQUISITION'),
        ];

        if ($request->hasFile('LOGO')) {
            unlink('storage/inventory_master'.$request->input('hid_logo'));
            $img = $request->file('LOGO');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'inventorymaster_'.date('Y-m-d_h-i-s').'.'.$ext;
            $img->storeAs('public/inventory_master/', $newfilename);
            $inventory['LOGO'] = $newfilename;
        }

        inventory_master_setupModel::where(["ID" => $id])->update($inventory);

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'inventory_master_setup_update',
            'entity_type' => 'inventory_master_setup',
            'entity_id'   => $id,
            'new_values'  => $inventory,
        ]);

        $message['status_code'] = "1";
        $message = [
            "message" => "Inventory Setup Details Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_inventory_master_setup.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_master_setupModel::where(["ID" => $id])->delete();

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'inventory_master_setup_delete',
            'entity_type' => 'inventory_master_setup',
            'entity_id'   => $id,
        ]);

        $message['status_code'] = "1";
        $message = [
            "message" => "Inventory Setup Details Deleted Successfully",
        ];

        return is_mobile($type, "add_inventory_master_setup.index", $message, "redirect");

    }
}
