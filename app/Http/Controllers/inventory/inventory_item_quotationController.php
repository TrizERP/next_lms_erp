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
        if (session()->has('data')) {
            $data_arr = session('data');
            if (isset($data_arr['message'])) {
                $item_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = DB::table('inventory_item_quotation_details')
            ->leftJoin('inventory_item_master', 'inventory_item_quotation_details.item_id', '=', 'inventory_item_master.id')
            ->join('inventory_vendor_master', 'inventory_item_quotation_details.vendor_id', '=', 'inventory_vendor_master.id')
            ->join('inventory_requisition_status_master', 'inventory_requisition_status_master.id', '=', 'inventory_item_quotation_details.approved_status')
            ->join('tbluser', 'tbluser.id', '=', 'inventory_item_quotation_details.approved_by')
            ->select(
                'inventory_item_quotation_details.*',
                'inventory_item_master.title as item_id',
                'inventory_vendor_master.vendor_name as vendor_id',
                'inventory_requisition_status_master.title as approved_status',
                'tbluser.first_name', 'tbluser.middle_name', 'tbluser.last_name'
            )
            ->where([
                'inventory_item_quotation_details.sub_institute_id' => $sub_institute_id,
                'inventory_item_quotation_details.syear' => $syear
            ])
            ->get();

        $item_data['status_code'] = 1;
        $item_data['data'] = $data;

        return is_mobile($request->input('type'), "inventory/show_inventory_item_quotation", $item_data, "view");
    }


    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $vendors = inventory_vendor_masterModel::where([
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
        ])->get();

        $items = inventory_item_masterModel::where([
            'syear' => $syear,
        ])->get();

        $unit_data = DB::table('master_setup_select')
            ->where('type', 'item_unit')
            ->get();

        return view('inventory/add_inventory_item_quotation', [
            'menu' => $vendors,
            'item_data' => $items,
            'unit_data' => $unit_data
        ]);
    }


    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        // SAFE ARRAY FETCHING
        $items  = $request->item ?? [];
        $qtys   = $request->qty ?? [];
        $prices = $request->price ?? [];
        $units  = $request->unit ?? [];
        $taxes  = $request->tax ?? [];

        if (count($items) === 0) {
            return back()->with('message', 'Please add at least one item.');
        }

        foreach ($items as $i => $item_id) {

            $qty   = $qtys[$i] ?? 0;
            $price = $prices[$i] ?? 0;
            $unit  = $units[$i] ?? '';
            $tax   = $taxes[$i] ?? 0;

            $total = $qty * $price;

            inventory_item_quotationModel::create([
                'syear'                 => $syear,
                'sub_institute_id'      => $sub_institute_id,
                'item_id'               => $item_id,
                'vendor_id'             => $request->vendor_id,
                'transportation_charge' => $request->transportation_charge,
                'installation_charge'   => $request->installation_charge,
                'qty'                   => $qty,
                'price'                 => $price,
                'total'                 => $total,
                'unit'                  => $unit,
                'tax'                   => $tax,
                'remarks'               => $request->remarks,
                'approved_status'       => 2,
                'approved_date'         => date('Y-m-d'),
                'approved_by'           => $created_by,
                'created_by'            => $created_by,
                'created_on'            => now(),
                'created_ip_address'    => $request->ip(),
            ]);
        }

        return is_mobile(
            $request->input('type'),
            "add_inventory_item_quotation.index",
            ["message" => "Item Quotation Added Successfully"],
            "redirect"
        );
    }


    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = inventory_item_quotationModel::find($id);

        return view('inventory/add_inventory_item_quotation', [
            'data' => $data,
            'menu' => inventory_vendor_masterModel::where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear
            ])->get(),
            'item_data' => inventory_item_masterModel::where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear
            ])->get(),
        ]);
    }


    public function update(Request $request, $id)
    {
        $qty   = $request->qty[0] ?? 0;
        $price = $request->price[0] ?? 0;

        $data = [
            'item_id'               => $request->item[0],
            'vendor_id'             => $request->vendor_id,
            'transportation_charge' => $request->transportation_charge,
            'installation_charge'   => $request->installation_charge,
            'qty'                   => $qty,
            'price'                 => $price,
            'total'                 => $qty * $price,
            'unit'                  => $request->unit[0] ?? '',
            'tax'                   => $request->tax[0] ?? 0,
            'remarks'               => $request->remarks,
        ];

        inventory_item_quotationModel::where("id", $id)->update($data);

        return is_mobile(
            $request->input('type'),
            "add_inventory_item_quotation.index",
            ["message" => "Item Quotation Updated Successfully"],
            "redirect"
        );
    }


    public function destroy(Request $request, $id)
    {
        inventory_item_quotationModel::where("id", $id)->delete();

        return is_mobile(
            $request->input('type'),
            "add_inventory_item_quotation.index",
            ["message" => "Item Quotation Deleted Successfully"],
            "redirect"
        );
    }
}
