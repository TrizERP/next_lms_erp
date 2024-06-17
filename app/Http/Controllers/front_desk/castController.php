<?php

namespace App\Http\Controllers\front_desk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class castController extends Controller
{
    //
    public function index(Request $request){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $res['castData'] = DB::table('cast')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
        return is_mobile($type, "front_desk/cast/index", $res, "view");
    }

    public function create(Request $request){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $res['lastData'] = DB::table('cast')->where('sub_institute_id',$sub_institute_id)->latest('sort_order')->first();
        return is_mobile($type, "front_desk/cast/add", $res, "view");
    }

    public function store(Request $request){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $checkExists = DB::table('cast')->where('sub_institute_id',$sub_institute_id)->where('title',$request->title)->get()->toArray();

        if(empty($checkExists)){
            $insert = DB::table('cast')->insert([
                'title'=>$request->title,
                'sort_order'=>$request->sort_order,
                'sub_institute_id'=>$sub_institute_id,
            ]);
            $res['status_code'] = 1;
            $res['message'] = "Add Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Already exists";
        }
        return is_mobile($type, "add_cast.index", $res);
    }

    public function edit(Request $request,$id){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $res['editData'] = DB::table('cast')->where('id',$id)->first();

        return is_mobile($type, "front_desk/cast/add", $res, "view");
    }

    public function update(Request $request,$id){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
      
        $update = DB::table('cast')->where('id',$id)->update([
                'title'=>$request->title,
                'sort_order'=>$request->sort_order,
                'sub_institute_id'=>$sub_institute_id,
            ]);

        if($update){
            $res['status_code'] = 1;
            $res['message'] = "Updated Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Update";
        }
        return is_mobile($type, "add_cast.index", $res);
    }

    public function destroy(Request $request,$id){
        $type= $request->type;

        $delete = DB::table('cast')->where('id',$id)->delete();

        if($delete){
            $res['status_code'] = 1;
            $res['message'] = "Delete Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Delete";
        }
        return is_mobile($type, "add_cast.index", $res);
    }
}
