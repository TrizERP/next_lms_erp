<?php

namespace App\Http\Controllers\inventory;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationData;
use App\Models\inventory\inventory_item_lostModel;
use App\Models\user\tbluserModel;
use App\Models\inventory\inventory_item_masterModel;
use Illuminate\Support\Facades\DB;

class inventory_item_lostController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
         
        $sub_institute_id = $request->session()->get('sub_institute_id');      
        $sub_data = "SELECT il.*,ii.title as ITEM_NAME,
                    concat_ws(' ',u.first_name,u.middle_name,u.last_name) as requisition_name,il.REMARKS
                    FROM inventory_item_lost_details il
                    INNER JOIN inventory_item_master ii ON ii.id = il.ITEM_ID AND ii.sub_institute_id = il.SUB_INSTITUTE_ID
                    INNER JOIN tbluser u ON u.id = il.REQUISITION_BY
                    WHERE il.SUB_INSTITUTE_ID = '".$sub_institute_id."' ";
        $RET = DB::select($sub_data);
        
        //$data = hostel_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        $data['status_code'] = 1;
        $data['data'] = $RET;
//        dd($data);
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "inventory/show_inventory_item_lost", $data, "view");

    }
    
    public function create(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $item_data = inventory_item_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
        $user_data = tbluserModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
        return view('inventory/add_inventory_item_lost',['item' => $item_data,'users' => $user_data]);
    }
    
    public function store(Request $request) {
        //echo '<pre>';
//        dd($request);
        
        
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');
                
        $data = new inventory_item_lostModel([
            'SYEAR' => $syear,
            'SUB_INSTITUTE_ID' => $sub_institute_id,
            'ITEM_ID' => $request->get('item_id'),
            'REQUISITION_BY' => $request->get('requisition_id'),
            'REMARKS' => $request->get('remarks'),
            'LOST_DATE' => $request->get('lost_date'),
            'CREATED_BY' => $created_by,
            'CREATED_ON' => date('Y-m-d'),
            'CREATED_IP_ADDRESS' => $_SERVER['REMOTE_ADDR']
        ]);
//        dd($data);
        $data->save();
        $message['status_code'] = "1";
        $message = array(
            "message" => "Lost Item Added Succesfully",
        );
        $message = inventory_item_lostModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_inventory_item_lost.index", $message, "redirect");

    }
    
     public function edit(Request $request, $id)
    {    
        $type = $request->input('type');
        $data = inventory_item_lostModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $edit_item_data = inventory_item_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $edit_user_data = tbluserModel::where(['sub_institute_id' => $sub_institute_id])->get();
        view()->share('edit_item_data', $edit_item_data);
        view()->share('edit_user_data', $edit_user_data);
//        dd($id);
        return view('inventory/add_inventory_item_lost',['data' => $data]);
    }
      
    public function update(Request $request, $id)
    {
//        dd($request);
       //\App\Helpers\ValidateInsertData('hostel_master', 'update'); 
     
      $syear = $request->session()->get('syear');
      $sub_institute_id = $request->session()->get('sub_institute_id'); 
      //$created_by = $request->session()->get('user_id');
      $data = array(
            'SUB_INSTITUTE_ID' => $sub_institute_id,
            'ITEM_ID' => $request->get('item_id'),
            'REQUISITION_BY' => $request->get('requisition_id'),
            'REMARKS' => $request->get('remarks'),
            'LOST_DATE' => $request->get('lost_date')
      );
      inventory_item_lostModel::where(["id" => $id])->update($data);
       $message['status_code'] = "1"; 
       $message = array(
            "message" => "Lost Item Details Updated Successfully",
        );
       $type = $request->input('type');

       return \App\Helpers\is_mobile($type, "add_inventory_item_lost.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
     $type = $request->input('type');
     inventory_item_lostModel::where(["id" => $id])->delete();
     $message['status_code'] = "1";
        $message = array(
            "message" => "Lost Item Deleted successfully",
        );

        return \App\Helpers\is_mobile($type, "add_inventory_item_lost.index", $message, "redirect");
     
    }
}
