<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use App\Models\student\houseModel;

class houseController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $inward_data['message'] = $data_arr['message'];
            }
        }
         
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        
        $house = DB::table('house_master')->where(['sub_institute_id' => $sub_institute_id,'syear' => $syear])->get();

        $house_data['status_code'] = 1;
        $house_data['data'] = $house;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "student/show_house", $house_data, "view");

    }
    
    public function create(Request $request)
    {
        return view('student/add_house');
    }
    
    public function store(Request $request) 
    {
        
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        $house = new houseModel([
            'house_name' => $request->get('house_name'),
            'sort_order' => $request->get('sort_order'),
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
            'created_by' => $user_id,
            'created_at' => date('Y-m-d h:i:s'),
            'created_ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        $house->save();
        $message['status_code'] = "1";
        $message['message'] = "House Added Succesfully";
    
        return \App\Helpers\is_mobile($type, "add_house.index", $message, "redirect");
    }
    
    public function edit(Request $request, $id)
    {   
        $data = houseModel::find($id);
        return view('student/add_house',['data' => $data]);
    }
      
    public function update(Request $request, $id)
    {            
        $type = $request->input('type');
        
        $data = array(
            'house_name' => $request->get('house_name'),
            'sort_order' => $request->get('sort_order')
        );
        
        houseModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1"; 
        $message['message'] = "Data Updated Successfully";
        
        return \App\Helpers\is_mobile($type, "add_house.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
        $type = $request->input('type');
        houseModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message['message'] = "Data Deleted successfully";
    
        return \App\Helpers\is_mobile($type, "add_house.index", $message, "redirect");
     
    }
}
