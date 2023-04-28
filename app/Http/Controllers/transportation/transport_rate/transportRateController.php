<?php 

namespace App\Http\Controllers\transportation\transport_rate;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\transportation\transport_rate\transport_rate;
use function App\Helpers\is_mobile;

use DB;

class transportRateController extends Controller {

	public function index(){
		$type = " ";
		$datas = transport_rate::where('sub_institute_id',session()->get('sub_institute_id'))->where('syear',session()->get('syear'))->orderBy('id', 'desc')->get();
		return view('transportation.transport_rate.show',compact('datas'));
	}
	public function create(){
		return view('transportation.transport_rate.add_rate');
	}
	public function store(Request $request){
		// return "hello";
		$type = " ";
		if(isset($request) && !empty($request)){
		$data = new transport_rate();
		$data->syear = session()->get('syear');
		$data->sub_institute_id = session()->get('sub_institute_id');
		$data->distance_from_school = $request->distance_from_school;
		$data->from_distance = $request->from_distance;
		$data->to_distance = $request->to_distance;
		$data->rick_old = $request->rick_old;
		$data->rick_new = $request->rick_new;
		$data->van_old = $request->van_old;
		$data->van_new = $request->van_new;
		$data->created_on = now();
		$data->save();
		$res['status'] = 1;
		$res['message'] = "Added Succesfully";
		return is_mobile($type,'transport_rate.index',$res,'redirect');
	}
	else{
		return view('transportation.transport_rate.add_rate')->with('fail','Failed to Add');
	}
}

	public function edit($id){
		$data = transport_rate::where(['id'=>$id])->first(); 
		// return $data;exit;
		return view('transportation.transport_rate.edit_rate',compact('data'));
	}

	public function update(Request $request,$id){
		// return $id;exit;
		$data = transport_rate::where('id',$id)->update([
			
			"distance_from_school" => $request->distance_from_school,
			"from_distance" => $request->from_distance,
			"to_distance" => $request->to_distance,
			"rick_old" => $request->rick_old,
			"rick_new" => $request->rick_new,
			"van_old" => $request->van_old,
			"van_new" => $request->van_new,
			"created_on" => now()
	]);
		$type = " ";
 		if($data == false){

		$res['status'] = 0;
		$res['message'] = "Update Failed" ;

		}else{
		$res['status'] = 1;
		$res['message'] = "Updated Succesfully" ;

		// return view('transportation.transport_rate.show')->with('success','Updated Succesfully');
	}
        return is_mobile($type, "transport_rate.index", $res, "redirect");
	}

	public function destroy($id){
		// return $id;exit;
		transport_rate::where(['id'=>$id])->delete();
		return back()->with('success','Deleted Successfully');
	}
}