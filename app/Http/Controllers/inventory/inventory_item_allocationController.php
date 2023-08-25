<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_allocationModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_item_allocationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        // dd($request);
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $items_data = DB::table("inventory_requisition_details AS ird")
            ->join('inventory_requisition_status_master as rs', function ($join) {
                $join->whereRaw("rs.id = ird.requisition_status");
            })
            ->join('inventory_item_master as i', function ($join) {
                $join->whereRaw("i.id = ird.item_id AND i.sub_institute_id = ird.sub_institute_id");
            })
            ->selectRaw('i.id,ird.item_id,i.title AS item_title')
            ->where("ird.sub_institute_id", "=", $sub_institute_id)
            ->where("ird.syear", "=", $syear)
            ->where("rs.title", "=", 'APPROVED')
            ->get()->toArray();

        $items = json_decode(json_encode($items_data), true);

        $users_data = DB::table("inventory_requisition_details AS ird")
            ->join('inventory_requisition_status_master as rs', function ($join) {
                $join->whereRaw("rs.id = ird.requisition_status");
            })
            ->join('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = ird.requisition_by AND tu.sub_institute_id = ird.sub_institute_id");
            })
            ->selectRaw('ird.requisition_by,tu.id,CONCAT_WS(" ",tu.first_name,tu.middle_name,tu.last_name) AS requisition_by_name')
            ->where("ird.sub_institute_id", "=", $sub_institute_id)
            ->where("ird.syear", "=", $syear)
            ->where("rs.title", "=", 'APPROVED')
            ->get()->toArray();

        $users = json_decode(json_encode($users_data), true);

        $requisition_RET = DB::table("inventory_requisition_details AS IRD")
            ->join('inventory_requisition_status_master as rs', function ($join) {
                $join->whereRaw("rs.id = IRD.requisition_status");
            })
            ->join('inventory_item_master AS IIM', function ($join) {
                $join->whereRaw("IIM.ID = IRD.ITEM_ID");
            })
            ->join('tbluser AS u', function ($join) {
                $join->whereRaw("u.id = IRD.requisition_by");
            })
            ->leftJoin('inventory_item_type as IIT', function ($join) {
                $join->whereRaw("IIT.ID = IIM.ITEM_TYPE_ID");
            })
            ->selectRaw('concat_ws(" ",u.first_name,u.middle_name,u.last_name) as REQUISITION_BY_NAME,
                IRD.ID AS REQUISITION_DETAILS_ID,IRD.APPROVED_QTY, IRD.ITEM_ID, IRD.REQUISITION_BY, 
                DATE_FORMAT(IRD.REQUISITION_DATE, "%d-%m-%Y %h:%i:%s") AS REQUISITION_DATE, IRD.REQUISITION_NO,IIM.title AS ITEM_NAME, 
                IRD.ITEM_QTY, DATE_FORMAT(IRD.EXPECTED_DELIVERY_TIME, "%d-%m-%Y %h:%i:%s") AS EXPECTED_DELIVERY_TIME, 
                IRD.REMARKS, "" STOCK_STATUS, IRD.DEPARTMENT_ID,IIT.TITLE AS ITEM_TYPE,IRD.REQUISITION_BY AS ACTION,
                IRD.REQUISITION_STATUS,IRD.USER_GROUP_ID, "" LOCATION_OF_MATERIAL,"" PERSON_RESPONSIBLE,rs.title as APPROVED_status')
            ->where("rs.title", "=", 'APPROVED')
            ->where("IRD.sub_institute_id", "=", $sub_institute_id)
            ->where("IRD.syear", "=", $syear)
            ->whereRaw("IRD.ID NOT IN (SELECT REQUISITION_DETAILS_ID FROM inventory_allocation_details)")
            ->orderby("IRD.ID")
            ->get()->toArray();

        $res['item'] = $items;
        $res['user'] = $users;
        $res['requisition_RET'] = $requisition_RET;
        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "inventory/show_inventory_item_allocation", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $item_id = $request->get('item_id');
        $requisition_by = $request->get('requisition_by');
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');

        $items_data = DB::table("inventory_requisition_details AS ird")
            ->join('inventory_requisition_status_master as rs', function ($join) {
                $join->whereRaw("rs.id = ird.requisition_status");
            })
            ->join('inventory_item_master as i', function ($join) {
                $join->whereRaw("i.id = ird.item_id AND i.sub_institute_id = ird.sub_institute_id");
            })
            ->selectRaw("i.id,ird.item_id,i.title AS item_title")
            ->where("ird.sub_institute_id", "=", $sub_institute_id)
            ->where("ird.syear", "=", $syear)
            ->where("rs.title", "=", 'APPROVED')
            ->get()->toArray();

        $items = json_decode(json_encode($items_data), true);

        $users_data = DB::table("inventory_requisition_details AS ird")
            ->join('inventory_requisition_status_master as rs', function ($join) {
                $join->whereRaw("rs.id = ird.requisition_status");
            })
            ->join('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = ird.requisition_by AND tu.sub_institute_id = ird.sub_institute_id");
            })
            ->selectRaw("ird.requisition_by,tu.id,CONCAT_WS(' ',tu.first_name,tu.middle_name,tu.last_name) AS requisition_by_name")
            ->where("ird.sub_institute_id", "=", $sub_institute_id)
            ->where("ird.syear", "=", $syear)
            ->where("rs.title", "=", 'APPROVED')
            ->get()->toArray();

        $users = json_decode(json_encode($users_data), true);

        $requisition_RET = DB::table('inventory_requisition_details AS IRD')
            ->join('inventory_requisition_status_master as rs', function ($join) {
                $join->whereRaw('rs.id = IRD.requisition_status');
            })
            ->join('inventory_item_master AS IIM', function ($join) {
                $join->whereRaw("IIM.ID = IRD.ITEM_ID");
            })
            ->join('tbluser AS u', function ($join) {
                $join->whereRaw("u.id = IRD.requisition_by");
            })
            ->leftJoin('inventory_item_type as IIT', function ($join) {
                $join->whereRaw("IIT.ID = IIM.ITEM_TYPE_ID");
            })
            ->selectRaw('concat_ws(" ",u.first_name,u.middle_name,u.last_name) as REQUISITION_BY_NAME,
                        IRD.ID AS REQUISITION_DETAILS_ID,IRD.APPROVED_QTY, IRD.ITEM_ID, IRD.REQUISITION_BY, 
                        DATE_FORMAT(IRD.REQUISITION_DATE, "%d-%m-%Y %h:%i:%s") AS REQUISITION_DATE, IRD.REQUISITION_NO,IIM.title AS ITEM_NAME, 
                        IRD.ITEM_QTY, DATE_FORMAT(IRD.EXPECTED_DELIVERY_TIME, "%d-%m-%Y %h:%i:%s") AS EXPECTED_DELIVERY_TIME, 
                        IRD.REMARKS, " " STOCK_STATUS, IRD.DEPARTMENT_ID,IIT.TITLE AS ITEM_TYPE,IRD.REQUISITION_BY AS ACTION,
                        IRD.REQUISITION_STATUS,IRD.USER_GROUP_ID, " " LOCATION_OF_MATERIAL," " PERSON_RESPONSIBLE,rs.title as APPROVED_status')
            ->where("rs.title", "=", 'APPROVED')
            ->where("IRD.sub_institute_id", "=", $sub_institute_id)
            ->where("IRD.syear", "=", $syear)
            ->whereRaw("IRD.ID NOT IN (SELECT REQUISITION_DETAILS_ID FROM inventory_allocation_details)")
            ->where(function ($q) use ($item_id, $requisition_by, $from_date, $to_date) {
                if ($item_id != '') {
                    $q->where('IRD.ITEM_ID', $item_id);
                }
                if ($requisition_by != '') {
                    $q->where('IRD.REQUISITION_BY', $requisition_by);
                }
                if ($from_date != '' && $to_date != '') {
                    $q->whereBetween('IRD.REQUISITION_DATE', [$from_date, $to_date]);
                }
            })
            ->orderby('IRD.ID')
            ->get()->toArray();

        $res['item'] = $items;
        $res['user'] = $users;
        $res['item_id'] = $item_id;
        $res['requisition_by'] = $requisition_by;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['requisition_RET'] = $requisition_RET;

        return is_mobile($type, "inventory/show_inventory_item_allocation", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // dd($request);
        $type = $request->get('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $created_by = $request->session()->get('user_id');
        $items = $request->get('items');
        $requisition_details_id = $request->get('requisition_details_id');
        $requisition_by = $request->get('requisition_by');
        $location_of_material = $request->get('location_of_material');
        $person_responsible = $request->get('person_responsible');

        if (empty($items)) {
            $res['status'] = "0";
            $res['message'] = "Please Select Minimum One Inventory Items.";
        } else {
            foreach ($items as $k => $item_id) {
                $item_allocation = new inventory_item_allocationModel([
                    'SYEAR'                  => $syear,
                    'SUB_INSTITUTE_ID'       => $sub_institute_id,
                    'REQUISITION_DETAILS_ID' => $requisition_details_id[$item_id],
                    'REQUISITION_ID'         => $requisition_by[$item_id],
                    'LOCATION_OF_MATERIAL'   => $location_of_material[$item_id],
                    'PERSON_RESPONSIBLE'     => $person_responsible[$item_id],
                    'ITEM_ID'                => $item_id,
                    'CREATED_BY'             => $created_by,
                    'CREATED_ON'             => date('Y-m-d H:i:s'),
                    'CREATED_IP_ADDRESS'     => $_SERVER['REMOTE_ADDR'],
                ]);
                $item_allocation->save();
            }

            $res['status'] = "1";
            $res['message'] = "Item Allocated successfully";
        }


        return is_mobile($type, "show_inventory_item_allocation.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

}
