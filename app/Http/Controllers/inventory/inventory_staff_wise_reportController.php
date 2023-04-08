<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_staff_wise_reportController extends Controller
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

        return is_mobile($type, "inventory/inventory_staff_wise_report", $res, "view");
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

        $result = DB::table("inventory_requisition_details as IRD")
            ->selectRaw('CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) AS REQUISITION_BY_NAME,IRD.REQUISITION_NO,IRD.REQUISITION_BY,IRD.ITEM_ID,IRD.ITEM_QTY,IRD.APPROVED_QTY, DATE_FORMAT(IRD.REQUISITION_APPROVED_DATE, "%d-%m-%Y") AS REQUISITION_DATE,IIM.title as ITEM_NAME,IRD.USER_GROUP_ID,IIC.TITLE AS Category')
            ->join('inventory_item_master as IIM', function ($join) {
                $join->whereRaw("IIM.ID = IRD.ITEM_ID");
            })
            ->join('inventory_item_category_master as IIC', function ($join) {
                $join->whereRaw("IIC.ID = IIM.CATEGORY_ID");
            })
            ->join('tbluser as u', function ($join) {
                $join->whereRaw("u.id = IRD.requisition_by");
            })
            ->where("IRD.sub_institute_id", "=", $sub_institute_id)
            ->where(function ($q) use ($from_date, $to_date, $requisition_by) {
                if ($from_date != '' && $to_date != '') {
                    $q->whereRaw("date_format(IRD.REQUISITION_APPROVED_DATE,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."'");
                }

                if ($requisition_by != '') {
                    $q->where('IRD.requisition_by', $requisition_by);
                }
            })
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

        return is_mobile($type, "inventory/inventory_staff_wise_report", $res, "view");
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
