<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_status_masterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_requisition_reportController extends Controller
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
        $requisition_status = inventory_status_masterModel::select('id', 'title')->get()->toArray();


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['requisition_status'] = $requisition_status;

        return is_mobile($type, "inventory/inventory_requisition_report", $res, "view");
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
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $requisition_status_selected = $request->input('requisition_status');

        $sql = "SELECT CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS REQUISITION_BY_NAME,REQUISITION_BY,DATE_FORMAT(REQUISITION_DATE, '%d-%m-%Y') AS REQUISITION_DATE, REQUISITION_NO,IM.title as ITEM_NAME, ITEM_ID, ITEM_QTY,DATE_FORMAT(EXPECTED_DELIVERY_TIME, '%d-%m-%Y') AS EXPECTED_DELIVERY_TIME, REMARKS,IRS.title as REQUISITION_STATUS,APPROVED_QTY,CONCAT_WS(' ',tu.first_name,tu.middle_name,tu.last_name) as REQUISITION_APPROVED_BY,REQUISITION_APPROVED_REMARKS,REQUISITION_APPROVED_DATE,IRD.ID,IRD.USER_GROUP_ID
			FROM inventory_requisition_details IRD
			INNER JOIN tbluser u ON u.id = IRD.requisition_by
			INNER JOIN inventory_item_master IM ON IM.ID=IRD.ITEM_ID
			INNER JOIN inventory_requisition_status_master IRS ON IRS.id = IRD.requisition_status
			LEFT JOIN tbluser tu ON tu.id = IRD.requisition_approved_by
            WHERE 1=1 AND IRD.sub_institute_id = '".$sub_institute_id."'"; // AND IRD.syear = '".$syear."' 

        $result = DB::table("inventory_requisition_details as IRD")
            ->join('tbluser as u', function ($join) {
                $join->whereRaw("u.id = IRD.requisition_by");
            })
            ->join('inventory_item_master as IM', function ($join) {
                $join->whereRaw("IM.ID=IRD.ITEM_ID");
            })
            ->join('inventory_requisition_status_master as IRS', function ($join) {
                $join->whereRaw("IRS.id = IRD.requisition_status");
            })
            ->leftJoin('tbluser as tu', function ($join) {
                $join->whereRaw("tu.id = IRD.requisition_approved_by");
            })
            ->selectRaw('CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) AS REQUISITION_BY_NAME,REQUISITION_BY,
                DATE_FORMAT(REQUISITION_DATE, "%d-%m-%Y") AS REQUISITION_DATE, REQUISITION_NO,IM.title as ITEM_NAME, ITEM_ID, ITEM_QTY,
                DATE_FORMAT(EXPECTED_DELIVERY_TIME, "%d-%m-%Y") AS EXPECTED_DELIVERY_TIME, REMARKS,IRS.title as REQUISITION_STATUS,
                APPROVED_QTY,CONCAT_WS(" ",tu.first_name,tu.middle_name,tu.last_name) as REQUISITION_APPROVED_BY,
                REQUISITION_APPROVED_REMARKS,REQUISITION_APPROVED_DATE,IRD.ID,IRD.USER_GROUP_ID')
            ->where("IRD.sub_institute_id", "=", $sub_institute_id)
            ->where(function ($q) use ($from_date, $to_date, $requisition_status_selected) {
                if ($from_date != '' && $to_date != '') {
                    $q->whereRaw("date_format(IRD.REQUISITION_DATE,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."'");
                }

                if ($requisition_status_selected != '') {
                    $q->where('IRD.REQUISITION_STATUS', $requisition_status_selected);
                }
            })
            ->get()->toArray();

        $requisition_status = inventory_status_masterModel::select('id', 'title')->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['requisition_status'] = $requisition_status;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['requisition_status_selected'] = $requisition_status_selected;

        return is_mobile($type, "inventory/inventory_requisition_report", $res, "view");
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
