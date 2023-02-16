<?php

namespace App\Http\Controllers\front_desk\exam_schedule;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use File;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;

class exam_scheduleController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
	use GetsJwtToken;
	 
    public function index(Request $request) {		
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
//echo "<pre>";
//print_r($school_data['data']);
//exit;
//        $school_data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/exam_schedule/show", $school_data, "view");
    }

    function getData() {
        $sql = "SELECT c.*,s.name std_name, d.name division_name 
            FROM exam_schedule c
            INNER JOIN standard s on s.id = c.standard_id
            INNER JOIN division d on d.id = c.division_id
            WHERE c.syear = '" . session()->get('syear') . "' 
                and c.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
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
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {		
//        echo "<pre>";
//        print_r($_REQUEST);
//        exit;


        $file_name = $file_size = $ext = "";
        if ($request->hasFile('attechment')) {
            $file = $request->file('attechment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = $request->get('attechment') . date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = "attechment_" . $name . '.' . $ext;
            $path = $file->storeAs('public/exam_schedule/', $file_name);
//            echo "hewrer";
        }
        if (isset($_REQUEST['standard'])) {
            foreach ($_REQUEST['standard'] as $id => $std) {
                foreach ($_REQUEST['division'] as $ids => $div_id) {
                    $values = array(
                        'syear' => session()->get('syear'),
                        'standard_id' => $std,
                        'title' => $_REQUEST['title'],
                        'division_id' => $div_id,
                        'file_name' => $file_name,
                        'file_size' => $file_size,
                        'file_type' => $ext,
                        'date_' => $_REQUEST['date_'],
                        'sub_institute_id' => session()->get('sub_institute_id'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                    DB::table('exam_schedule')->insert($values);
                }
            }
        }

        $res = array(
            "status_code" => 1,
            "message" => "Done",
        );
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "exam_schedule.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }
	
	public function studentExamScheduleAPI(Request $request) {
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
		$student_id = $request->input("student_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$syear = $request->input("syear");

		if($student_id != "" && $sub_institute_id != "" && $syear != "")
		{	
		
			$data = DB::select("
			SELECT title,date_,
if(file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/exam_schedule/',file_name)) as file_name
             FROM exam_schedule e
			INNER JOIN tblstudent_enrollment s ON (s.standard_id = e.standard_id AND s.section_id = e.division_id AND s.sub_institute_id = e.sub_institute_id AND s.syear = e.syear)
			WHERE e.syear = '".$syear."' AND e.sub_institute_id = '".$sub_institute_id."' 
			AND student_id = '".$student_id."'				
			");
			
			$res['status'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;	
		}else{
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		//return  \App\Helpers\is_mobile($type, "implementation", $res);
		return json_encode($res);				
	}

}
