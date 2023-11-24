<?php

namespace App\Http\Controllers\inward_outward;

use App\Http\Controllers\Controller;
use App\Models\inward_outward\inwardModel;
use App\Models\inward_outward\physical_file_locationModel;
use App\Models\inward_outward\place_masterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;

class inwardController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $inward_data['message'] = $data_arr['message'];
            }
        }

        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        
        $inward = DB::table('inward')
            ->join('place_master', 'inward.place_id', '=', 'place_master.id')
            ->join('physical_file_location', 'inward.file_location_id', '=', 'physical_file_location.id')
            ->select('inward.*', 'place_master.title as place_id', 'physical_file_location.title as file_name',
                'physical_file_location.file_location as file_location_id')
            ->where(['inward.sub_institute_id' => $sub_institute_id, 'inward.syear' => $syear])->get();

        $inward_data['status_code'] = 1;
        $inward_data['data'] = $inward;

        return is_mobile($type, "inward_outward/show_inward", $inward_data, "view");

    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        $data = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $data1 = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $get_inward_no = DB::table("inward")
            ->selectRaw('(IFNULL(MAX(CAST(inward_number AS INT)),0) + 1) AS inward_no')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("syear", "=", $syear)
            ->get()->toArray();

        view()->share('inward_no', $get_inward_no[0]->inward_no);
        $res['menu'] = $data;
        $res['menu1']=$data1;
        return is_mobile($type, "inward_outward/add_inward", $res, "view");

        // return view('inward_outward/add_inward', ['menu' => $data], ['menu1' => $data1]);
    }

    public function store(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $type = $request->input('type');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');            
        }
        $file_name = $file_size = $ext = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/inward/', $file_name);
        }

        $inward = new inwardModel([
            'place_id'         => $request->input('place_id'),
            'file_location_id' => $request->input('file_location_id'),
            'inward_number'    => $request->input('inward_number'),
            'title'            => $request->input('title'),
            'description'      => $request->input('description'),
            'attachment'       => $file_name,
            'attachment_size'  => $file_size,
            'attachment_type'  => $ext,
            'acedemic_year'    => $request->input('acedemic_year'),
            'inward_date'      => $request->input('inward_date'),
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ]);

        $inward->save();
        if($inward->save()){
            $message['status_code'] = "1";
        }else{
            $message['status_code'] = "0";
        }
//        $message = [
//            "message" => "Inward Added Succesfully",
//        ];
        $message = inwardModel::where(['sub_institute_id' => $sub_institute_id])->get();

        return is_mobile($type, "add_inward.index", $message, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');            
        }
        $data = inwardModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editdata = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $editdata1 = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get();

        view()->share('menu', $editdata);
        view()->share('menu1', $editdata1);
        view()->share('inward_no', $data->inward_number);
        // $res['data']=$data;
        $data['menu'] = place_masterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $data['menu1'] = physical_file_locationModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        
        return is_mobile($type, "inward_outward/add_inward", $data, "view");

        // return view('inward_outward/add_inward', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = [
            'place_id'         => $request->input('place_id'),
            'file_location_id' => $request->input('file_location_id'),
            'inward_number'    => $request->input('inward_number'),
            'title'            => $request->input('title'),
            'description'      => $request->input('description'),
            'acedemic_year'    => $request->input('acedemic_year'),
            'inward_date'      => $request->input('inward_date'),
        ];
        $type = $request->input('type');  

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');        
        }
        $file_name = $file_size = $ext = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/inward/', $file_name);
        }

        if ($file_name != "") {
            $data['attachment'] = $file_name;
            $data['attachment_size'] = $file_size;
            $data['attachment_type'] = $ext;
        }

        inwardModel::where(["id" => $id])->update($data);
        $message['status_code'] = "1";
        $message = [
            "message" => "Data Updated Successfully",
        ];


        return is_mobile($type, "add_inward.index", $message, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
                      
        }
        inwardModel::where(["id" => $id])->delete();
        $message['status_code'] = "1";
        $message = [
            "message" => "Data Deleted successfully",
        ];

        return is_mobile($type, "add_inward.index", $message, "redirect");
    }
}
