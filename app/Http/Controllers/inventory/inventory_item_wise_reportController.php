<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_masterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_item_wise_reportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $items = inventory_item_masterModel::select('id', 'title')->where([
            'sub_institute_id' => $sub_institute_id, 'item_status' => 'Active',
        ])->get()->toArray();

        //'syear' => $syear, above items query error item now show front side because of syear there are no any data syear that why remove syear in query.

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['item'] = $items;

        return is_mobile($type, "inventory/inventory_item_wise_report", $res, "view");
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
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $item_id = $request->input('item_id');
        $extra_query = '';

        if ($from_date != '' && $to_date != '') {
            $extra_query .= " AND date_format(IRD.REQUISITION_APPROVED_DATE, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
        }        

        if ($item_id != '') {
            $extra_query .= " AND IRD.ITEM_ID = '".$item_id."' ";
        }

        $result = DB::table("inventory_requisition_details as IRD")
            ->join('inventory_allocation_details as IAD', function ($join) {
                $join->whereRaw("IAD.REQUISITION_DETAILS_ID = IRD.ID");
            })
            ->join('inventory_item_master as IM', function ($join) {
                $join->whereRaw("IM.ID=IRD.ITEM_ID");
            })
            ->join('tbluser as u', function ($join) {
                $join->whereRaw("u.id = IRD.requisition_by");
            })
            ->selectRaw('CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) AS REQUISITION_BY_NAME,IRD.REQUISITION_NO,
                IRD.REQUISITION_BY,IRD.ITEM_ID,IRD.ITEM_QTY,IRD.APPROVED_QTY, DATE_FORMAT(IRD.REQUISITION_APPROVED_DATE, "%d-%m-%Y") 
                AS REQUISITION_DATE,IM.title as ITEM_NAME,IRD.USER_GROUP_ID')
            ->where("IRD.sub_institute_id", "=", $sub_institute_id)
            ->where("IRD.syear", "=", $syear)
            ->get()->toArray();

        $items = inventory_item_masterModel::select('id', 'title')->where([
            'sub_institute_id' => $sub_institute_id, 'item_status' => 'Active',
        ])->get()->toArray();

        //'syear' => $syear, above items query error item now show front side because of syear there are no any data syear that why remove syear in query.

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['item'] = $items;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['item_id'] = $item_id;

        return is_mobile($type, "inventory/inventory_item_wise_report", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        //
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
