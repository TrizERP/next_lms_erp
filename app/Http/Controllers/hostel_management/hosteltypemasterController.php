<?php

namespace App\Http\Controllers\hostel_management;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\hostel_management\hosteltypemasterModel;

class hosteltypemasterController extends Controller
{
    public function index(Request $request)
    {
        
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $hostel_data['message'] = $data_arr['message'];
            }
        }
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = hosteltypemasterModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        $hostel_data['status_code'] = 1;
        $hostel_data['data'] = $data;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "hostel_management/show_hostel_type", $hostel_data, "view");

    }
    
    public function create(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data =  hosteltypemasterModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
        return view('hostel_management/add_hostel_type_master',['menu' => $data]);
       //return view('hostel_management/add_hostel_type_master');
    }
    
    public function getData(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $hostel_data = hosteltypemasterModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        return $hostel_data;
    }
    
    //public function listhosteltype(Request $request) {
      // $hostel_data = hosteltypemasterModel::get();
       //return view('hostel_management/show_hostel_type', ['data' => $hostel_data]);
    //}

    public function store(Request $request) {

        \App\Helpers\ValidateInsertData('hostel_type_master',$request);
     
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $hostel_type = new hosteltypemasterModel([
            'hostel_type' => $request->get('hostel_type'),
            'status' => $request->get('status'),
            'description' => $request->get('description'),
            'sub_institute_id' => $sub_institute_id
        ]);
        $hostel_type->save();
        
        $message['status_code'] = "1";
        $message = array(
            "message" => "Hostel type Added Succesfully",
        );
        $message = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_hostel_type_master.index", $message, "redirect");
        
        //return redirect(route('add_hostel_type_master.index'))->with('success', 'Hostel Type Added Successfully.');
    }
    
     public function edit(Request $request, $id)
    {  
        
        $type = $request->input('type');
        $data = hosteltypemasterModel::find($id);

        return \App\Helpers\is_mobile($type, "hostel_management/add_hostel_type_master", $data, "view");
    }
      
    public function update(Request $request, $id)
    {
       \App\Helpers\ValidateInsertData('hostel_type_master', 'update'); 
     
      $data = array(
            'hostel_type' => $request->get('hostel_type'),
            'status' => $request->get('status'),
            'description' => $request->get('description'),
      );
      hosteltypemasterModel::where(["id" => $id])->update($data);
       $message['status_code'] = "1";
       $message = array(
            "message" => "Data Updated Successfully",
        );
       $type = $request->input('type');

       return \App\Helpers\is_mobile($type, "add_hostel_type_master.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
     $type = $request->input('type');
     hosteltypemasterModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message = array(
            "message" => "Data Deleted successfully",
        );

        return \App\Helpers\is_mobile($type, "add_hostel_type_master.index", $message, "redirect");
     
    }
}
