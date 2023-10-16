<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_category_masterModel;
use App\Models\inventory\inventory_item_direct_purchaseModel;
use App\Models\inventory\inventory_item_masterModel;
use App\Models\inventory\inventory_item_sub_category_masterModel;
use App\Models\inventory\inventory_master_setupModel;
use App\Models\inventory\inventory_vendor_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_item_direct_purchaseController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $inventory['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $data = DB::table("inventory_item_direct_purchase as idp")
            ->join('inventory_vendor_master as iv', function ($join) {
                $join->whereRaw("iv.id = idp.vendor_id AND iv.sub_institute_id = idp.sub_institute_id");
            })
            ->join('inventory_item_category_master as ic', function ($join) {
                $join->whereRaw("ic.id = idp.category_id AND ic.sub_institute_id = idp.sub_institute_id");
            })
            ->join('inventory_item_sub_category_master as ics', function ($join) {
                $join->whereRaw("ics.id = idp.sub_category_id AND ics.sub_institute_id = idp.sub_institute_id");
            })
            ->join('inventory_item_master as im', function ($join) {
                $join->whereRaw("im.id = idp.item_id AND im.sub_institute_id = idp.sub_institute_id");
            })
            ->join('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = idp.created_by AND tu.sub_institute_id = idp.sub_institute_id");
            })
            ->selectRaw('idp.*,iv.vendor_name,ic.title AS catergory_name,ics.title AS sub_catergory_name,im.title AS item_name,
                CONCAT_WS(" ",tu.first_name,tu.middle_name,tu.last_name) AS created_by')
            ->where("idp.sub_institute_id", "=", $sub_institute_id)
            ->where("idp.syear", "=", $syear)
            ->get()->toArray();

        $inventory['status_code'] = 1;
        $inventory['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_item_direct_purchase", $inventory, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $vendor_data = inventory_vendor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();//,'syear' => $syear
        $category_data = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray(); //,'syear' => $syear
        $item_data = DB::table('inventory_item_master')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('item_status', 'Active')
            ->get()->toArray();

        $item_setting_data = inventory_master_setupModel::where(['sub_institute_id' => $sub_institute_id])
            ->get()->toArray(); //,'syear' => $syear

        $item_setting_data_value = $item_setting_data[0]['ITEM_SETTING_FOR_REQUISITION'] ?? [];

        $data['vendor_data'] = $vendor_data;
        $data['item_setting_data_value'] = $item_setting_data_value;
        $data['category_data'] = $category_data;
        $data['sub_category_data'] = [];
        $data['item_data'] = [];
        $data['menu1'] = $item_data;

        return view('inventory/add_inventory_item_direct_purchase', $data);
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');
        $created_on = date('Y-m-d H:i:s');
        $created_ip = $_SERVER['REMOTE_ADDR'];
        $items = $request->get('item_id');

        foreach ($items as $key => $val) {
            $category_id = $request->input("category_id.$key", null);
            $sub_category_id = $request->input("sub_category_id.$key", null);
            $item_id = $request->input("item_id.$key", null);
            $item_qty = $request->input("item_qty.$key", null);
            $price = $request->input("price.$key", null);
            $amount = $request->input("amount.$key", null);

            $item_direct_purchase = new inventory_item_direct_purchaseModel([
                'vendor_id'        => $request->get('vendor_id'),
                'category_id'      => $category_id,
                'sub_category_id'  => $sub_category_id,
                'item_id'          => $item_id,
                'item_qty'         => $item_qty,
                'price'            => $price,
                'amount'           => $amount,
                'challan_no'       => $request->get('challan_no'),
                'challan_date'     => $request->get('challan_date'),
                'bill_no'          => $request->get('bill_no'),
                'bill_date'        => $request->get('bill_date'),
                'remarks'          => $request->get('remarks'),
                'created_by'       => $created_by,
                'created_on'       => $created_on,
                'created_ip'       => $created_ip,
                'sub_institute_id' => $sub_institute_id,
                'syear'            => $syear,
            ]);

            $item_direct_purchase->save();

            $get_item_data = inventory_item_masterModel::where([
                "id" => $request->get('item_id')[$key], 'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
            ])->first();

            if (!empty($get_item_data)) {
                $opening_stock = ($get_item_data->opening_stock + $request->get('item_qty')[$key]);

                $item_stock = [
                    'opening_stock'         => $opening_stock,
                    'direct_purchase_stock' => $request->get('item_qty')[$key],
                ];

                inventory_item_masterModel::where(["id" => $request->get('item_id')[$key]])->update($item_stock);
            }
        }

        $message['status_code'] = "1";
        $message['message'] = "Item Direct Purchase Added Succesfully";
        $type = $request->input('type');

        return is_mobile($type, "add_item_direct_purchase.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = inventory_item_direct_purchaseModel::select('*')
            ->where([
                'inventory_item_direct_purchase.id'               => $id,
                'inventory_item_direct_purchase.sub_institute_id' => $sub_institute_id,
            ])
            ->get();
        $data = $data[0];

        $editdata = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $editdata1 = inventory_item_sub_category_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'category_id' => $data['category_id'],
        ])->get();

        $item_data = DB::table('inventory_item_master')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('item_status', 'Active')
            ->where('category_id', $data['category_id'])
            ->where('sub_category_id', $data['sub_category_id'])
            ->get()->toArray();

        $vendor_data = inventory_vendor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();//,'syear' => $syear

        $item_setting_data = inventory_master_setupModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])
            ->get()->toArray();
        $item_setting_data_value = $item_setting_data[0]['ITEM_SETTING_FOR_REQUISITION'];

        view()->share('item_setting_data_value', $item_setting_data_value);
        view()->share('vendor_data', $vendor_data);
        view()->share('category_data', $editdata);
        view()->share('sub_category_data', $editdata1);
        view()->share('item_data', $item_data);
        view()->share('menu1', $item_data);

        return view('inventory/add_inventory_item_direct_purchase', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $items = $request->get('item_id');

        foreach ($items as $key => $val) {

            $category_id = isset($category_ids[$key]) ? $category_ids[$key] : null;
            $sub_category_id = isset($sub_category_id[$key]) ? $sub_category_id[$key] : null;
            $item_id = isset($item_id[$key]) ? $item_id[$key] : null;
            $item_qty = isset($item_qty[$key]) ? $item_qty[$key] : null;
            $price = isset($price[$key]) ? $price[$key] : null;
            $amount = isset($amount[$key]) ? $amount[$key] : null;
            
            $data = [
                'vendor_id'       => $request->get('vendor_id'),
                'category_id'      => $category_id,
                'sub_category_id'  => $sub_category_id,
                'item_id'          => $item_id,
                'item_qty'         => $item_qty,
                'price'            => $price,
                'amount'           => $amount,
                'challan_no'      => $request->get('challan_no'),
                'challan_date'    => $request->get('challan_date'),
                'bill_no'         => $request->get('bill_no'),
                'bill_date'       => $request->get('bill_date'),
                'remarks'         => $request->get('remarks'),
            ];

            inventory_item_direct_purchaseModel::where(["id" => $id])->update($data);

            /* $get_item_data = inventory_item_masterModel::where([
                "id" => $request->get('item_id')[$key], 'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
            ])->get()->toArray(); */

            $get_item_data = inventory_item_masterModel::where([
                "id" => $request->get('item_id')[$key], 'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
            ])->first();

            if (!empty($get_item_data)) 
            {
                $opening_stock = ($get_item_data[0]['opening_stock'] - $get_item_data[0]['direct_purchase_stock'] +
                    $request->get('item_qty')[$key]);

                $item_stock = [
                    'opening_stock'         => $opening_stock,
                    'direct_purchase_stock' => $request->get('item_qty')[$key],
                ];

                inventory_item_masterModel::where(["id" => $request->get('item_id')[$key]])->update($item_stock);
            }
        }

        $message['status_code'] = "1";
        $message['message'] = "Item Direct Purchase Updated Successfully";
        $type = $request->input('type');

        return is_mobile($type, "add_item_direct_purchase.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_item_direct_purchaseModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message['message'] = "Item Direct Purchase Deleted successfully";

        return is_mobile($type, "add_item_direct_purchase.index", $message, "redirect");

    }
}
