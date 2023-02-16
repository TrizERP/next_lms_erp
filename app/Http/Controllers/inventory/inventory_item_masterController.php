<?php

namespace App\Http\Controllers\inventory;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\inventory\inventory_item_masterModel;
use App\Models\inventory\inventory_item_category_masterModel;
use App\Models\inventory\inventory_item_sub_category_masterModel;
use App\Models\inventory\inventory_item_typeModel;
use Illuminate\Validation\ValidationData;
use Illuminate\Http\Response;

class inventory_item_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $item_data['message'] = $data_arr['message'];
            }
        }
         
        $sub_institute_id = $request->session()->get('sub_institute_id');
        //$syear = $request->session()->get('syear');
        
        $item = DB::table('inventory_item_master')
            ->join('inventory_item_category_master', 'inventory_item_master.category_id', '=', 'inventory_item_category_master.id')
            ->join('inventory_item_sub_category_master', 'inventory_item_master.sub_category_id', '=', 'inventory_item_sub_category_master.id')
            ->join('inventory_item_type', 'inventory_item_master.item_type_id', '=', 'inventory_item_type.id')
            ->select('inventory_item_master.*', 'inventory_item_category_master.title as category_id',
                    'inventory_item_sub_category_master.title as sub_category_id','inventory_item_type.title as item_type_id')
            ->where(['inventory_item_master.sub_institute_id' => $sub_institute_id])->get(); //'inventory_item_master.syear' => $syear
        $item_data['status_code'] = 1;
        $item_data['data'] = $item;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "inventory/show_inventory_item", $item_data, "view");

    }
    
    public function create(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = inventory_item_category_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
        
        //$SubcategoryData = inventory_item_masterModel::where(['sub_institute_id' => $sub_institute_id,'category_id' => $category_id])->get();
        
        $data1 = inventory_item_sub_category_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
        $data2 = inventory_item_typeModel::get()->toArray();
        $data_arr['menu'] = $data;
        $data_arr['menu1'] = array();
        $data_arr['menu2'] = $data2;
        //echo '<pre>';
        //print_r($data2);die;
        return view('inventory/add_inventory_item',$data_arr);
    }
    
    public function store(Request $request) {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        //$syear = $request->session()->get('syear');
        //$hid_category_id = $_REQUEST['hid_academic_section_id'];
        
        $file_name = "";
        if ($request->hasFile('item_attachment')) {
            $file = $request->file('item_attachment');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/inventory_item/', $file_name);
        }

        $item = new inventory_item_masterModel([
            //'syear' => $syear,
            'category_id' => $request->get('category_id'),
            'sub_category_id' => $request->get('sub_category_id'),
            'item_type_id' => $request->get('item_type_id'),
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'opening_stock' => $request->get('opening_stock'),
            'minimum_stock' => $request->get('minimum_stock'),
            'item_attachment' => $file_name,
            'item_status' => $request->get('item_status'),
            'sub_institute_id' => $sub_institute_id
        ]);

        $item->save();
        $message['status_code'] = "1";
        $message = array(
            "message" => "Inventory Item Added Succesfully",
        );
        $message = inventory_item_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_inventory_item.index", $message, "redirect");
    }
    
    public function edit(Request $request, $id)
    {   
        $type = $request->input('type');
        $data = inventory_item_masterModel::find($id);

        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = inventory_item_category_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $editdata1 = inventory_item_sub_category_masterModel::where(['sub_institute_id' => $sub_institute_id,'category_id' => $data['category_id']])->get();
        $editdata2 = inventory_item_typeModel::get();
        view()->share('menu', $editdata);
        view()->share('menu1', $editdata1);
        view()->share('menu2', $editdata2);
        return view('inventory/add_inventory_item',['data' => $data]);
    }
      
    public function update(Request $request, $id)
    {
   // $syear = $request->session()->get('syear');
    $sub_institute_id = $request->session()->get('sub_institute_id');    
      $data = array(
            //'syear' => $syear,
            'category_id' => $request->get('category_id'),
            'sub_category_id' => $request->get('sub_category_id'),
            'item_type_id' => $request->get('item_type_id'),
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'opening_stock' => $request->get('opening_stock'),
            'minimum_stock' => $request->get('minimum_stock'),
            'item_status' => $request->get('item_status'),
            'sub_institute_id' => $sub_institute_id
      );
      $file_name = "";
        if ($request->hasFile('item_attachment')) {
            $file = $request->file('item_attachment');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/inventory_item/', $file_name);
        }
        if ($file_name != "") {
            $data['item_attachment'] = $file_name;
        }
      inventory_item_masterModel::where(["id" => $id])->update($data);
       $message['status_code'] = "1"; 
       $message = array(
            "message" => "Inventory Item Updated Successfully",
        );
       $type = $request->input('type');

       return \App\Helpers\is_mobile($type, "add_inventory_item.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
     $type = $request->input('type');
     inventory_item_masterModel::where(["id" => $id])->delete();
     $message['status_code'] = "1";
        $message = array(
            "message" => "Inventory Item Deleted successfully",
        );

        return \App\Helpers\is_mobile($type, "add_inventory_item.index", $message, "redirect");
     
    }

    public function ajax_CategorywiseSubcategory(Request $request)
    {        
        $sub_institute_id = $request->session()->get("sub_institute_id");
       // $syear = $request->session()->get("syear");
        $category_id = $request->input("category_id");        
                        
        $SubcategoryData = inventory_item_sub_category_masterModel::where(['sub_institute_id' => $sub_institute_id,'category_id' => $category_id]) //'syear' => $syear,
        ->get()->toArray();
        return $SubcategoryData;    
    }

    public function ajax_SubcategoryeiseItems(Request $request)
    {        
        $sub_institute_id = session()->get("sub_institute_id");
        //$syear = session()->get("syear");
        $sub_category_id = $request->input("sub_category_id");        
                        
        $ItemData = inventory_item_masterModel::where(['sub_institute_id' => $sub_institute_id,'sub_category_id' => $sub_category_id]) //'syear' => $syear,
        ->get()->toArray();
        return $ItemData;    
    }
}
