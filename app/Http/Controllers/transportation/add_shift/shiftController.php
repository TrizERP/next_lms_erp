<?php

namespace App\Http\Controllers\transportation\add_shift;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class shiftController extends Controller
{
    //
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['status_code'] = $data_arr['status_code'];                
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        $type = $request->input('type');
        // echo "<pre>";print_r($school_data['data']);exit;
        return is_mobile($type, "transportation/add_shift/show", $school_data, "view");
    }

    public function getData()
    {
        return DB::table('transport_school_shift')->where([
            'sub_institute_id' => session()->get('sub_institute_id'),
        ])->get();
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore = [];

        return is_mobile($type, 'transportation/add_shift/add', $dataStore, "view");
    }

    public function store(Request $request)
    {

        $shift =[
            "shift_title"        => $request->get('shift_title'),
            "shift_rate"        => $request->get('shift_rate'),
            "km_amount"        => $request->get('km_amount'),            
            'sub_institute_id' => session()->get('sub_institute_id'),
        ];
       $check = DB::table('transport_school_shift')->where([
        "shift_title"        => $request->get('shift_title'),
        'sub_institute_id' => session()->get('sub_institute_id'),
        ])->get()->toArray();

       if(empty($check)){
        $data=DB::table('transport_school_shift')->insert($shift);
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
       }else{
        $res = [
            "status_code" => 0,
            "message"     => "Shift Already Exist",
        ];
       }
     

        $type = $request->input('type');

        return is_mobile($type, "transport_shift.index", $res, "redirect");
    }
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = DB::table('transport_school_shift')->find($id);
        $data = json_decode(json_encode($data), true);
        // echo "<pre>";print_r($data['id']);exit;
        return is_mobile($type, "transportation/add_shift/edit", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $shift =[
            "shift_title"        => $request->get('shift_title'),
            "shift_rate"        => $request->get('shift_rate'),
            "km_amount"        => $request->get('km_amount'),            
            'sub_institute_id' => session()->get('sub_institute_id'),
        ];

       $update = DB::table('transport_school_shift')->where(["id" => $id])->update($shift);
       
        if($update == true){
            $res = [
                "status_code" => 1,
                "message"     => "Data Updated",
            ];
        }else{
            $res = [
                "status_code" => 0,
                "message"     => "Failed Updated",
            ];
        }
       
        $type = $request->input('type');

        return is_mobile($type, "transport_shift.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('transport_school_shift')->where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "transport_shift.index", $res, "redirect");
    }
}
