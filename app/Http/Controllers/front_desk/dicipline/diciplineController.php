<?php

namespace App\Http\Controllers\front_desk\dicipline;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\sendNotification;
use Illuminate\Support\Facades\Session;

class diciplineController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
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

        $data['data'] = array();
        $type = $request->input('type');
        // added on 07-03-2025 for standalone modules start
        // http://127.0.0.1:8000/dicipline_alone?type=webForm&sub_institute_id=254
        if($type=='webForm'){
            $sub_institute_id = $request->get('sub_institute_id');
            $data['syears'] = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->groupBy('syear')->get()->toArray();
            $data['gradeList'] = DB::table('academic_section')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->get()->toArray();
            $data['syear'] = Date('Y');
            $data['profiles'] = DB::table("tbluser as tu")
            ->join('tbluserprofilemaster as tup','tup.id','=','tu.user_profile_id')
            ->selectRaw("concat(tu.first_name,' ',tu.last_name) name,tu.id")
            ->where("tu.sub_institute_id", "=", $sub_institute_id)
            ->where('tu.status',1)  
            ->where('tup.name','Watchman')
            ->get()->toArray();
            // echo "<pre>";print_r($data);exit;

            return is_mobile($type, "front_desk/dicipline/standalone", $data, "view");
        }
        // added on 07-03-2025 for standalone modules end
        return is_mobile($type, "front_desk/dicipline/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear'); 

        if($type=='webForm'){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear'); 
        }
        $grade_id = isset($_REQUEST['grade']) ? $_REQUEST['grade'] : '';
        $standard = isset($_REQUEST['standard']) ? $_REQUEST['standard'] : '';
        $division = isset($_REQUEST['division']) ? $_REQUEST['division'] : '';

        $student_data = SearchStudent($grade_id, $standard, $division,$sub_institute_id,$syear, "", "", "", "", $request->grNo);

        $responce_arr['grade'] = $grade_id;
        $responce_arr['standard'] = $standard;
        $responce_arr['division'] = $division;
        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['standard_name'] = $arr['standard_name'];
            $responce_arr['stu_data'][$id]['division_name'] = $arr['division_name'];
        }
        $dd = DB::table('dicipline_dd')->where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->pluck('message', 'id');
        // echo "<pre>";print_r($dd);exit;
        if(count($dd)==0){
            $dd = DB::table('dicipline_dd')->where('sub_institute_id',0)->whereNull('deleted_at')->pluck('message', 'id');
        }
        $responce_arr['dd'] = $dd;
        if($type=='webForm'){
            $responce_arr['syears'] = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->groupBy('syear')->get()->toArray();
            $responce_arr['gradeList'] = DB::table('academic_section')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->get()->toArray();
            $responce_arr['profiles'] = DB::table("tbluser as tu")
            ->join('tbluserprofilemaster as tup','tup.id','=','tu.user_profile_id')
            ->selectRaw("concat(tu.first_name,' ',tu.last_name) name,tu.id")
            ->where("tu.sub_institute_id", "=", $sub_institute_id)
            ->where('tu.status',1)  
            ->where('tup.name','Watchman')
            ->get()->toArray();
            $responce_arr['syear'] = $request->syear;
            $responce_arr['grade'] = $request->grade;
            $responce_arr['standard'] = $request->standard;
            $responce_arr['division'] = $request->division;
            $responce_arr['user_id'] = $request->user_id;
            $responce_arr['grno'] = $request->grNo;

            // echo "<pre>";print_r($responce_arr);exit;

            return is_mobile($type, "front_desk/dicipline/standalone", $responce_arr, "view");
        }
        return is_mobile($type, "front_desk/dicipline/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
            // echo "<pre>";print_r($request->all());exit;
        $type = $request->type;
        $user_id = session()->get('user_id');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($type=='webForm'){
            $user_id = 0 ;
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
            // echo "<pre>";print_r($request->all());exit;
        }

        $stu_arr = [];
        $student_ids = $_REQUEST['values']['stud_id'] ?? [];
        
        foreach ($student_ids as $student_id => $on) {
            $stu_arr[] = $student_id;
        }

        $result = DB::table("tbluser")
            ->selectRaw("concat(first_name,' ',last_name) name")
            ->where("id", "=", $user_id)
            ->where('status',1)  // 23-04-24 by uma
            ->get()->toArray();
            
        $name = $result[0]->name;
        // echo("<pre>");print_r($name);exit;
        $i=0;
        foreach ($stu_arr as $id => $stu_id) {
            $insert = DB::table('dicipline')->insert([
                'syear'            => $syear,
                'student_id'       => $stu_id,
                'name'             => $name,
                'dicipline'        => $_REQUEST['values']['dd'][$stu_id],
                'message'          => $_REQUEST['values']['text'][$stu_id],
                'date_'            => date('Y-m-d'),
                'sub_institute_id' => $sub_institute_id,
                'created_by'       => $user_id,
                'created_at'       => now(),
            ]);

            if($insert){
                $i++;
            }

            //START Send Notification Code
            $app_notification_content = [
                'NOTIFICATION_TYPE'        => 'Student Remarks',
                'NOTIFICATION_DATE'        => date('Y-m-d'),
                'STUDENT_ID'               => $stu_id,
                'NOTIFICATION_DESCRIPTION' => $_REQUEST['values']['text'][$stu_id],
                'STATUS'                   => 0,
                'SUB_INSTITUTE_ID'         => $sub_institute_id,
                'SYEAR'                    => $syear,
                'CREATED_BY'               => $user_id,
                'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
            ];
            sendNotification($app_notification_content);
            //END Send Notification Code
        }
        $res = [
            "status_code" => 1,
            "message"     => "Dicipline Added",
        ];

        if($type=='webForm'){
            if($i!=0){
                Session::flash('status', 1); 
                Session::flash('message', 'Dicipline Added!'); 
            }else{
                Session::flash('status', 0); 
                Session::flash('message', 'Dicipline Added!'); 
            }

            return redirect('dicipline_alone?type=webForm&sub_institute_id='.$sub_institute_id);
        }
        return is_mobile($type, "dicipline.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function studentDisciplineAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            
            $data = DB::table("dicipline")
                ->selectRaw('dicipline as discipline,message,date_ AS discipline_date')
                ->where("syear", "=", $syear)
                ->where("sub_institute_id", "=", $sub_institute_id)
                ->where("student_id", "=", $student_id)
                ->get()->toArray();
                
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

}
