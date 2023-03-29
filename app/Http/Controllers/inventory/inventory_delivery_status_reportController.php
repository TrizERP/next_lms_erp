<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_delivery_status_reportController extends Controller
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
        $users = tbluserModel::select('id', 'user_name', 'first_name', 'middle_name', 'last_name')
            ->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['user'] = $users;

        return is_mobile($type, "inventory/inventory_delivery_status_report", $res, "view");
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
        $requisition_by = $request->input('requisition_by');
        $extra_query = '';

        if ($from_date != '' && $to_date != '') {
            $extra_query .= " AND date_format(IA.CREATED_ON,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
        }

        if ($requisition_by != '') {
            $extra_query .= " AND IRD.requisition_by = '".$requisition_by."' ";
        }

        $result = DB::table("inventory_requisition_details as IRD")
            ->join('inventory_item_master as IM', function ($join) {
                $join->whereRaw("IM.ID=IRD.ITEM_ID");
            })
            ->join('inventory_allocation_details as IA', function ($join) {
                $join->whereRaw("(IA.REQUISITION_DETAILS_ID = IRD.ID AND IA.ITEM_ID = IRD.ITEM_ID)");
            })
            ->join('tbluser as u', function ($join) {
                $join->whereRaw("u.id = IRD.requisition_by");
            })
            ->leftJoin('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = IRD.requisition_approved_by");
            })
            ->join('inventory_requisition_status_master as IRS', function ($join) {
                $join->whereRaw("IRS.id = IRD.requisition_status");
            })
            ->selectRaw("concat_ws(' ',u.first_name,u.middle_name,u.last_name) as REQUISITION_BY_NAME,ITEM_UNIT, REQUISITION_BY,
            DATE_FORMAT(IRD.REQUISITION_DATE, '%d-%m-%Y %h:%i:%s') AS REQUISITION_DATE, REQUISITION_NO,IM.TITLE as ITEM_NAME, IRD.ITEM_ID,
             ITEM_QTY,DATE_FORMAT(IRD.EXPECTED_DELIVERY_TIME, '%d-%m-%Y %h:%i:%s') AS EXPECTED_DELIVERY_TIME, REMARKS,IRS.title as
              REQUISITION_STATUS,CONCAT_WS(' ',tu.first_name,tu.middle_name,tu.last_name) as REQUISITION_APPROVED_BY, REQUISITION_APPROVED_REMARKS,
              DATE_FORMAT(IRD.REQUISITION_APPROVED_DATE, '%d-%m-%Y %h:%i:%s') AS REQUISITION_APPROVED_DATE,'Delivered' AS delivery_status,
               DATE_FORMAT(IA.CREATED_ON, '%d-%m-%Y') AS DELIVERY_DATE,IRD.ID,IRD.USER_GROUP_ID")
            ->where("IRD.sub_institute_id", "=", $sub_institute_id)
            ->where("IRD.syear", "=", $syear)
            ->get()->toArray();

        $users = tbluserModel::select('id', 'user_name', 'first_name', 'middle_name', 'last_name')
            ->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['user'] = $users;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['requisition_by'] = $requisition_by;

        return is_mobile($type, "inventory/inventory_delivery_status_report", $res, "view");
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
