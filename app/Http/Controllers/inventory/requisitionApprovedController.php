<?php

namespace App\Http\Controllers\inventory;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\inventory\requisitionModel;
use App\Models\inventory\requisitionApprovedModel;
use Illuminate\Support\Facades\DB;

class requisitionApprovedController extends Controller
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
        
        // $data = requisitionModel::where(['sub_institute_id'=>$sub_institute_id])->get();

        $data = DB::select("SELECT ir.*, concat_ws(' ',tu.first_name,tu.middle_name,tu.last_name) AS requisition_name,i.title AS item_name,
            date_format(ir.requisition_date,'%d-%m-%Y') as requisition_date,date_format(ir.expected_delivery_time,'%d-%m-%Y') as expected_delivery_time,concat_ws(' ',tu1.first_name,tu1.middle_name,tu1.last_name) AS requisition_approved_by,i.opening_stock
            FROM inventory_requisition_details ir
            INNER JOIN tbluser tu ON tu.id = ir.requisition_by 
            LEFT JOIN tbluser tu1 ON tu1.id = ir.requisition_approved_by 
            INNER JOIN inventory_item_master i ON i.id = ir.item_id  
            WHERE ir.sub_institute_id = '".$sub_institute_id."' AND ir.syear = '".$syear."' order by  requisition_no DESC ");

        // dd($data);

        $requisition_status_data =  DB::select("SELECT id as requisition_status,title as title 
        FROM inventory_requisition_status_master order by sort_order");
        // dd($requisition_status_data);    
        $requisition_data['status_code'] = 1;
        $requisition_data['data'] = $data;
        $requisition_data['requisition_status_data'] = $requisition_status_data;
       // dd($requisition_data);
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "inventory/show_requisition_approved", $requisition_data, "view");

    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $requisitions = $request->get('requisitions');
        $type = $request->get('type');
        $approved_qty = $request->input('approved_qty');
        $requisition_status = $request->input('requisition_status');
        $requisition_approved_remarks = $request->input('requisition_approved_remarks');       

        if(empty($requisitions))
        {
            $res['status_code'] = "0";
            $res['message'] = "Please select minimum one requistion for approval.";
        }else{
            foreach ($requisitions as $key => $id) 
            {
                $requisitionsArray = array();
                $requisitionsArray['requisition_approved_remarks'] = $requisition_approved_remarks[$id];
                $requisitionsArray['approved_qty'] = $approved_qty[$id];
                $requisitionsArray['requisition_status'] = $requisition_status[$id];
                $requisitionsArray['requisition_approved_by'] = $request->session()->get('user_id');
                $requisitionsArray['requisition_approved_date'] = date('Y-m-d');

                requisitionModel::where(["id" => $id, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id])->update($requisitionsArray);
            }

            $res['status_code'] = "1";
            $res['message'] = "Requisitions Approved successfully";
        }

        return \App\Helpers\is_mobile($type, "requisition_approved.index", $res);

    }
}
