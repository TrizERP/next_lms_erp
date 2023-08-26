<?php

namespace App\Http\Controllers\inventory;

use App\Http\Controllers\Controller;
use App\Models\inventory\inventory_item_returnModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inventory_item_returnController extends Controller
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

        $users = DB::table("tbluser")
            ->selectRaw('id,CONCAT_WS(" ",first_name,middle_name,last_name) as requisition_by_name')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("status", "=", '1')
            ->get()->toArray();

        $users = json_decode(json_encode($users), true);

        $res['status'] = 1;
        $res['message'] = "Success";
        $res['users'] = $users;

        return is_mobile($type, "inventory/show_inventory_item_return", $res, "view");
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
        $requisition_by = $request->input('requisition_by');

        $result = DB::table("inventory_allocation_details as IAD")
            ->join('inventory_requisition_details as IRD', function ($join) {
                $join->whereRaw("IRD.requisition_by=IAD.REQUISITION_ID AND IRD.id = IAD.REQUISITION_DETAILS_ID");
            })
            ->leftJoin('inventory_item_master as IIM', function ($join) {
                $join->whereRaw("IIM.id = IRD.item_id");
            })
            ->leftJoin('tbluser as TS', function ($join) {
                $join->whereRaw("(TS.id=IRD.requisition_by AND TS.`status` = 1)");
            })
            ->selectRaw('IIM.title as ITEM_NAME,IRD.requisition_by,IRD.id AS requisition_details_id,
				CONCAT_WS(" ",TS.first_name,TS.middle_name,TS.last_name) as REQUISITION_BY_NAME, 
				SUM(IRD.item_qty) TOTAL_QTY, IRD.ITEM_ID,"" REMARKS,"" RECEIVED_BY,"" RETURN_QTY, 
				DATE_FORMAT(IRD.requisition_date,"%d-%m-%Y") AS REQUISITION_DATE,
				IRD.remarks AS REQUISITION_REMARK')
            ->where("IIM.item_type_id", "=", '2')
            ->where("IAD.SUB_INSTITUTE_ID", "=", $sub_institute_id)
            ->where("IAD.SYEAR", "=", $syear)
            ->where(function ($q) use ($requisition_by) {
                if ($requisition_by != '') {
                    $q->where('IRD.requisition_by', $requisition_by);
                }
            })
            ->groupby('IRD.id')
            ->get()->toArray();

        $users = DB::table("tbluser")
            ->selectRaw('id,CONCAT_WS(" ",first_name,middle_name,last_name) as requisition_by_name')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("status", "=", '1')
            ->get()->toArray();

        $users = json_decode(json_encode($users), true);

        $res['status'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $result;
        $res['users'] = $users;
        $res['requisition_by'] = $requisition_by;

        return is_mobile($type, "inventory/show_inventory_item_return", $res, "view");
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
        $created_by = $request->session()->get('user_id');
        $items = $request->get('items');
        $requisition_by = $request->get('requisition_by');
        $requisition_details_id = $request->get('requisition_details_id');
        $return_qty = $request->get('return_qty');
        $remarks = $request->get('remarks');


        if (! empty($items)) {
            foreach ($items as $k => $item_id) {
                $check = DB::table("inventory_item_return_details")
                    ->where("SUB_INSTITUTE_ID", "=", $sub_institute_id)
                    ->where("SYEAR", "=", $syear)
                    ->where("ITEM_ID", "=", $item_id)
                    ->where("REQUISITION_DETAILS_ID", "=", $requisition_details_id[$item_id])
                    ->where("REQUISITION_BY", "=", $requisition_by[$item_id])
                    ->get()->toArray();

                if (count($check) == 0) {
                    $item_return = new inventory_item_returnModel([
                        'SYEAR'                  => $syear,
                        'SUB_INSTITUTE_ID'       => $sub_institute_id,
                        'ITEM_ID'                => $item_id,
                        'REQUISITION_DETAILS_ID' => $requisition_details_id[$item_id],
                        'RETURN_DATE'            => date('Y-m-d H:i:s'),
                        'REMARKS'                => $remarks[$item_id],
                        'RETURN_QTY'             => $return_qty[$item_id],
                        'REQUISITION_BY'         => $requisition_by[$item_id],
                        'CREATED_BY'             => $created_by,
                        'CREATED_ON'             => date('Y-m-d H:i:s'),
                        'CREATED_IP_ADDRESS'     => $_SERVER['REMOTE_ADDR'],
                    ]);
                    $item_return->save();
                }
            }

            $res['status'] = "1";
            $res['message'] = "Item Return successfully";
        } else {
            $res['status'] = "0";
            $res['message'] = "Please select minimum one item for return.";
        }


        return is_mobile($type, "show_inventory_item_return.index", $res);
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
