<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_masterModel;
use App\Models\inventory\inventory_item_quotationModel;
use App\Models\inventory\inventory_vendor_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_item_quotationController extends Controller
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
        $syear = $request->session()->get('syear');

        $data = DB::table('inventory_item_quotation_details')
            ->leftjoin('inventory_item_master', 'inventory_item_quotation_details.item_id', '=',
                'inventory_item_master.id')
            ->join('inventory_vendor_master', 'inventory_item_quotation_details.vendor_id', '=',
                'inventory_vendor_master.id')
            ->join('inventory_requisition_status_master', 'inventory_requisition_status_master.id', '=',
                'inventory_item_quotation_details.approved_status')
            ->join('tbluser', 'tbluser.id', '=', 'inventory_item_quotation_details.approved_by')
            ->select('inventory_item_quotation_details.*', 'inventory_item_master.title as item_id',
                'inventory_vendor_master.vendor_name as vendor_id',
                'inventory_requisition_status_master.title as approved_status', 'tbluser.first_name',
                'tbluser.middle_name', 'tbluser.last_name')
            ->where([
                'inventory_item_quotation_details.sub_institute_id' => $sub_institute_id,
                'inventory_item_quotation_details.syear'            => $syear,
            ])->get();

        $item_data['status_code'] = 1;
        $item_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_item_quotation", $item_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = inventory_vendor_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();
        $itemdata = inventory_item_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        return view('inventory/add_inventory_item_quotation', ['menu' => $data, 'item_data' => $itemdata]);
    }

    public function store(Request $request)
    {

        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        for ($i = 0; $i < count($request->get('item')); $i++) {
            $total_price = ($request->get('price')[$i] * $request->get('qty')[$i]);

            $item_quotation = new inventory_item_quotationModel([
                'syear'                 => $syear,
                'sub_institute_id'      => $sub_institute_id,
                'item_id'               => $request->get('item')[$i],
                'vendor_id'             => $request->get('vendor_id'),
                'transportation_charge' => $request->get('transportation_charge'),
                'installation_charge'   => $request->get('installation_charge'),
                'qty'                   => $request->get('qty')[$i],
                'price'                 => $request->get('price')[$i],
                'total'                 => $total_price,
                'unit'                  => $request->get('unit')[$i],
                'tax'                   => $request->get('tax')[$i],
                'remarks'               => $request->get('remarks'),
                'approved_status'       => 2,
                'approved_date'         => date('Y-m-d'),
                'approved_by'           => $created_by,
                'created_by'            => $created_by,
                'created_on'            => date('Y-m-d H:i:s'),
                'created_ip_address'    => $_SERVER['REMOTE_ADDR'],
            ]);
            $item_quotation->save();
        }

        $message['status_code'] = "1";
        $message = [
            "message" => "Item Quotation Added Succesfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_item_quotation.index", $message, "redirect");

    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = inventory_item_quotationModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $editdata = inventory_vendor_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get();
        $editdata1 = inventory_item_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get();
        view()->share('menu', $editdata);
        view()->share('item_data', $editdata1);

        return view('inventory/add_inventory_item_quotation', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        $total_price = ($request->get('price')[0] * $request->get('qty')[0]);

        $data = [
            'item_id'               => $request->get('item')[0],
            'vendor_id'             => $request->get('vendor_id'),
            'transportation_charge' => $request->get('transportation_charge'),
            'installation_charge'   => $request->get('installation_charge'),
            'qty'                   => $request->get('qty')[0],
            'price'                 => $request->get('price')[0],
            'total'                 => $total_price,
            'unit'                  => $request->get('unit')[0],
            'tax'                   => $request->get('tax')[0],
            'remarks'               => $request->get('remarks'),
        ];
        inventory_item_quotationModel::where(["id" => $id])->update($data);

        $message['status_code'] = "1";
        $message = [
            "message" => "Item Quatation Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_inventory_item_quotation.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_item_quotationModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message = [
            "message" => "Item Quotation Deleted successfully",
        ];

        return is_mobile($type, "add_inventory_item_quotation.index", $message, "redirect");
    }
}
