<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_receivableModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_item_receivableController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $approved_po_numbers = DB::table("inventory_generate_po_details as igp")
            ->join('inventory_requisition_status_master as irs', function ($join) {
                $join->whereRaw("irs.id = igp.po_approval_status");
            })
            ->selectRaw('igp.po_number,igp.id')
            ->where("igp.sub_institute_id", "=", $sub_institute_id)
            ->where("igp.syear", "=", $syear)
            ->where("irs.title", "=", 'APPROVED')
            ->groupby("igp.po_number")
            ->orderby("igp.id")
            ->get()->toArray();

        $po_numbers = json_decode(json_encode($approved_po_numbers), true);
        $res['status'] = 1;
        $res['message'] = "Success";
        $res['po_numbers'] = $po_numbers;

        return is_mobile($type, "inventory/show_inventory_item_receivable", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $po_number = $request->input('po_number');

        $result = DB::table("inventory_negotiate_po_details as inp")
            ->join('inventory_item_master as i', function ($join) {
                $join->whereRaw("i.id = inp.item_id AND i.sub_institute_id = inp.sub_institute_id");
            })
            ->leftJoin('inventory_item_receivable_details as ir', function ($join) {
                $join->whereRaw("ir.PURCHASE_ORDER_NO = inp.po_number AND ir.ITEM_ID = inp.item_id AND ir.ITEM_ID = i.id");
            })
            ->leftJoin('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = ir.RECEIVED_BY");
            })
            ->selectRaw('inp.id,inp.po_number,inp.item_id,i.title AS item_name,inp.qty, 
                IFNULL(ir.PREVIOUS_RECEIVED_QTY,0) AS previous_receive_qty,ir.ACTUAL_RECEIVED_QTY,IFNULL(ir.PENDING_QTY,0) AS pending_qty,
                ir.REMARKS,ir.WARRANTY_START_DATE,ir.WARRANTY_END_DATE,ir.BILL_NO,ir.BILL_DATE,ir.BILL_DATE,ir.CHALLAN_NO,
                ir.CHALLAN_DATE, CONCAT_WS(" ",tu.first_name,tu.middle_name,tu.last_name) AS received_by,
				ir.RECEIVED_DATE,ir.GATEPASS_NO,ir.CHEQUE_NO,ir.BANK_NAME')
            ->where("inp.sub_institute_id", "=", $sub_institute_id)
            ->where("inp.syear", "=", $syear)
            ->where("inp.po_number", "=", $po_number)
            ->groupBy('inp.item_id')
            ->get()->toArray();

        $approved_po_numbers = DB::table("inventory_generate_po_details as igp")
            ->join('inventory_requisition_status_master as irs', function ($join) {
                $join->whereRaw("irs.id = igp.po_approval_status");
            })
            ->selectRaw(' igp.po_number,igp.id')
            ->where("igp.sub_institute_id", "=", $sub_institute_id)
            ->where("igp.syear", "=", $syear)
            ->where("irs.title", "=", 'APPROVED')
            ->groupBy('igp.po_number')
            ->orderBy('igp.id')->get()->toArray();

        $po_numbers = json_decode(json_encode($approved_po_numbers), true);

        $res['status'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $result;
        $res['po_numbers'] = $po_numbers;

        $res['po_number'] = $po_number;

        return is_mobile($type, "inventory/show_inventory_item_receivable", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $type = $request->get('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $items = $request->get('items');
        $qty = $request->get('qty');
        $previous_receive_qty = $request->get('previous_receive_qty');
        $actual_received_qty = $request->get('actual_received_qty');
        $pending_qty = $request->get('pending_qty');
        $remarks = $request->get('remarks');
        $warranty_start_date = $request->get('warranty_start_date');
        $warranty_end_date = $request->get('warranty_end_date');
        $challan_no = $request->get('challan_no');
        $challan_date = $request->get('challan_date');
        $bill_no = $request->input('bill_no');
        $bill_date = $request->input('bill_date');
        $po_number = $request->input('po_number');
        $created_by = $request->session()->get('user_id');

        $sql_chk = DB::table("inventory_item_receivable_details")
            ->where("PURCHASE_ORDER_NO", "=", $po_number)
            ->where("SUB_INSTITUTE_ID", "=", $sub_institute_id)
            ->where("SYEAR", "=", $syear)
            ->get()->toArray();

        if (count($sql_chk) == 0) {
            foreach ($items as $k => $item_id) {
                $item_receivable = new inventory_item_receivableModel([
                    'SYEAR'                 => $syear,
                    'SUB_INSTITUTE_ID'      => $sub_institute_id,
                    'PURCHASE_ORDER_NO'     => $po_number,
                    'ITEM_ID'               => $item_id,
                    'ORDER_QTY'             => $qty[$item_id],
                    'PREVIOUS_RECEIVED_QTY' => $actual_received_qty[$item_id],
                    'ACTUAL_RECEIVED_QTY'   => $actual_received_qty[$item_id],
                    'PENDING_QTY'           => ($qty[$item_id] - $actual_received_qty[$item_id]),
                    'REMARKS'               => $remarks[$item_id],
                    'WARRANTY_START_DATE'   => $warranty_start_date[$item_id],
                    'WARRANTY_END_DATE'     => $warranty_end_date[$item_id],
                    'BILL_NO'               => $bill_no[$item_id],
                    'BILL_DATE'             => $bill_date[$item_id],
                    'CHALLAN_NO'            => $challan_no[$item_id],
                    'CHALLAN_DATE'          => $challan_date[$item_id],
                    'RECEIVED_BY'           => $created_by,
                    'RECEIVED_DATE'         => date('Y-m-d H:i:s'),
                    'CREATED_BY'            => $created_by,
                    'CREATED_ON'            => date('Y-m-d H:i:s'),
                    'CREATED_IP_ADDRESS'    => $_SERVER['REMOTE_ADDR'],
                ]);
                $item_receivable->save();
            }
        } else {
            foreach ($items as $k => $item_id) {
                $item_receivable = [
                    'PREVIOUS_RECEIVED_QTY' => $previous_receive_qty[$item_id],
                    'ACTUAL_RECEIVED_QTY'   => $actual_received_qty[$item_id],
                    'PENDING_QTY'           => $pending_qty[$item_id],
                    'REMARKS'               => $remarks[$item_id],
                    'WARRANTY_START_DATE'   => $warranty_start_date[$item_id],
                    'WARRANTY_END_DATE'     => $warranty_end_date[$item_id],
                    'BILL_NO'               => $bill_no[$item_id],
                    'BILL_DATE'             => $bill_date[$item_id],
                    'CHALLAN_NO'            => $challan_no[$item_id],
                    'CHALLAN_DATE'          => $challan_date[$item_id],
                    'RECEIVED_BY'           => $created_by,
                    'RECEIVED_DATE'         => date('Y-m-d H:i:s'),
                    'CREATED_BY'            => $created_by,
                    'CREATED_ON'            => date('Y-m-d H:i:s'),
                    'CREATED_IP_ADDRESS'    => $_SERVER['REMOTE_ADDR'],
                ];

                inventory_item_receivableModel::where([
                    "PURCHASE_ORDER_NO" => $po_number, "item_id" => $item_id, "sub_institute_id" => $sub_institute_id,
                    "syear"             => $syear,
                ])->update($item_receivable);
            }
        }

        $res['status'] = "1";
        $res['message'] = "Item Received successfully";

        return is_mobile($type, "show_inventory_item_receivable.index", $res);
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
