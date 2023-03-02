<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_item_lost_reportController extends Controller
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

        $lost_items = DB::table("inventory_item_lost_details as IILD")
            ->join('inventory_item_master as IIM', function ($join) {
                $join->whereRaw("IIM.ID = IILD.ITEM_ID");
            })
            ->selectRaw('IILD.ITEM_ID,IIM.TITLE')
            ->where("IILD.SUB_INSTITUTE_ID", "=", $sub_institute_id)
            ->orderby('IILD.ITEM_ID')
            ->get()->distinct()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['lost_item'] = $lost_items;

        return is_mobile($type, "inventory/inventory_item_lost_report", $res, "view");
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
        $lost_item_id = $request->input('lost_item_id');
        $extra_query = '';

        if ($from_date != '' && $to_date != '') {
            $extra_query .= " AND date_format(IILD.LOST_DATE,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
        }

        if ($lost_item_id != '') {
            $extra_query .= " AND IILD.ITEM_ID = '".$lost_item_id."' ";
        }

        $result = DB::table("inventory_item_lost_details as IILD")
            ->join('inventory_requisition_details as IRD', function ($join) {
                $join->whereRaw("(IRD.REQUISITION_BY = IILD.REQUISITION_BY AND IRD.ITEM_ID = IILD.ITEM_ID)");
            })
            ->join('inventory_item_master as IM', function ($join) {
                $join->whereRaw("IM.ID=IILD.ITEM_ID");
            })
            ->join('tbluser as u', function ($join) {
                $join->whereRaw("u.id = IRD.requisition_by");
            })
            ->selectRaw('CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) AS REQUISITION_BY_NAME,IRD.USER_GROUP_ID,
                IILD.REQUISITION_BY,IILD.ITEM_ID,IM.title as ITEM_NAME,IRD.ITEM_QTY, DATE_FORMAT(IILD.LOST_DATE, "%d-%m-%Y") AS LOST_DATE')
            ->where("IILD.sub_institute_id", "=", $sub_institute_id)
            ->get()->toArray();

        $lost_items = DB::table("inventory_item_lost_details as IILD")
            ->join('inventory_item_master as IIM', function ($join) {
                $join->whereRaw("IIM.ID = IILD.ITEM_ID");
            })
            ->selectRaw('IILD.ITEM_ID,IIM.TITLE')
            ->where("IILD.SUB_INSTITUTE_ID", "=", $sub_institute_id)
            ->orderBy('IILD.ITEM_ID')
            ->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['lost_item'] = $lost_items;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['lost_item_id'] = $lost_item_id;

        return is_mobile($type, "inventory/inventory_item_lost_report", $res, "view");
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
