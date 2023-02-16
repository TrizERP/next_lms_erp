<?php

namespace App\Http\Controllers\inward_outward;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use App\Models\inward_outward\inwardModel;
use App\Models\inward_outward\place_masterModel;
use App\Models\inward_outward\physical_file_locationModel;

class inwardController extends Controller
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
        
        $inward = DB::table('inward')
            ->join('place_master', 'inward.place_id', '=', 'place_master.id')
            ->join('physical_file_location', 'inward.file_location_id', '=', 'physical_file_location.id')
            ->select('inward.*', 'place_master.title as place_id','physical_file_location.title as file_name','physical_file_location.file_location as file_location_id')
            ->where(['inward.sub_institute_id' => $sub_institute_id,'inward.syear' => $syear])->get();
        $inward_data['status_code'] = 1;
        $inward_data['data'] = $inward;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "inward_outward/show_inward", $inward_data, "view");

    }
    
    public function create(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data = place_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();
        $data1 = physical_file_locationModel::where(['sub_institute_id'=>$sub_institute_id])->get()->toArray();

        $get_inward_no = DB::select("SELECT (IFNULL(MAX(CAST(inward_number AS INT)),0) + 1) AS inward_no
                                    FROM inward
                                    WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."' ");

        view()->share('inward_no', $get_inward_no[0]->inward_no);

        

        return view('inward_outward/add_inward',['menu' => $data],['menu1' => $data1]);
    }
    
    public function store(Request $request) 
    {
        
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        
        $file_name = $file_size = $ext = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/inward/', $file_name);
        }
       // echo '<pre>';
        //print_R($request);die;
        $inward = new inwardModel([
            'place_id' => $request->get('place_id'),
            'file_location_id' => $request->get('file_location_id'),
            'inward_number' => $request->get('inward_number'),
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'attachment' => $file_name,
            'attachment_size' => $file_size,
            'attachment_type' => $ext,
            'acedemic_year' => $request->get('acedemic_year'),
            'inward_date' => $request->get('inward_date'),
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear
        ]);
        //echo "<pre>";
        //dd($inward);
        // die;
        $inward->save();
        $message['status_code'] = "1";
        $message = array(
            "message" => "Inward Added Succesfully",
        );
        $message = inwardModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "add_inward.index", $message, "redirect");
    }
    
     public function edit(Request $request, $id)
    {   
        $type = $request->input('type');
        $data = inwardModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $editdata1 = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get();
        view()->share('menu', $editdata);
        view()->share('menu1', $editdata1);
        return view('inward_outward/add_inward',['data' => $data]);
    }
      
    public function update(Request $request, $id)
    {       
      // \App\Helpers\ValidateInsertData('hostel_master', 'update');      
      $data = array(
            'place_id' => $request->get('place_id'),
            'file_location_id' => $request->get('file_location_id'),
            'inward_number' => $request->get('inward_number'),
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'acedemic_year' => $request->get('acedemic_year'),
            'inward_date' => $request->get('inward_date'),
            // 'sub_institute_id' => "1",
      );
      $file_name = $file_size = $ext = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/inward/', $file_name);
        }
        if ($file_name != "") {
            $data['attachment'] = $file_name;
            $data['attachment_size'] = $file_size;
            $data['attachment_type'] = $ext;
        }
        
      inwardModel::where(["id" => $id])->update($data);
       $message['status_code'] = "1"; 
       $message = array(
            "message" => "Data Updated Successfully",
        );
       $type = $request->input('type');

       return \App\Helpers\is_mobile($type, "add_inward.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
     $type = $request->input('type');
     inwardModel::where(["id" => $id])->delete();
     $message['status_code'] = "1";
        $message = array(
            "message" => "Data Deleted successfully",
        );

        return \App\Helpers\is_mobile($type, "add_inward.index", $message, "redirect");
     
    }
}
