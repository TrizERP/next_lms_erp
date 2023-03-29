<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_generate_poModel;
use App\Models\inventory\inventory_vendor_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_generate_poController extends Controller
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

        $data = DB::table('inventory_generate_po_details')
            ->join('inventory_vendor_master', 'inventory_generate_po_details.vendor_id', '=',
                'inventory_vendor_master.id')
            ->join('inventory_item_master', 'inventory_generate_po_details.item_id', '=', 'inventory_item_master.id')
            ->select('inventory_generate_po_details.*', 'inventory_vendor_master.vendor_name as vendor_id',
                'inventory_vendor_master.company_name', 'inventory_item_master.title as item_name')
            ->where([
                'inventory_generate_po_details.sub_institute_id' => $sub_institute_id,
                'inventory_generate_po_details.syear'            => $syear,
            ])->get();

        $item_data['status_code'] = 1;
        $item_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_generate_po", $item_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = inventory_vendor_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        $item_data = DB::table('inventory_item_quotation_details')
            ->join('inventory_item_master', 'inventory_item_quotation_details.item_id', '=', 'inventory_item_master.id')
            ->select('inventory_item_quotation_details.*', 'inventory_item_master.title as item_name')
            ->where([
                'inventory_item_quotation_details.sub_institute_id' => $sub_institute_id,
                'inventory_item_quotation_details.syear'            => $syear,
            ])->get();

        $GET_NO = inventory_generate_poModel::select(DB::raw('IFNULL(max(substring(po_number,6)),0) as LAST_REQ_NO'))->where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        $FORM_NO = $GET_NO[0]['LAST_REQ_NO'] + 1;

        if (strlen($FORM_NO) == 1) {
            $FORM_NO = $syear."/000".$FORM_NO;
        } else {
            if (strlen($FORM_NO) == 2) {
                $FORM_NO = $syear."/00".$FORM_NO;
            } else {
                if (strlen($FORM_NO) == 3) {
                    $FORM_NO = $syear."/0".$FORM_NO;
                }
            }
        }

        $data['menu'] = $data;
        $data['item_data'] = $item_data;
        $data['PO_NO'] = $FORM_NO;

        return view('inventory/add_inventory_generate_po', $data);
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        foreach ($request->get('chkbx_item_id_arr') as $i => $iValue) {
            $item_po = new inventory_generate_poModel([
                'syear'                 => $syear,
                'sub_institute_id'      => $sub_institute_id,
                'item_id'               => $request->get('chkbx_item_id_arr')[$i],
                'price'                 => $request->get('price')[$iValue],
                'qty'                   => $request->get('qty')[$iValue],
                'amount'                => $request->get('amount')[$iValue],
                'dis_per'               => $request->get('dis_per')[$iValue],
                'dis_amount_value'      => $request->get('dis_amount_value')[$iValue],
                'after_dis_amount'      => $request->get('after_dis_amount')[$iValue],
                'tax_per'               => $request->get('tax_per')[$iValue],
                'tax_amount_value'      => $request->get('tax_amount_value')[$iValue],
                'after_tax_amount'      => $request->get('after_tax_amount')[$iValue],
                'amount_per_item'       => (($request->get('price')[$iValue]) * ($request->get('qty')[$iValue])),
                'po_number'             => $request->get('po_number'),
                'vendor_id'             => $request->get('vendor_id'),
                'transportation_charge' => $request->get('transportation_charge'),
                'installation_charge'   => $request->get('installation_charge'),
                'delivery_time'         => $request->get('delivery_time'),
                'po_place_of_delivery'  => $request->get('po_place_of_delivery'),
                'payment_terms'         => $request->get('payment_terms'),
                'remarks'               => $request->get('remarks'),
                'created_by'            => $created_by,
                'created_on'            => date('Y-m-d H:i:s'),
                'created_ip_address'    => $_SERVER['REMOTE_ADDR'],

            ]);
            $item_po->save();
        }

        $message['status_code'] = "1";
//        $message = array(
//            "message" => "PO generated Successfully",
//        );
        $message = inventory_generate_poModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get();

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_generate_po.index", $message, "redirect");

    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = inventory_generate_poModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $editdata = inventory_vendor_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get();
        view()->share('menu', $editdata);

        $item_data = DB::table('inventory_generate_po_details')
            ->join('inventory_item_quotation_details', 'inventory_item_quotation_details.item_id', '=',
                'inventory_generate_po_details.item_id')
            ->join('inventory_item_master', 'inventory_item_quotation_details.item_id', '=', 'inventory_item_master.id')
            ->select('inventory_item_quotation_details.*', 'inventory_item_master.title as item_name')
            ->where([
                'inventory_item_quotation_details.sub_institute_id' => $sub_institute_id,
                'inventory_item_quotation_details.syear'            => $syear,
            ])
            ->where('inventory_generate_po_details.id', '=', $id)->get();

        return view('inventory/add_inventory_generate_po', ['data' => $data, 'item_data' => $item_data]);
    }

    public function update(Request $request, $id)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');
        foreach ($request->get('chkbx_item_id_arr') as $iValue) {
            $data = array(
                'syear'                 => $syear,
                'sub_institute_id'      => $sub_institute_id,
                'price'                 => $request->get('price')[$iValue],
                'qty'                   => $request->get('qty')[$iValue],
                'amount'                => $request->get('amount')[$iValue],
                'dis_per'               => $request->get('dis_per')[$iValue],
                'dis_amount_value'      => $request->get('dis_amount_value')[$iValue],
                'after_dis_amount'      => $request->get('after_dis_amount')[$iValue],
                'tax_per'               => $request->get('tax_per')[$iValue],
                'tax_amount_value'      => $request->get('tax_amount_value')[$iValue],
                'after_tax_amount'      => $request->get('after_tax_amount')[$iValue],
                'amount_per_item'       => (($request->get('price')[$iValue]) * ($request->get('qty')[$iValue])),
                'vendor_id'             => $request->get('vendor_id'),
                'transportation_charge' => $request->get('transportation_charge'),
                'installation_charge'   => $request->get('installation_charge'),
                'delivery_time'         => $request->get('delivery_time'),
                'po_place_of_delivery'  => $request->get('po_place_of_delivery'),
                'payment_terms'         => $request->get('payment_terms'),
                'remarks'               => $request->get('remarks'),
                'created_by'            => $created_by,
                'created_on'            => date('Y-m-d H:i:s'),
                'created_ip_address'    => $_SERVER['REMOTE_ADDR'],
            );
            inventory_generate_poModel::where(["id" => $id])->update($data);
        }

        $message['status_code'] = "1";
        $message = [
            "message" => "PO Updated Successfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_generate_po.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_generate_poModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "PO Deleted successfully",
        ];

        return is_mobile($type, "add_inventory_generate_po.index", $message, "redirect");
    }
}
