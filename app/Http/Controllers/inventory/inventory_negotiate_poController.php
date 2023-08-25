<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_generate_poModel;
use App\Models\inventory\inventory_negotiate_poModel;
use App\Models\inventory\inventory_status_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_negotiate_poController extends Controller
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

        $data = DB::table("inventory_generate_po_details")
            ->join('inventory_vendor_master', function ($join) {
                $join->whereRaw("`inventory_generate_po_details`.`vendor_id` = `inventory_vendor_master`.`id`");
            })
            ->join('inventory_item_master', function ($join) {
                $join->whereRaw("`inventory_generate_po_details`.`item_id` = `inventory_item_master`.`id`");
            })
            ->leftJoin('inventory_negotiate_po_details', function ($join) {
                $join->whereRaw("`inventory_generate_po_details`.`po_number` = `inventory_negotiate_po_details`.`po_number`");
            })
            ->leftJoin('tbluser', function ($join) {
                $join->whereRaw("`tbluser`.`id` = `inventory_generate_po_details`.`po_approved_by`");
            })
            ->leftJoin('inventory_requisition_status_master', function ($join) {
                $join->whereRaw("`inventory_generate_po_details`.`po_approval_status` = `inventory_requisition_status_master`.`id`");
            })
            ->selectRaw('inventory_generate_po_details.*, inventory_vendor_master.vendor_name AS vendor_id, 
                inventory_vendor_master.company_name, inventory_item_master.title AS item_name,
                 inventory_requisition_status_master.title AS po_approval_status, 
                concat_ws(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name) AS po_approved_by')
            ->where("inventory_generate_po_details.sub_institute_id", "=", $sub_institute_id)
            ->where("inventory_generate_po_details.syear", "=", $syear)
            ->groupby('inventory_generate_po_details.id')
            ->get()->toArray();

        $item_data['status_code'] = 1;
        $item_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_negotiate_po", $item_data, "view");
    }

    public function create(Request $request)
    {
        //code here
    }

    public function store(Request $request)
    {
        // dd($request);
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        $data = array(
            'po_approval_status' => $request->get('po_approval_status'),
            'po_approval_remark' => $request->get('po_approval_remark'),
            'po_approved_by'     => $created_by,
            'po_approved_date'   => date('Y-m-d H:i:s'),
        );
        inventory_generate_poModel::where([
            "po_number"        => $request->get('po_number'),
            "sub_institute_id" => $sub_institute_id, "syear" => $syear,
        ])
            ->update($data);

        foreach ($request->get('chkbx_item_id_arr') as $i => $iValue) {
            $check_sql = inventory_negotiate_poModel::where([
                'po_number'        => $request->get('po_number'),
                'item_id'          => $request->get('chkbx_item_id_arr')[$i],
                'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
            ]);

            $check_data = json_decode(json_encode($check_sql), true);

            if (count($check_data) == 0) {
                $negotiate_po = new inventory_negotiate_poModel([
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
                    'po_approval_status'    => $request->get('po_approval_status'),
                    'po_approval_remark'    => $request->get('po_approval_remark'),
                    'po_approved_by'        => $created_by,
                    'po_approved_date'      => date('Y-m-d H:i:s'),
                    'created_by'            => $created_by,
                    'created_on'            => date('Y-m-d H:i:s'),
                    'created_ip_address'    => $_SERVER['REMOTE_ADDR'],
                ]);
                $negotiate_po->save();
            } else {
                $negotiate_po = [
                    'price'              => $request->get('price')[$iValue],
                    'qty'                => $request->get('qty')[$iValue],
                    'amount'             => $request->get('amount')[$iValue],
                    'dis_per'            => $request->get('dis_per')[$iValue],
                    'dis_amount_value'   => $request->get('dis_amount_value')[$iValue],
                    'after_dis_amount'   => $request->get('after_dis_amount')[$iValue],
                    'tax_per'            => $request->get('tax_per')[$iValue],
                    'tax_amount_value'   => $request->get('tax_amount_value')[$iValue],
                    'after_tax_amount'   => $request->get('after_tax_amount')[$iValue],
                    'amount_per_item'    => (($request->get('price')[$iValue]) * ($request->get('qty')[$iValue])),
                    'po_approval_status' => $request->get('po_approval_status'),
                    'po_approval_remark' => $request->get('po_approval_remark'),
                    'po_approved_by'     => $created_by,
                    'po_approved_date'   => date('Y-m-d H:i:s'),
                ];
                inventory_negotiate_poModel::where([
                    "po_number"        => $request->get('po_number'),
                    "item_id"          => $request->get('chkbx_item_id_arr')[$i],
                    "sub_institute_id" => $sub_institute_id, "syear" => $syear,
                ])->update($negotiate_po);
            }
        }

        $message['status_code'] = "1";
        $message = [
            "message" => "Negotiate PO Successfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_inventory_negotiate_po.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = inventory_negotiate_poModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $po_data = inventory_generate_poModel::where(['id' => $id])->get()->toArray();
        $po_number = $po_data[0]['po_number'];

        $generate_po_data = DB::table("inventory_generate_po_details as gp")
            ->join('inventory_vendor_master as vm', function ($join) {
                $join->whereRaw("vm.id = gp.vendor_id AND vm.sub_institute_id = gp.sub_institute_id");
            })
            ->join('inventory_item_master as im', function ($join) {
                $join->whereRaw("im.id = gp.item_id");
            })
            ->selectRaw('gp.id,gp.syear,gp.po_number,vm.vendor_name,gp.delivery_time,gp.po_place_of_delivery,gp.payment_terms,
                gp.remarks,im.title AS item_name,gp.price,gp.qty,gp.amount,gp.dis_per,gp.dis_amount_value,gp.after_dis_amount,gp.tax_per,
                gp.tax_amount_value,gp.after_tax_amount,gp.amount_per_item,gp.transportation_charge,gp.installation_charge,gp.vendor_id')
            ->where("gp.po_number", "=", $po_number)
            ->where("gp.sub_institute_id", "=", $sub_institute_id)
            ->get()->toArray();

        $generate_po_data = json_decode(json_encode($generate_po_data), true);

        $status_data = inventory_status_masterModel::get()->toArray();

        $new_query = "SELECT `inventory_item_quotation_details`.*, `inventory_item_master`.`title` AS `item_name`
           FROM `inventory_item_quotation_details`
           LEFT JOIN `inventory_negotiate_po_details` ON `inventory_item_quotation_details`.`item_id` =                  `inventory_negotiate_po_details`.`item_id`
           INNER JOIN `inventory_item_master` ON `inventory_item_quotation_details`.`item_id` = `inventory_item_master`.`id`
           WHERE `inventory_item_quotation_details`.`sub_institute_id` = '$sub_institute_id' OR `inventory_negotiate_po_details`.`id` = '$id'";

        $item_data = DB::table("inventory_item_quotation_details")
            ->leftJoin('inventory_negotiate_po_details', function ($join) {
                $join->whereRaw("inventory_item_quotation_details.item_id = inventory_negotiate_po_details.item_id");
            })
            ->join('inventory_item_master', function ($join) {
                $join->whereRaw("`inventory_item_quotation_details`.`item_id` = `inventory_item_master`.`id`");
            })
            ->selectRaw('inventory_item_quotation_details.*, `inventory_item_master`.`title` AS `item_name`')
            ->where(function ($q) use ($sub_institute_id, $id) {
                $q->where("`inventory_item_quotation_details`.`sub_institute_id`", $sub_institute_id)
                    ->orWhere("`inventory_negotiate_po_details`.`id`", $id);
            })
            ->get()->toArray();

        return view('inventory/add_inventory_negotiate_po', [
            'data'             => $data, 'item_data' => $item_data, 'status_data' => $status_data,
            'generate_po_data' => $generate_po_data,
        ]);
    }

    public function update(Request $request, $id)
    {
        //

    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        inventory_negotiate_poModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "Negotiate PO Deleted successfully",
        ];

        return is_mobile($type, "add_inventory_negotiate_po.index", $message, "redirect");
    }
}
