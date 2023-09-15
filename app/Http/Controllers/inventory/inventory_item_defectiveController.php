<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_defectiveModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class inventory_item_defectiveController extends Controller
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

        $item = DB::select("SELECT ir.*,im.title AS item_name,concat_ws(' ',tu.first_name,tu.middle_name,tu.last_name) as created_by
                FROM inventory_item_defective_details ir
                INNER JOIN inventory_item_master im ON im.id = ir.ITEM_ID AND im.sub_institute_id = ir.SUB_INSTITUTE_ID
                INNER JOIN tbluser tu ON tu.id = ir.CREATED_BY
                WHERE ir.sub_institute_id = '".$sub_institute_id."' AND ir.syear = '".$syear."' ");

        $item = DB::table("inventory_item_defective_details as ir")
            ->join('inventory_item_master as im', function ($join) {
                $join->whereRaw("im.id = ir.ITEM_ID AND im.sub_institute_id = ir.SUB_INSTITUTE_ID");
            })
            ->join('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = ir.CREATED_BY");
            })
            ->selectRaw('ir.*,im.title AS item_name,concat_ws(" ",tu.first_name,tu.middle_name,tu.last_name) as created_by')
            ->where("ir.sub_institute_id", "=", $sub_institute_id)
            ->where("ir.syear", "=", $syear)
            ->get()->toArray();

        $item_data['status_code'] = 1;
        $item_data['data'] = $item;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_inventory_item_defective", $item_data, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = inventory_item_defectiveModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        $data_1 = DB::table("inventory_item_receivable_details as ir")
            ->join('inventory_item_master as im', function ($join) {
                $join->whereRaw("im.id = ir.ITEM_ID");
            })
            ->selectRaw('ir.ITEM_ID,im.title AS item_name')
            ->where("ir.SUB_INSTITUTE_ID", "=", $sub_institute_id)
            ->where("ir.SYEAR", "=", $syear)
            ->get()->toArray();

        $data_1 = json_decode(json_encode($data_1), true);

        $data['menu'] = $data_1;

        return view('inventory/add_inventory_item_defective', $data);
    }


    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $created_by = $request->session()->get('user_id');
        $created_ip_address = $_SERVER['REMOTE_ADDR'];

        $item_defective = new inventory_item_defectiveModel([
            'SYEAR'                   => $syear,
            'SUB_INSTITUTE_ID'        => $sub_institute_id,
            'ITEM_ID'                 => $request->get('item_id'),
            'WARRANTY_START_DATE'     => $request->get('warranty_start_date'),
            'WARRANTY_END_DATE'       => $request->get('warranty_end_date'),
            'DEFECT_REMARKS'          => $request->get('defect_remarks'),
            'ITEM_GIVEN_TO'           => $request->get('item_given_to'),
            'ESTIMATED_RECEIVED_DATE' => $request->get('estimated_received_date'),
            'CREATED_BY'              => $created_by,
            'CREATED_ON'              => date('Y-m-d H:i:s'),
            'CREATED_IP_ADDRESS'      => $created_ip_address,
        ]);

        $item_defective->save();
        $message['status'] = "1";
        $message['message'] = "Defective Item Added Succesfully";

        return is_mobile($type, "add_inventory_item_defective.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = inventory_item_defectiveModel::where(["ID" => $id])->get()->toArray();

        $editdata = DB::table("inventory_item_receivable_details as ir")
            ->join('inventory_item_master as im', function ($join) {
                $join->whereRaw("im.id = ir.ITEM_ID");
            })
            ->selectRaw('ir.ITEM_ID,im.title AS item_name')
            ->where("ir.SUB_INSTITUTE_ID", "=", $sub_institute_id)
            ->where("ir.SYEAR", "=", $syear)
            ->orderby('ir.ITEM_ID')
            ->get()->toArray();

        $editdata = json_decode(json_encode($editdata), true);

        view()->share('menu', $editdata);

        return view('inventory/add_inventory_item_defective', ['data' => $data[0]]);
    }

    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');
        $created_ip_address = $_SERVER['REMOTE_ADDR'];

        $data = array(
            'ITEM_ID'                 => $request->get('item_id'),
            'WARRANTY_START_DATE'     => $request->get('warranty_start_date'),
            'WARRANTY_END_DATE'       => $request->get('warranty_end_date'),
            'DEFECT_REMARKS'          => $request->get('defect_remarks'),
            'ITEM_GIVEN_TO'           => $request->get('item_given_to'),
            'ESTIMATED_RECEIVED_DATE' => $request->get('estimated_received_date'),
            'CREATED_BY'              => $created_by,
            'CREATED_ON'              => date('Y-m-d H:i:s'),
            'CREATED_IP_ADDRESS'      => $created_ip_address,
        );

        inventory_item_defectiveModel::where(["ID" => $id])->update($data);

        $message['status'] = "1";
        $message['message'] = "Defective Item Updated Successfully";

        return is_mobile($type, "add_inventory_item_defective.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        inventory_item_defectiveModel::where(["ID" => $id])->delete();
        $message['status'] = "1";
        $message['message'] = "Defective Item Deleted successfully";

        return is_mobile($type, "add_inventory_item_defective.index", $message, "redirect");
    }
}
