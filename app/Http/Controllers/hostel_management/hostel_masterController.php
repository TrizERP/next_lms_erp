<?php

namespace App\Http\Controllers\hostel_management;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\hostel_management\hostel_masterModel;
use App\Models\hostel_management\hosteltypemasterModel;
use Illuminate\Validation\ValidationData;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use Illuminate\Http\Response;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use function App\Helpers\is_mobile;
use App\Models\hostel_management\admission_category_masterModel;
use App\Models\hostel_management\hostel_building_masterModel;

use App\Models\hostel_management\hostel_floor_masterModel;
use App\Models\hostel_management\hostel_room_masterModel;
//use App\Models\hostel_management\hosteltypemasterModel;

use App\Models\hostel_management\room_type_masterModel;

use App\Models\hostel_management\tblhostelRoomAllocationModel;




class hostel_masterController extends Controller
{
     public function index(Request $request)
    {
         $sub_institute_id = $request->session()->get('sub_institute_id');
        // $data = hostel_mastelModel::where(['sub_institute_id' => $sub_institute_id, 'status' => "1"])->get();
         $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "hostel_master"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
            ->get();
        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $hostel_data['message'] = $data_arr['message'];
            }
        }
         
       
        
        $users = DB::table('hostel_master')
            ->join('hostel_type_master', 'hostel_master.hostel_type_id', '=', 'hostel_type_master.id')
            ->select('hostel_master.*', 'hostel_type_master.hostel_type as hostel_type_id')
            ->where('hostel_master.sub_institute_id', '=', $sub_institute_id)->get();
        
        //$data = hostel_masterModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        $hostel_data['status_code'] = 1;
        $hostel_data['data'] = $users;

        if (count($finalfieldsData) > 0) {
           $inward_data['data_fields'] = $finalfieldsData;
        }
        $type = $request->input('type');
//dd($hostel_data);
        return \App\Helpers\is_mobile($type, "hostel_management/show_hostel", $hostel_data, "view");

    }
    
    public function create(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data =  hosteltypemasterModel::where(['sub_institute_id'=>$sub_institute_id])->get();

         $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "hostel_master"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
            ->get();
        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['custom_fields'] = $dataCustomFields;
        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }
        $res['menu'] = $data;
        //$res['menu1'] = $data1;
        $type = $request->input('type');
       // dd($res);
        return is_mobile($type, "hostel_management/add_hostel_master", $res, "view");

       // return view('hostel_management/add_hostel_master',['menu' => $data]);
    }
    
    public function store(Request $request) {

        \App\Helpers\ValidateInsertData('hostel_master',$request);
     
        //$this->validate($request, [
            //'warden_contact' => 'required|numeric|digits:10',
        //]);
        
        $sub_institute_id = $request->session()->get('sub_institute_id');
         $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        
        $file_name = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/hostel_master/', $file_name);
        }
             $request->request->add(['image' => $file_name]); 

        $dataCustomFields = tblcustomfieldsModel::select('field_name')->where(['status' => "1", 'table_name' => "hostel_master", 'field_type' => "file"])
            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
            ->get()
            ->toArray();

        foreach ($dataCustomFields as $key => $value) {
            $file_name = '';
            // echo $value['field_name'];
            if ($request->hasFile($value['field_name'])) {
                $file = $request->file($value['field_name']);
                $originalname = $file->getClientOriginalName();
                $name = $value['field_name'] . "_" . $request->input('user_name') . date('YmdHis');
                $ext = \File::extension($originalname);
                $file_name = $name . '.' . $ext;
                $path = $file->storeAs('public/hostel_master/', $file_name);
                $request->files->remove($value['field_name']);
                $request->request->add([$value['field_name'] => $file_name]); //add request
            }

        }

        $hostel = new hostel_masterModel([
            'code' => $request->get('code'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'warden' => $request->get('warden'),
            'warden_contact' => $request->get('warden_contact'),
            'hostel_type_id' => $request->get('hostel_type_id'),
            'sub_institute_id' => $sub_institute_id
        ]);
        $hostel->save();
       // $message['status_code'] = "1";
       // $message = array(
           // "message" => "Hostel Details Added Succesfully",
       // );
         $res['status_code'] = 1;
        $res['message'] = "Hostel Details Added Succesfully.";
        $res['data'] = $data; 
        $res = hostel_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        //dd($res);
        $type = $request->input('type');

        dd($res);
         return is_mobile($type, "hostel_management/add_hostel_master", $res, "view");
         //  return \App\Helpers\is_mobile($type, "add_hostel_master.index", $message, "redirect");
        
        //return redirect(route('add_hostel_type_master.index'))->with('success', 'Hostel Type Added Successfully.');
    }
    
     public function edit(Request $request, $id)
    {  
          
        $type = $request->input('type');
        $data = hostel_masterModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = hosteltypemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        view()->share('menu', $editdata);
        return view('hostel_management/add_hostel_master',['data' => $data]);
        //return \App\Helpers\is_mobile($type, "hostel_management/add_hostel_master", $data, "view");
    }
      
    public function update(Request $request, $id)
    {
       \App\Helpers\ValidateInsertData('hostel_master', 'update'); 
     
      $data = array(
            'code' => $request->get('code'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'warden' => $request->get('warden'),
            'warden_contact' => $request->get('warden_contact'),
            'hostel_type_id' => $request->get('hostel_type_id'),
      );
      hostel_masterModel::where(["id" => $id])->update($data);
       $message['status_code'] = "1"; 
       $message = array(
            "message" => "Data Updated Successfully",
        );
       $type = $request->input('type');

       return \App\Helpers\is_mobile($type, "add_hostel_master.index", $message, "redirect");
    }
    public function destroy(Request $request,$id)
    {  
     $type = $request->input('type');
     hostel_masterModel::where(["id" => $id])->delete();
     $message['status_code'] = "1";
        $message = array(
            "message" => "Data Deleted successfully",
        );

        return \App\Helpers\is_mobile($type, "add_hostel_master.index", $message, "redirect");
     
    }

    public function hostelList(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $hostel_type_id = $request->input('hostel_type_id');

        $extraSearchArray = array();

        $extraSearchArray['sub_institute_id'] = $sub_institute_id;
        if($hostel_type_id != '')
        {
            $extraSearchArray['hostel_type_id'] = $hostel_type_id;
        }

        $hostels = hostel_masterModel::select('id','name')->where($extraSearchArray)->get()->toArray();

        return $hostels;
    } 
}
