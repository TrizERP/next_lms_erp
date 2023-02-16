<?php

namespace App\Http\Controllers\hostel_management;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\hostel_management\hostel_visitor_masterModel;
use Illuminate\Support\Facades\DB;

class hostel_visitor_masterController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $visitor_data['message'] = $data_arr['message'];
            }
        }
         
        $sub_institute_id = $request->session()->get('sub_institute_id');
 
        $data = hostel_visitor_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        $visitor_data['status_code'] = 1;
        $visitor_data['data'] = $data;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "hostel_management/show_hostel_visitor", $visitor_data, "view");

    }
    
   public function create(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data =  hostel_visitor_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
        return view('hostel_management/add_hostel_visitor_master',['menu' => $data]);
    }
    
    public function store(Request $request) {

        //\App\Helpers\ValidateInsertData('hostel_visitor_master',$request);
     
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $visitor = new hostel_visitor_masterModel([
            'name' => $request->get('name'),
            'contact' => $request->get('contact'),
            'email' => $request->get('email'),
            'coming_from' => $request->get('coming_from'),
            'to_meet' => $request->get('to_meet'),
            'relation' => $request->get('relation'),
            'meet_date' => $request->get('meet_date'),
            'in_time' => $request->get('in_time'),
            'out_time' => $request->get('out_time'),
            'sub_institute_id' => $sub_institute_id
        ]);
        $visitor->save();
        $message['status_code'] = "1";
        $message = array(
            "message" => "Visitor details Added Succesfully",
        );
        $message = hostel_visitor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_hostel_visitor_master.index", $message, "redirect");
        
        //return redirect(route('add_hostel_type_master.index'))->with('success', 'Hostel Type Added Successfully.');
    }
    
     public function edit(Request $request, $id)
    {  
        
        $type = $request->input('type');
        $data = hostel_visitor_masterModel::find($id);

        return \App\Helpers\is_mobile($type, "hostel_management/add_hostel_visitor_master", $data, "view");
    }
      
    public function update(Request $request, $id)
    {
       //\App\Helpers\ValidateInsertData('hostel_visitor_master', 'update'); 
     
      $visitor = array(
            'name' => $request->get('name'),
            'contact' => $request->get('contact'),
            'email' => $request->get('email'),
            'coming_from' => $request->get('coming_from'),
            'to_meet' => $request->get('to_meet'),
            'relation' => $request->get('relation'),
            'meet_date' => $request->get('meet_date'),
            'in_time' => $request->get('in_time'),
            'out_time' => $request->get('out_time'),
      );
      hostel_visitor_masterModel::where(["id" => $id])->update($visitor);
       $message['status_code'] = "1"; 
       $message = array(
            "message" => "Data Updated Successfully",
        );
       $type = $request->input('type');

       return \App\Helpers\is_mobile($type, "add_hostel_visitor_master.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
     $type = $request->input('type');
     hostel_visitor_masterModel::where(["id" => $id])->delete();
     $message['status_code'] = "1";
        $message = array(
            "message" => "Data Deleted successfully",
        );

        return \App\Helpers\is_mobile($type, "add_hostel_visitor_master.index", $message, "redirect");
     
    } 
}
