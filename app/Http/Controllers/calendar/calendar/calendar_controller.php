<?php

namespace App\Http\Controllers\calendar\calendar;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\calendar\calendar\calendar;
use Illuminate\Support\Facades\Validator;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;

class calendar_controller extends Controller
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
                $data['message'] = $data_arr['message'];
            }
        }

        //        $data['data'] = $this->getData();
        //        echo "<pre>";
        //        print_r($data['data']);
        //        exit;
        $data['data'] = array();

        $data = $this->getData($request);

        // dd($data);

        $calendarData = array();
        if (count($data) > 0) {
            foreach ($data as $key => $val) {
                $std = "";
                if ($val['standard'] != "") {
                    // $std_arr = explode($val['standard'], ",");
                    $std_arr = explode(",", $val['standard']);
                    $std = json_encode($std_arr);
                }

                $color_bg = array('vacation' => 'bg-warning',
                                  'event' => 'bg-success',
                                  'holiday' => 'bg-danger');

                $calendarData[] = array(
                    'id' => $val['id'],
                    'title' => $val['title'],
                    'start' => $val['school_date'],
                    'description' => $val['description'],
                    'event_type' => $val['event_type'],
                    'standard' => $std,
                    'sub_institute_id' => $val['sub_institute_id'],
                    'className' => $color_bg[$val['event_type']]
                );
            }
        }
        // dd($calendarData);
        $calendarData = json_encode($calendarData, true);

        $standard = DB::table("standard")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->pluck("name", "id");
        //echo '<pre>'; print_r($standard); exit;

        $res['calendarData'] = $calendarData;
        $res['standardData'] = $standard;
        // dd($res);
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "calendar/calendar/show", $res, "view");
    }

    public function getData($request)
    {
        // echo '<pre>'; print_r($_REQUEST); exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $extra = array('lp.sub_institute_id' => $sub_institute_id,'lp.syear' => $syear);

        $data = calendar::from("calendar_events as lp")
            ->where($extra)
            ->get()->toArray();
        return $data;
    }

    public function fetchData(Request $request)
    {
        $response = array('response' => '', 'success' => false);
        $validator =  Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type' => ["in:holiday,event,vacation,"]
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

            $sql = "SELECT se.standard_id,se.section_id,se.grade_id
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                INNER JOIN division d ON  d.id = se.section_id
                INNER JOIN school_setup ss on s.sub_institute_id = ss.Id
                WHERE s.sub_institute_id = '" . $sub_institute_id . "'
                AND se.syear = '" . $syear . "'
                AND se.student_id = '" . $student_id . "'
                GROUP BY s.id
                ";
            $sql = preg_replace('/\n+/', '', $sql);
            $result = DB::select($sql);
            if ($result) {
                $standard_id = $result[0]->standard_id;
                $extra_condition = "";
                if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                    $extra_condition = " AND event_type = '" . $_REQUEST["type"] . "'";
                }
                $data_sql = "SELECT *
                FROM calendar_events ce
                WHERE FIND_IN_SET($standard_id,ce.standard)
                $extra_condition
                ";
                $data_sql = preg_replace('/\n+/', '', $data_sql);
                $result_data = DB::select($data_sql);
                $response['response'] = $result_data;
                $response['success'] = true;
            // echo '<pre>'; print_r($result); exit;
            } else {
                $response['response'] = array("student_id" => array("No student found."));
            }
        }

        return json_encode($response);

        exit;
    }
    public function TeacherFetchData(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 200);
        }
        $response = array('status' => '0', 'message' => '', 'data' => array());
        $validator =  Validator::make($request->all(), [
            'standard_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type' => ["in:holiday,event,vacation,"]
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $_REQUEST['standard_id'];

            
            // $standard_id = $result[0]->standard_id;
            $extra_condition = "";
            if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                $extra_condition = " AND event_type = '" . $_REQUEST["type"] . "'";
            }
            $data_sql = "SELECT *
                FROM calendar_events ce
                WHERE FIND_IN_SET($standard_id,ce.standard)
                $extra_condition
                ";
            $data_sql = preg_replace('/\n+/', '', $data_sql);
            $result_data = DB::select($data_sql);
            $response['data'] = $result_data;
            $response['status'] = '1';
            $response['message'] = 'Sucsses';
        }

        return json_encode($response);

        exit;
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        // echo "<pre>";
        // print_r($_REQUEST);
        // exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $finalArray[] = array(
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'event_type' => $request->get('event_type'),
            'standard' => implode($request->get('standard'), ","),
            'school_date' => date("Y-m-d", $request->get('school_date') / 1000),
            'syear' => $syear,
            'sub_institute_id' => $sub_institute_id,
        );
        calendar::insert($finalArray);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // public function show($id)
    // {
    //     //
    // }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //        echo "<pre>";
        //        echo $request->get('description') . "<br>";
        //        echo $request->get('event_type') . "<br>";
        //        echo $request->get('title') . "<br>";
        //        echo $request->get('school_date') . "<br>";
        //        print_r($_REQUEST);
        // echo 'divya';
        //        echo $id;
        //        dd($request);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $finalArray = array(
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'event_type' => $request->get('event_type'),
            'standard' => implode($request->get('standard'), ","),
            'school_date' => date("Y-m-d", $request->get('school_date') / 1000),
            'syear' => $syear,
            'sub_institute_id' => $sub_institute_id,
        );
        //        echo "<pre>";
        //        print_r($finalArray);
        //        exit;

        calendar::where(["id" => $id])->update($finalArray);
        exit;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        calendar::where(["id" => $id])->delete();
        //        echo "<pre>";
        //        print_r($id);
        //        exit;
    }
    
    public function studentCalenderAPI(Request $request)
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
                
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $action = $request->input("action");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $extra_sql = "";
            if($action != ''){
                $extra_sql = " AND event_type = '".$action."' ";
            }

            $data = DB::select("SELECT c.school_date AS school_date,c.title,c.description,c.event_type FROM tblstudent_enrollment s
			LEFT JOIN calendar_events c ON find_in_set (s.standard_id,c.standard) AND c.syear=s.syear
			WHERE s.student_id = '".$student_id."' AND s.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."' 
            $extra_sql	
			ORDER BY school_date"); //AND event_type = '".$action."'
            
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        
        //return is_mobile($type, "implementation", $res);
        return json_encode($res);
    }
}
