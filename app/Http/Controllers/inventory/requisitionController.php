<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\inventory\inventory_item_category_masterModel;
use App\Models\inventory\inventory_item_sub_category_masterModel;
use App\Models\inventory\inventory_master_setupModel;
use App\Models\inventory\requisitionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;
use DateTime;

class requisitionController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $requisition_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $user_profile = $request->session()->get('DUSER_ID');

        $data = DB::table("inventory_requisition_details as ir")
            ->join('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = ir.requisition_by");
            })
            ->leftJoin('tbluser as ira', function ($join) {
                $join->whereRaw("ira.id = ir.requisition_approved_by");
            })
            ->join('inventory_item_master as i', function ($join) {
                $join->whereRaw("i.id = ir.item_id");
            })
            ->join('inventory_requisition_status_master as irs', function ($join) {
                $join->whereRaw("irs.id = ir.requisition_status");
            })
            ->selectRaw('ir.*, concat_ws(" ",tu.first_name,tu.middle_name,tu.last_name) as requisition_name,i.title as item_name,
                irs.title as requisition_status,concat_ws(" ",ira.first_name,ira.middle_name,ira.last_name) as requisition_approved_by')
            ->where("ir.sub_institute_id", "=", $sub_institute_id)
            ->where("ir.syear", "=", $syear)
            ->where(function ($q) use ($user_profile, $user_id) {
                if ($user_profile != 'admin') {
                    $q->where('ir.requisition_by', $user_id);
                }
            })
            ->orderby('requisition_no', 'DESC')
            ->get()->toArray();

        $requisition_data['status_code'] = 1;
        $requisition_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "inventory/show_requisition", $requisition_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $user_profile = $request->session()->get('DUSER_ID');

        $extra = '';
        if ($user_profile != 'admin') {
            $extra .= " AND id = '".$user_id."' ";
        }

        $data = requisitionModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $category_data = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $user_data = DB::table('tbluser')->selectRaw("*,concat_ws(' ',first_name,middle_name,last_name) as requisition_name")
            ->where('sub_institute_id', $sub_institute_id)
            ->where(function ($q) use ($user_profile, $user_id) {
                if ($user_profile != 'admin') {
                    $q->where('id', $user_id);
                }
            })->get()->toArray();

        $item_data = DB::table('inventory_item_master')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('item_status', 'Active')
            ->get()->toArray();
$item_setting_data = inventory_master_setupModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        if (count($item_setting_data) == 0) {
            $res['status_code'] = "0";
            $res['message'] = "Please add Master setup for add requsition.";
            $type = $request->input('type');

            return is_mobile($type, "add_requisition.index", $res, "redirect");
        }

        $item_setting_data_value = '';

        if (isset($item_setting_data[0]['ITEM_SETTING_FOR_REQUISITION']) &&
            $item_setting_data[0]['ITEM_SETTING_FOR_REQUISITION'] != '') {

            $item_setting_data_value = $item_setting_data[0]['ITEM_SETTING_FOR_REQUISITION'];
        }

        $unit_data = DB::table('master_setup_select')
                    ->where('type', 'item_unit')
                    ->get()
                    ->toArray();


        $FORM_NO = $this->generate_requisition_no($sub_institute_id, $syear);

        $data['menu'] = $user_data;
        $data['item_setting_data_value'] = $item_setting_data_value;
        $data['category_data'] = $category_data;
        $data['sub_category_data'] = [];
        $data['item_data'] = [];
        $data['menu1'] = $item_data;
        $data['unit_data'] = $unit_data;
        $data['REQ_NO'] = $FORM_NO;
        return view('inventory/add_requisition', $data);
    }

    public function store(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');
        $created_ip_address = $_SERVER['REMOTE_ADDR'];
        $user_group_id = $request->session()->get('user_group_id');
        $marking_period_id = $request->session()->get('marking_period_id');

        $items = $request->get('item_id');

        $validator = Validator::make($request->all(), [
            'requisition_no'   => 'required|string|max:50',
            'requisition_by'   => 'required|integer',
            'requisition_date' => 'required',
            'item_id'          => 'required|array|min:1',
        ]);
        if ($validator->fails()) {
            $res['status_code'] = "0";
            $res['message'] = $validator->messages()->first();
            $type = $request->input('type');

            return is_mobile($type, "add_requisition.index", $res, "redirect");
        }

        foreach ($items as $key => $val) {

            $expected_delivery_time = $request->get('expected_delivery_time')[$key];
            if (!empty($expected_delivery_time)) {
                $date = DateTime::createFromFormat('d-m-Y', $expected_delivery_time);
                if ($date) {
                    $expected_delivery_time = $date->format('Y-m-d H:i:s');
                }
            }

            $requisition = new requisitionModel([
                'syear'                  => $syear,
                'sub_institute_id'       => $sub_institute_id,
                'requisition_no'         => $request->get('requisition_no'),
                'requisition_by'         => $request->get('requisition_by'),
                'requisition_date'       => $request->get('requisition_date'),
                'item_id'                => $request->get('item_id')[$key],
                'item_qty'               => $request->get('item_qty')[$key],
                'item_unit'              => $request->get('item_unit')[$key],
                'expected_delivery_time' => $expected_delivery_time,
                'requisition_status'     => 1,
                'remarks'                => $request->get('remarks')[$key],
                'user_group_id'          => $user_group_id,
                'created_by'             => $created_by,
                'created_ip_address'     => $created_ip_address,
            ]);
            $requisition->save();
        }

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'requisition_store',
            'entity_type' => 'tblrequisition',
            'entity_id'   => $request->get('requisition_no'),
            'new_values'  => $request->except(['_token']),
        ]);

        $res['status_code'] = "1";
        $res['message'] = "Requisition Added Succesfully";
        $type = $request->input('type');

        return is_mobile($type, "add_requisition.index", $res, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = requisitionModel::select('*')
            ->join('inventory_item_master', 'inventory_item_master.id', '=', 'inventory_requisition_details.item_id')
            ->where([
                'inventory_requisition_details.id'               => $id,
                'inventory_requisition_details.sub_institute_id' => $sub_institute_id,
            ])
            ->get();
        $data = $data[0];

        $editdata = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $editdata1 = inventory_item_sub_category_masterModel::where([
            'sub_institute_id' => $sub_institute_id, 'category_id' => $data['category_id'],
        ])->get();

        $user_data = DB::table("tbluser")
            ->selectRaw('*,concat_ws(" ",first_name,middle_name,last_name) as requisition_name')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->get()->toArray();

        $item_data = DB::table("inventory_item_master")
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("item_status", "=", 'Active')
            ->where("category_id", "=", $data['category_id'])
            ->where("sub_category_id", "=", $data['sub_category_id'])
            ->get()->toArray();

        $item_setting_data = inventory_master_setupModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        $item_setting_data_value = $item_setting_data[0]['ITEM_SETTING_FOR_REQUISITION'];

        $GET_NO = requisitionModel::select(DB::raw('requisition_no'))->where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        $FORM_NO = $GET_NO[0]['requisition_no'];

        $unit_data = DB::table('units')->get()->toArray();

        view()->share('item_setting_data_value', $item_setting_data_value);
        view()->share('menu', $user_data);
        view()->share('menu1', $item_data);
        view()->share('category_data', $editdata);
        view()->share('sub_category_data', $editdata1);
        view()->share('item_data', $item_data);
        view()->share('unit_data', $unit_data);
        view()->share('REQ_NO', $FORM_NO);
        view()->share('requisition_id', $id);

        return is_mobile($type, "inventory/add_requisition", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $expected_delivery_time = $request->get('expected_delivery_time');
        if (!empty($expected_delivery_time)) {
            $date = DateTime::createFromFormat('d-m-Y', $expected_delivery_time);
            if ($date) {
                $expected_delivery_time = $date->format('Y-m-d H:i:s');
            }
        }

        $validator = Validator::make($request->all(), [
            'item_id'   => 'required|integer',
            'item_qty'  => 'required',
            'item_unit' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            $message['status_code'] = "0";
            $message['message'] = $validator->messages()->first();
            $type = $request->input('type');

            return is_mobile($type, "add_requisition.index", $message, "redirect");
        }

        $requisition = [
            'item_id'                => $request->get('item_id'),
            'item_qty'               => $request->get('item_qty'),
            'item_unit'              => $request->get('item_unit'),
            'expected_delivery_time' => $expected_delivery_time,
            'requisition_status'     => 1,
            'remarks'                => $request->get('remarks'),
        ];

        requisitionModel::where(["id" => $id])->update($requisition);

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'requisition_update',
            'entity_type' => 'tblrequisition',
            'entity_id'   => $id,
            'new_values'  => $requisition,
        ]);

        $message['status_code'] = "1";
        $message = [
            "message" => "Requisition Details Updated Successfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "add_requisition.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        requisitionModel::where(["ID" => $id])->delete();

        AuditLog::record([
            'module'      => 'inventory',
            'action'      => 'requisition_delete',
            'entity_type' => 'tblrequisition',
            'entity_id'   => $id,
        ]);

        $message['status_code'] = "1";
        $message = [
            "message" => "Requisition Setup Details Deleted successfully",
        ];

        return is_mobile($type, "add_requisition.index", $message, "redirect");
    }

    public function generate_requisition_no($sub_institute_id, $syear)
    {
        $GET_NO = requisitionModel::select(DB::raw('IFNULL(max(substring(requisition_no,5,6)),1) as LAST_REQ_NO'))
            ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        $FORM_NO = $GET_NO[0]['LAST_REQ_NO'] + 1;

        if (strlen($FORM_NO) == 1) {
            $FORM_NO = "REQ-00".$FORM_NO;
        } else {
            if (strlen($FORM_NO) == 2) {
                $FORM_NO = "REQ-0".$FORM_NO;
            } else {
                if (strlen($FORM_NO) == 3) {
                    $FORM_NO = "REQ-".$syear.$FORM_NO;
                }
            }
        }

        return $FORM_NO;
    }
}
