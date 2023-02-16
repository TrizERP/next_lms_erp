<?php

namespace App\Http\Controllers\school_setup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\subjectModel;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use function App\Helpers\ValidateInsertData;
// use GenTux\Jwt\JwtToken;
// use GenTux\Jwt\GetsJwtToken;
// use function App\Helpers\aut_token;


class subjectController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	// use GetsJwtToken;
	
    public function index(Request $request){
        $data = $this->getData($request);               
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;        
        return is_mobile($type,'school_setup/show_subject',$res,"view");  
    }

    public function getData($request){
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $subject_data =  subjectModel::where(['sub_institute_id'=>$sub_institute_id])->get();        
        return $subject_data;
    }

    public function create(){
        return view('school_setup/add_subject');
    }
    public function store(Request $request){
        $sub_institute_id = $request->session()->get('sub_institute_id'); 
		
		//Check if Subject Already Exist or not
		$exist = $this->check_exist($request->get('subject_name'),$sub_institute_id);		
		if($exist == 0)
		{		
			$sub = new subjectModel([
				'subject_name' => $request->get('subject_name'),
				'subject_type' => $request->get('subject_type') != '' ? $request->get('subject_type') : "",
				'subject_code' => $request->get('subject_code'),
				'short_name' => $request->get('short_name'),
				'sub_institute_id' => $sub_institute_id,
				'status' => "1",            
			]);
				 
			$sub->save();
			$res = array(
				"status_code" => 1,
				"message" => "Subject Added Successfully",
			);
		}
		else
		{
			$res = array(
				"status_code" => 0,
				"message" => "Subject Already Exist",
			);
		}

        $type = $request->input('type');
        return is_mobile($type, "subject_master.index", $res, "redirect");
    }
	
	public function check_exist($subject_name,$sub_institute_id)
	{           
		$subject_name = strtoupper($subject_name);
		
		$data = DB::select("SELECT count(*) as tot FROM subject WHERE sub_institute_id = '".$sub_institute_id."'
		AND UPPER(subject_name) = '".$subject_name."'");
		$total_count = $data[0]->tot;
        return $total_count;
	}
	
    public function edit(Request $request,$id){
        $type = $request->input('type');
        $sub_data = subjectModel::find($id)->toArray();
        return is_mobile($type, "school_setup/add_subject", $sub_data, "view");

    }
    public function update(Request $request,$id){
        ValidateInsertData('subject','update');        
        $sub_institute_id = $request->session()->get('sub_institute_id'); 

		//Check if Subject Already Exist or not
		$exist = $this->check_exist($request->get('subject_name'),$sub_institute_id);		
		if($exist == 0)
		{				
			$data = array(
				'subject_name' => $request->get('subject_name'),
				'subject_type' => $request->get('subject_type'),
				'subject_code' => $request->get('subject_code'),
				'short_name' => $request->get('short_name'),
				'sub_institute_id' => $sub_institute_id,            
			);
			subjectModel::where(["id" => $id])->update($data);
			$res = array(
				"status_code" => 1,
				"message" => "Subject Updated Successfully",
			);
		}
		else
		{
			$res = array(
				"status_code" => 0,
				"message" => "Subject Already Exist",
			);
		}
        $type = $request->input('type');
        return is_mobile($type, "subject_master.index", $res, "redirect");
    }
    public function destroy(Request $request,$id){
        $type = $request->input('type');
        subjectModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Subject Deleted Successfully";
        return is_mobile($type, "subject_master.index", $res);
    }

}
