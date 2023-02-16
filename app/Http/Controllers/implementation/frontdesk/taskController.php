<?php

namespace App\Http\Controllers\frontdesk;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\frontdesk\taskModel;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use function App\Helpers\is_mobile;
use Illuminate\Http\Response;

class taskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        
        $type = $request->input("type");
        $from_date = $request->input("from_date");
        $to_date = $request->input("to_date");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");
        $user_profile_name = $request->session()->get("user_profile_name");
        $user_id = $request->session()->get("user_id");

        $data = "SELECT t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS ALLOCATOR, CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS ALLOCATED_TO
        FROM task t
        INNER JOIN tbluser u ON t.TASK_ALLOCATED = u.id AND u.sub_institute_id = '".$sub_institute_id."'
        INNER JOIN tbluser u2 ON t.TASK_ALLOCATED_TO = u2.id AND u2.sub_institute_id = '".$sub_institute_id."'
        WHERE t.SYEAR = '".$syear."'";

        if(isset($from_date))
        {
            $data .= "  AND t.TASK_DATE >= '".$from_date."' ";
            $res['from_date'] = $from_date;
        }

        if(isset($to_date))
        {
            $data .= "  AND t.TASK_DATE <= '".$to_date."' ";
            $res['to_date'] = $to_date;
        }

        if(strtoupper($user_profile_name) != 'ADMIN')
        {
            $data .= "  AND t.TASK_ALLOCATED_TO = '".$user_id."' ";
        }

        $data = DB::select($data);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        
        return is_mobile($type, "frontdesk.show_task", $res , "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id") ;
        $syear = $request->session()->get("syear") ;
        $user_id = $request->session()->get("user_id") ;

        $users = tbluserModel::where(["sub_institute_id" => $sub_institute_id])->whereRaw("id != '".$user_id."'")->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['userList'] = $users;

        return is_mobile($type, "frontdesk.add_task", $res , "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id") ;
        $syear = $request->session()->get("syear") ;
        $term_id = $request->session()->get("term_id") ;
        $user_id = $request->session()->get("user_id") ;
        $data = $request->except(['_method', '_token','submit','TASK_ATTACHMENT']);
        
        $data['SYEAR'] = $syear;
        $data['MARKING_PERIOD_ID'] = $term_id;
        $data['CREATED_BY'] = $user_id;
        $data['TASK_ALLOCATED'] = $user_id;
        $data['CREATED_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
        $data['CREATED_ON'] = date('Y-m-d H:i:s');


        $TASK_ALLOCATED_TO = $request->input("TASK_ALLOCATED_TO");
        $TASK_ALLOCATED_TO = explode("-",$TASK_ALLOCATED_TO);
        $data['TASK_ALLOCATED_TO'] = trim($TASK_ALLOCATED_TO[1]);


        $file_name = "";
        if ($request->hasFile('TASK_ATTACHMENT')) {
            $file = $request->file('TASK_ATTACHMENT');
            $originalname = $file->getClientOriginalName();
            $name = "task_".date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/frontdesk/', $file_name);
        }

        if($file_name != '')
        {
            $data['TASK_ATTACHMENT'] = $file_name;
        }
        
        taskModel::insert($data);

        $res['status_code'] = "1";
        $res['message'] = "Added successfully";

        return is_mobile($type, "task.index", $res);
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
        $user_id = $request->session()->get("user_id") ;
        $syear = $request->session()->get("syear") ;


        $data = "SELECT t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS ALLOCATOR, CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS ALLOCATED_TO
        FROM task t
        INNER JOIN tbluser u ON t.TASK_ALLOCATED = u.id AND u.sub_institute_id = '".$sub_institute_id."'
        INNER JOIN tbluser u2 ON t.TASK_ALLOCATED_TO = u2.id AND u2.sub_institute_id = '".$sub_institute_id."'
        WHERE t.ID = '".$id."'";

        $result = DB::select($data);

        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);

        $editData = $result[0];

        $users = tbluserModel::where(["sub_institute_id" => $sub_institute_id])->whereRaw("id != '".$user_id."'")->get()->toArray();

        $dataResult = "SELECT * from complaint_status WHERE TYPE = 'TASK'";

		$dataResult = DB::select($dataResult);

		$dataResult = array_map(function ($value) {
			return (array) $value;
		}, $dataResult);

		$taskStatus = $dataResult;

        return view('frontdesk/edit_task',['data' => $editData,'userList' => $users,'taskStatus' => $taskStatus]);
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
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id") ;
        $syear = $request->session()->get("syear") ;
        $term_id = $request->session()->get("term_id") ;
        $user_id = $request->session()->get("user_id") ;
        $data = $request->except(['_method', '_token','submit','TASK_ATTACHMENT']);
        
        $data['SYEAR'] = $syear;
        $data['MARKING_PERIOD_ID'] = $term_id;
        $data['CREATED_BY'] = $user_id;
        $data['TASK_ALLOCATED'] = $user_id;
        $data['CREATED_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
        $data['CREATED_ON'] = date('Y-m-d H:i:s');


        $TASK_ALLOCATED_TO = $request->input("TASK_ALLOCATED_TO");
        $TASK_ALLOCATED_TO = explode("-",$TASK_ALLOCATED_TO);
        $data['TASK_ALLOCATED_TO'] = trim($TASK_ALLOCATED_TO[1]);


        $file_name = "";
        if ($request->hasFile('TASK_ATTACHMENT')) {
            $file = $request->file('TASK_ATTACHMENT');
            $originalname = $file->getClientOriginalName();
            $name = "task_".date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $path = $file->storeAs('public/frontdesk/', $file_name);
        }

        if($file_name != '')
        {
            $data['TASK_ATTACHMENT'] = $file_name;
        }
        
        $data = taskModel::where(['id'=>$id])->update($data);

        $res['status_code'] = "1";
        $res['message'] = "Updated successfully";

        return is_mobile($type, "task.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        taskModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Deleted successfully";
        return is_mobile($type, "task.index", $res);
    }

    public function taskReportIndex(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "frontdesk.task_report", $res , "view");
    }
}
