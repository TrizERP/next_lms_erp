<?php

namespace App\Http\Controllers\inventory;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use App\Models\inventory\inventory_item_masterModel;

class inventory_overall_item_reportController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $items = inventory_item_masterModel::select('id', 'title')->where(['sub_institute_id' => $sub_institute_id,'syear' => $syear,'item_status' => 'Active'])->get()->toArray();
                
                
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['item'] = $items;
        return is_mobile($type, "inventory/inventory_overall_item_report", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $item_id = $request->input('item_id');
        $extra_query = $other_q = $other_issue = '';

        if($from_date != '' && $to_date != '')
        {
            //$extra_query .= " AND date_format(IRD.REQUISITION_APPROVED_DATE,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
        }

        if($item_id != '')
        {
            $extra_query .= " AND IIM.ID = '".$item_id."' ";
            $other_q .= " AND ITEM_ID='".$item_id."' ";
            $other_issue .= " AND IRD.ITEM_ID='".$item_id."' ";
        }

        $sql = "SELECT '' S_NO, IIM.TITLE AS ITEM_NAME,IIM.DESCRIPTION,
			(IIM.OPENING_STOCK - IFNULL(IIM.direct_purchase_stock,0)) AS OPENING_INVENTORY_QTY,
			SUM(IFNULL(iir.ACTUAL_RECEIVED_QTY,0)) PURCHASE_QTY,SUM(IFNULL(IRD.APPROVED_QTY,0)) ISSUE_QTY, 
			COUNT(IILD.ITEM_ID) LOST_SOLD_QTY,COUNT(IIRD.ITEM_ID) as RETURNED_QTY,
			(IIM.OPENING_STOCK + SUM(IFNULL(iir.ACTUAL_RECEIVED_QTY,0)) - SUM(IFNULL(IRD.APPROVED_QTY,0)) -count(IILD.ITEM_ID) +  COUNT(IIRD.ITEM_ID)) as CLOSING_INVENTORY_VALUE,igp.qty AS PO_QTY,IFNULL(IIM.direct_purchase_stock,0) as DIRECT_PURCHASE_STOCK
			FROM inventory_item_master IIM
			LEFT JOIN inventory_item_receivable_details AS iir on iir.ITEM_ID = IIM.id
			LEFT JOIN inventory_allocation_details AS IAD ON IAD.ITEM_ID = IIM.id
			LEFT JOIN inventory_requisition_details AS IRD ON (IAD.REQUISITION_DETAILS_ID = IRD.ID AND IRD.REQUISITION_STATUS != ' ')
			LEFT JOIN inventory_item_lost_details AS IILD ON IILD.ITEM_ID = IIM.id
			LEFT JOIN inventory_item_return_details AS IIRD ON IIRD.ITEM_ID = IIM.id
            LEFT JOIN inventory_generate_po_details AS igp ON igp.item_id = IIM.id AND igp.po_approval_status = 2
			WHERE IIM.ITEM_STATUS='Active' AND IIM.sub_institute_id='".$sub_institute_id."' ";//AND IIM.syear='".$syear."' 
        $full_sql = $sql.$extra_query;
        $full_sql .= " GROUP BY IIM.id";

        $result = DB::select($full_sql);
      echo "<pre>"; print_r($full_sql); exit;

        
        $items = inventory_item_masterModel::select('id', 'title')->where(['sub_institute_id' => $sub_institute_id,'syear' => $syear,'item_status' => 'Active'])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
     	$res['item'] = $items;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['item_id'] = $item_id;
        return is_mobile($type, "inventory/inventory_overall_item_report", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
