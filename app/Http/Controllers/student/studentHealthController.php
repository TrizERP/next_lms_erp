<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\student\studentHealthModel;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class studentHealthController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    
    use GetsJwtToken;
    
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        
        $data = "SELECT si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name
        FROM student_health si
        INNER JOIN tblstudent s ON si.student_id = s.id
        WHERE si.sub_institute_id = '".$sub_institute_id."' order by si.id desc ";

        $result = DB::select($data);

        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);

        // dd($result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $result;
        
        return is_mobile($type, "student/health/show_student_health", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('student/health/add_student_health');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method','_token','submit','file');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-",$STUDENT);
        $finalArray['student_id'] = trim($STUDENT[1]);

        $file_name = $ext = $file_size = "";
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = "health_document_".date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/frontdesk/', $file_name);
        }
        if($file_name != ''){
            $finalArray['file'] = $file_name;
            $finalArray['file_size'] = $file_size;
            $finalArray['file_type'] = $ext;
        }

        $finalArray['created_by'] = $user_id;
        $finalArray['syear'] = $syear;
        $finalArray['marking_period_id'] = $term_id;
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['created_on'] = date('Y-m-d H:i:s');

        studentHealthModel::insert($finalArray);
        $id = DB::getPdo()->lastInsertId();
        
        $res['status_code'] = 1;
        $res['message'] = "Student Health Successfully Created.";
        
        return is_mobile($type, "student_health.index", $res);
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
        $sub_institute_id = $request->session()->get("sub_institute_id") ;

        $data = "SELECT si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name
        FROM student_health si
        INNER JOIN tblstudent s ON si.student_id = s.id
        WHERE si.sub_institute_id = '".$sub_institute_id."' and si.id = '".$id."' order by si.id desc";

        $result = DB::select($data);

        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);

        $editData = $result[0];

        return view('student/health/edit_student_health',['data' => $editData]);
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method','_token','submit','file');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-",$STUDENT);
        $finalArray['student_id'] = trim($STUDENT[1]);

        $file_name = $ext = $file_size = "";       
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = "health_document_".date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/frontdesk/', $file_name);
        }
        if($file_name != ''){
            $finalArray['file'] = $file_name;
            $finalArray['file_size'] = $file_size;
            $finalArray['file_type'] = $ext;
        }

        $data = studentHealthModel::where(['id'=>$id])->update($finalArray);
        
        $res['status_code'] = 1;
        $res['message'] = "Student Health successfully updated.";
        
        return is_mobile($type, "student_health.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
    {
        $type = $request->input('type');
        studentHealthModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Student Health deleted successfully";
        return is_mobile($type, "student_health.index", $res);
    }
    
    public function studentHealthAPI(Request $request) {
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
            $data = DB::select("SELECT doctor_name,doctor_contact,DATE_FORMAT(date,'%d-%m-%Y') AS date, if(file = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/frontdesk/',file)) as file
            FROM student_health
            WHERE syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."' 
            AND student_id = '".$student_id."'
            ORDER BY date");
            
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;   
        
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return is_mobile($type, "implementation", $res);  
        return json_encode($res);
    }
}