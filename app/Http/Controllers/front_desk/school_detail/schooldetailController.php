<?php

namespace App\Http\Controllers\front_desk\school_detail;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;

class schooldetailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
	 
	use GetsJwtToken;
	
    public function index(Request $request)
    {			
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }
		$school_data['type_arr'] = $this->getTypeArr($request);
		
        $school_data['data'] = $this->getData();				
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/schooldetail/show", $school_data, "view");
    }
    function getData() {
        $sql = "SELECT c.*
            FROM school_detail c
            WHERE c.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                ";
        $sql = preg_replace('/\n+/', '', $sql);
        $result = DB::select($sql);
        return $result;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
		
		$data['type_arr'] = $this->getTypeArr($request);
		/*//START Check for existing records
		$data = DB::select("SELECT GROUP_CONCAT(DISTINCT(type)) AS types FROM school_detail WHERE sub_institute_id = '".$sub_institute_id."'");
		$existing_type = array();
		if($data[0]->types != "")
		{
			$existing_type = explode(",",$data[0]->types);
		}
		
		$type_arr = array(
			"About Us" => "About Us",
			"School Information" => "School Information",
			"School Timing" => "School Timing",
			"Achievement" => "Achievement",
			"Principal Desk" => "Principal Desk",
			"Rules" => "Rules",
			"Facility" => "Facility",
			"Academic Activity" => "Academic Activity",
			"Reach Us" => "Reach Us"
		);
		
		$new_arr = array_diff($type_arr,$existing_type);
		$data['type_arr'] = $new_arr;*/
		$data = json_decode (json_encode ($data), FALSE);		
		
        return \App\Helpers\is_mobile($type, 'front_desk/schooldetail/add', $data, "view");
    }
	
	public function getTypeArr($request)
	{
		$type_arr = array();
		$sub_institute_id = $request->session()->get('sub_institute_id');
		//START Check for existing records
		$data = DB::select("SELECT GROUP_CONCAT(DISTINCT(type)) AS types FROM school_detail WHERE sub_institute_id = '".$sub_institute_id."'");
		$existing_type = array();
		if($data[0]->types != "")
		{
			$existing_type = explode(",",$data[0]->types);
		}
		
		$type_arr = array(
			"About Us" => "About Us",
			"School Information" => "School Information",
			"School Timing" => "School Timing",
			"Achievement" => "Achievement",
			"Principal Desk" => "Principal Desk",
			"Rules" => "Rules",
			"Facility" => "Facility",
			"Academic Activity" => "Academic Activity",
			"Reach Us" => "Reach Us"
		);
		
		$new_arr = array_diff($type_arr,$existing_type);
		$type_arr = $new_arr;
		
		return $type_arr;
	}

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

        $values = array(
            'title' =>  $_REQUEST['message'],
            'type' => $_REQUEST['type'],
            'sub_institute_id' => session()->get('sub_institute_id'),
            'created_at' => now(),
            'updated_at' => now(),
        );
        DB::table('school_detail')->insert($values);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "schooldetail.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {         
        $type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
        $data = DB::select("SELECT * FROM school_detail WHERE id = '".$id."'");		
		$data = $data[0];		
		return \App\Helpers\is_mobile($type, "front_desk/schooldetail/add", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$finalArr = array(
			'title' =>  $_REQUEST['message'],				
			'updated_at' => now(),
		);
        DB::table("school_detail")->where(['id'=>$id])->update($finalArr);
		$res = array(
            "status_code" => 1,
            "message" => "School Detail Updated Successfully",
        );
		return \App\Helpers\is_mobile($type, "schooldetail.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        DB::table('school_detail')->where(["Id" => $id])->delete();
//        ExamMaster::where(["Id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "schooldetail.index", $res, "redirect");
    }
	
	public function schoolDetailAPI(Request $request)
	{
		try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
		
		$type = $request->input("type");
		$action = ucfirst($request->input("action"));
		$sub_institute_id = $request->input("sub_institute_id");

		if($action != "" && $sub_institute_id != "")
		{

			$data = DB::select("SELECT type as title,title as description
			FROM school_detail 
			WHERE sub_institute_id = '".$sub_institute_id."'
			AND type = '".$action."'");
			
			$res['status'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;	
		}else{
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		//return \App\Helpers\is_mobile($type, "implementation", $res);
		return json_encode($res);						
	}

}
